<?php
/**
 * Module Berichttypes en velden: bewust eenvoudig (geen repeaters of relaties).
 * Configuratie in één tekstvak (alleen Scrolla), bijvoorbeeld:
 *
 *   dienst | Dienst | Diensten | diensten | dashicons-hammer | archief, categorieen
 *     prijs | Prijs vanaf | text
 *     icoon | Icoon | image
 *   vacature | Vacature | Vacatures | vacatures | dashicons-id | archief
 *     uren | Uren per week | number
 *     sluitingsdatum | Sluitingsdatum | date
 *
 * Veldtypes: text, textarea, url, image, number, date, bool.
 * Velden zijn in Elementor beschikbaar als dynamic tags (groep Scrolla) en als post meta.
 */

defined( 'ABSPATH' ) || exit;

class Scrolla_Types {

	const OPTION = 'scrolla_types_config';
	const TYPES  = [ 'text', 'textarea', 'url', 'image', 'number', 'date', 'bool' ];

	public static function parse( string $config ): array {
		$types = [];
		$cur   = null;
		foreach ( preg_split( '/\r?\n/', $config ) as $line ) {
			if ( '' === trim( $line ) || str_starts_with( trim( $line ), '#' ) ) {
				continue;
			}
			$p = array_map( 'trim', explode( '|', trim( $line ) ) );
			if ( ! preg_match( '/^\s/', $line ) ) { // berichttype
				$key = sanitize_key( $p[0] );
				if ( ! $key || strlen( $key ) > 20 ) {
					continue;
				}
				$opts          = array_map( 'trim', explode( ',', strtolower( $p[5] ?? '' ) ) );
				$types[ $key ] = [
					'enkelvoud'   => $p[1] ?? ucfirst( $key ),
					'meervoud'    => $p[2] ?? ucfirst( $key ),
					'slug'        => sanitize_title( $p[3] ?? $key ),
					'icon'        => $p[4] ?? 'dashicons-admin-post',
					'archief'     => in_array( 'archief', $opts, true ),
					'categorieen' => in_array( 'categorieen', $opts, true ) || in_array( 'categorieën', $opts, true ),
					'velden'      => [],
				];
				$cur = $key;
			} elseif ( $cur ) { // veld
				$fk = sanitize_key( $p[0] );
				$ft = in_array( $p[2] ?? '', self::TYPES, true ) ? $p[2] : 'text';
				if ( $fk ) {
					$types[ $cur ]['velden'][ $fk ] = [ 'label' => $p[1] ?? $fk, 'type' => $ft ];
				}
			}
		}
		return $types;
	}

	public static function config(): array {
		static $c = null;
		return $c ??= self::parse( (string) get_option( self::OPTION, '' ) );
	}

	/** Alle velden over alle types, sleutel = meta key. */
	public static function all_fields(): array {
		$out = [];
		foreach ( self::config() as $pt => $t ) {
			foreach ( $t['velden'] as $k => $f ) {
				$out[ $k ] = $f + [ 'post_type' => $pt, 'post_type_label' => $t['enkelvoud'] ];
			}
		}
		return $out;
	}

	public function __construct() {
		add_action( 'init', [ $this, 'register' ] );
		add_action( 'add_meta_boxes', [ $this, 'metaboxes' ] );
		add_action( 'save_post', [ $this, 'save_meta' ], 10, 2 );
		add_action( 'admin_menu', [ $this, 'menu' ], 100 );
		add_action( 'admin_post_scrolla_types_save', [ $this, 'save_config' ] );
		add_action( 'elementor/dynamic_tags/register', [ $this, 'register_tags' ], 20 );
	}

	public function register(): void {
		foreach ( self::config() as $key => $t ) {
			register_post_type( $key, [
				'labels'        => [
					'name' => $t['meervoud'], 'singular_name' => $t['enkelvoud'], 'add_new' => 'Nieuwe ' . strtolower( $t['enkelvoud'] ),
					'add_new_item' => 'Nieuwe ' . strtolower( $t['enkelvoud'] ), 'edit_item' => $t['enkelvoud'] . ' bewerken',
					'all_items' => 'Alle ' . strtolower( $t['meervoud'] ), 'search_items' => 'Zoeken', 'not_found' => 'Niets gevonden',
				],
				'public'        => true,
				'has_archive'   => $t['archief'] ? $t['slug'] : false,
				'rewrite'       => [ 'slug' => $t['slug'], 'with_front' => false ],
				'menu_icon'     => $t['icon'],
				'menu_position' => 21,
				'supports'      => [ 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'revisions' ],
				'show_in_rest'  => true,
			] );
			if ( $t['categorieen'] ) {
				register_taxonomy( $key . '_cat', $key, [
					'labels'            => [ 'name' => 'Categorieën', 'singular_name' => 'Categorie' ],
					'hierarchical'      => true,
					'show_admin_column' => true,
					'show_in_rest'      => true,
					'rewrite'           => [ 'slug' => $t['slug'] . '-categorie', 'with_front' => false ],
				] );
			}
			foreach ( $t['velden'] as $fk => $f ) {
				register_post_meta( $key, $fk, [ 'single' => true, 'type' => in_array( $f['type'], [ 'image', 'number' ], true ) ? 'number' : 'string', 'show_in_rest' => true ] );
			}
		}
		// Elementor mag deze types bewerken.
		$support = (array) get_option( 'elementor_cpt_support', [ 'page', 'post' ] );
		$want    = array_values( array_unique( array_merge( $support, array_keys( self::config() ) ) ) );
		if ( $want !== $support ) {
			update_option( 'elementor_cpt_support', $want );
		}
		if ( get_option( 'scrolla_types_flush' ) ) {
			flush_rewrite_rules();
			delete_option( 'scrolla_types_flush' );
		}
	}

	public function metaboxes(): void {
		foreach ( self::config() as $key => $t ) {
			if ( $t['velden'] ) {
				add_meta_box( 'scrolla-velden', 'Gegevens', [ $this, 'metabox' ], $key, 'normal', 'high', $t['velden'] );
			}
		}
	}

	public function metabox( $post, $box ): void {
		wp_nonce_field( 'scrolla_velden', 'scrolla_velden_nonce' );
		wp_enqueue_media();
		echo '<table class="form-table">';
		foreach ( $box['args'] as $k => $f ) {
			$v    = get_post_meta( $post->ID, $k, true );
			$name = 'scrolla_veld[' . esc_attr( $k ) . ']';
			switch ( $f['type'] ) {
				case 'textarea':
					$in = sprintf( '<textarea name="%s" rows="4" class="large-text">%s</textarea>', $name, esc_textarea( $v ) );
					break;
				case 'bool':
					$in = sprintf( '<label><input type="checkbox" name="%s" value="1" %s> Ja</label>', $name, checked( (bool) $v, true, false ) );
					break;
				case 'image':
					$src = $v ? wp_get_attachment_image_url( (int) $v, 'thumbnail' ) : '';
					$in  = sprintf(
						'<div class="sc-img"><img src="%1$s" style="max-width:120px;display:%2$s;margin-bottom:6px"><br><input type="hidden" name="%3$s" value="%4$s"><button type="button" class="button sc-img-kies">Kies afbeelding</button> <button type="button" class="button-link sc-img-weg">Verwijderen</button></div>',
						esc_url( (string) $src ), $src ? 'block' : 'none', $name, esc_attr( $v )
					);
					break;
				default:
					$type = [ 'url' => 'url', 'number' => 'number', 'date' => 'date' ][ $f['type'] ] ?? 'text';
					$in   = sprintf( '<input type="%s" name="%s" value="%s" class="regular-text" %s>', $type, $name, esc_attr( $v ), 'number' === $type ? 'step="any"' : '' );
			}
			printf( '<tr><th><label>%s</label></th><td>%s</td></tr>', esc_html( $f['label'] ), $in ); // phpcs:ignore
		}
		echo '</table>';
		?>
		<script>
		jQuery(function($){
			$(document).on('click','.sc-img-kies',function(e){e.preventDefault();var w=$(this).closest('.sc-img'),f=wp.media({title:'Kies afbeelding',multiple:false,library:{type:'image'}});
				f.on('select',function(){var a=f.state().get('selection').first().toJSON();w.find('input').val(a.id);w.find('img').attr('src',(a.sizes&&a.sizes.thumbnail?a.sizes.thumbnail.url:a.url)).show();});f.open();});
			$(document).on('click','.sc-img-weg',function(e){e.preventDefault();var w=$(this).closest('.sc-img');w.find('input').val('');w.find('img').hide();});
		});
		</script>
		<?php
	}

	public function save_meta( $post_id, $post ): void {
		if ( ! isset( $_POST['scrolla_velden_nonce'] ) || ! wp_verify_nonce( $_POST['scrolla_velden_nonce'], 'scrolla_velden' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$fields = self::config()[ $post->post_type ]['velden'] ?? [];
		foreach ( $fields as $k => $f ) {
			$raw = wp_unslash( $_POST['scrolla_veld'][ $k ] ?? '' );
			$v   = match ( $f['type'] ) {
				'url'      => esc_url_raw( $raw ),
				'image'    => absint( $raw ) ?: '',
				'number'   => is_numeric( $raw ) ? $raw + 0 : '',
				'bool'     => $raw ? '1' : '',
				'textarea' => sanitize_textarea_field( $raw ),
				'date'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ? $raw : '',
				default    => sanitize_text_field( $raw ),
			};
			'' === $v ? delete_post_meta( $post_id, $k ) : update_post_meta( $post_id, $k, $v );
		}
	}

	public function menu(): void {
		if ( scrolla_is_scrolla_user() ) {
			add_submenu_page( 'scrolla-core', 'Berichttypes', 'Berichttypes', 'manage_options', 'scrolla-types', [ $this, 'render' ] );
		}
	}

	public function render(): void {
		if ( ! scrolla_is_scrolla_user() ) {
			wp_die( 'Geen toegang.' );
		}
		Scrolla_Core_Settings::styles();
		echo '<div class="wrap sc-wrap"><h1>Berichttypes en velden</h1>';
		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Opgeslagen. Permalinks zijn vernieuwd.</p></div>';
		}
		echo '<div class="sc-card"><p>Eén berichttype per regel, velden eronder ingesprongen.<br><code>sleutel | Enkelvoud | Meervoud | slug | dashicon | archief, categorieen</code><br><code>&nbsp;&nbsp;veldsleutel | Label | text|textarea|url|image|number|date|bool</code></p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'scrolla_types_save' );
		echo '<input type="hidden" name="action" value="scrolla_types_save">';
		printf( '<textarea name="config" rows="16" class="large-text code" spellcheck="false">%s</textarea>', esc_textarea( (string) get_option( self::OPTION, '' ) ) );
		submit_button( 'Opslaan' );
		echo '</form></div>';
		if ( self::config() ) {
			echo '<div class="sc-card"><h2>Resultaat</h2><table class="widefat striped"><thead><tr><th>Type</th><th>Slug</th><th>Archief</th><th>Categorieën</th><th>Velden</th></tr></thead><tbody>';
			foreach ( self::config() as $k => $t ) {
				$v = implode( ', ', array_map( fn( $fk, $f ) => "$f[label] ($f[type])", array_keys( $t['velden'] ), $t['velden'] ) );
				printf( '<tr><td><span class="dashicons %s"></span> %s <code>%s</code></td><td>/%s/</td><td>%s</td><td>%s</td><td>%s</td></tr>', esc_attr( $t['icon'] ), esc_html( $t['meervoud'] ), esc_html( $k ), esc_html( $t['slug'] ), $t['archief'] ? 'ja' : 'nee', $t['categorieen'] ? 'ja' : 'nee', esc_html( $v ) );
			}
			echo '</tbody></table></div>';
		}
		echo '</div>';
	}

	public function save_config(): void {
		if ( ! scrolla_is_scrolla_user() || ! check_admin_referer( 'scrolla_types_save' ) ) {
			wp_die( 'Geen toegang.' );
		}
		update_option( self::OPTION, sanitize_textarea_field( wp_unslash( $_POST['config'] ?? '' ) ) );
		update_option( 'scrolla_types_flush', 1 );
		wp_safe_redirect( admin_url( 'admin.php?page=scrolla-types&saved=1' ) );
		exit;
	}

	public function register_tags( $manager ): void {
		if ( ! self::all_fields() ) {
			return;
		}
		$manager->register_group( 'scrolla', [ 'title' => 'Scrolla' ] );
		require_once __DIR__ . '/elementor-tags.php';
		$manager->register( new Scrolla_Tag_Veld_Tekst() );
		$manager->register( new Scrolla_Tag_Veld_Link() );
		$manager->register( new Scrolla_Tag_Veld_Afbeelding() );
	}
}

new Scrolla_Types();
