<?php
/**
 * Module Menu per rol (vervanger van Admin Menu Editor Pro).
 * - Per rol een lijst met toegestane menu-items (whitelist): wat niet is aangevinkt is verborgen,
 *   ook nieuwe items van later geïnstalleerde plug-ins.
 * - Verborgen pagina's zijn ook via de directe URL niet bereikbaar.
 * - Rollen zonder regels en Scrolla-beheerders worden nooit beperkt.
 * - Instellen alleen door Scrolla-beheerders; per site, met export/import als startpunt.
 */

defined( 'ABSPATH' ) || exit;

class Scrolla_Menu {

	const RULES    = 'scrolla_menu_rules';
	const SNAPSHOT = 'scrolla_menu_snapshot';

	/** Altijd toegestaan (anders kan niemand meer werken). */
	const ALWAYS = [ 'index.php', 'profile.php', 'admin-ajax.php', 'async-upload.php', 'media-upload.php', 'admin-post.php' ];

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'snapshot' ], PHP_INT_MAX );
		add_action( 'admin_menu', [ $this, 'apply' ], PHP_INT_MAX - 1 );
		add_action( 'current_screen', [ $this, 'guard' ] );
		add_action( 'admin_menu', [ $this, 'menu' ], 102 );
		add_action( 'admin_post_scrolla_menu_save', [ $this, 'save' ] );
		add_action( 'init', [ $this, 'caps' ] );
	}

	/** Menu-slugs bevatten soms &amp;; vergelijk altijd gedecodeerd. */
	private static function n( string $s ): string {
		return html_entity_decode( $s, ENT_QUOTES );
	}

	private static function rules(): array {
		return (array) get_option( self::RULES, [] );
	}

	/** Regels voor de huidige gebruiker, of null als die niet beperkt wordt. */
	private function user_rules(): ?array {
		if ( scrolla_is_scrolla_user() ) {
			return null;
		}
		$rules = self::rules();
		foreach ( (array) wp_get_current_user()->roles as $role ) {
			if ( ! empty( $rules[ $role ]['actief'] ) ) {
				return array_map( [ self::class, 'n' ], (array) ( $rules[ $role ]['toegestaan'] ?? [] ) );
			}
		}
		return null;
	}

	private static function clean( string $title ): string {
		return trim( wp_strip_all_tags( preg_replace( '#<span.*?</span>#s', '', $title ) ) );
	}

	/** Bewaar de volledige menustructuur zoals een Scrolla-beheerder hem ziet (voor het instelscherm). */
	public function snapshot(): void {
		global $menu, $submenu;
		if ( ! scrolla_is_scrolla_user() ) {
			return;
		}
		$snap = [];
		foreach ( (array) $menu as $m ) {
			if ( empty( $m[2] ) || str_starts_with( (string) $m[4], 'wp-menu-separator' ) || 'scrolla-core' === $m[2] ) {
				continue;
			}
			$item = [ 'slug' => $m[2], 'title' => self::clean( (string) $m[0] ), 'sub' => [] ];
			foreach ( (array) ( $submenu[ $m[2] ] ?? [] ) as $s ) {
				$item['sub'][] = [ 'slug' => $s[2], 'title' => self::clean( (string) $s[0] ) ];
			}
			$snap[] = $item;
		}
		if ( class_exists( 'Scrolla_Beheer' ) ) {
			$order = Scrolla_Beheer::instance()->menu_order( array_column( $snap, 'slug' ) );
			usort( $snap, fn( $a, $b ) => array_search( $a['slug'], $order, true ) <=> array_search( $b['slug'], $order, true ) );
		}
		if ( $snap !== get_option( self::SNAPSHOT ) ) {
			update_option( self::SNAPSHOT, $snap, false );
		}
	}

	public function apply(): void {
		global $menu, $submenu;
		$allowed = $this->user_rules();
		if ( null === $allowed ) {
			return;
		}
		foreach ( (array) $menu as $i => $m ) {
			$slug = self::n( (string) ( $m[2] ?? '' ) );
			if ( ! $slug || str_starts_with( (string) ( $m[4] ?? '' ), 'wp-menu-separator' ) ) {
				continue;
			}
			$subs_allowed = array_filter( (array) ( $submenu[ $m[2] ] ?? [] ), fn( $s ) => in_array( $slug . '>' . self::n( $s[2] ), $allowed, true ) );
			if ( ! in_array( $slug, $allowed, true ) && ! in_array( $slug, self::ALWAYS, true ) ) {
				unset( $menu[ $i ] );
				continue;
			}
			// Hoofditem toegestaan maar geen subitems: alleen het hoofditem tonen (subitems weg).
			if ( isset( $submenu[ $m[2] ] ) ) {
				foreach ( $submenu[ $m[2] ] as $j => $s ) {
					if ( ! in_array( $slug . '>' . self::n( $s[2] ), $allowed, true ) ) {
						unset( $submenu[ $m[2] ][ $j ] );
					}
				}
				// Eén toegestaan subitem: hoofditem krijgt die naam (bijv. Weergave > Menu's wordt "Menu's").
				$only_slug = $subs_allowed ? self::n( (string) reset( $subs_allowed )[2] ) : '';
				if ( 1 === count( $subs_allowed ) && $only_slug !== $slug ) {
					$only          = reset( $subs_allowed );
					$menu[ $i ][0] = $only[0];
				}
				// Hoofditem wijst naar het eerste toegestane subitem.
				if ( $subs_allowed && ! in_array( $slug . '>' . $slug, $allowed, true ) ) {
					$first          = reset( $subs_allowed );
					$menu[ $i ][2]  = $first[2];
				}
			}
		}
	}

	/** Blokkeer directe toegang tot verborgen schermen. */
	public function guard( $screen ): void {
		$allowed = $this->user_rules();
		if ( null === $allowed || wp_doing_ajax() ) {
			return;
		}
		global $pagenow;
		if ( in_array( $pagenow, self::ALWAYS, true ) ) {
			return;
		}
		$cands = [];
		if ( ! empty( $_GET['page'] ) ) { // phpcs:ignore
			$cands[] = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore
		}
		$pt = $screen->post_type ?: 'post';
		if ( in_array( $pagenow, [ 'post.php', 'post-new.php', 'edit.php' ], true ) ) {
			$cands[] = 'post' === $pt ? 'edit.php' : 'edit.php?post_type=' . $pt;
			if ( 'post-new.php' === $pagenow ) {
				$cands[] = 'post' === $pt ? 'post-new.php' : 'post-new.php?post_type=' . $pt;
			}
		} elseif ( in_array( $pagenow, [ 'edit-tags.php', 'term.php' ], true ) ) {
			$cands[] = 'edit-tags.php?taxonomy=' . $screen->taxonomy . ( 'post' !== $pt ? '&post_type=' . $pt : '' );
			$cands[] = 'edit-tags.php?taxonomy=' . $screen->taxonomy;
		} elseif ( 'upload.php' === $pagenow || 'media-new.php' === $pagenow ) {
			$cands[] = 'upload.php';
		} else {
			$cands[] = $pagenow;
		}
		$flat = array_map( fn( $a ) => str_contains( $a, '>' ) ? substr( $a, strpos( $a, '>' ) + 1 ) : $a, $allowed );
		$flat = array_merge( $flat, $allowed );
		foreach ( $cands as $c ) {
			if ( in_array( $c, $flat, true ) ) {
				return;
			}
		}
		// Elementor-editor en pagina's van een toegestaan berichttype mogen altijd.
		if ( 'post.php' === $pagenow && 'elementor' === ( $_GET['action'] ?? '' ) ) { // phpcs:ignore
			return;
		}
		wp_die( 'Je hebt geen toegang tot dit onderdeel. Neem contact op met Scrolla! als je dit wel nodig hebt.', 'Geen toegang', [ 'response' => 403, 'back_link' => true ] );
	}

	/** Optioneel: Redacteur mag menu's bewerken (edit_theme_options). */
	public function caps(): void {
		$want = ! empty( self::rules()['editor']['menus'] );
		$role = get_role( 'editor' );
		if ( $role && $role->has_cap( 'edit_theme_options' ) !== $want ) {
			$want ? $role->add_cap( 'edit_theme_options' ) : $role->remove_cap( 'edit_theme_options' );
		}
	}

	public function menu(): void {
		if ( scrolla_is_scrolla_user() ) {
			add_submenu_page( 'scrolla-core', 'Menu per rol', 'Menu per rol', 'manage_options', 'scrolla-menu', [ $this, 'render' ] );
		}
	}

	public static function preset_editor(): array {
		$a = [ 'index.php', 'index.php>index.php', 'scrolla-algemeen', 'edit.php?post_type=page', 'edit.php?post_type=page>edit.php?post_type=page', 'edit.php?post_type=page>post-new.php?post_type=page',
			'edit.php', 'edit.php>edit.php', 'edit.php>post-new.php', 'edit.php>edit-tags.php?taxonomy=category', 'edit.php>edit-tags.php?taxonomy=post_tag',
			'upload.php', 'upload.php>upload.php', 'upload.php>media-new.php',
			'nav-menus.php',
			'edit.php?post_type=elementor_library', 'elementor', 'elementor>e-form-submissions', 'profile.php' ];
		if ( class_exists( 'Scrolla_Types' ) ) {
			foreach ( array_keys( Scrolla_Types::config() ) as $pt ) {
				$p   = 'edit.php?post_type=' . $pt;
				$a[] = $p;
				$a[] = $p . '>' . $p;
				$a[] = $p . '>post-new.php?post_type=' . $pt;
				$a[] = $p . '>edit-tags.php?taxonomy=' . $pt . '_cat&post_type=' . $pt;
			}
		}
		return $a;
	}

	public function render(): void {
		if ( ! scrolla_is_scrolla_user() ) {
			wp_die( 'Geen toegang.' );
		}
		$roles = wp_roles()->get_names();
		unset( $roles['administrator'] );
		$role  = sanitize_key( $_GET['rol'] ?? 'editor' ); // phpcs:ignore
		$role  = isset( $roles[ $role ] ) ? $role : 'editor';
		$rules = self::rules();
		$r     = $rules[ $role ] ?? [];
		$on    = array_map( [ self::class, 'n' ], (array) ( $r['toegestaan'] ?? [] ) );
		$snap  = (array) get_option( self::SNAPSHOT, [] );
		Scrolla_Core_Settings::styles();
		echo '<style>
		.sc-roles{display:flex;gap:4px;margin:0 0 12px}.sc-roles a{padding:6px 16px;border-radius:4px;text-decoration:none;color:#2271b1;background:#fff;border:1px solid #c3c4c7;font-size:13px;font-weight:600}
		.sc-roles a.on{background:#2271b1;border-color:#2271b1;color:#fff}.sc-roles a i{font-style:normal;color:#00a32a;margin-left:4px}
		.sc-bar{display:flex;flex-wrap:wrap;gap:8px 18px;align-items:center}
		.sc-menu{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:8px;margin-top:12px}
		.sc-item{border:1px solid #c3c4c7;border-radius:4px;padding:8px 12px}.sc-item.on{border-color:#2271b1;background:#f0f6fc}
		.sc-item>label{font-weight:600;font-size:13px}.sc-subs{display:flex;flex-wrap:wrap;gap:2px 10px;margin:4px 0 0 22px}
		.sc-subs label{font-size:12px;color:#50575e}.sc-item input{margin:0 4px 0 0}
		</style>';
		echo '<div class="wrap sc-wrap">'; Scrolla_Core_Settings::head( 'Menu per rol' ); echo '<p class="sc-intro">Bepaal per rol wat zichtbaar en bereikbaar is. Niet aangevinkt is verborgen, ook voor nieuwe menu-items.</p>';
		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible sc-keep"><p>Opgeslagen.</p></div>';
		}
		echo '<div class="sc-roles">';
		foreach ( $roles as $k => $label ) {
			printf( '<a class="%s" href="%s">%s%s</a>', $k === $role ? 'on' : '', esc_url( admin_url( 'admin.php?page=scrolla-menu&rol=' . $k ) ), esc_html( translate_user_role( $label ) ), ! empty( $rules[ $k ]['actief'] ) ? '<i>●</i>' : '' );
		}
		echo '</div><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'scrolla_menu_save' );
		printf( '<input type="hidden" name="action" value="scrolla_menu_save"><input type="hidden" name="rol" value="%s">', esc_attr( $role ) );
		echo '<div class="sc-card"><div class="sc-bar">';
		printf( '<label><input type="checkbox" name="actief" value="1" %s> <strong>Menu beperken voor %s</strong></label>', checked( ! empty( $r['actief'] ), true, false ), esc_html( translate_user_role( $roles[ $role ] ) ) );
		if ( 'editor' === $role ) {
			printf( '<label><input type="checkbox" name="menus" value="1" %s> Mag menu\'s bewerken</label>', checked( ! empty( $r['menus'] ), true, false ) );
		}
		echo '<span style="flex:1"></span><button type="button" class="button button-small" id="sc-preset">Scrolla-basis</button><button type="button" class="button button-small" id="sc-alles">Alles aan</button><button type="button" class="button button-small" id="sc-niets">Alles uit</button></div>';
		echo '<div class="sc-menu">';
		foreach ( $snap as $m ) {
			$pv = self::n( $m['slug'] );
			$pon = in_array( $pv, $on, true );
			printf( '<div class="sc-item%s"><label><input type="checkbox" class="sc-parent" name="toegestaan[]" value="%s" %s>%s</label>', $pon ? ' on' : '', esc_attr( $pv ), checked( $pon, true, false ), esc_html( $m['title'] ?: $m['slug'] ) );
			if ( $m['sub'] ) {
				echo '<div class="sc-subs">';
				foreach ( $m['sub'] as $sub ) {
					$v = $pv . '>' . self::n( $sub['slug'] );
					printf( '<label><input type="checkbox" class="sc-child" name="toegestaan[]" value="%s" %s>%s</label>', esc_attr( $v ), checked( in_array( $v, $on, true ), true, false ), esc_html( $sub['title'] ?: $sub['slug'] ) );
				}
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
		if ( ! $snap ) {
			echo '<p class="sc-muted">Open één keer een willekeurig beheerscherm, dan verschijnt hier het menu.</p>';
		}
		echo '<div class="sc-actions">';
		submit_button( 'Opslaan', 'primary', 'submit', false );
		echo '</div></div>';
		printf( '<details class="sc-card"><summary style="cursor:pointer;font-weight:600">Exporteren / importeren</summary><p class="sc-muted">Kopieer de instellingen naar een andere site als startpunt.</p><div class="sc-field"><label>Export (alle rollen)</label><textarea readonly rows="2" onclick="this.select()">%s</textarea></div><div class="sc-field" style="margin-top:8px"><label>Importeren</label><textarea name="import" rows="2" placeholder="Plak een export en klik op Opslaan"></textarea></div></details>', esc_textarea( wp_json_encode( $rules ) ) );
		echo '</form>';
		?>
		<script>
		(function(){
			var preset = <?php echo wp_json_encode( self::preset_editor() ); ?>;
			var boxes = function(){ return document.querySelectorAll('input[name="toegestaan[]"]'); };
			var mark = function(){ document.querySelectorAll('.sc-item').forEach(function(i){ i.classList.toggle('on', i.querySelector('.sc-parent').checked); }); };
			document.getElementById('sc-preset').onclick = function(){ boxes().forEach(function(b){ b.checked = preset.indexOf(b.value) > -1; }); mark(); };
			document.getElementById('sc-alles').onclick = function(){ boxes().forEach(function(b){ b.checked = true; }); mark(); };
			document.getElementById('sc-niets').onclick = function(){ boxes().forEach(function(b){ b.checked = false; }); mark(); };
			document.querySelectorAll('.sc-parent').forEach(function(p){ p.addEventListener('change', function(){ p.closest('.sc-item').querySelectorAll('.sc-child').forEach(function(c){ c.checked = p.checked; }); mark(); }); });
			document.querySelectorAll('.sc-child').forEach(function(c){ c.addEventListener('change', function(){ if (c.checked) c.closest('.sc-item').querySelector('.sc-parent').checked = true; mark(); }); });
		})();
		</script>
		<?php
		echo '</div>';
	}

	public function save(): void {
		if ( ! scrolla_is_scrolla_user() || ! check_admin_referer( 'scrolla_menu_save' ) ) {
			wp_die( 'Geen toegang.' );
		}
		$rules  = self::rules();
		$import = trim( wp_unslash( $_POST['import'] ?? '' ) );
		if ( $import ) {
			$data = json_decode( $import, true );
			if ( is_array( $data ) ) {
				update_option( self::RULES, map_deep( $data, 'sanitize_text_field' ) );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=scrolla-menu&saved=1' ) );
			exit;
		}
		$role           = sanitize_key( $_POST['rol'] ?? 'editor' );
		$rules[ $role ] = [
			'actief'     => ! empty( $_POST['actief'] ),
			'menus'      => ! empty( $_POST['menus'] ),
			'toegestaan' => array_values( array_map( 'sanitize_text_field', array_map( 'wp_unslash', (array) ( $_POST['toegestaan'] ?? [] ) ) ) ),
		];
		update_option( self::RULES, $rules );
		wp_safe_redirect( admin_url( 'admin.php?page=scrolla-menu&saved=1&rol=' . $role ) );
		exit;
	}
}

new Scrolla_Menu();
