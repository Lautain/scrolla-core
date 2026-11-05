<?php
/**
 * Module Klantrol: de rol "[Bedrijfsnaam] beheerder" voor de klant.
 *
 * - Rechten van Redacteur, plus menu's bewerken; met WooCommerce ook die van Winkelmanager.
 *   Geen plug-ins, thema's, gebruikers of instellingen.
 * - De naam volgt de bedrijfsnaam uit Algemeen (anders de sitenaam).
 * - Menu per rol: de rol staat standaard beperkt met de Scrolla-basis (whitelist). Nieuwe menu-items
 *   van later geïnstalleerde plug-ins zijn dus verborgen tot een Scrolla-beheerder ze aanzet.
 */

defined( 'ABSPATH' ) || exit;

class Scrolla_Klant {

	const ROL  = 'klant_beheerder';
	const VERSIE = 1; // ophogen als de standaardrechten veranderen

	public function __construct() {
		add_action( 'init', [ $this, 'sync' ], 20 );
	}

	public static function naam(): string {
		$n = class_exists( 'Scrolla_Algemeen' ) ? Scrolla_Algemeen::value( 'bedrijfsnaam' ) : '';
		return trim( ( $n ?: get_bloginfo( 'name' ) ) . ' beheerder' );
	}

	private static function caps(): array {
		$caps = (array) ( get_role( 'editor' )->capabilities ?? [] );
		$caps['edit_theme_options'] = true;
		if ( class_exists( 'WooCommerce' ) && ( $shop = get_role( 'shop_manager' ) ) ) {
			$caps = array_merge( $caps, (array) $shop->capabilities );
		}
		foreach ( [ 'manage_options', 'install_plugins', 'activate_plugins', 'edit_plugins', 'delete_plugins', 'update_plugins', 'switch_themes', 'edit_themes', 'install_themes', 'delete_themes', 'update_themes', 'update_core', 'list_users', 'create_users', 'edit_users', 'delete_users', 'promote_users', 'remove_users', 'import', 'export', 'unfiltered_upload' ] as $nee ) {
			unset( $caps[ $nee ] );
		}
		return array_filter( $caps );
	}

	/** Rol aanmaken, naam bijhouden en standaard menuregels zetten (eenmalig per VERSIE). */
	public function sync(): void {
		$roles = wp_roles();
		$naam  = self::naam();
		$versie = (int) get_option( 'scrolla_klant_versie', 0 );

		if ( ! $roles->is_role( self::ROL ) ) {
			add_role( self::ROL, $naam, self::caps() );
		} elseif ( $versie < self::VERSIE ) {
			$rol = get_role( self::ROL );
			foreach ( self::caps() as $cap => $grant ) {
				$rol->add_cap( $cap, $grant );
			}
		}
		if ( isset( $roles->roles[ self::ROL ] ) && $roles->roles[ self::ROL ]['name'] !== $naam ) {
			$roles->roles[ self::ROL ]['name'] = $naam;
			$roles->role_names[ self::ROL ]   = $naam;
			update_option( $roles->role_key, $roles->roles );
		}
		if ( $versie < self::VERSIE ) {
			$rules = (array) get_option( 'scrolla_menu_rules', [] );
			if ( empty( $rules[ self::ROL ] ) && class_exists( 'Scrolla_Menu' ) ) {
				$toegestaan = Scrolla_Menu::preset_editor();
				if ( class_exists( 'WooCommerce' ) ) {
					$toegestaan = array_merge( $toegestaan, [ 'woocommerce', 'woocommerce>wc-orders', 'woocommerce>edit.php?post_type=shop_order', 'woocommerce>wc-admin&path=/customers', 'edit.php?post_type=product', 'edit.php?post_type=product>edit.php?post_type=product', 'edit.php?post_type=product>post-new.php?post_type=product', 'edit.php?post_type=product>edit-tags.php?taxonomy=product_cat&post_type=product' ] );
				}
				$rules[ self::ROL ] = [ 'actief' => true, 'menus' => true, 'toegestaan' => $toegestaan ];
				update_option( 'scrolla_menu_rules', $rules );
			}
			update_option( 'scrolla_klant_versie', self::VERSIE, false );
		}
	}
}

new Scrolla_Klant();
