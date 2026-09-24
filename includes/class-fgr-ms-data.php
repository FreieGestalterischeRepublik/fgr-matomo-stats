<?php
defined( 'ABSPATH' ) || exit;

/**
 * Holt die vorab von der zentralen FGR-Update-API generierten Matomo-Zahlen
 * für die eigene Domain. Kein Matomo-Token liegt je auf dieser Seite –
 * es wird nur eine fertige JSON-Datei abgerufen, benannt nach der Domain.
 */
class FGR_MS_Data {

    const TRANSIENT = 'fgr_ms_data';

    public static function get(): ?array {
        $cached = get_transient( self::TRANSIENT );
        if ( $cached !== false ) {
            return $cached === '' ? null : $cached;
        }
        return self::fetch_fresh();
    }

    /**
     * Fragt die API sofort ab (ungecacht) und speichert das Ergebnis für 24h.
     * Wird bei der Aktivierung genutzt (Domain-Check) sowie als Fallback,
     * falls der Cache abgelaufen ist.
     */
    public static function fetch_fresh(): ?array {
        $domain = self::current_domain();
        $url    = FGR_MS_API_BASE . '/matomo/' . rawurlencode( $domain ) . '.json';

        $response = wp_remote_get( $url, [ 'timeout' => 10 ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            set_transient( self::TRANSIENT, '', HOUR_IN_SECONDS );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) {
            set_transient( self::TRANSIENT, '', HOUR_IN_SECONDS );
            return null;
        }

        set_transient( self::TRANSIENT, $data, DAY_IN_SECONDS );
        return $data;
    }

    public static function current_domain(): string {
        $host = (string) parse_url( home_url(), PHP_URL_HOST );
        $host = strtolower( $host );
        return preg_replace( '/^www\./', '', $host );
    }
}
