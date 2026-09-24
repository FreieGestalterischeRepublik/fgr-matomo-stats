<?php
defined( 'ABSPATH' ) || exit;

class FGR_MS_Dashboard_Widget {

    const WIDGET_ID = 'fgr_ms_dashboard_widget';

    public function __construct() {
        add_action( 'wp_dashboard_setup', [ $this, 'register' ] );
    }

    public function register(): void {
        if ( ! fgr_ms_current_user_has_access() ) {
            return;
        }

        // Dafuer sorgen, dass das Widget direkt sichtbar ist, nicht erst nach
        // manuellem Einschalten ueber die Bildschirm-Optionen.
        self::force_widget_visible( get_current_user_id() );

        wp_add_dashboard_widget(
            self::WIDGET_ID,
            'Matomo-Statistiken',
            [ $this, 'render' ]
        );
    }

    public function render(): void {
        $data = FGR_MS_Data::get();

        if ( $data === null ) {
            echo '<p>Keine Matomo-Daten verfügbar.</p>';
            return;
        }

        $today = $data['periods']['today'] ?? null;
        $month  = $data['periods']['30days'] ?? null;

        echo '<div class="fgr-ms-widget">';
        echo '<p style="display:flex;gap:24px;">';
        echo '<span><strong>' . esc_html( (string) ( $today['visits'] ?? 0 ) ) . '</strong><br>Besuche heute</span>';
        echo '<span><strong>' . esc_html( (string) ( $month['visits'] ?? 0 ) ) . '</strong><br>Besuche (30 Tage)</span>';
        echo '</p>';
        $this->render_sparkline( $month['trend'] ?? [] );
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=fgr-matomo-stats' ) ) . '">Alle Statistiken ansehen &rarr;</a></p>';
        echo '</div>';
    }

    /**
     * Kompakte, statische SVG-Sparkline (kein JS nötig, da auf der
     * Dashboard-Seite kein admin.js geladen wird) für den 30-Tage-Verlauf.
     */
    private function render_sparkline( array $trend ): void {
        if ( count( $trend ) < 2 ) {
            return;
        }

        $width  = 280;
        $height = 50;
        $pad    = 4;

        $values = array_map( static fn( $p ) => (int) $p['visits'], $trend );
        $max    = max( array_merge( $values, [ 1 ] ) );
        $stepX  = ( $width - $pad * 2 ) / max( count( $trend ) - 1, 1 );

        $points = [];
        foreach ( $values as $i => $v ) {
            $x = $pad + $i * $stepX;
            $y = $height - $pad - ( $v / $max ) * ( $height - $pad * 2 );
            $points[] = round( $x, 1 ) . ',' . round( $y, 1 );
        }

        $area = $points;
        $area[] = round( $pad + ( count( $values ) - 1 ) * $stepX, 1 ) . ',' . ( $height - $pad );
        $area[] = round( $pad, 1 ) . ',' . ( $height - $pad );

        echo '<svg class="fgr-ms-sparkline" viewBox="0 0 ' . esc_attr( $width ) . ' ' . esc_attr( $height ) . '" preserveAspectRatio="none">';
        echo '<polygon points="' . esc_attr( implode( ' ', $area ) ) . '" fill="rgba(34,113,177,0.12)" stroke="none" />';
        echo '<polyline points="' . esc_attr( implode( ' ', $points ) ) . '" fill="none" stroke="#2271b1" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />';
        echo '</svg>';
    }

    /**
     * Entfernt das Widget aus der Liste der vom User ausgeblendeten
     * Dashboard-Widgets, damit es sofort sichtbar ist statt erst nach
     * manueller Aktivierung über die Bildschirm-Optionen.
     */
    public static function force_widget_visible( int $user_id ): void {
        $hidden = get_user_meta( $user_id, 'metaboxhidden_dashboard', true );
        if ( ! is_array( $hidden ) ) {
            return; // Noch nie ein Dashboard aufgerufen -> nichts zu tun, Widget ist ohnehin sichtbar.
        }
        $key = array_search( self::WIDGET_ID, $hidden, true );
        if ( $key !== false ) {
            unset( $hidden[ $key ] );
            update_user_meta( $user_id, 'metaboxhidden_dashboard', array_values( $hidden ) );
        }
    }
}

new FGR_MS_Dashboard_Widget();
