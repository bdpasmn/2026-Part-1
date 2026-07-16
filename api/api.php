<?php

/**
 * 1. Constructor default version:
 *      - Change `$version = 'v1'` to `$version = 'v2'` (or pass 'v2'
 *        explicitly wherever `new AirportsAPI(AIRPORTS_API_KEY)` is called
 *        across admin.php / root.php / customer.php / attendant.php).
 *
 * 2. Deprecated endpoints (REMOVE these methods / replace their bodies):
 *      - getAllFlights() currently calls '/flights/all' — REMOVE this
 *        endpoint call. In v2, emulate it by calling the unified
 *        '/flights' endpoint with no match/regexMatch params.
 *      - searchFlights() currently calls '/flights/search' — REMOVE this
 *        endpoint call. In v2, emulate it by calling '/flights' directly
 *        with match / regexMatch / sort query params (already accepted
 *        as args to this method, so only the endpoint path needs to
 *        change from '/flights/search' to '/flights').
 *      - getFlightsByIds() currently calls '/flights/with-ids' — REMOVE
 *        this endpoint call. In v2, emulate it via '/flights' using
 *        regexMatch on flight_id with the '|' operator, e.g.
 *        { "flight_id": "id1|id2|id3" }. NOTE: no other regex operators
 *        are supported for flight_id matching in v2.
 *
 * 3. New unified endpoint to ADD:
 *      - getFlights($match = null, $regexMatch = null, $sort = null,
 *        $after = null) hitting GET '/flights' — this single method can
 *        replace getAllFlights(), searchFlights(), and getFlightsByIds()
 *        above once they're removed.
 *
 * 4. New metadata endpoints to ADD:
 *      - getAllExtras() -> GET '/info/all-extras' (returns array of
 *        extras names available for purchase, e.g. "wifi", "blanket").
 *      - getSeatClasses() -> GET '/info/seat-classes' (returns array of
 *        seat class names, e.g. "economy", "first class").
 *      (getAirlines(), getAirports(), getNoFlyList() are unchanged in v2.)
 *
 * 5. Flight object shape changes (affects callers in admin.php, root.php,
 *    customer.php, attendant.php, flight_lookup.php — NOT this file, but
 *    noting here since it's driven by the API version):
 *      - `seatPrice` key REMOVED from flight objects. REMOVE any code
 *        reading $flight['seatPrice'] directly.
 *      - `baggage` key ADDED — object keyed by bag type ("checked",
 *        "carry") each with `max` and `prices` (array; sum first N
 *        elements for the cost of bringing N bags). Bag-fee calculation
 *        logic (currently hardcoded: carry-on 2nd = $30, checked 2nd =
 *        $50, each additional checked beyond 2 = $100) will need to be
 *        REMOVED/REPLACED with logic that reads $flight['baggage'].
 *      - `seats` key ADDED — object keyed by seat class ("economy",
 *        "exit row", "economy plus", "first class") each with `total`,
 *        `priceDollars`, `priceFfms`. Ticket price logic will need to
 *        read seat price from $flight['seats'][$seatClass]['priceDollars']
 *        instead of a flat seatPrice.
 *      - `extras` key ADDED — object keyed by extra name ("blanket",
 *        "headphones", "wifi", "extra food") each with `priceDollars`
 *        and `priceFfms`. Currently unused by this app; would need new
 *        UI/DB support to let customers select extras.
 *      - `ffms` key ADDED — frequent flier miles awarded for booking.
 *        Currently unused; would need a new column if we start tracking
 *        FFMs per user/ticket.
 * =====================================================================
 */

class AirportsAPI {
    private $baseUrl;
    private $apiKey;
    private $maxRetries;

    public function __construct($apiKey, $version = 'v1') {
        // V2 TODO: default this to 'v2' once migration is underway.
        $this->baseUrl = "https://airports.api.hscc.bdpa.org/$version";
        $this->apiKey = $apiKey;
        $this->maxRetries = 3;
    }

private function request($method, $endpoint, $data = null, $query = []) {
    $url = $this->baseUrl . $endpoint;

    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer {$this->apiKey}"
    ];

    $retry = $this->maxRetries;

    while ($retry > 0) {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($curlError) {
            error_log("Airports API - cURL Error: " . $curlError);
            $this->redirectToError(503);
            return false;
        }

        if ($httpCode >= 429 && $httpCode < 600) {
            $retry--;

            if ($retry > 0) {
                error_log("Airports API - Retrying request. Attempts remaining: " . $retry);
                sleep(1);
            } else {
                error_log("Airports API - All retry attempts exhausted.");
                $this->redirectToError(503);
                return false;
            }
        } else {

            if ($httpCode >= 200 && $httpCode < 300) {
                return json_decode($response, true);
            }

            if ($httpCode >= 400 && $httpCode < 500) {

                error_log("Airports API Debug - URL: $url");
                error_log("Airports API Debug - HTTP Code: $httpCode");
                error_log("Airports API Debug - Response: " . substr($response, 0, 200));

                if (in_array($httpCode, [401, 403, 404])) {
                    $this->redirectToError($httpCode);
                    return false;
                }

                $responseData = json_decode($response, true);

                if ($responseData === null) {
                    $responseData = [
                        'error' => true,
                        'message' => 'Client error',
                        'code' => $httpCode
                    ];
                }

                $responseData['_httpCode'] = $httpCode;
                return $responseData;
            }

            error_log("Airports API Unexpected Error - Code: $httpCode");
            $this->redirectToError($httpCode);
            return false;
        }
    }

    return false;
}
    private function redirectToError($httpCode) {
        error_log("Airports API Debug - redirectToError called with code: " . $httpCode);

        $errorUrl = '/bdpa_airports/2026-Part-1/api/error.php?code=' . $httpCode;

        if (headers_sent($file, $line)) {
            error_log("Airports API Debug - Headers already sent at $file:$line");

            echo "<script>window.location.href='{$errorUrl}';</script>";
            exit();
        }

        error_log("Airports API Debug - Redirecting to: " . $errorUrl);

        header('Location: ' . $errorUrl);
        exit();
    }
    public function getAirlines() {
        return $this->request('GET', '/info/airlines');
    }

    public function getAirports() {
        return $this->request('GET', '/info/airports');
    }

    public function getNoFlyList() {
        return $this->request('GET', '/info/no-fly-list');
    }

    // V2 TODO: ADD these two new metadata methods when migrating:
    //
    // public function getAllExtras() {
    //     return $this->request('GET', '/info/all-extras');
    // }
    //
    // public function getSeatClasses() {
    //     return $this->request('GET', '/info/seat-classes');
    // }

    public function getAllFlights($after = null) {
        // V2 TODO: '/flights/all' is deprecated in v2.
        // REMOVE this method body and replace callers with getFlights()
        // (see unified endpoint below) called with no match/regexMatch.
        $query = [];

        if ($after) {
            $query['after'] = $after;
        }

        return $this->request('GET', '/flights/all', null, $query);
    }

    public function searchFlights($match = null, $regexMatch = null, $sort = null, $after = null) {
        // V2 TODO: '/flights/search' is deprecated in v2.
        // REMOVE the '/flights/search' endpoint below and change it to
        // '/flights' — the rest of this method (building $query) can stay.
        $query = [];

        if ($match) {
            $query['match'] = json_encode($match);
        }

        if ($regexMatch) {
            $query['regexMatch'] = json_encode($regexMatch);
        }

        if ($sort) {
            $query['sort'] = $sort;
        }

        if ($after) {
            $query['after'] = $after;
        }

        return $this->request('GET', '/flights/search', null, $query);
    }

    public function getFlightsByIds(array $ids) {
        // V2 TODO: '/flights/with-ids' is deprecated in v2.
        // REMOVE this method body. Replace callers with getFlights()
        // passing regexMatch = ['flight_id' => implode('|', $ids)].
        return $this->request(
            'GET',
            '/flights/with-ids',
            null,
            [
                'ids' => json_encode($ids)
            ]
        );
    }

    // V2 TODO: ADD this new unified method to replace getAllFlights(),
    // searchFlights(), and getFlightsByIds() above once those are removed:
    //
    // public function getFlights($match = null, $regexMatch = null, $sort = null, $after = null) {
    //     $query = [];
    //     if ($match)       $query['match'] = json_encode($match);
    //     if ($regexMatch)  $query['regexMatch'] = json_encode($regexMatch);
    //     if ($sort)        $query['sort'] = $sort;
    //     if ($after)       $query['after'] = $after;
    //     return $this->request('GET', '/flights', null, $query);
    // }

    public function getFlightById($flightId) {
        // V2 TODO: once getFlightsByIds() is replaced by getFlights(),
        // update this to call:
        //   $result = $this->getFlights(null, ['flight_id' => $flightId]);
        $result = $this->getFlightsByIds([$flightId]);

        if (
            isset($result['flights']) &&
            count($result['flights']) > 0
        ) {
            return $result['flights'][0];
        }

        return null;
    }
}
?>