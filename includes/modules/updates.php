<?php
/**
 * Module Updates: geteste updates voor de vaste stack.
 *
 * Werkwijze
 * - bp.scrolla.dev staat in kanariemodus (optie scrolla_kanarie = 'bp.scrolla.dev'): daar komt elke update direct binnen.
 * - Claude test de kanarie en zet de geteste versies in onderhoud.json in de GitHub-repo.
 * - Elke site leest dat bestand dagelijks. Automatische updates van de stack (WordPress, Elementor, Elementor Pro,
 *   Rank Math, Hello Elementor, ...) gaan alleen door tot en met de goedgekeurde versie.
 * - Plug-ins buiten de lijst volgen de gewone instelling voor automatische updates van de site.
 * - "terugdraaien" in onderhoud.json zet een plug-in of thema van wordpress.org terug naar een opgegeven versie.
 * - Fatale fouten worden geteld; WordPress draait een plug-in-autoupdate met een fatale fout zelf terug.
 * - GET /wp-json/scrolla/v1/status geeft een korte, publieke status zonder versienummers (behalve op de kanarie).
 *
 * Belasting: één verzoek per dag naar GitHub en per week een paar verzoeken van Claude.
 */

defined( 'ABSPATH' ) || exit;

class Scrolla_Updates {

	const URL      = 'https://raw.githubusercontent.com/Lautain/scrolla-core/main/onderhoud.json';
	const OPTION   = 'scrolla_onderhoud_lijst';
	const KANARIE  = 'scrolla_kanarie';
	const FOUTEN   = 'scrolla_fouten';
	const HOOK     = 'scrolla_onderhoud_sync';

	public function __construct() {
		add_action( 'init', [ $this, 'schedule' ] );
		add_action( 'template_redirect', [ $this, 'testpagina' ], 0 );
		add_action( self::HOOK, [ $this, 'sync' ] );
		add_filter( 'auto_update_plugin', [ $this, 'gate_plugin' ], 20, 2 );
		add_filter( 'auto_update_theme', [ $this, 'gate_theme' ], 20, 2 );
		add_filter( 'auto_update_core', [ $this, 'gate_core' ], 20, 2 );
		add_filter( 'allow_major_auto_core_updates', [ $this, 'allow_core' ], 20 );
		add_filter( 'allow_minor_auto_core_updates', [ $this, 'allow_core' ], 20 );
		add_filter( 'auto_core_update_send_email', '__return_false' );
		add_filter( 'auto_plugin_update_send_email', '__return_false' );
		add_filter( 'auto_theme_update_send_email', '__return_false' );
		add_action( 'rest_api_init', [ $this, 'rest' ] );
		add_action( 'automatic_updates_complete', [ $this, 'log_updates' ] );
		// WordPress' eigen fatale-foutafhandeling stopt met exit, dus via zijn filter loggen; shutdown als vangnet.
		add_filter( 'wp_should_handle_php_error', [ $this, 'from_wp' ], 1, 2 );
		register_shutdown_function( [ $this, 'catch_fatal' ] );
	}

	/* ---------- lijst ---------- */

	public static function lijst(): array {
		return (array) get_option( self::OPTION, [] );
	}

	/** Kanarie alleen op de host die in de optie staat, zodat klonen van de blueprint dit niet erven. */
	public static function kanarie(): bool {
		$host = (string) get_option( self::KANARIE, '' );
		return '' !== $host && wp_parse_url( home_url(), PHP_URL_HOST ) === $host;
	}

	/** De testpagina van de kanarie bestaat op andere sites niet (vangnet als hij na het klonen is blijven staan). */
	public function testpagina(): void {
		if ( is_page( 'onderhoudstest' ) && ! self::kanarie() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
		}
	}

	public function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 300, 'daily', self::HOOK );
		}
	}

	public function sync(): void {
		$res = wp_remote_get( self::URL . '?t=' . time(), [ 'timeout' => 10, 'headers' => [ 'User-Agent' => 'scrolla-core' ] ] );
		if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
			return;
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) || empty( $data['stack'] ) ) {
			return;
		}
		$data['opgehaald'] = time();
		update_option( self::OPTION, $data, false );
		$this->terugdraaien( (array) ( $data['terugdraaien'] ?? [] ) );
	}

	/** Goedgekeurde versie voor een slug (of null als de slug niet in de stack zit). */
	private function approved( string $slug ): ?string {
		$stack = (array) ( self::lijst()['stack'] ?? [] );
		return isset( $stack[ $slug ] ) ? (string) $stack[ $slug ] : null;
	}

	private function ok( string $slug, string $new ) {
		if ( 'scrolla-core' === $slug ) {
			return null; // eigen updater
		}
		$ok = $this->approved( $slug );
		if ( null === $ok ) {
			return null; // niet in de stack: gewone instelling
		}
		if ( self::kanarie() ) {
			return true;
		}
		return '' !== $new && version_compare( $new, $ok, '<=' );
	}

	/* ---------- poortwachter voor automatische updates ---------- */

	public function gate_plugin( $update, $item ) {
		$slug = dirname( (string) ( $item->plugin ?? '' ) );
		$ok   = $this->ok( $slug, (string) ( $item->new_version ?? '' ) );
		return null === $ok ? $update : $ok;
	}

	public function gate_theme( $update, $item ) {
		$ok = $this->ok( (string) ( $item->theme ?? '' ), (string) ( $item->new_version ?? '' ) );
		return null === $ok ? $update : $ok;
	}

	public function gate_core( $update, $item ) {
		$ok = $this->ok( 'wordpress', (string) ( $item->current ?? '' ) );
		return null === $ok ? $update : $ok;
	}

	public function allow_core( $allow ) {
		return null === $this->approved( 'wordpress' ) ? $allow : true; // gate_core beslist per versie
	}

	/* ---------- terugdraaien (alleen wordpress.org) ---------- */

	private function terugdraaien( array $list ): void {
		if ( ! $list || self::kanarie() ) {
			return;
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		foreach ( $list as $slug => $rule ) {
			$rule   = is_array( $rule ) ? $rule : [ 'versie' => (string) $rule ];
			$versie = (string) ( $rule['versie'] ?? '' );
			$sites  = (array) ( $rule['sites'] ?? [] );
			if ( ! preg_match( '/^[a-z0-9-]+$/', $slug ) || ! preg_match( '/^[0-9][0-9a-z.\-]*$/i', $versie ) || ( $sites && ! in_array( $host, $sites, true ) ) ) {
				continue;
			}
			$type = ( $rule['type'] ?? 'plugin' ) === 'theme' ? 'theme' : 'plugin';
			$cur  = $this->installed( $slug, $type );
			if ( null === $cur || version_compare( $cur, $versie, '<=' ) ) {
				continue;
			}
			$zip  = "https://downloads.wordpress.org/{$type}/{$slug}.{$versie}.zip";
			$skin = new Automatic_Upgrader_Skin();
			if ( 'theme' === $type ) {
				$up = new Theme_Upgrader( $skin );
				$ok = $up->install( $zip, [ 'overwrite_package' => true ] );
			} else {
				$file   = $this->plugin_file( $slug );
				$active = $file && is_plugin_active( $file );
				$up     = new Plugin_Upgrader( $skin );
				$ok     = $up->install( $zip, [ 'overwrite_package' => true ] );
				if ( $active && true === $ok ) {
					activate_plugin( $file );
				}
			}
			$this->log( true === $ok ? 'terug' : 'terug-mislukt', $slug . ' ' . $cur . ' > ' . $versie );
		}
	}

	private function plugin_file( string $slug ): ?string {
		foreach ( array_keys( get_plugins() ) as $f ) {
			if ( dirname( $f ) === $slug ) {
				return $f;
			}
		}
		return null;
	}

	private function installed( string $slug, string $type ): ?string {
		if ( 'theme' === $type ) {
			$t = wp_get_theme( $slug );
			return $t->exists() ? (string) $t->get( 'Version' ) : null;
		}
		if ( 'wordpress' === $slug ) {
			return get_bloginfo( 'version' );
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$f = $this->plugin_file( $slug );
		return $f ? (string) ( get_plugins()[ $f ]['Version'] ?? '' ) : null;
	}

	/* ---------- logboek en fouten ---------- */

	private function log( string $type, string $msg ): void {
		$log = (array) get_option( 'scrolla_update_log', [] );
		array_unshift( $log, [ 't' => time(), 'type' => $type, 'msg' => $msg ] );
		update_option( 'scrolla_update_log', array_slice( $log, 0, 100 ), false );
	}

	public function log_updates( $results ): void {
		foreach ( (array) $results as $type => $items ) {
			foreach ( (array) $items as $r ) {
				$name = is_object( $r ) ? ( $r->name ?? ( $r->item->slug ?? '' ) ) : '';
				$this->log( empty( $r->result ) || is_wp_error( $r->result ) ? 'update-mislukt' : 'update', $type . ': ' . wp_strip_all_tags( (string) $name ) );
			}
		}
	}

	public function from_wp( $should, $error ) {
		$this->catch_fatal( is_array( $error ) ? $error : null );
		return $should;
	}

	public function catch_fatal( ?array $e = null ): void {
		static $done = false;
		$e = $e ?? error_get_last();
		if ( $done || ! $e || ! in_array( $e['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true ) || ! function_exists( 'update_option' ) ) {
			return;
		}
		$done = true;
		$waar = 'onbekend';
		if ( preg_match( '#/(plugins|themes)/([^/]+)/#', (string) $e['file'], $m ) ) {
			$waar = $m[2];
		} elseif ( str_contains( (string) $e['file'], 'wp-includes' ) || str_contains( (string) $e['file'], 'wp-admin' ) ) {
			$waar = 'wordpress';
		}
		$f = array_filter( (array) get_option( self::FOUTEN, [] ), fn( $r ) => $r['t'] > time() - 14 * DAY_IN_SECONDS );
		array_unshift( $f, [ 't' => time(), 'waar' => $waar, 'msg' => substr( (string) $e['message'], 0, 300 ) ] );
		update_option( self::FOUTEN, array_slice( $f, 0, 50 ), false );
	}

	/* ---------- status voor Claude ---------- */

	public function rest(): void {
		register_rest_route( 'scrolla/v1', '/status', [
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => [ $this, 'status' ],
		] );
	}

	public function status(): array {
		$lijst  = self::lijst();
		$achter = [];
		$versies = [];
		foreach ( (array) ( $lijst['stack'] ?? [] ) as $slug => $ok ) {
			$type = wp_get_theme( $slug )->exists() ? 'theme' : 'plugin';
			$cur  = $this->installed( $slug, $type );
			if ( null === $cur ) {
				continue;
			}
			$versies[ $slug ] = $cur;
			if ( version_compare( $cur, (string) $ok, '<' ) ) {
				$achter[] = $slug;
			}
		}
		$week   = time() - 7 * DAY_IN_SECONDS;
		$fouten = array_values( array_filter( (array) get_option( self::FOUTEN, [] ), fn( $r ) => $r['t'] > $week ) );
		$log    = array_values( array_filter( (array) get_option( 'scrolla_update_log', [] ), fn( $r ) => $r['t'] > $week ) );
		$out    = [
			'core'        => SCROLLA_CORE_VERSION,
			'kanarie'     => self::kanarie(),
			'lijst'       => (string) ( $lijst['bijgewerkt'] ?? '' ),
			'opgehaald'   => (int) ( $lijst['opgehaald'] ?? 0 ),
			'achter'      => $achter,
			'fouten_7d'   => count( $fouten ),
			'fouten_waar' => array_values( array_unique( array_column( $fouten, 'waar' ) ) ),
			'updates_7d'  => count( array_filter( $log, fn( $r ) => 'update' === $r['type'] ) ),
			'mislukt_7d'  => count( array_filter( $log, fn( $r ) => str_ends_with( $r['type'], 'mislukt' ) ) ),
		];
		if ( self::kanarie() ) {
			$out['versies'] = $versies + [ 'php' => PHP_VERSION ];
			$out['fouten']  = $fouten;
			$out['log']     = $log;
		}
		return $out;
	}
}

new Scrolla_Updates();
