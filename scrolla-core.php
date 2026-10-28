<?php
/**
 * Plugin Name:       Scrolla Core
 * Plugin URI:        https://github.com/Lautain/scrolla-core
 * Description:       Basis voor elke Scrolla!-website: bedrijfsgegevens, beheer, beveiliging, optimalisaties, menu-editor per rol, SMTP en eenvoudige berichttypes met velden.
 * Version:           0.2.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Scrolla!
 * Author URI:        https://www.scrolla.nl
 * License:           GPL-2.0-or-later
 * Text Domain:       scrolla-core
 * Update URI:        https://github.com/Lautain/scrolla-core
 */

defined( 'ABSPATH' ) || exit;

define( 'SCROLLA_CORE_VERSION', '0.2.0' );
define( 'SCROLLA_CORE_FILE', __FILE__ );
define( 'SCROLLA_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCROLLA_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'SCROLLA_CORE_REPO', 'Lautain/scrolla-core' );

require_once SCROLLA_CORE_DIR . 'includes/class-settings.php';
require_once SCROLLA_CORE_DIR . 'includes/class-updater.php';

/**
 * Modules. Elke module is los aan of uit te zetten op de instellingenpagina (alleen voor Scrolla).
 * Sleutel => [bestand, standaard aan?, label, omschrijving]
 */
function scrolla_core_modules(): array {
	return [
		'algemeen'   => [ 'algemeen.php', true, 'Algemeen (bedrijfsgegevens)', 'Pagina "Algemeen" met bedrijfsgegevens, socials en openingstijden; beschikbaar als dynamic tags in Elementor en als shortcode [scrolla veld="telefoon"].' ],
		'beheer'     => [ 'beheer.php', true, 'Beheeromgeving', 'Opgeruimde toolbar en dashboard, meldingen gebundeld, breder menu, systeemoverzicht, zoekmachinestatus in de toolbar, dupliceren.' ],
		'login'      => [ 'login.php', true, 'Inloggen', 'Inloggen met gebruikersnaam, algemene foutmelding, kolom "laatste login".' ],
		'opschonen'  => [ 'opschonen.php', true, 'Onnodige onderdelen uit', 'Gutenberg, reacties, feeds, emoji, oEmbed, jQuery Migrate, Dashicons op de voorkant, overbodige head-tags, blokwidgets.' ],
		'beveiliging'=> [ 'beveiliging.php', true, 'Beveiliging', 'Loginpogingen beperken, XML-RPC uit, gebruikers niet op te vragen, applicatiewachtwoorden uit, bestanden bewerken uit, security-headers.' ],
		'media'      => [ 'media.php', true, 'Afbeeldingen en optimalisatie', 'Uploads maximaal 2400px, jpg/png naar webp (82), EXIF eruit, maximaal 10 revisies, rustigere heartbeat.' ],
		'menu'       => [ 'menu.php', true, 'Menu per rol', 'Bepaal per rol welke menu-items zichtbaar zijn. Verborgen pagina\'s zijn ook niet via de URL bereikbaar.' ],
		'smtp'       => [ 'smtp.php', false, 'SMTP', 'Mail via een SMTP-server (bijv. noreply@ van de klant), met testmail en een mailog van 30 dagen.' ],
		'onderhoud'  => [ 'onderhoud.php', true, 'Onderhoud', 'Nette onderhoudspagina tijdens updates (met naam en logo van de site) en een knop in de toolbar om de site handmatig in onderhoud te zetten.' ],
		'bouwen'     => [ 'bouwen.php', true, 'Bouwen (Elementor-import)', 'REST-importpunt voor Scrolla-beheerders: Elementor-pagina\'s, templates en kit aanmaken en bijwerken, afbeeldingen importeren. Gebruikt door de ontwikkeltool.' ],
		'types'      => [ 'types.php', true, 'Berichttypes en velden', 'Eenvoudige berichttypes (diensten, vacatures, ...) met velden, als dynamic tags in Elementor.' ],
	];
}

add_action( 'plugins_loaded', function () {
	$active = Scrolla_Core_Settings::active_modules();
	foreach ( scrolla_core_modules() as $key => $m ) {
		if ( in_array( $key, $active, true ) ) {
			require_once SCROLLA_CORE_DIR . 'includes/modules/' . $m[0];
		}
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

register_activation_hook( __FILE__, function () {
	if ( false === get_option( 'scrolla_core_settings' ) ) {
		add_option( 'scrolla_core_settings', Scrolla_Core_Settings::defaults() );
	}
} );
