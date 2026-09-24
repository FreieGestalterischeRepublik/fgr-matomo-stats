<?php
/**
 * Plugin Name:  FGR Matomo Stats
 * Description:  Zeigt die Matomo-Statistiken dieser Seite direkt im WordPress-Backend an. Funktioniert nur mit dem Matomo der Freien Gestalterischen Republik.
 * Version:      1.0.2
 * Author:       Freie Gestalterische Republik
 * Author URI:   https://fgr.design
 * License:      GPL-2.0-or-later
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Text Domain:  fgr-matomo-stats
 */

defined( 'ABSPATH' ) || exit;

define( 'FGR_MS_VERSION', '1.0.2' );
define( 'FGR_MS_DIR', plugin_dir_path( __FILE__ ) );
define( 'FGR_MS_URL', plugin_dir_url( __FILE__ ) );
define( 'FGR_MS_API_BASE', 'https://fgr-plugins-api.fgr.design' );
define( 'FGR_MS_OPTION', 'fgr_matomo_stats' );

// Update-Checker: fragt die zentrale FGR-Update-API ab (nicht direkt GitHub,
// wegen des GitHub-API-Rate-Limits bei vielen Kundenseiten auf derselben IP).
require_once FGR_MS_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
$fgr_ms_updater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    FGR_MS_API_BASE . '/fgr-matomo-stats.json',
    __FILE__,
    'fgr-matomo-stats'
);

// Auto-Update: WordPress' täglicher Update-Cron installiert neue Versionen
// dieses Plugins automatisch, kein manueller Klick auf jeder Seite nötig.
add_filter( 'auto_update_plugin', function ( $update, $item ) {
    if ( isset( $item->slug ) && $item->slug === 'fgr-matomo-stats' ) {
        return true;
    }
    return $update;
}, 10, 2 );

require_once FGR_MS_DIR . 'includes/class-fgr-ms-data.php';
require_once FGR_MS_DIR . 'includes/class-fgr-ms-settings.php';
require_once FGR_MS_DIR . 'includes/class-fgr-ms-dashboard-widget.php';

/**
 * Nur mit FGR-Matomo verbundene Seiten dürfen das Plugin nutzen. Ohne Treffer
 * für die eigene Domain deaktiviert sich das Plugin bei der Aktivierung
 * selbst wieder, statt kaputt/leer zu bleiben.
 */
register_activation_hook( __FILE__, 'fgr_ms_on_activate' );

function fgr_ms_on_activate(): void {
    $data = FGR_MS_Data::fetch_fresh();

    if ( $data === null ) {
        // WordPress traegt das Plugin NACH dem Activation-Hook selbst wieder in
        // active_plugins ein - ein deactivate_plugins() an dieser Stelle wuerde
        // also sofort wieder ueberschrieben. Deshalb nur einen Flag setzen und
        // beim naechsten Request (admin_init) wirklich deaktivieren.
        set_transient( 'fgr_ms_needs_deactivation', 1, 60 );
    } else {
        fgr_ms_grant_access_to_admins();
    }
}

add_action( 'admin_init', 'fgr_ms_maybe_deactivate' );

function fgr_ms_maybe_deactivate(): void {
    if ( ! get_transient( 'fgr_ms_needs_deactivation' ) ) {
        return;
    }
    delete_transient( 'fgr_ms_needs_deactivation' );
    deactivate_plugins( plugin_basename( __FILE__ ) );
    set_transient( 'fgr_ms_activation_error', 1, 60 );
    unset( $_GET['activate'] ); // WordPress' Standard-"Plugin aktiviert"-Hinweis unterdrücken.
}

add_action( 'admin_notices', 'fgr_ms_activation_error_notice' );

function fgr_ms_activation_error_notice(): void {
    if ( ! get_transient( 'fgr_ms_activation_error' ) ) {
        return;
    }
    delete_transient( 'fgr_ms_activation_error' );
    ?>
    <div class="notice notice-error">
        <p>
            <strong>FGR Matomo Stats</strong> wurde automatisch wieder deaktiviert:
            Diese Seite ist nicht mit dem Matomo der Freien Gestalterischen Republik verbunden.
            Dieses Plugin funktioniert ausschließlich mit unserem eigenen Matomo-System.
        </p>
    </div>
    <?php
}

/**
 * Administratoren haben immer Zugriff, unabhängig von der User-Auswahl in
 * den Einstellungen. Wird beim Aktivieren einmalig genutzt, um allen
 * aktuellen Admins das Dashboard-Widget sichtbar zu schalten.
 */
function fgr_ms_grant_access_to_admins(): void {
    $admins = get_users( [ 'role' => 'administrator', 'fields' => 'ID' ] );
    foreach ( $admins as $user_id ) {
        FGR_MS_Dashboard_Widget::force_widget_visible( (int) $user_id );
    }
}

/**
 * Ob der aktuelle Benutzer die Matomo-Statistiken sehen darf: Admins immer,
 * sonst nur explizit in den Einstellungen freigeschaltete User.
 */
function fgr_ms_current_user_has_access(): bool {
    return fgr_ms_user_has_access( get_current_user_id() );
}

function fgr_ms_user_has_access( int $user_id ): bool {
    if ( user_can( $user_id, 'manage_options' ) ) {
        return true;
    }
    $allowed = (array) ( get_option( FGR_MS_OPTION, [] )['allowed_users'] ?? [] );
    return in_array( $user_id, array_map( 'intval', $allowed ), true );
}
