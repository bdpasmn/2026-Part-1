<?php

class AirportsAPI {
    private $baseUrl;
    private $apiKey;
    private $maxRetries;

    public function __construct($apiKey, $version = 'v1') {
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

    public function getAllFlights($after = null) {
        $query = [];

        if ($after) {
            $query['after'] = $after;
        }

        return $this->request('GET', '/flights/all', null, $query);
    }

    public function searchFlights($match = null, $regexMatch = null, $sort = null, $after = null) {
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
        return $this->request(
            'GET',
            '/flights/with-ids',
            null,
            [
                'ids' => json_encode($ids)
            ]
        );
    }

    public function getFlightById($flightId) {
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