<?php
/**
 * Plugin Name:       scrolla | digital agency | core
 * Plugin URI:        https://github.com/Lautain/scrolla-core
 * Description:       De vaste basis van elke scrolla-website: bedrijfsgegevens, opgeruimd beheer, beveiliging, optimalisaties, menu per rol, SMTP en berichttypes per project.
 * Version:           0.3.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            scrolla | digital agency
 * Author URI:        https://www.scrolla.nl
 * License:           GPL-2.0-or-later
 * Text Domain:       scrolla-core
 * Update URI:        https://github.com/Lautain/scrolla-core
 */

defined( 'ABSPATH' ) || exit;

define( 'SCROLLA_CORE_VERSION', '0.3.1' );
define( 'SCROLLA_CORE_FILE', __FILE__ );
define( 'SCROLLA_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCROLLA_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'SCROLLA_CORE_REPO', 'Lautain/scrolla-core' );

require_once SCROLLA_CORE_DIR . 'includes/class-settings.php';
require_once SCROLLA_CORE_DIR . 'includes/class-updater.php';

/**
 * Onderdelen. Alles staat standaard aan: dit is de Scrolla-standaard voor elke site.
 * SMTP doet pas iets als er een server is ingevuld; berichttypes alleen als Claude ze per project aanmaakt.
 */
function scrolla_core_modules(): array {
	return [ 'algemeen', 'beheer', 'login', 'opschonen', 'beveiliging', 'media', 'menu', 'smtp', 'onderhoud', 'bouwen', 'types' ];
}

add_action( 'plugins_loaded', function () {
	foreach ( scrolla_core_modules() as $m ) {
		require_once SCROLLA_CORE_DIR . 'includes/modules/' . $m . '.php';
	}
	new Scrolla_Core_Settings();
	new Scrolla_Core_Updater( SCROLLA_CORE_FILE, SCROLLA_CORE_REPO, SCROLLA_CORE_VERSION );
}, 5 );

/**
 * Is de huidige (of opgegeven) gebruiker een Scrolla-beheerder?
 * Beheerder met een @scrolla.nl-adres, of de gebruiker "Scrolla!". Uitbreidbaar via filter.
 */
function scrolla_is_scrolla_user( $user = null ): bool {
	$user = $user ? $user : wp_get_current_user();
	if ( ! $user || ! $user->exists() || ! user_can( $user, 'manage_options' ) ) {
		return false;
	}
	$is = (bool) preg_match( '/@scrolla\.nl$/i', (string) $user->user_email ) || in_array( strtolower( $user->user_login ), [ 'scrolla!', 'scrolla' ], true );
	return (bool) apply_filters( 'scrolla_core_is_scrolla_user', $is, $user );
}

