<?php
    session_start();
    $userId = $_SESSION['user_id'] ?? null;

    require_once "../../database/db.php";
    require_once "../../api/api.php";
    require_once "../../api/key.php";

    $api = new AirportsAPI(AIRPORTS_API_KEY);

    // Flight information
    $flightId = $_POST['flight_id'];
    $seat = $_POST['seat'];

    // Clean and format payment information
    $cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $expirationDate = trim($_POST['expiration_date'] ?? '');
    $cvc = preg_replace('/\D/', '', $_POST['cvc'] ?? '');
    $zipCode = trim($_POST['zip_code'] ?? '');

    // Format the phone number
    $phoneDigits = preg_replace('/\D/', '', $_POST['phone'] ?? '');

    if (strlen($phoneDigits) == 11) {
        $phone = '+' . $phoneDigits[0] . ' (' .
            substr($phoneDigits, 1, 3) . ') ' .
            substr($phoneDigits, 4, 3) . '-' .
            substr($phoneDigits, 7, 4);
    } elseif (strlen($phoneDigits) == 10) {
        $phone = '(' .
            substr($phoneDigits, 0, 3) . ') ' .
            substr($phoneDigits, 3, 3) . '-' .
            substr($phoneDigits, 6, 4);
    } else {
        $phone = $_POST['phone'] ?? '';
    }

    // Validate the email address
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: bookingFailed.php?" . http_build_query(['message' => 'Please enter a valid email address.']));
        exit;
    }

    // Validate the credit card number
    if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
        header("Location: bookingFailed.php?" . http_build_query(['message' => 'Please enter a valid card number.']));
        exit;
    }

    // Validate the expiration date
    if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expirationDate)) {
        header("Location: bookingFailed.php?" . http_build_query(['message' => 'Please enter a valid expiration date (MM/YY).']));
        exit;
    }

    // Validate the CVC
    if (!preg_match('/^\d{3,4}$/', $cvc)) {
        header("Location: bookingFailed.php?" . http_build_query(['message' => 'Please enter a valid CVC.']));
        exit;
    }

    // Validate the ZIP code
    if (!preg_match('/^\d{5}(-\d{4})?$/', $zipCode)) {
        header("Location: bookingFailed.php?" . http_build_query(['message' => 'Please enter a valid ZIP code.']));
        exit;
    }

    // Passenger information
    $first = strtolower(trim($_POST['first_name'] ?? ''));
    $middle = strtolower(trim($_POST['middle_name'] ?? ''));
    $last = strtolower(trim($_POST['last_name'] ?? ''));
    $sex = strtolower(trim($_POST['sex'] ?? ''));
    $dob = trim($_POST['dob'] ?? '');

    // Normalize passenger names for comparison
    function normalizeName($first, $middle, $last) {
        return strtolower(trim("$first $middle $last"));
    }

    // Normalize date of birth format
    function normalizeDob($dob) {
        $dob = trim($dob);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dob, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        return $dob;
    }

    $inputName = normalizeName($first, $middle, $last);
    $inputDob = normalizeDob($dob);

    // Retrieve the no-fly list
    $noFlyList = $api->getNoFlyList();
    if (!isset($noFlyList['noFlyList']) || !is_array($noFlyList['noFlyList'])) {
        $noFlyList['noFlyList'] = [];
    }

    // Compare the passenger against every person on the no-fly list
    foreach ($noFlyList['noFlyList'] as $person) {
        $nfFirst  = strtolower(trim($person['name']['first'] ?? ''));
        $nfMiddle = strtolower(trim($person['name']['middle'] ?? ''));
        $nfLast   = strtolower(trim($person['name']['last'] ?? ''));

        $nfName = normalizeName($nfFirst, $nfMiddle, $nfLast);
        $nfSex = strtolower(trim($person['sex'] ?? ''));
        $nfDobArray = $person['birthdate'] ?? [];

        $nfDob = '';

        if (is_array($nfDobArray)) {
            $nfDob = $nfDobArray['year'] . '-' . str_pad($nfDobArray['month'], 2, "0", STR_PAD_LEFT) . '-' . str_pad($nfDobArray['day'], 2, "0", STR_PAD_LEFT);
        }

        if ($inputName == $nfName && $sex == $nfSex && $inputDob == $nfDob) {
            header("Location: bookingFailed.php?" . http_build_query(['message' => 'Passenger is on the No Fly List and cannot book this flight.']));
            exit;
        }
    }

    // Retrieve the flight record
    $stmt = $pdo->prepare("SELECT * FROM \"Flights\" WHERE flight_id = ?");
    $stmt->execute([$flightId]);
    $flight = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($flight) {
        $takenSeats = json_decode($flight['taken_seats'] ?? '[]', true);
        if (!is_array($takenSeats)) {
            $takenSeats = [];
        }

        // Prevent duplicate seat bookings
        if (in_array($seat, $takenSeats)) {
            header("Location: bookingFailed.php?" . http_build_query(['message' => 'Seat already taken. Please choose another seat.']));
            exit;
        }

        // Add the selected seat to the flight
        $takenSeats[] = $seat;
        sort($takenSeats);

        $update = $pdo->prepare("UPDATE \"Flights\" SET taken_seats = ? WHERE flight_id = ?");
        $update->execute([json_encode($takenSeats), $flightId]);
    } else {
        // Create a new flight record if one does not exist
        $insert = $pdo->prepare("INSERT INTO \"Flights\" (flight_id, taken_seats) VALUES (?, ?)");
        $insert->execute([$flightId, json_encode([$seat])]);
    }

    // Retrieve additional flight information
    $flightInfo = $api->getFlightById($flightId);
    $destination = $flightInfo['departingTo'] ?? '';

    // Generate the confirmation code
    $ticketPrice = floatval($_POST['price'] ?? 0);
    $confirmationCode = strtoupper(substr(md5(uniqid()), 0, 8));

    // Create the ticket
    $stmt = $pdo->prepare("INSERT INTO \"Tickets\" (user_id, flight_id, confirmation_code, seat, destination, name_first, name_middle, name_last, sex, date_birth, phone_number, email, bags_carried, bags_checked, price, status) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        $flightId,
        $confirmationCode,
        $seat,
        $destination,
        $_POST['first_name'],
        $_POST['middle_name'],
        $_POST['last_name'],
        $_POST['sex'],
        $_POST['dob'],
        $phone,
        $email,
        intval($_POST['bags_carried']),
        intval($_POST['bags_checked']),
        $ticketPrice,
        "active"
    ]);

    // Save the payment method if requested
    if ($userId && !empty($_POST['save_card'])) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM "Saved Cards" WHERE user_id = ? AND card_number = ?');
        $cardholderName = trim($_POST['cardholder_name'] ?? '');
        $cardName = trim($_POST['card_name'] ?? '');

        if ($cardName == '') {
            $cardName = $cardholderName . "'s card";
        }

        $check->execute([$userId, $_POST['card_number']]);
        if (!$check->fetchColumn()) {
            $stmt = $pdo->prepare('INSERT INTO "Saved Cards" (user_id, card_name, cardholder_name, card_number, expiration_date, cvc, billing_address, zip_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $userId,
                $cardName,
                $cardholderName,
                $_POST['card_number'],
                $_POST['expiration_date'],
                $_POST['cvc'],
                $_POST['billing_address'],
                $_POST['zip_code']
            ]);
        }
    }

    // Add the ticket to the user's flight history
    if ($userId !== null) {
        $stmt = $pdo->prepare("SELECT flights FROM \"Users\" WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $flights = json_decode($user['flights'] ?? '[]', true);

        if (!is_array($flights)) {
            $flights = [];
        }

        $flights[] = ['flight_id' => $flightId, 'confirmation_code' => $confirmationCode, 'last_name' => $_POST['last_name']];
        $stmt = $pdo->prepare("UPDATE \"Users\" SET flights = ? WHERE user_id = ?");
        $stmt->execute([json_encode($flights), $userId]);
    }

    header("Location: ./confirmation.php?" . http_build_query(['confirmation' => $confirmationCode]));
    exit;
?>