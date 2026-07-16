<?php

class AirportsAPI {
    private $baseUrl;
    private $apiKey;
    private $maxRetries;


    public function __construct($apiKey, $version = 'v2') {
        // V2 TODO: default this to 'v2' once migration is underway. DONE
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


    // V2 TODO: ADD these two new metadata methods when migrating: DONE


        public function getAllExtras() {
        return $this->request('GET', '/info/all-extras');
     }
   
     public function getSeatClasses() {
         return $this->request('GET', '/info/seat-classes');
     }


   
        // V2 TODO: '/flights/all' is deprecated in v2. NOTES
        // REMOVE this method body and replace callers with getFlights() NOTES
         
       
        // V2 TODO: '/flights/search' is deprecated in v2. DONE
        // REMOVE the '/flights/search' endpoint below and change it to DONE
        // '/flights' — the rest of this method (building $query) can stay. DONE


   


    // V2 TODO: ADD this new unified method to replace getAllFlights(), DONE
    // searchFlights(), and getFlightsByIds() above once those are removed: DONE


     public function getFlights($match = null, $regexMatch = null, $sort = null, $after = null) {
          $query = [];
          if ($match)       $query['match'] = json_encode($match);
          if ($regexMatch)  $query['regexMatch'] = json_encode($regexMatch);
          if ($sort)        $query['sort'] = $sort;
          if ($after)       $query['after'] = $after;
          return $this->request('GET', '/flights', null, $query);
       }


    public function getFlightById($flightId) {
        // V2 TODO: once getFlightsByIds() is replaced by getFlights(),DONE
        // update this to call: DONE
        $result = $this->getFlights(null, ['flight_id' => $flightId]);
      //  $result = $this->getFlightsByIds([$flightId]); OLD


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


