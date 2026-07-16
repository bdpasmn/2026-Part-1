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

    // Fetch flight info early — we need it both for pricing/FFM math below
    // and for the destination used later in the ticket record.
    $flightInfo = $api->getFlightById($flightId);
    $destination = $flightInfo['departingTo'] ?? '';

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

    /**
     * Replicates the seat-layout algorithm from seats.php so the server can
     * independently derive which class a given seat ID (e.g. "12A") belongs
     * to, instead of trusting price/class info submitted by the client.
     *
     * Must stay in lockstep with the JS generation logic in seats.php:
     * - iterate $flight['seats'] in REVERSED key order
     * - 9 columns per row, letters A-I
     * - consecutive seatIndex assigned per class in order, info.total seats each
     */
    function resolveSeatClass(array $seatsByClass, string $seatId): ?string {
        $seatInfo = array_reverse($seatsByClass);

        $cols = 9;
        $letters = ["A","B","C","D","E","F","G","H","I"];
        $seatIndex = 0;

        foreach ($seatInfo as $type => $info) {
            $total = intval($info['total'] ?? 0);

            for ($i = 0; $i < $total; $i++) {
                $row = intdiv($seatIndex, $cols) + 1;
                $col = $seatIndex % $cols;
                $candidateId = $row . $letters[$col];

                if ($candidateId === $seatId) {
                    return $type;
                }

                $seatIndex++;
            }
        }

        return null; // seat ID doesn't correspond to any generated seat
    }

    // Resolve the passenger's seat to its real class, then price from that —
    // never trust a class/price submitted by the client.
    $seatClass = resolveSeatClass($flightInfo['seats'] ?? [], $seat);

    if ($seatClass === null || !isset($flightInfo['seats'][$seatClass])) {
        header("Location: bookingFailed.php?" . http_build_query(['message' => 'Invalid seat selection.']));
        exit;
    }

    $seatPriceDollars = floatval($flightInfo['seats'][$seatClass]['priceDollars'] ?? 0);
    $flightFfmCost     = intval($flightInfo['seats'][$seatClass]['priceFfms'] ?? 0);
    $flightFfmEarn     = intval($flightInfo['ffms'] ?? 0);


    // --- FFM payment method (ticket + extras) ---
    // Never trust the client-submitted price/ffm_charge/ffm_earned — recompute
    // everything from $flightInfo and the posted extras list.
    $ticketPaymentMethod = ($_POST['ticket_payment_method'] ?? 'money') === 'ffm' ? 'ffm' : 'money';
    $extras = json_decode($_POST['extras'] ?? '[]', true);
    if (!is_array($extras)) {
        $extras = [];
    }
    $extrasJson = json_encode($extras);

    // Guests can't use FFMs at all — guard against a spoofed request.
    if (!$userId && $ticketPaymentMethod === 'ffm') {
        $ticketPaymentMethod = 'money';
    }

    $ffmCharge = 0;
    $ffmEarned = 0;
    $moneyDue = 0.0;

    if ($ticketPaymentMethod === 'ffm') {
        $ffmCharge += $flightFfmCost;
    } else {
        $ffmEarned += $flightFfmEarn;
        $moneyDue += $seatPriceDollars;
    }

    // Only card payment is required for baggage — extras can each be paid with
    // card or FFMs depending on the per-item choice made in the payment modal.
    foreach ($extras as $extra) {
        $extraPaymentMethod = ($extra['payment_method'] ?? 'money') === 'ffm' ? 'ffm' : 'money';

        if (!$userId) {
            $extraPaymentMethod = 'money';
        }

        if ($extraPaymentMethod === 'ffm') {
            $ffmCharge += intval($extra['ffm'] ?? 0);
        } else {
            $moneyDue += floatval($extra['price'] ?? 0);
        }
    }

    /**
     * Baggage is always paid in dollars (never FFMs). Prices are tiered per
     * bag: prices[0] is the cost of the 1st bag, prices[1] the 2nd, etc., so
     * the cost of carrying N bags is the sum of prices[0..N-1]. Bag counts
     * are clamped to the allowed max — never trust the client's count either.
     */
    function priceBags(array $bagInfo, int $requestedCount): array {
        $max = intval($bagInfo['max'] ?? 0);
        $prices = $bagInfo['prices'] ?? [];

        $count = max(0, min($requestedCount, $max));
        $total = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $total += floatval($prices[$i] ?? 0);
        }

        return ['count' => $count, 'total' => $total];
    }

    $bagsCarriedRequested = intval($_POST['bags_carried'] ?? 0);
    $bagsCheckedRequested = intval($_POST['bags_checked'] ?? 0);

    $carryResult = priceBags($flightInfo['baggage']['carry'] ?? [], $bagsCarriedRequested);
    $checkedResult = priceBags($flightInfo['baggage']['checked'] ?? [], $bagsCheckedRequested);

    $bagsCarried = $carryResult['count'];
    $bagsChecked = $checkedResult['count'];
    $moneyDue += $carryResult['total'] + $checkedResult['total'];

    // Card is required whenever anything is actually owed in dollars.
    $cardRequired = $moneyDue > 0;

    // Validate the credit card number (only required if money is actually due)
    if ($cardRequired && (strlen($cardNumber) < 13 || strlen($cardNumber) > 19)) {
        header("Location: bookingFailed.php?" . http_build_query(['message' => 'Please enter a valid card number.']));
        exit;
    }

    // Validate the expiration date
    if ($cardRequired && !preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expirationDate)) {
        header("Location: bookingFailed.php?" . http_build_query(['message' => 'Please enter a valid expiration date (MM/YY).']));
        exit;
    }

    // Validate the CVC
    if ($cardRequired && !preg_match('/^\d{3,4}$/', $cvc)) {
        header("Location: bookingFailed.php?" . http_build_query(['message' => 'Please enter a valid CVC.']));
        exit;
    }

    // If paying with FFMs, make sure the user actually has enough *before*
    // we touch the flight's seat map or create a ticket.
    if ($userId && $ffmCharge > 0) {
        $stmt = $pdo->prepare('SELECT ffm FROM "Users" WHERE user_id = ?');
        $stmt->execute([$userId]);
        $currentFfms = (int) $stmt->fetchColumn();

        if ($ffmCharge > $currentFfms) {
            header("Location: bookingFailed.php?" . http_build_query(['message' => 'You do not have enough Frequent Flier Miles for this purchase.']));
            exit;
        }
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

    // Generate the confirmation code
    // Ticket price stored is the seat's real dollar price when paid with
    // money, or 0 when paid with FFMs — never the client-posted price.
    $ticketPrice = ($ticketPaymentMethod === 'money') ? $seatPriceDollars : 0.0;
    $confirmationCode = strtoupper(substr(md5(uniqid()), 0, 8));

    // Create the ticket
    $stmt = $pdo->prepare("
    INSERT INTO \"Tickets\"
    (
        user_id,
        flight_id,
        confirmation_code,
        seat,
        destination,
        name_first,
        name_middle,
        name_last,
        sex,
        date_birth,
        phone_number,
        email,
        bags_carried,
        bags_checked,
        price,
        status,
        extras
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");    $stmt->execute([
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
        $bagsCarried,
        $bagsChecked,
        $ticketPrice,
        "active",
        $extrasJson
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

    // Apply FFM balance changes: subtract anything paid for with FFMs, add
    // anything earned from paying the ticket with money. Done as a single
    // conditional UPDATE so a concurrent purchase can't push the balance
    // negative between our earlier balance check and this write.
    if ($userId && ($ffmCharge > 0 || $ffmEarned > 0)) {
        $stmt = $pdo->prepare('
            UPDATE "Users"
            SET ffm = COALESCE(ffm, 0) - ? + ?
            WHERE user_id = ? AND COALESCE(ffm, 0) >= ?
        ');
        $stmt->execute([$ffmCharge, $ffmEarned, $userId, $ffmCharge]);

        if ($ffmCharge > 0 && $stmt->rowCount() === 0) {
            // Balance changed since our earlier check (race condition) — the
            // ticket/seat are already committed at this point, so just log it
            // rather than leaving the user with a ticket and no confirmation.
            error_log("FFM balance race condition for user_id={$userId}, confirmation={$confirmationCode}, ffmCharge={$ffmCharge}");
        }
    }

    header("Location: ./confirmation.php?" . http_build_query(['confirmation' => $confirmationCode]));
    exit;
?>