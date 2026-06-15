<?php
    session_start();

    $userId = $_SESSION['user_id'] ?? null;

    require_once "../../database/db.php";
    require_once "../../api/api.php";
    require_once "../../api/key.php";

    $api = new AirportsAPI(AIRPORTS_API_KEY);

    $flightId = $_POST['flight_id'];
    $seat = $_POST['seat'];

    $first = strtolower(trim($_POST['first_name'] ?? ''));
    $middle = strtolower(trim($_POST['middle_name'] ?? ''));
    $last = strtolower(trim($_POST['last_name'] ?? ''));

    $sex = strtolower(trim($_POST['sex'] ?? ''));
    $dob = trim($_POST['dob'] ?? '');

    function normalizeName($first, $middle, $last) {
        return strtolower(trim("$first $middle $last"));
    }

    function normalizeDob($dob) {
        $dob = trim($dob);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dob, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        return $dob;
    }

    $inputName = normalizeName($first, $middle, $last);
    $inputDob = normalizeDob($dob);

    $noFlyList = $api->getNoFlyList();

    if (!isset($noFlyList['noFlyList']) || !is_array($noFlyList['noFlyList'])) {
        $noFlyList['noFlyList'] = [];
    }

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

    $stmt = $pdo->prepare("SELECT * FROM \"Flights\" WHERE flight_id = ?");

    $stmt->execute([$flightId]);
    $flight = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($flight) {
        $takenSeats = json_decode($flight['taken_seats'] ?? '[]', true);

        if (!is_array($takenSeats)) {
            $takenSeats = [];
        }

        if (in_array($seat, $takenSeats)) {
            header("Location: bookingFailed.php?" . http_build_query(['message' => 'Seat already taken. Please choose another seat.']));
            exit;
        }

        $takenSeats[] = $seat;
        sort($takenSeats);

        $update = $pdo->prepare("UPDATE \"Flights\" SET taken_seats = ? WHERE flight_id = ?");

        $update->execute([json_encode($takenSeats), $flightId]);
    } else {
        $insert = $pdo->prepare("INSERT INTO \"Flights\" (flight_id, taken_seats) VALUES (?, ?)");

        $insert->execute([
            $flightId,
            json_encode([$seat])
        ]);
    }

    $flightInfo = $api->getFlightById($flightId);
    $destination = $flightInfo['departingTo'] ?? '';

    $ticketPrice = floatval($_POST['price'] ?? 0);
    $confirmationCode = strtoupper(substr(md5(uniqid()), 0, 8));

    $stmt = $pdo->prepare("INSERT INTO \"Tickets\" (user_id, flight_id, confirmation_code, seat, destination, name_first, name_middle, name_last, sex, date_birth, phone_number, email, bags_carried, bags_checked, price) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
    
        $_POST['phone'],
        $_POST['email'],
    
        intval($_POST['bags_carried']),
        intval($_POST['bags_checked']),
    
        $ticketPrice,
    ]);

    if ($userId && !empty($_POST['save_card']) && !empty($_POST['card_name'])) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM "Saved Cards" WHERE user_id = ? AND card_number = ?');

        $check->execute([
            $userId,
            $_POST['card_number']
        ]);

        if (!$check->fetchColumn()) {
            $stmt = $pdo->prepare('INSERT INTO "Saved Cards" (user_id, card_name, cardholder_name, card_number, expiration_date, cvc, billing_address, zip_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

            $stmt->execute([
                $userId,
                $_POST['card_name'],
                $_POST['cardholder_name'],
                $_POST['card_number'],
                $_POST['expiration_date'],
                $_POST['cvc'],
                $_POST['billing_address'],
                $_POST['zip_code']
            ]);
        }
    }

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
        $stmt->execute([
            json_encode($flights),
            $userId
        ]);
    }

    header("Location: ./confirmation.php?" . http_build_query(['confirmation' => $confirmationCode]));
    exit;
?>