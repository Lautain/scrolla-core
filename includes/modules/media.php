<?php
/**
 * Module Afbeeldingen en optimalisatie.
 * - Uploads (jpg/png) worden verkleind tot maximaal X px en opgeslagen als webp (origineel vervalt).
 *   Uitzondering: bestandsnaam eindigt op "-nr" (bijv. logo-nr.png), of gif/svg.
 * - Alle tussenformaten ook als webp; formaten 1536 en 2048 blijven bestaan voor retina-schermen.
 * - Revisies beperkt, heartbeat rustiger.
 */

defined( 'ABSPATH' ) || exit;

class Scrolla_Media {

	public function __construct() {
		$s = Scrolla_Core_Settings::get();
		add_filter( 'big_image_size_threshold', fn() => (int) $s['media_max'] );
		add_filter( 'wp_handle_upload', [ $this, 'convert' ] );
		add_filter( 'wp_handle_sideload', [ $this, 'convert' ] );
		add_filter( 'image_editor_output_format', [ $this, 'output_format' ] );
		add_filter( 'wp_editor_set_quality', fn( $q, $mime = '' ) => in_array( $mime, [ 'image/webp', 'image/jpeg' ], true ) ? (int) $s['media_quality'] : $q, 10, 2 );
		add_filter( 'wp_revisions_to_keep', fn() => (int) $s['revisions'] );
		add_filter( 'heartbeat_settings', function ( $set ) {
			$set['interval'] = 60;
			return $set;
		} );
		add_action( 'wp_enqueue_scripts', function () {
			if ( ! is_user_logged_in() ) {
				wp_deregister_script( 'heartbeat' );
			}
		}, 1 );
	}

	private function skip( string $file ): bool {
		return (bool) preg_match( '/-nr\.(jpe?g|png)$/i', $file );
	}

	public function output_format( $formats ) {
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
		return $formats;
	}

	/** Zet het geüploade origineel om naar webp en verklein het, vóórdat WordPress de tussenformaten maakt. */
	public function convert( array $upload ): array {
		if ( ! empty( $upload['error'] ) || ! in_array( $upload['type'] ?? '', [ 'image/jpeg', 'image/png' ], true ) || $this->skip( $upload['file'] ) ) {
			return $upload;
		}
		if ( ! wp_image_editor_supports( [ 'mime_type' => 'image/webp' ] ) ) {
			return $upload;
		}
		$editor = wp_get_image_editor( $upload['file'] );
		if ( is_wp_error( $editor ) ) {
			return $upload;
		}
		$max  = (int) Scrolla_Core_Settings::get( 'media_max' );
		$size = $editor->get_size();
		if ( $size['width'] > $max || $size['height'] > $max ) {
			$editor->resize( $max, $max, false );
		}
		if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
			$editor->maybe_exif_rotate();
		}
		$editor->set_quality( (int) Scrolla_Core_Settings::get( 'media_quality' ) );
		$new   = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $upload['file'] );
		$new   = wp_unique_filename( dirname( $new ), basename( $new ) );
		$saved = $editor->save( trailingslashit( dirname( $upload['file'] ) ) . $new, 'image/webp' );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			return $upload;
		}
		wp_delete_file( $upload['file'] );
		$upload['file'] = $saved['path'];
		$upload['url']  = trailingslashit( dirname( $upload['url'] ) ) . basename( $saved['path'] );
		$upload['type'] = 'image/webp';
		return $upload;
	}
}

new Scrolla_Media();
