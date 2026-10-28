<?php
/**
 * Module Bouwen: REST-importpunt waarmee Claude (via de ingelogde browser van een Scrolla-beheerder)
 * Elementor-pagina's, templates en de kit kan aanmaken en bijwerken. Alleen voor Scrolla-beheerders.
 *
 * Endpoints (namespace scrolla/v1, cookie + X-WP-Nonce):
 *   GET  /elementor/document/<id>       data, instellingen en type van een document
 *   POST /elementor/document            { id?, type, title, status?, data[], settings{}, conditions[]?, slug? }
 *   POST /elementor/kit                 { settings{} }  (wordt samengevoegd met de huidige kit-instellingen)
 *   GET  /elementor/kit                 huidige kit-instellingen
 *   POST /media/sideload                { url, title?, alt? }  -> { id, url }
 *
 * type: page | header | footer | single-page | single-post | archive | loop-item | section | container | popup | error-404
 * conditions (Theme Builder, Elementor Pro): bijv. ["include/general"] of ["include/singular/page"].
 */

defined( 'ABSPATH' ) || exit;

class Scrolla_Bouwen {

	const NS = 'scrolla/v1';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'routes' ] );
	}

	public function can(): bool {
		return scrolla_is_scrolla_user();
	}

	public function routes(): void {
		$perm = [ $this, 'can' ];
		register_rest_route( self::NS, '/elementor/document/(?P<id>\d+)', [ 'methods' => 'GET', 'callback' => [ $this, 'get_document' ], 'permission_callback' => $perm ] );
		register_rest_route( self::NS, '/elementor/document', [ 'methods' => 'POST', 'callback' => [ $this, 'save_document' ], 'permission_callback' => $perm ] );
		register_rest_route( self::NS, '/elementor/kit', [
			[ 'methods' => 'GET', 'callback' => [ $this, 'get_kit' ], 'permission_callback' => $perm ],
			[ 'methods' => 'POST', 'callback' => [ $this, 'save_kit' ], 'permission_callback' => $perm ],
		] );
		register_rest_route( self::NS, '/media/sideload', [ 'methods' => 'POST', 'callback' => [ $this, 'sideload' ], 'permission_callback' => $perm ] );
	}

	private function elementor(): bool {
		return class_exists( '\Elementor\Plugin' );
	}

	private function clear_cache(): void {
		if ( $this->elementor() ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	public function get_document( WP_REST_Request $r ) {
		$id   = (int) $r['id'];
		$post = get_post( $id );
		if ( ! $post ) {
			return new WP_Error( 'niet_gevonden', 'Document bestaat niet.', [ 'status' => 404 ] );
		}
		return [
			'id'       => $id,
			'title'    => $post->post_title,
			'type'     => get_post_meta( $id, '_elementor_template_type', true ) ?: $post->post_type,
			'status'   => $post->post_status,
			'link'     => get_permalink( $id ),
			'data'     => json_decode( (string) get_post_meta( $id, '_elementor_data', true ), true ) ?: [],
			'settings' => get_post_meta( $id, '_elementor_page_settings', true ) ?: new stdClass(),
		];
	}

	public function save_document( WP_REST_Request $r ) {
		if ( ! $this->elementor() ) {
			return new WP_Error( 'geen_elementor', 'Elementor is niet actief.', [ 'status' => 400 ] );
		}
		$p     = $r->get_json_params();
		$type  = sanitize_key( $p['type'] ?? 'page' );
		$data  = $p['data'] ?? [];
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'ongeldig', 'data moet een array met Elementor-elementen zijn.', [ 'status' => 400 ] );
		}
		$is_page   = in_array( $type, [ 'page', 'post' ], true );
		$post_type = $is_page ? $type : 'elementor_library';
		$args      = [
			'post_type'   => $post_type,
			'post_title'  => sanitize_text_field( $p['title'] ?? 'Zonder titel' ),
			'post_status' => in_array( $p['status'] ?? '', [ 'publish', 'draft', 'private' ], true ) ? $p['status'] : 'publish',
		];
		if ( ! empty( $p['slug'] ) ) {
			$args['post_name'] = sanitize_title( $p['slug'] );
		}
		if ( ! empty( $p['id'] ) ) {
			$args['ID'] = (int) $p['id'];
			$id         = wp_update_post( $args, true );
		} else {
			$id = wp_insert_post( $args, true );
		}
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$data = $this->ids( $data );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', $is_page ? 'wp-page' : $type );
		update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' );
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			update_post_meta( $id, '_elementor_pro_version', ELEMENTOR_PRO_VERSION );
		}
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		if ( isset( $p['settings'] ) && is_array( $p['settings'] ) ) {
			update_post_meta( $id, '_elementor_page_settings', $p['settings'] );
			if ( $is_page && ! empty( $p['settings']['template'] ) ) {
				update_post_meta( $id, '_wp_page_template', sanitize_text_field( $p['settings']['template'] ) );
			}
		}
		if ( ! $is_page ) {
			wp_set_object_terms( $id, $type, 'elementor_library_type' );
		}
		if ( isset( $p['conditions'] ) && class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
			\ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->save_conditions( $id, array_map( 'sanitize_text_field', (array) $p['conditions'] ) );
		}
		$this->clear_cache();
		return [ 'id' => $id, 'link' => get_permalink( $id ), 'edit' => admin_url( 'post.php?post=' . $id . '&action=elementor' ), 'type' => $type ];
	}

	/** Geef elk element een uniek id van 7 tekens als het ontbreekt (Elementor vereist dat). */
	private function ids( array $elements ): array {
		foreach ( $elements as &$el ) {
			if ( empty( $el['id'] ) ) {
				$el['id'] = substr( md5( uniqid( '', true ) . wp_rand() ), 0, 7 );
			}
			$el['settings'] = (object) ( $el['settings'] ?? [] );
			$el['elements'] = $this->ids( (array) ( $el['elements'] ?? [] ) );
		}
		return $elements;
	}

	private function kit_id(): int {
		return (int) get_option( 'elementor_active_kit' );
	}

	public function get_kit() {
		$id = $this->kit_id();
		return [ 'id' => $id, 'settings' => get_post_meta( $id, '_elementor_page_settings', true ) ?: new stdClass() ];
	}

	public function save_kit( WP_REST_Request $r ) {
		$id = $this->kit_id();
		if ( ! $id ) {
			return new WP_Error( 'geen_kit', 'Geen actieve Elementor-kit.', [ 'status' => 400 ] );
		}
		$new = (array) ( $r->get_json_params()['settings'] ?? [] );
		$cur = (array) get_post_meta( $id, '_elementor_page_settings', true );
		update_post_meta( $id, '_elementor_page_settings', array_merge( $cur, $new ) );
		$this->clear_cache();
		return [ 'id' => $id, 'keys' => array_keys( $new ) ];
	}

	public function sideload( WP_REST_Request $r ) {
		$p   = $r->get_json_params();
		$url = esc_url_raw( $p['url'] ?? '' );
		if ( ! $url ) {
			return new WP_Error( 'geen_url', 'url ontbreekt.', [ 'status' => 400 ] );
		}
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = download_url( $url, 60 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		$name = sanitize_file_name( ( $p['title'] ?? 'afbeelding' ) );
		$ext  = wp_check_filetype( wp_basename( wp_parse_url( $url, PHP_URL_PATH ) ) )['ext'] ?: 'png';
		$id   = media_handle_sideload( [ 'name' => $name . '.' . $ext, 'tmp_name' => $tmp ], 0, sanitize_text_field( $p['title'] ?? '' ) );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return $id;
		}
		if ( ! empty( $p['alt'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $p['alt'] ) );
		}
		return [ 'id' => $id, 'url' => wp_get_attachment_url( $id ) ];
	}
}

new Scrolla_Bouwen();
