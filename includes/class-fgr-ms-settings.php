<?php
defined( 'ABSPATH' ) || exit;

class FGR_MS_Settings {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'handle_save' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function add_menu(): void {
        add_submenu_page(
            'fgr-plugins',
            'FGR Matomo Stats',
            'Matomo Stats',
            'read',
            'fgr-matomo-stats',
            [ $this, 'render_stats_page' ]
        );

        // Bewusst kein add_submenu_page mit sichtbarem Parent: nur über das
        // Zahnrad auf der Statistik-Seite erreichbar, nicht im Menü gelistet.
        add_submenu_page(
            null,
            'FGR Matomo Stats – Einstellungen',
            'Matomo Stats – Einstellungen',
            'manage_options',
            'fgr-matomo-stats-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function enqueue( string $hook ): void {
        if ( ! in_array( $hook, [ 'fgr-plugins_page_fgr-matomo-stats', 'admin_page_fgr-matomo-stats-settings' ], true ) ) {
            return;
        }
        wp_enqueue_style( 'fgr-ms-admin', FGR_MS_URL . 'assets/css/admin.css', [], FGR_MS_VERSION );
        wp_enqueue_script( 'fgr-ms-admin', FGR_MS_URL . 'assets/js/admin.js', [], FGR_MS_VERSION, true );
    }

    public function render_stats_page(): void {
        if ( ! fgr_ms_current_user_has_access() ) {
            wp_die( 'Keine Berechtigung für diese Seite.' );
        }

        $data = FGR_MS_Data::get();
        ?>
        <div class="wrap fgr-ms-page">
            <div class="fgr-ms-header">
                <h1>Matomo-Statistiken</h1>
                <?php if ( current_user_can( 'manage_options' ) ) : ?>
                    <a class="fgr-ms-gear" title="Einstellungen"
                       href="<?php echo esc_url( admin_url( 'admin.php?page=fgr-matomo-stats-settings' ) ); ?>">⚙️</a>
                <?php endif; ?>
            </div>

            <?php if ( $data === null ) : ?>
                <div class="notice notice-warning">
                    <p>Noch keine Matomo-Daten für diese Seite verfügbar. Die zentrale Datenaktualisierung läuft einmal täglich – schau später nochmal vorbei.</p>
                </div>
                <?php return; ?>
            <?php endif; ?>

            <select id="fgr-ms-period">
                <option value="today">Heute</option>
                <option value="yesterday">Gestern</option>
                <option value="7days">Letzte 7 Tage</option>
                <option value="30days" selected>Letzte 30 Tage</option>
                <option value="month">Dieser Monat</option>
                <option value="year">Dieses Jahr</option>
            </select>

            <div class="fgr-ms-kpis">
                <div class="fgr-ms-kpi"><span class="fgr-ms-kpi-value" data-field="visits">–</span><span class="fgr-ms-kpi-label">Besuche</span></div>
                <div class="fgr-ms-kpi"><span class="fgr-ms-kpi-value" data-field="pageviews">–</span><span class="fgr-ms-kpi-label">Seitenaufrufe</span></div>
                <div class="fgr-ms-kpi"><span class="fgr-ms-kpi-value" data-field="bounce_rate">–</span><span class="fgr-ms-kpi-label">Absprungrate</span></div>
                <div class="fgr-ms-kpi"><span class="fgr-ms-kpi-value" data-field="avg_time">–</span><span class="fgr-ms-kpi-label">Ø Besuchsdauer (Sek.)</span></div>
            </div>

            <div class="fgr-ms-chart-wrap">
                <h2>Besucherverlauf</h2>
                <svg id="fgr-ms-chart"></svg>
                <p id="fgr-ms-chart-empty" hidden>Für diesen Zeitraum gibt es keinen Verlauf (nur ein einzelner Tag).</p>
            </div>

            <div class="fgr-ms-columns">
                <div>
                    <h2>Top-Seiten</h2>
                    <table class="widefat" id="fgr-ms-top-pages"><tbody></tbody></table>
                </div>
                <div>
                    <h2>Traffic-Quellen</h2>
                    <table class="widefat" id="fgr-ms-referrers"><tbody></tbody></table>
                </div>
            </div>

            <p class="fgr-ms-updated">Stand: <?php echo esc_html( $this->format_date( $data['generated_at'] ?? '' ) ); ?></p>
        </div>

        <script>
            window.fgrMsData = <?php echo wp_json_encode( $data ); ?>;
        </script>
        <?php
    }

    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Keine Berechtigung für diese Seite.' );
        }

        $option        = get_option( FGR_MS_OPTION, [] );
        $allowed_users = array_map( 'intval', (array) ( $option['allowed_users'] ?? [] ) );
        $users         = get_users( [ 'orderby' => 'display_name' ] );
        ?>
        <div class="wrap">
            <h1>FGR Matomo Stats – Einstellungen</h1>

            <?php settings_errors( 'fgr_ms' ); ?>

            <form method="post">
                <?php wp_nonce_field( 'fgr_ms_save', 'fgr_ms_nonce' ); ?>

                <h2>Zusätzlicher Zugriff</h2>
                <p>Administratoren sehen die Statistiken immer. Zusätzlich freischalten:</p>

                <table class="widefat">
                    <tbody>
                    <?php foreach ( $users as $user ) : ?>
                        <?php if ( user_can( $user, 'manage_options' ) ) continue; // Admins ohnehin immer erlaubt. ?>
                        <tr>
                            <td style="width:24px;">
                                <input type="checkbox" name="allowed_users[]" value="<?php echo esc_attr( $user->ID ); ?>"
                                    <?php checked( in_array( (int) $user->ID, $allowed_users, true ) ); ?>>
                            </td>
                            <td><?php echo esc_html( $user->display_name ); ?> (<?php echo esc_html( $user->user_login ); ?>)</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" name="fgr_ms_save" class="button button-primary">Speichern</button>
                </p>
            </form>

            <h2>Verbindung</h2>
            <p>Domain: <code><?php echo esc_html( FGR_MS_Data::current_domain() ); ?></code></p>
        </div>
        <?php
    }

    public function handle_save(): void {
        if ( ! isset( $_POST['fgr_ms_save'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;
        check_admin_referer( 'fgr_ms_save', 'fgr_ms_nonce' );

        $old_allowed = array_map( 'intval', (array) ( get_option( FGR_MS_OPTION, [] )['allowed_users'] ?? [] ) );
        $new_allowed = array_map( 'intval', (array) ( $_POST['allowed_users'] ?? [] ) );

        update_option( FGR_MS_OPTION, [ 'allowed_users' => $new_allowed ] );

        // Neu freigeschaltete User bekommen das Dashboard-Widget sofort sichtbar,
        // nicht erst nachdem sie es manuell über die Bildschirm-Optionen einschalten.
        foreach ( array_diff( $new_allowed, $old_allowed ) as $user_id ) {
            FGR_MS_Dashboard_Widget::force_widget_visible( $user_id );
        }

        add_settings_error( 'fgr_ms', 'saved', 'Einstellungen gespeichert.', 'success' );
    }

    private function format_date( string $iso ): string {
        if ( $iso === '' ) return '–';
        $ts = strtotime( $iso );
        return $ts ? date_i18n( 'd.m.Y H:i', $ts ) : '–';
    }
}

new FGR_MS_Settings();
