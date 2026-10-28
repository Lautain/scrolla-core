<?php
/**
 * Updates via GitHub-releases. Kijkt één keer per week; bij een geforceerde controle
 * (Dashboard > Updates > "Opnieuw controleren", WP-CLI / WP Toolkit "Alles bijwerken") direct.
 * Release: publiceer op GitHub een release met tag "v0.2.0". Een asset "scrolla-core.zip" is optioneel;
 * zonder asset gebruikt de updater de broncode-zip van de tag (de map wordt hernoemd naar scrolla-core/).
 */

defined( 'ABSPATH' ) || exit;

class Scrolla_Core_Updater {

	private string $file;
	private string $slug;
	private string $repo;
	private string $version;
	private string $cache_key = 'scrolla_core_release';

	public function __construct( string $file, string $repo, string $version ) {
		$this->file    = $file;
		$this->slug    = plugin_basename( $file );
		$this->repo    = $repo;
		$this->version = $version;
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check' ] );
		// Ook bij het lezen: zo ziet WordPress (en WP Toolkit) een nieuwe release zodra onze eigen controle hem kent.
		add_filter( 'site_transient_update_plugins', [ $this, 'check' ] );
		add_filter( 'plugins_api', [ $this, 'info' ], 20, 3 );
		add_filter( 'upgrader_source_selection', [ $this, 'fix_folder' ], 10, 4 );
		add_filter( 'auto_update_plugin', [ $this, 'auto_update' ], 10, 2 );
	}

	private function forced(): bool {
		return ( defined( 'WP_CLI' ) && WP_CLI )
			|| ! empty( $_GET['force-check'] ) // phpcs:ignore
			|| ( isset( $GLOBALS['pagenow'] ) && 'update-core.php' === $GLOBALS['pagenow'] && ! empty( $_GET['force-check'] ) ); // phpcs:ignore
	}

	private function release(): ?array {
		$cached = get_site_transient( $this->cache_key );
		if ( is_array( $cached ) && ! $this->forced() ) {
			return $cached ?: null;
		}
		$res = wp_remote_get( "https://api.github.com/repos/{$this->repo}/releases/latest", [
			'timeout' => 10,
			'headers' => [ 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'scrolla-core' ],
		] );
		$data = [];
		if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
			$json = json_decode( wp_remote_retrieve_body( $res ), true );
			$zip  = '';
			foreach ( (array) ( $json['assets'] ?? [] ) as $a ) {
				if ( 'scrolla-core.zip' === ( $a['name'] ?? '' ) ) {
					$zip = $a['browser_download_url'];
				}
			}
			if ( ! $zip && ! empty( $json['zipball_url'] ) ) {
				$zip = $json['zipball_url'];
			}
			if ( $zip ) {
				$data = [
					'version'   => ltrim( (string) $json['tag_name'], 'v' ),
					'zip'       => $zip,
					'notes'     => (string) ( $json['body'] ?? '' ),
					'published' => (string) ( $json['published_at'] ?? '' ),
				];
			}
		}
		// Ook een mislukte controle een dag cachen, zodat GitHub niet bij elke request wordt bevraagd.
		set_site_transient( $this->cache_key, $data, $data ? WEEK_IN_SECONDS : DAY_IN_SECONDS );
		return $data ?: null;
	}

	public function check( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}
		$r = $this->release();
		$item = (object) [
			'slug'        => dirname( $this->slug ),
			'plugin'      => $this->slug,
			'new_version' => $r['version'] ?? $this->version,
			'url'         => "https://github.com/{$this->repo}",
			'package'     => $r['zip'] ?? '',
			'icons'       => [],
			'tested'      => get_bloginfo( 'version' ),
		];
		if ( $r && version_compare( $r['version'], $this->version, '>' ) ) {
			$transient->response[ $this->slug ] = $item;
		} else {
			$transient->no_update[ $this->slug ] = $item;
		}
		return $transient;
	}

	public function info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || dirname( $this->slug ) !== ( $args->slug ?? '' ) ) {
			return $res;
		}
		$r = $this->release();
		return (object) [
			'name'          => 'scrolla | digital agency | core',
			'slug'          => dirname( $this->slug ),
			'version'       => $r['version'] ?? $this->version,
			'author'        => 'scrolla | digital agency',
			'homepage'      => "https://github.com/{$this->repo}",
			'download_link' => $r['zip'] ?? '',
			'last_updated'  => $r['published'] ?? '',
			'sections'      => [ 'changelog' => wpautop( esc_html( $r['notes'] ?? '' ) ) ],
		];
	}

	/** Zorg dat de uitgepakte map altijd "scrolla-core" heet. */
	public function fix_folder( $source, $remote_source, $upgrader, $hook_extra = [] ) {
		if ( ( $hook_extra['plugin'] ?? '' ) !== $this->slug ) {
			return $source;
		}
		$target = trailingslashit( $remote_source ) . 'scrolla-core/';
		if ( trailingslashit( $source ) !== $target && @rename( $source, $target ) ) { // phpcs:ignore
			return $target;
		}
		return $source;
	}

	public function auto_update( $update, $item ) {
		return ( isset( $item->plugin ) && $item->plugin === $this->slug ) ? true : $update;
	}
}
