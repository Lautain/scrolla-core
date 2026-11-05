<?php
/**
 * Module Mail: alle mails van de site in de huisstijl (formulieren, wachtwoord vergeten, nieuwe gebruiker, systeemmails).
 *
 * - Mails die al een volledig HTML-document zijn (WooCommerce, nieuwsbrieven) blijven ongewijzigd.
 * - Platte tekst wordt nette HTML (alinea's, klikbare links); een HTML-fragment (bijv. Elementor-formulier) wordt omhuld.
 * - Logo, kleuren en bedrijfsgegevens komen uit Scrolla_Huisstijl en Algemeen.
 * - Uitzetten per site: add_filter( 'scrolla_mail_huisstijl', '__return_false' );
 */

defined( 'ABSPATH' ) || exit;

class Scrolla_Mail {

	public function __construct() {
		add_filter( 'wp_mail', [ $this, 'wrap' ], 20 );
	}

	public function wrap( $args ) {
		if ( ! is_array( $args ) || ! apply_filters( 'scrolla_mail_huisstijl', true, $args ) ) {
			return $args;
		}
		$msg = (string) ( $args['message'] ?? '' );
		if ( '' === trim( $msg ) || preg_match( '/<html[\s>]|<!doctype/i', $msg ) ) {
			return $args;
		}
		$headers = $args['headers'] ?? [];
		$headers = is_array( $headers ) ? $headers : preg_split( "/\r\n|\n/", (string) $headers );
		$headers = array_values( array_filter( array_map( 'trim', $headers ) ) );
		$type    = '';
		foreach ( $headers as $i => $h ) {
			if ( preg_match( '/^content-type:\s*([^;]+)/i', $h, $m ) ) {
				$type = strtolower( trim( $m[1] ) );
				unset( $headers[ $i ] );
			}
		}
		if ( str_starts_with( $type, 'multipart' ) ) {
			return $args;
		}
		$is_html = 'text/html' === $type || ( '' === $type && 'text/html' === apply_filters( 'wp_mail_content_type', 'text/plain' ) );
		$body    = $is_html ? $msg : self::tekst_naar_html( $msg );

		$headers[]         = 'Content-Type: text/html; charset=UTF-8';
		$args['headers']   = array_values( $headers );
		$args['message']   = self::template( $body, (string) ( $args['subject'] ?? '' ) );
		return $args;
	}

	/** Platte tekst naar HTML: links klikbaar, alinea's en regeleinden behouden. */
	public static function tekst_naar_html( string $txt ): string {
		$txt = preg_replace( '/<(https?:\/\/[^>\s]+)>/', '$1', $txt ); // WordPress zet links soms tussen < >
		$txt = make_clickable( esc_html( $txt ) );
		return wpautop( $txt );
	}

	public static function template( string $body, string $subject = '' ): string {
		$h    = class_exists( 'Scrolla_Huisstijl' ) ? Scrolla_Huisstijl::get() : [ 'naam' => get_bloginfo( 'name' ), 'logo' => '', 'kleur' => [ 'primair' => '#1d1d1f', 'secundair' => '#50575e', 'tekst' => '#1d1d1f', 'accent' => '#2271b1' ], 'op_primair' => '#ffffff', 'radius' => '6px' ];
		$k    = $h['kleur'];
		$naam = esc_html( $h['naam'] );
		$kop  = $h['logo']
			? sprintf( '<img src="%s" alt="%s" width="180" style="display:block;width:180px;max-width:100%%;height:auto;border:0">', esc_url( $h['logo'] ), $naam )
			: sprintf( '<span style="font-size:20px;font-weight:700;color:%s">%s</span>', esc_attr( $k['tekst'] ), $naam );

		$g     = fn( $key ) => class_exists( 'Scrolla_Algemeen' ) ? Scrolla_Algemeen::value( $key ) : '';
		$regel = array_filter( [ trim( $g( 'adres' ) . ', ' . trim( $g( 'postcode' ) . ' ' . $g( 'plaats' ) ), ', ' ) ] );
		$contact = [];
		if ( $g( 'telefoon' ) ) {
			$contact[] = sprintf( '<a href="%s" style="color:%s;text-decoration:none">%s</a>', esc_attr( Scrolla_Algemeen::value( 'telefoon', true ) ), esc_attr( $k['secundair'] ), esc_html( $g( 'telefoon' ) ) );
		}
		if ( $g( 'email' ) ) {
			$contact[] = sprintf( '<a href="mailto:%1$s" style="color:%2$s;text-decoration:none">%1$s</a>', esc_attr( $g( 'email' ) ), esc_attr( $k['secundair'] ) );
		}
		$contact[] = sprintf( '<a href="%s" style="color:%s;text-decoration:none">%s</a>', esc_url( home_url( '/' ) ), esc_attr( $k['secundair'] ), esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) );

		$link  = 'color:' . $k['primair'] . ';text-decoration:underline';
		$body  = preg_replace( '/<a (?![^>]*style=)/i', '<a style="' . esc_attr( $link ) . '" ', $body );
		$body  = preg_replace( '/<p(?=[\s>])(?![^>]*style=)/i', '<p style="margin:0 0 16px"', $body );
		$body  = preg_replace( '/<table(?=[\s>])(?![^>]*style=)/i', '<table style="border-collapse:collapse;width:100%"', $body );
		$body  = preg_replace( '/<(td|th)(?=[\s>])(?![^>]*style=)/i', '<$1 style="padding:6px 8px;border-bottom:1px solid #e6e6e6;text-align:left;vertical-align:top"', $body );

		$voet = '<strong style="color:' . esc_attr( $k['tekst'] ) . '">' . $naam . '</strong>'
			. ( $regel ? '<br>' . esc_html( $regel[0] ) : '' )
			. '<br>' . implode( ' &middot; ', $contact );

		return '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="x-apple-disable-message-reformatting"><title>' . esc_html( $subject ?: $h['naam'] ) . '</title></head>'
			. '<body style="margin:0;padding:0;background:#f4f4f5;-webkit-text-size-adjust:100%">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f5"><tr><td align="center" style="padding:32px 16px">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:100%;background:#ffffff;border-radius:' . esc_attr( $h['radius'] ) . ';overflow:hidden">'
			. '<tr><td style="height:4px;line-height:4px;font-size:0;background:' . esc_attr( $k['primair'] ) . '">&nbsp;</td></tr>'
			. '<tr><td style="padding:28px 32px 8px">' . $kop . '</td></tr>'
			. '<tr><td style="padding:16px 32px 24px;font-size:15px;line-height:150%;color:' . esc_attr( $k['tekst'] ) . ';mso-line-height-rule:exactly">' . $body . '</td></tr>'
			. '<tr><td style="padding:20px 32px 28px;border-top:1px solid #ececec;font-size:13px;line-height:150%;color:' . esc_attr( $k['secundair'] ) . '">' . $voet . '</td></tr>'
			. '</table>'
			. '<p style="margin:16px 0 0;font-size:11px;color:#9a9ea3">Website door <a href="https://www.scrolla.nl" style="color:#9a9ea3">Scrolla!</a></p>'
			. '</td></tr></table></body></html>';
	}
}

new Scrolla_Mail();
