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
        $this->render_sparkline_placeholder( $month['trend'] ?? [] );
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=fgr-matomo-stats' ) ) . '">Alle Statistiken ansehen &rarr;</a></p>';
        echo '</div>';
    }

    /**
     * Responsive Sparkline: admin.js zeichnet sie anhand der tatsächlichen
     * Widget-Breite neu (auch bei Fensteränderung). Für Browser ohne JS gibt
     * es als Fallback die feste, serverseitig gerenderte Variante.
     */
    private function render_sparkline_placeholder( array $trend ): void {
        if ( count( $trend ) < 2 ) {
            return;
        }

        echo '<svg id="fgr-ms-widget-chart" class="fgr-ms-sparkline"></svg>';
        echo '<script>window.fgrMsWidgetTrend = ' . wp_json_encode( $trend ) . ';</script>';
        echo '<noscript>';
        $this->render_sparkline_static( $trend );
        echo '</noscript>';
    }

    /**
     * Feste Pixelgröße statt width:100% - preserveAspectRatio würde beim
     * Strecken sonst die Achsenbeschriftung verzerren. Nur als <noscript>-
     * Fallback genutzt; normalerweise übernimmt admin.js das responsive
     * Zeichnen (siehe render_sparkline_placeholder()).
     */
    private function render_sparkline_static( array $trend ): void {
        if ( count( $trend ) < 2 ) {
            return;
        }

        $width     = 280;
        $height    = 80;
        $padLeft   = 26;
        $padRight  = 6;
        $padTop    = 6;
        $padBottom = 16;
        $plotWidth  = $width - $padLeft - $padRight;
        $plotHeight = $height - $padTop - $padBottom;

        $values = array_map( static fn( $p ) => (int) $p['visits'], $trend );
        $max    = max( array_merge( $values, [ 1 ] ) );
        $stepX  = $plotWidth / max( count( $trend ) - 1, 1 );

        $xAt = static fn( $i ) => $padLeft + $i * $stepX;
        $yAt = static fn( $v ) => $padTop + $plotHeight - ( $v / $max ) * $plotHeight;

        $points = [];
        foreach ( $values as $i => $v ) {
            $points[] = round( $xAt( $i ), 1 ) . ',' . round( $yAt( $v ), 1 );
        }

        $area   = $points;
        $area[] = round( $xAt( count( $values ) - 1 ), 1 ) . ',' . round( $yAt( 0 ), 1 );
        $area[] = round( $xAt( 0 ), 1 ) . ',' . round( $yAt( 0 ), 1 );

        echo '<svg class="fgr-ms-sparkline" width="' . esc_attr( $width ) . '" height="' . esc_attr( $height ) . '" viewBox="0 0 ' . esc_attr( $width ) . ' ' . esc_attr( $height ) . '">';

        // Y-Achse: 0 und Maximum.
        foreach ( [ 0, $max ] as $gridVal ) {
            $y = round( $yAt( $gridVal ), 1 );
            echo '<line x1="' . esc_attr( $padLeft ) . '" x2="' . esc_attr( $width - $padRight ) . '" y1="' . esc_attr( $y ) . '" y2="' . esc_attr( $y ) . '" stroke="#e2e4e7" />';
            echo '<text x="' . esc_attr( $padLeft - 4 ) . '" y="' . esc_attr( $y + 3 ) . '" text-anchor="end" font-size="9" fill="#646970">' . esc_html( (string) $gridVal ) . '</text>';
        }

        // X-Achse: nur erstes und letztes Datum, mehr passt in der Breite nicht sinnvoll.
        $first = $trend[0]['date'];
        $last  = $trend[ count( $trend ) - 1 ]['date'];
        echo '<text x="' . esc_attr( $padLeft ) . '" y="' . esc_attr( $height - 3 ) . '" text-anchor="start" font-size="9" fill="#646970">' . esc_html( $this->format_short_date( $first ) ) . '</text>';
        echo '<text x="' . esc_attr( $width - $padRight ) . '" y="' . esc_attr( $height - 3 ) . '" text-anchor="end" font-size="9" fill="#646970">' . esc_html( $this->format_short_date( $last ) ) . '</text>';

        echo '<polygon points="' . esc_attr( implode( ' ', $area ) ) . '" fill="rgba(34,113,177,0.12)" stroke="none" />';
        echo '<polyline points="' . esc_attr( implode( ' ', $points ) ) . '" fill="none" stroke="#2271b1" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />';
        echo '</svg>';
    }

    private function format_short_date( string $iso ): string {
        $parts = explode( '-', $iso );
        if ( count( $parts ) === 3 ) {
            return $parts[2] . '.' . $parts[1] . '.';
        }
        return $iso;
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
