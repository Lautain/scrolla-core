<?php
/**
 * Module Beheeromgeving: rustig, snel en overzichtelijk wp-admin.
 */

defined( 'ABSPATH' ) || exit;

class Scrolla_Beheer {

	private static ?Scrolla_Beheer $inst = null;

	public static function instance(): Scrolla_Beheer {
		return self::$inst ??= new self();
	}

	public function __construct() {
		add_action( 'admin_bar_menu', [ $this, 'toolbar' ], 999 );
		add_action( 'wp_dashboard_setup', [ $this, 'dashboard' ], 999 );
		add_filter( 'show_admin_bar', [ $this, 'show_admin_bar' ] );
		add_action( 'admin_head', [ $this, 'admin_css' ] );
		add_action( 'admin_menu', [ $this, 'menu_items' ], 999 );
		add_filter( 'custom_menu_order', '__return_true' );
		add_filter( 'menu_order', [ $this, 'menu_order' ], 999 );
		add_action( 'admin_footer', [ $this, 'notices_js' ] );
		add_filter( 'admin_footer_text', fn() => 'Website door <a href="https://www.scrolla.nl" target="_blank" rel="noopener">scrolla | digital agency</a> · Vragen? <a href="mailto:support@scrolla.nl">support@scrolla.nl</a>' );
		add_filter( 'update_footer', fn( $t ) => scrolla_is_scrolla_user() ? $t : '', 11 );
		add_filter( 'media_library_infinite_scrolling', '__return_true' );
		add_filter( 'all_plugins', [ $this, 'active_plugins_first' ] );
		add_action( 'admin_head', fn() => get_current_screen() && get_current_screen()->remove_help_tabs() );
		// Dupliceren
		add_filter( 'post_row_actions', [ $this, 'row_action' ], 10, 2 );
		add_filter( 'page_row_actions', [ $this, 'row_action' ], 10, 2 );
		add_action( 'admin_action_scrolla_dupliceer', [ $this, 'duplicate' ] );
	}

	public function toolbar( WP_Admin_Bar $bar ): void {
		foreach ( [ 'wp-logo', 'customize', 'comments', 'new-content', 'updates', 'elementor_notes', 'rank-math', 'search', 'litespeed-menu' ] as $id ) {
			if ( 'updates' === $id && scrolla_is_scrolla_user() ) {
				continue;
			}
			if ( 'litespeed-menu' === $id && scrolla_is_scrolla_user() ) {
				continue;
			}
			$bar->remove_node( $id );
		}
		$acc = $bar->get_node( 'my-account' );
		if ( $acc ) {
			$bar->add_node( [ 'id' => 'my-account', 'title' => preg_replace( '/^.*?(<span class="display-name">)/', '$1', (string) $acc->title ) ] );
		}
		if ( ! get_option( 'blog_public' ) && current_user_can( 'manage_options' ) ) {
			$bar->add_node( [
				'id'     => 'scrolla-se',
				'parent' => 'top-secondary',
				'title'  => 'Zoekmachines: UIT',
				'href'   => admin_url( 'options-reading.php' ),
				'meta'   => [ 'class' => 'scrolla-se-uit', 'title' => 'Zoekmachines mogen deze site niet indexeren (Instellingen > Lezen).' ],
			] );
		}
		if ( is_admin() ) {
			$bar->add_node( [ 'id' => 'scrolla-meldingen', 'parent' => 'top-secondary', 'title' => 'Meldingen <span class="sc-count">0</span>', 'href' => '#', 'meta' => [ 'class' => 'scrolla-meldingen hidden' ] ] );
		}
	}

	/** Snelkoppelingen Menu's en Templates, en de koppen Website / Instellingen. */
	public function menu_items(): void {
		global $menu;
		if ( current_user_can( 'edit_theme_options' ) && current_theme_supports( 'menus' ) ) {
			add_menu_page( 'Menu\'s', 'Menu\'s', 'edit_theme_options', 'nav-menus.php', '', 'dashicons-menu', 26 );
		}
		$exists = in_array( 'edit.php?post_type=elementor_library', array_column( (array) $menu, 2 ), true );
		if ( ! $exists && post_type_exists( 'elementor_library' ) && current_user_can( 'edit_posts' ) ) {
			add_menu_page( 'Templates', 'Templates', 'edit_posts', 'edit.php?post_type=elementor_library', '', 'dashicons-layout', 27 );
		}
		$menu[] = [ '', 'read', 'sc-kop-website', '', 'wp-menu-separator sc-kop sc-kop-website' ];
		$menu[] = [ '', 'read', 'sc-kop-instellingen', '', 'wp-menu-separator sc-kop sc-kop-instellingen' ];
	}

	public function menu_order( $order ) {
		if ( ! is_array( $order ) ) {
			return $order;
		}
		$website = [ 'scrolla-algemeen', 'edit.php?post_type=page', 'edit.php' ];
		if ( class_exists( 'Scrolla_Types' ) ) {
			foreach ( array_keys( Scrolla_Types::config() ) as $pt ) {
				$website[] = 'edit.php?post_type=' . $pt;
			}
		}
		$website = array_merge( $website, [ 'upload.php', 'nav-menus.php', 'edit.php?post_type=elementor_library' ] );
		$rest    = array_values( array_filter( $order, fn( $s ) => ! in_array( $s, $website, true ) && ! in_array( $s, [ 'index.php', 'sc-kop-website', 'sc-kop-instellingen', 'scrolla-core' ], true ) && ! str_starts_with( (string) $s, 'separator' ) ) );
		$web     = array_values( array_filter( $website, fn( $s ) => in_array( $s, $order, true ) ) );
		$new     = array_merge( [ 'index.php', 'sc-kop-website' ], $web, $rest ? [ 'sc-kop-instellingen' ] : [], $rest, in_array( 'scrolla-core', $order, true ) ? [ 'scrolla-core' ] : [] );
		return $new;
	}

	public function dashboard(): void {
		global $wp_meta_boxes;
		$keep = [ 'scrolla_welkom', 'scrolla_systeem' ];
		foreach ( [ 'normal', 'side', 'column3', 'column4' ] as $ctx ) {
			foreach ( [ 'high', 'core', 'default', 'low' ] as $prio ) {
				foreach ( array_keys( $wp_meta_boxes['dashboard'][ $ctx ][ $prio ] ?? [] ) as $id ) {
					if ( ! in_array( $id, $keep, true ) && ! str_starts_with( $id, 'ame-custom-widget' ) ) {
						unset( $wp_meta_boxes['dashboard'][ $ctx ][ $prio ][ $id ] );
					}
				}
			}
		}
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
		wp_add_dashboard_widget( 'scrolla_welkom', 'Welkom bij je website', [ $this, 'widget_welkom' ] );
		if ( scrolla_is_scrolla_user() ) {
			add_meta_box( 'scrolla_systeem', 'Systeemoverzicht', [ $this, 'widget_systeem' ], 'dashboard', 'side', 'high' );
		}
	}

	public function widget_welkom(): void {
		$links = [
			[ 'edit.php?post_type=page', 'Pagina\'s', 'dashicons-admin-page', 'edit_pages' ],
			[ 'edit.php', 'Berichten', 'dashicons-admin-post', 'edit_posts' ],
			[ 'upload.php', 'Media', 'dashicons-admin-media', 'upload_files' ],
			[ 'admin.php?page=scrolla-algemeen', 'Algemeen', 'dashicons-building', 'edit_pages' ],
			[ 'nav-menus.php', 'Menu\'s', 'dashicons-menu', 'edit_theme_options' ],
		];
		echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px">';
		foreach ( $links as $l ) {
			if ( current_user_can( $l[3] ) ) {
				printf( '<a href="%s" style="display:flex;gap:8px;align-items:center;padding:12px;border:1px solid #e2e4e7;border-radius:8px;text-decoration:none"><span class="dashicons %s"></span>%s</a>', esc_url( admin_url( $l[0] ) ), esc_attr( $l[2] ), esc_html( $l[1] ) );
			}
		}
		echo '</div><p style="margin-top:14px">Hulp nodig? Mail <a href="mailto:support@scrolla.nl">support@scrolla.nl</a> of bel <a href="tel:+31850160806">085 016 08 06</a>.</p>';
	}

	public function widget_systeem(): void {
		global $wpdb;
		$rows = [
			'WordPress' => get_bloginfo( 'version' ),
			'PHP'       => PHP_VERSION . ' (geheugen ' . ini_get( 'memory_limit' ) . ', upload ' . size_format( wp_max_upload_size() ) . ')',
			'Database'  => $wpdb->db_server_info(),
			'Server'    => ( $_SERVER['SERVER_SOFTWARE'] ?? '?' ) . ' · ' . ( $_SERVER['SERVER_ADDR'] ?? gethostbyname( (string) gethostname() ) ),
			'Thema'     => wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ),
			'Plug-ins'  => count( (array) get_option( 'active_plugins', [] ) ) . ' actief',
			'Indexeren' => get_option( 'blog_public' ) ? 'aan' : '<strong style="color:#d63638">uit</strong>',
			'Uploads'   => size_format( (int) get_transient( 'scrolla_uploads_size' ) ?: $this->uploads_size() ),
		];
		echo '<table class="widefat striped">';
		foreach ( $rows as $k => $v ) {
			printf( '<tr><td><strong>%s</strong></td><td>%s</td></tr>', esc_html( $k ), wp_kses_post( $v ) );
		}
		echo '</table>';
	}

	private function uploads_size(): int {
		$dir  = wp_upload_dir()['basedir'];
		$size = 0;
		if ( is_dir( $dir ) ) {
			foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
				$size += $f->getSize();
			}
		}
		set_transient( 'scrolla_uploads_size', $size, DAY_IN_SECONDS );
		return $size;
	}

	public function show_admin_bar( $show ) {
		$u = wp_get_current_user();
		return array_intersect( (array) $u->roles, [ 'subscriber', 'contributor', 'customer' ] ) ? false : $show;
	}

	public function admin_css(): void {
		echo '<style>
		#adminmenuback,#adminmenuwrap,#adminmenu{width:200px}#wpcontent,#wpfooter{margin-left:200px}
		#adminmenu .wp-submenu{left:200px;width:200px;min-width:200px}
		#adminmenu .wp-has-current-submenu .wp-submenu,#adminmenu .wp-has-current-submenu.opensub .wp-submenu,#adminmenu a.wp-has-current-submenu:focus+.wp-submenu{width:auto;min-width:0;left:auto}
		#adminmenu li.wp-menu-separator.sc-kop{height:auto;margin:14px 0 4px;cursor:default}#adminmenu li.sc-kop .separator{display:none}
		#adminmenu li.sc-kop:after{display:block;padding:0 12px;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#8c8f94}
		#adminmenu li.sc-kop-website:after{content:"Website"}#adminmenu li.sc-kop-instellingen:after{content:"Instellingen"}
		.folded #adminmenu li.sc-kop:after{content:"";border-top:1px solid #3c434a;margin:0 8px}
		.folded #adminmenuback,.folded #adminmenuwrap,.folded #adminmenu{width:36px}.folded #wpcontent,.folded #wpfooter{margin-left:36px}.folded #adminmenu .wp-submenu{left:36px}
		@media (max-width:960px){.auto-fold #wpcontent,.auto-fold #wpfooter{margin-left:36px}.auto-fold #adminmenuback,.auto-fold #adminmenuwrap,.auto-fold #adminmenu{width:36px}.auto-fold #adminmenu .wp-submenu{left:36px}}
		@media (max-width:782px){#wpcontent,.auto-fold #wpcontent,#wpfooter{margin-left:0}#adminmenu .wp-submenu{left:auto}}
		#adminmenu li.current a.menu-top,#adminmenu li.wp-has-current-submenu a.wp-has-current-submenu,#adminmenu li.wp-has-current-submenu .wp-submenu .wp-submenu-head{background:#EE4166}#adminmenu .wp-submenu a:focus,#adminmenu .wp-submenu a:hover,#adminmenu a:hover,#adminmenu li.menu-top>a:focus{color:#EE4166}#adminmenu li a:focus div.wp-menu-image:before,#adminmenu li.opensub div.wp-menu-image:before,#adminmenu li:hover div.wp-menu-image:before{color:#EE4166}#adminmenu li.current a.menu-top div.wp-menu-image:before,#adminmenu li.wp-has-current-submenu a.wp-has-current-submenu div.wp-menu-image:before{color:#fff}
		#wpadminbar .scrolla-se-uit>.ab-item{background:#f0a400!important;color:#1d2327!important;font-weight:600}
		#wpadminbar .scrolla-meldingen.hidden{display:none}#wpadminbar .sc-count{background:#d63638;color:#fff;border-radius:9px;padding:0 6px;margin-left:4px;font-size:11px}
		#sc-meldingen{position:fixed;top:32px;right:12px;width:min(560px,92vw);max-height:70vh;overflow:auto;background:#fff;border:1px solid #c3c4c7;border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,.15);padding:8px 12px;z-index:99999;display:none}
		#sc-meldingen.open{display:block}#sc-meldingen .notice{margin:8px 0}
		</style>';
	}

	/** Verzamelt beheermeldingen in een paneel achter "Meldingen" in de toolbar. Succes- en foutmeldingen van de eigen actie blijven staan. */
	public function notices_js(): void {
		?>
		<div id="sc-meldingen"></div>
		<script>
		(function(){
			var keep = '.updated.settings-error, #setting-error-settings_updated, .notice-success.is-dismissible, #message, .inline, .hidden, .below-h2, .sc-keep';
			var list = document.querySelectorAll('#wpbody-content > .notice, #wpbody-content > .update-nag, #wpbody-content > .error, #wpbody-content > .updated, #wpbody-content .wrap > .notice, #wpbody-content .wrap > .error, #wpbody-content .wrap > .updated');
			var panel = document.getElementById('sc-meldingen'), n = 0;
			list.forEach(function(el){ if (el.matches(keep) || (el.closest('form') && !el.closest('.wrap > form'))) return; panel.appendChild(el); n++; });
			var node = document.getElementById('wp-admin-bar-scrolla-meldingen');
			if (node && n) { node.classList.remove('hidden'); node.querySelector('.sc-count').textContent = n;
				node.querySelector('a').addEventListener('click', function(e){ e.preventDefault(); panel.classList.toggle('open'); }); }
			document.addEventListener('click', function(e){ if (panel.classList.contains('open') && !panel.contains(e.target) && !(node && node.contains(e.target))) panel.classList.remove('open'); });
		})();
		</script>
		<?php
	}

	public function active_plugins_first( $plugins ) {
		if ( ! is_admin() || ( $GLOBALS['pagenow'] ?? '' ) !== 'plugins.php' ) {
			return $plugins;
		}
		uksort( $plugins, fn( $a, $b ) => ( is_plugin_active( $b ) <=> is_plugin_active( $a ) ) ?: strcasecmp( $plugins[ $a ]['Name'], $plugins[ $b ]['Name'] ) );
		return $plugins;
	}

	public function row_action( $actions, $post ) {
		if ( current_user_can( 'edit_posts' ) && ! in_array( $post->post_type, [ 'attachment', 'wp_block' ], true ) && post_type_supports( $post->post_type, 'title' ) ) {
			$url                  = wp_nonce_url( admin_url( 'admin.php?action=scrolla_dupliceer&post=' . $post->ID ), 'scrolla_dupliceer_' . $post->ID );
			$actions['scrolla_dup'] = '<a href="' . esc_url( $url ) . '">Dupliceren</a>';
		}
		return $actions;
	}

	public function duplicate(): void {
		$id   = absint( $_GET['post'] ?? 0 );
		$post = get_post( $id );
		if ( ! $post || ! check_admin_referer( 'scrolla_dupliceer_' . $id ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( 'Dupliceren niet toegestaan.' );
		}
		$new = wp_insert_post( wp_slash( [
			'post_type'    => $post->post_type,
			'post_title'   => $post->post_title . ' (kopie)',
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_status'  => 'draft',
			'post_parent'  => $post->post_parent,
			'menu_order'   => $post->menu_order,
			'post_author'  => get_current_user_id(),
		] ) );
		if ( is_wp_error( $new ) ) {
			wp_die( esc_html( $new->get_error_message() ) );
		}
		foreach ( get_object_taxonomies( $post->post_type ) as $tax ) {
			wp_set_object_terms( $new, wp_get_object_terms( $id, $tax, [ 'fields' => 'ids' ] ), $tax );
		}
		foreach ( get_post_meta( $id ) as $key => $values ) {
			if ( in_array( $key, [ '_edit_lock', '_edit_last', '_wp_old_slug', '_elementor_css' ], true ) ) {
				continue;
			}
			foreach ( $values as $v ) {
				add_post_meta( $new, $key, wp_slash( maybe_unserialize( $v ) ) );
			}
		}
		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new ) );
		exit;
	}
}

Scrolla_Beheer::instance();
