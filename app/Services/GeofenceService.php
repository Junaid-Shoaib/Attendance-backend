<?php

namespace App\Services;

use App\Models\Office;

class GeofenceService
{
    /**
     * Haversine distance in meters between two lat/lng points.
     */
    public function distanceInMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Find the nearest active office/approved location and determine whether
     * the given coordinates fall within its allowed geofence radius.
     *
     * @return array{within: bool, office: ?Office, distance: ?float}
     */
    public function resolveNearestOffice(float $lat, float $lng): array
    {
        $offices = Office::where('is_active', true)->get();

        $nearest = null;
        $nearestDistance = null;

        foreach ($offices as $office) {
            $distance = $this->distanceInMeters($lat, $lng, $office->latitude, $office->longitude);

            if ($nearestDistance === null || $distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearest = $office;
            }
        }

        if (! $nearest) {
            return ['within' => false, 'office' => null, 'distance' => null];
        }

        return [
            'within' => $nearestDistance <= $nearest->radius_meters,
            'office' => $nearest,
            'distance' => round($nearestDistance, 2),
        ];
    }
}
