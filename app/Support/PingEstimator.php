<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Rough visitor-to-server ping estimate from geography alone.
 *
 * Browsers can't send ICMP/UDP, so a true defrag ping can't be measured from
 * the web. Instead we take the visitor's location (Cloudflare injects
 * cf-iplatitude / cf-iplongitude request headers via the "Add visitor location
 * headers" managed transform) and the server's stored coordinates, and convert
 * the great-circle distance into an approximate round-trip time.
 *
 * This is the backbone portion only: it does NOT include the visitor's last
 * mile (home wifi / ISP / mobile), so real in-game ping reads a bit higher.
 * Always present it as an estimate ("~"), never an exact figure.
 */
class PingEstimator
{
    /**
     * Light in fibre travels ~200 km/ms, so a round trip is ~100 km/ms of
     * straight-line distance. Real paths aren't straight and add router hops,
     * so we inflate by this factor for a realistic backbone RTT.
     */
    private const ROUTE_FACTOR = 1.6;

    /** Earth's mean radius in kilometres. */
    private const EARTH_KM = 6371.0;

    /**
     * Visitor coordinates from Cloudflare's location headers, or null when the
     * request didn't come through Cloudflare (local dev, direct origin hit).
     *
     * @return array{0: float, 1: float}|null  [lat, lon]
     */
    public static function visitorLatLon(Request $request): ?array
    {
        $lat = $request->header('cf-iplatitude');
        $lon = $request->header('cf-iplongitude');

        if ($lat === null || $lon === null || ! is_numeric($lat) || ! is_numeric($lon)) {
            return null;
        }

        return [(float) $lat, (float) $lon];
    }

    /**
     * Estimated round-trip time in whole milliseconds between two points, or
     * null if either coordinate is missing.
     */
    public static function estimate(?float $aLat, ?float $aLon, ?float $bLat, ?float $bLon): ?int
    {
        if ($aLat === null || $aLon === null || $bLat === null || $bLon === null) {
            return null;
        }

        $km = self::haversineKm($aLat, $aLon, $bLat, $bLon);

        return max(1, (int) round($km / 100 * self::ROUTE_FACTOR));
    }

    /** Great-circle distance in kilometres. */
    private static function haversineKm(float $aLat, float $aLon, float $bLat, float $bLon): float
    {
        $dLat = deg2rad($bLat - $aLat);
        $dLon = deg2rad($bLon - $aLon);

        $h = sin($dLat / 2) ** 2
            + cos(deg2rad($aLat)) * cos(deg2rad($bLat)) * sin($dLon / 2) ** 2;

        return self::EARTH_KM * 2 * asin(min(1.0, sqrt($h)));
    }
}
