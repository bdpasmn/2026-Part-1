<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../api/key.php';
require_once '../../../api/api.php';
require_once '../../../database/db.php';

$sessionUserId = $_SESSION['user_id'] ?? null;

if (!$sessionUserId) {
    header('Location: ../../../index.php');
    exit;
}

// Load the logged-in user's own record to confirm role and display name
$selfStmt = $pdo->prepare('SELECT * FROM "Users" WHERE user_id = ? LIMIT 1');
$selfStmt->execute([$sessionUserId]);
$selfUser = $selfStmt->fetch(PDO::FETCH_ASSOC);

if (!$selfUser) {
    header('Location: ../../../index.php');
    exit;
}

// Only the Root role can access this dashboard
if (($selfUser['role'] ?? '') !== 'Root') {
    header('Location: ../../../index.php');
    exit;
}

$selfName = trim(($selfUser['first_name'] ?? '') . ' ' . ($selfUser['last_name'] ?? ''));
if ($selfName === '') $selfName = 'Root';

$api = new AirportsAPI(AIRPORTS_API_KEY);

// Build a lookup of airport short name -> full airport record
$airportResults = $api->getAirports();
$airports = $airportResults['airports'] ?? [];
$airportLookup = [];
foreach ($airports as $airport) {
    $airportLookup[strtolower($airport['shortName'])] = $airport;
}

// Load all live flights from the API
$allFlightsData = $api->getAllFlights();
$allFlights     = $allFlightsData['flights'] ?? [];

// Normalize the no-fly list into lowercase first/last name pairs for matching
$noFlyData = $api->getNoFlyList();
$noFlyList = [];
if ($noFlyData && isset($noFlyData['noFlyList'])) {
    foreach ($noFlyData['noFlyList'] as $nfl) {
        $noFlyList[] = [
            'first' => strtolower(trim($nfl['firstName'] ?? $nfl['first_name'] ?? '')),
            'last'  => strtolower(trim($nfl['lastName']  ?? $nfl['last_name']  ?? '')),
        ];
    }
}

// Index all flights by flight ID for fast lookup
$flightMap = [];
foreach ($allFlights as $f) {
    $fid = $f['flight_id']
        ?? $f['flightId']
        ?? $f['id']
        ?? '';
    if ($fid) $flightMap[$fid] = $f;
}

// Build a map of flight ID -> list of seats already taken according to the API
$takenSeatsByFlight = [];
foreach ($allFlights as $f) {
    $fid = $f['flight_id']
        ?? $f['flightId']
        ?? $f['id']
        ?? '';
    if (!$fid) continue;
    $seats = [];
    foreach ($f['takenSeats'] ?? $f['taken_seats'] ?? [] as $ts) {
        $s = is_array($ts) ? ($ts['seat'] ?? '') : (string)$ts;
        if ($s) $seats[] = strtoupper($s);
    }
    $takenSeatsByFlight[$fid] = $seats;
}

// Load all tickets and users from the database
$ticketsStmt = $pdo->query('SELECT * FROM "Tickets"');
$allTickets  = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);

$usersStmt = $pdo->query('SELECT * FROM "Users"');
$allUsers  = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Split users into admin/root accounts vs customer accounts
$admins    = array_values(array_filter($allUsers, fn($u) => in_array(strtolower($u['role'] ?? ''), ['admin', 'root'])));
$customers = array_values(array_filter($allUsers, fn($u) => strtolower($u['role'] ?? '') === 'customer'));

$daySec   = 86400;
$weekSec  = 7  * $daySec;
$monthSec = 30 * $daySec;
$yearSec  = 365 * $daySec;

$ticketStats = ['day' => 0, 'week' => 0, 'month' => 0, 'year' => 0, 'all' => 0];
$profitStats = ['day' => 0.0, 'week' => 0.0, 'month' => 0.0, 'year' => 0.0, 'all' => 0.0];

$utc = new DateTimeZone('UTC');
$nowUtc = new DateTime('now', $utc);
$now = $nowUtc->getTimestamp();

// Parse a ticket's price column into a safe, bounded float
function parseTicketPrice($rawPrice): float {
    if ($rawPrice === null) return 0.0;
    if (is_int($rawPrice) || is_float($rawPrice)) {
        $p = (float)$rawPrice;
        return ($p >= 0 && $p <= 100000) ? $p : 0.0;
    }
    $s = trim((string)$rawPrice);
    if ($s === '') return 0.0;
    $s = preg_replace('/[^0-9.\-]/', '', $s);
    if ($s === '' || !is_numeric($s)) return 0.0;
    $p = (float)$s;
    if ($p < 0 || $p > 100000) return 0.0;
    return $p;
}

// Tally ticket counts and gross profit for each rolling time period (day/week/month/year/all)
foreach ($allTickets as $t) {
    if (strtolower($t['status'] ?? '') === 'cancelled') continue;

    $price = parseTicketPrice($t['price'] ?? '0');

    $createdRaw = $t['created_at'] ?? '';
    try {
        $createdDt = $createdRaw !== ''
            ? new DateTime($createdRaw, $utc)
            : clone $nowUtc;
    } catch (Exception $e) {
        $createdDt = clone $nowUtc;
    }
    $createdDt->setTimezone($utc);

    $age = $now - $createdDt->getTimestamp();

    $ticketStats['all']++;
    $profitStats['all'] += $price;

    if ($age >= 0 && $age <= $daySec)   { $ticketStats['day']++;   $profitStats['day']   += $price; }
    if ($age >= 0 && $age <= $weekSec)  { $ticketStats['week']++;  $profitStats['week']  += $price; }
    if ($age >= 0 && $age <= $monthSec) { $ticketStats['month']++; $profitStats['month'] += $price; }
    if ($age >= 0 && $age <= $yearSec)  { $ticketStats['year']++;  $profitStats['year']  += $price; }
}

// Check that a seat string matches row 1-10, column A-I
function validSeat(string $seat): bool {
    return (bool)preg_match('/^([1-9]|10)[A-Ia-i]$/', $seat);
}

// Check whether a first/last name combo appears on the no-fly list
function isOnNoFlyList(string $fn, string $ln, array $noFlyList): bool {
    $fn = strtolower(trim($fn));
    $ln = strtolower(trim($ln));
    foreach ($noFlyList as $nfl) {
        if ($nfl['first'] === $fn && $nfl['last'] === $ln) return true;
    }
    return false;
}

// Check our own Tickets table for an active ticket already using this seat
function isSeatTakenInDb(string $flightId, string $seat, array $allTickets): bool {
    $seat = strtoupper($seat);
    foreach ($allTickets as $t) {
        if (strtolower($t['status'] ?? '') === 'cancelled') continue;
        if (($t['flight_id'] ?? '') !== $flightId) continue;
        if (strtoupper($t['seat'] ?? '') === $seat) return true;
    }
    return false;
}

// Format a raw digit string into a US phone display format
function formatPhone(string $raw): string {
    $d = preg_replace('/\D/', '', $raw);
    if (strlen($d) === 10) return '(' . substr($d,0,3) . ') ' . substr($d,3,3) . '-' . substr($d,6);
    if (strlen($d) === 11 && $d[0] === '1') return '+1 (' . substr($d,1,3) . ') ' . substr($d,4,3) . '-' . substr($d,7);
    return $raw;
}

// Reject phone numbers that contain alphabetic characters
function phoneHasLetters(string $raw): bool {
    return (bool)preg_match('/[A-Za-z]/', $raw);
}

// Basic email shape check (must contain "@" and ".") plus PHP's built-in filter
function isValidEmail(string $email): bool {
    if ($email === '') return false;
    if (!str_contains($email, '@') || !str_contains($email, '.')) return false;
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Format a timestamp (numeric or string) into a short readable date
function fmtTs($ts): string {
    if (!$ts) return '—';
    return is_numeric($ts) ? date('M j, Y H:i', (int)$ts) : date('M j, Y H:i', strtotime((string)$ts));
}

// Render a colored pill for a ticket status (active/cancelled/other)
function statusBadge(string $status): string {
    $cls = match(strtolower($status)) {
        'active'               => 'badge-active',
        'cancelled','refunded' => 'badge-cancelled',
        default                => 'badge-other',
    };
    return "<span class=\"badge {$cls}\">" . htmlspecialchars(ucfirst($status)) . "</span>";
}

// Render a colored pill for a user's role (root/admin/other)
function roleBadge(string $role): string {
    $cls = match(strtolower($role)) {
        'root'  => 'bg-purple-600/20 text-purple-400 border border-purple-700',
        'admin' => 'bg-blue-600/20 text-blue-400 border border-blue-700',
        default => 'bg-gray-600/20 text-gray-400 border border-gray-600',
    };
    return "<span class=\"px-3 py-1 rounded-full text-xs font-semibold {$cls}\">" . htmlspecialchars($role) . "</span>";
}

// Resolve a flight's destination code to "City (CODE)" using the airport lookup
function flightDestination(array $f, array $airportLookup): string {
    $code =
        $f['departingTo']
        ?? $f['landingAt']
        ?? $f['destination']
        ?? $f['arrivalCode']
        ?? $f['arrival']
        ?? '';

    if (!$code) return '—';

    $airport = $airportLookup[strtolower($code)] ?? null;
    if ($airport) {
        $city = $airport['city'] ?? $airport['cityName'] ?? $airport['location'] ?? '';
        if ($city !== '') return $city . ' (' . strtoupper($code) . ')';
    }
    return strtoupper($code);
}

// Prefer destination derived from live flight data; fall back to the posted form value
function resolveTicketDestination(?array $flight, array $airportLookup, string $postedDestination): string {
    if ($flight) return flightDestination($flight, $airportLookup);
    return strtoupper(trim($postedDestination));
}

// Look up a flight's details, checking the in-memory map first, then the API
function getFlightInfo(string $fid, array $flightMap, AirportsAPI $api): ?array {
    if (!$fid) return null;
    if (isset($flightMap[$fid])) return $flightMap[$fid];
    $f = $api->getFlightById($fid);
    return $f ?: null;
}

$activeTab = $_GET['tab'] ?? 'overview';
$updateMsg = null;
$errorMsg  = null;

$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'create_admin') {
        $fn    = trim($_POST['first_name']  ?? '');
        $mn    = trim($_POST['middle_name'] ?? '');
        $ln    = trim($_POST['last_name']   ?? '');
        $email = trim($_POST['email']       ?? '');
        $pw    = trim($_POST['password']    ?? '');
        if (!$fn || !$ln || !$email) {
            $errorMsg = 'First name, last name, and email are required.';
        } elseif (strlen($pw) < 10) {
            $errorMsg = 'Password must be at least 10 characters.';
        } else {
            $emailCheck = $pdo->prepare('SELECT 1 FROM "Users" WHERE LOWER(email) = LOWER(?)');
            $emailCheck->execute([$email]);
            if ($emailCheck->fetch()) {
                $errorMsg = 'That email address is already in use.';
            } else {
                $hash = password_hash($pw, PASSWORD_BCRYPT);
                $ins  = $pdo->prepare(
                    'INSERT INTO "Users" (first_name, middle_name, last_name, email, password, role)
                     VALUES (?,?,?,?,?,?)'
                );
                $ins->execute([$fn, $mn ?: null, $ln, $email, $hash, 'Admin']);
                $updateMsg = "Admin {$fn} {$ln} created.";
            }
        }
        $usersStmt = $pdo->query('SELECT * FROM "Users"');
        $allUsers  = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
        $admins    = array_values(array_filter($allUsers, fn($u) => in_array(strtolower($u['role'] ?? ''), ['admin', 'root'])));
        $customers = array_values(array_filter($allUsers, fn($u) => strtolower($u['role'] ?? '') === 'customer'));

        if ($isAjax) {
            header('Content-Type: application/json');
            if ($errorMsg) echo json_encode(['success' => false, 'message' => $errorMsg]);
            else echo json_encode(['success' => true, 'message' => $updateMsg]);
            exit;
        }
        $activeTab = 'admins';
    }

    if ($_POST['action'] === 'delete_admin') {
        $uid = $_POST['user_id'] ?? '';
        $check = $pdo->prepare('SELECT role FROM "Users" WHERE user_id = ?');
        $check->execute([$uid]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if ($row && strtolower($row['role'] ?? '') === 'root') {
            $errorMsg = 'The root account cannot be deleted.';
        } elseif ($uid) {
            $del = $pdo->prepare('DELETE FROM "Users" WHERE user_id = ? AND LOWER(role) = \'admin\'');
            $del->execute([$uid]);
            $updateMsg = 'Admin account deleted.';
        }
        $usersStmt = $pdo->query('SELECT * FROM "Users"');
        $allUsers  = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
        $admins    = array_values(array_filter($allUsers, fn($u) => in_array(strtolower($u['role'] ?? ''), ['admin', 'root'])));
        $customers = array_values(array_filter($allUsers, fn($u) => strtolower($u['role'] ?? '') === 'customer'));

        if ($isAjax) {
            header('Content-Type: application/json');
            if ($errorMsg) echo json_encode(['success' => false, 'message' => $errorMsg]);
            else echo json_encode(['success' => true, 'message' => $updateMsg]);
            exit;
        }
        $activeTab = 'admins';
    }

    if ($_POST['action'] === 'update_admin') {
        $uid   = $_POST['user_id']         ?? '';
        $email = trim($_POST['email']      ?? '');
        $fn    = trim($_POST['first_name'] ?? '');
        $mn    = trim($_POST['middle_name']?? '');
        $ln    = trim($_POST['last_name']  ?? '');
        $pw    = trim($_POST['password']   ?? '');
        if ($uid) {
            $emailCheck = $pdo->prepare('SELECT 1 FROM "Users" WHERE LOWER(email) = LOWER(?) AND user_id != ?');
            $emailCheck->execute([$email, $uid]);
            if ($emailCheck->fetch()) {
                $errorMsg = 'That email address is already in use.';
            } else {
                if ($pw) {
                    $hash = password_hash($pw, PASSWORD_BCRYPT);
                    $upd  = $pdo->prepare('UPDATE "Users" SET first_name=?, middle_name=?, last_name=?, email=?, password=? WHERE user_id=?');
                    $upd->execute([$fn, $mn ?: null, $ln, $email, $hash, $uid]);
                } else {
                    $upd = $pdo->prepare('UPDATE "Users" SET first_name=?, middle_name=?, last_name=?, email=? WHERE user_id=?');
                    $upd->execute([$fn, $mn ?: null, $ln, $email, $uid]);
                }
                $updateMsg = 'Admin updated.';
            }
        }
        $usersStmt = $pdo->query('SELECT * FROM "Users"');
        $allUsers  = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
        $admins    = array_values(array_filter($allUsers, fn($u) => in_array(strtolower($u['role'] ?? ''), ['admin', 'root'])));
        $customers = array_values(array_filter($allUsers, fn($u) => strtolower($u['role'] ?? '') === 'customer'));
        $activeTab = 'admins';
    }

    if ($_POST['action'] === 'create_customer') {
        $fn        = trim($_POST['first_name']  ?? '');
        $mn        = trim($_POST['middle_name'] ?? '');
        $ln        = trim($_POST['last_name']   ?? '');
        $email     = trim($_POST['email']       ?? '');
        $phone     = trim($_POST['phone']       ?? '');
        $pw        = trim($_POST['password']    ?? '');
        $dob       = trim($_POST['dob']         ?? '');
        $sex       = trim($_POST['sex']         ?? '');
        $street    = trim($_POST['street']      ?? '');
        $city      = trim($_POST['city']        ?? '');
        $state     = trim($_POST['state']       ?? '');
        $zip       = trim($_POST['zip']         ?? '');
        $country   = trim($_POST['country']     ?? '');
        $question1 = trim($_POST['question1']   ?? '');
        $answer1   = trim($_POST['answer1']     ?? '');
        $question2 = trim($_POST['question2']   ?? '');
        $answer2   = trim($_POST['answer2']     ?? '');
        $question3 = trim($_POST['question3']   ?? '');
        $answer3   = trim($_POST['answer3']     ?? '');

        if (!$fn || !$ln || !$email) {
            $errorMsg = 'First name, last name, and email are required.';
        } elseif (!isValidEmail($email)) {
            $errorMsg = 'Please enter a valid email address (must contain "@" and ".").';
        } elseif ($phone !== '' && phoneHasLetters($phone)) {
            $errorMsg = 'Phone number cannot contain letters.';
        } elseif (strlen($pw) < 8) {
            $errorMsg = 'Password must be at least 8 characters.';
        } elseif (!$question1 || !$answer1 || !$question2 || !$answer2 || !$question3 || !$answer3) {
            $errorMsg = 'All three security questions and answers are required.';
        } else {
            $emailCheck = $pdo->prepare('SELECT 1 FROM "Users" WHERE LOWER(email) = LOWER(?)');
            $emailCheck->execute([$email]);
            if ($emailCheck->fetch()) {
                $errorMsg = 'That email address is already in use.';
            } elseif (isOnNoFlyList($fn, $ln, $noFlyList)) {
                $errorMsg = "{$fn} {$ln} is on the no-fly list and cannot be registered.";
            } else {
                $phoneFormatted = $phone ? formatPhone($phone) : null;
                $hashed = password_hash($pw, PASSWORD_BCRYPT);
                $ins = $pdo->prepare(
                    'INSERT INTO "Users"
                        (first_name, middle_name, last_name, email, phone, password, role,
                         date_birth, sex, street_address, city, state, zip_code, country)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $ins->execute([
                    $fn, $mn ?: null, $ln, $email,
                    $phoneFormatted, $hashed, 'Customer',
                    $dob ?: null, $sex ?: null,
                    $street ?: null, $city ?: null, $state ?: null,
                    $zip ?: null, $country ?: null,
                ]);

                $qIns = $pdo->prepare(
                    'INSERT INTO "User Security Questions"
                        (email, question1, question1_answer, question2, question2_answer, question3, question3_answer)
                     VALUES (?,?,?,?,?,?,?)'
                );
                $qIns->execute([
                    $email,
                    $question1, password_hash($answer1, PASSWORD_BCRYPT),
                    $question2, password_hash($answer2, PASSWORD_BCRYPT),
                    $question3, password_hash($answer3, PASSWORD_BCRYPT),
                ]);

                $updateMsg = "Customer {$fn} {$ln} created successfully.";
            }
        }
        $usersStmt = $pdo->query('SELECT * FROM "Users"');
        $allUsers  = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
        $admins    = array_values(array_filter($allUsers, fn($u) => in_array(strtolower($u['role'] ?? ''), ['admin', 'root'])));
        $customers = array_values(array_filter($allUsers, fn($u) => strtolower($u['role'] ?? '') === 'customer'));
        $activeTab = 'customers';
    }

    if ($_POST['action'] === 'update_customer') {
        $uid     = $_POST['user_id']     ?? '';
        $email   = trim($_POST['email']  ?? '');
        $phone   = trim($_POST['phone']  ?? '');
        $street  = trim($_POST['street'] ?? '');
        $city    = trim($_POST['city']   ?? '');
        $state   = trim($_POST['state']  ?? '');
        $zip     = trim($_POST['zip']    ?? '');
        $country = trim($_POST['country']?? '');

        if ($email !== '' && !isValidEmail($email)) {
            $errorMsg = 'Please enter a valid email address (must contain "@" and ".").';
        } elseif ($phone !== '' && phoneHasLetters($phone)) {
            $errorMsg = 'Phone number cannot contain letters.';
        } else {
            $phoneFormatted = $phone !== '' ? formatPhone($phone) : null;

            $upd = $pdo->prepare(
                'UPDATE "Users"
                 SET email=?, phone=?, street_address=?, city=?, state=?, zip_code=?, country=?
                 WHERE user_id=? AND LOWER(role)=\'customer\''
            );
            $upd->execute([
                $email ?: null,
                $phoneFormatted,
                $street ?: null,
                $city ?: null,
                $state ?: null,
                $zip ?: null,
                $country ?: null,
                $uid
            ]);
            $updateMsg = 'Customer updated successfully.';

            $usersStmt = $pdo->query('SELECT * FROM "Users"');
            $allUsers  = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
            $admins    = array_values(array_filter($allUsers, fn($u) => in_array(strtolower($u['role'] ?? ''), ['admin', 'root'])));
            $customers = array_values(array_filter($allUsers, fn($u) => strtolower($u['role'] ?? '') === 'customer'));
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            if ($errorMsg) echo json_encode(['success' => false, 'message' => $errorMsg]);
            else echo json_encode(['success' => true, 'message' => $updateMsg]);
            exit;
        }
        $activeTab = 'customers';
    }

    if ($_POST['action'] === 'delete_customer') {
        $uid = $_POST['user_id'] ?? '';
        if ($uid) {
            $del = $pdo->prepare('DELETE FROM "Users" WHERE user_id = ? AND LOWER(role) = \'customer\'');
            $del->execute([$uid]);
            $updateMsg = 'Customer account deleted.';
        }
        $usersStmt = $pdo->query('SELECT * FROM "Users"');
        $allUsers  = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
        $admins    = array_values(array_filter($allUsers, fn($u) => in_array(strtolower($u['role'] ?? ''), ['admin', 'root'])));
        $customers = array_values(array_filter($allUsers, fn($u) => strtolower($u['role'] ?? '') === 'customer'));

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $updateMsg]);
            exit;
        }
        $activeTab = 'customers';
    }

    if ($_POST['action'] === 'create_ticket') {
        $fid       = trim($_POST['flight_id']  ?? '');
        $nameFirst = trim($_POST['name_first'] ?? '');
        $nameLast  = trim($_POST['name_last']  ?? '');
        $seat      = strtoupper(trim($_POST['seat'] ?? ''));
        $price     = trim($_POST['price']      ?? '0');
        $uid       = trim($_POST['user_id']    ?? '');
        $bags      = trim($_POST['bags']       ?? '0');
        $email     = trim($_POST['email']      ?? '');
        $phone     = trim($_POST['phone']      ?? '');
        $sex       = trim($_POST['sex']        ?? '');
        $dob       = trim($_POST['dob']        ?? '');

        if (!$fid) {
            $errorMsg = 'Flight ID is required.';
        } elseif (!$nameLast || !$nameFirst) {
            $errorMsg = 'First name and last name are required.';
        } elseif ($seat === '') {
            $errorMsg = 'Seat is required.';
        } elseif (!validSeat($seat)) {
            $errorMsg = 'Invalid seat. Must be row 1–10 and column A–I (Ex. 5A, 10I).';
        } elseif ($email !== '' && !isValidEmail($email)) {
            $errorMsg = 'Please enter a valid email address (must contain "@" and ".").';
        } elseif ($phone !== '' && phoneHasLetters($phone)) {
            $errorMsg = 'Phone number cannot contain letters.';
        } elseif (isOnNoFlyList($nameFirst, $nameLast, $noFlyList)) {
            $errorMsg = "{$nameFirst} {$nameLast} is on the no-fly list.";
        } else {
            $f = $flightMap[$fid] ?? null;
            $takenFromApi = [];
            if ($f) {
                foreach ($f['takenSeats'] ?? $f['taken_seats'] ?? [] as $ts) {
                    $s = is_array($ts) ? ($ts['seat'] ?? '') : (string)$ts;
                    if ($s) $takenFromApi[] = strtoupper($s);
                }
            }

            if (in_array($seat, $takenFromApi)) {
                $errorMsg = "Seat {$seat} is already taken on this flight.";
            } elseif (isSeatTakenInDb($fid, $seat, $allTickets)) {
                $errorMsg = "Seat {$seat} is already taken on this flight.";
            } else {
                $code = strtoupper(bin2hex(random_bytes(4)));
                $phoneFormatted = $phone ? formatPhone($phone) : null;
                $destination = resolveTicketDestination($f, $airportLookup, $_POST['destination'] ?? '');

                $ins = $pdo->prepare(
                    'INSERT INTO "Tickets"
                        (flight_id, name_first, name_last, confirmation_code, seat, price,
                        user_id, status, created_at, bags_carried, email, phone_number,
                        sex, date_birth, destination)
                    VALUES (?,?,?,?,?,?,?,?,NOW(),?,?,?,?,?,?)'
                );
                $ins->execute([
                    $fid, $nameFirst, $nameLast, $code, $seat,
                    is_numeric($price) ? (float)$price : 0,
                    $uid ?: null, 'active',
                    is_numeric($bags) ? (int)$bags : 0,
                    $email ?: null,
                    $phoneFormatted,
                    $sex ?: null,
                    $dob ?: null,
                    $destination
                ]);
                $updateMsg = "Ticket created. Confirmation: {$code}";
                $ticketsStmt = $pdo->query('SELECT * FROM "Tickets"');
                $allTickets  = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);

                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => $updateMsg,
                        'confirmation_code' => $code,
                        'redirect' => '../../../booking/confirmation.php?confirmation=' . urlencode($code)
                    ]);
                    exit;
                }
            }
        }

        if ($isAjax && $errorMsg) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $errorMsg]);
            exit;
        }

        $activeTab = 'tickets';
    }

    if ($_POST['action'] === 'cancel_ticket') {
        $tid = $_POST['ticket_id'] ?? '';
        $upd = $pdo->prepare('UPDATE "Tickets" SET status=? WHERE ticket_id=?');
        $upd->execute(['cancelled', $tid]);
        $updateMsg = 'Ticket cancelled.';
        $ticketsStmt = $pdo->query('SELECT * FROM "Tickets"');
        $allTickets  = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $updateMsg]);
            exit;
        }

        $activeTab = 'tickets';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Root Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
body { font-family: 'Inter', sans-serif; background: #0f1117; color: #e2e8f0; }

.tab-active   { background: rgb(37 99 235); color: white; }
.tab-inactive { color: rgb(156 163 175); }
.tab-inactive:hover { color: white; background: rgb(55 65 81); }
.period-btn { transition: all .15s; }
.period-active { background: #2563eb !important; color: #fff !important; border-color: #2563eb !important; }

.badge { display:inline-flex; align-items:center; padding:2px 10px; border-radius:9999px; font-size:.72rem; font-weight:600; white-space:nowrap; }
.badge-active    { background:rgba(16,185,129,.15); color:#34d399; border:1px solid rgba(16,185,129,.3); }
.badge-cancelled { background:rgba(239,68,68,.15);  color:#f87171; border:1px solid rgba(239,68,68,.3); }
.badge-other     { background:rgba(99,102,241,.15); color:#a5b4fc; border:1px solid rgba(99,102,241,.3); }

tbody tr { border-top:1px solid #1f2937; transition:background .12s; }
tbody tr:hover { background:rgba(55,65,81,.45); }
.cancelled-row { opacity:.5; }

.field {
    width:100%;
    height:2.5rem;
    background:#374151;
    border:1px solid #4b5563;
    border-radius:.5rem;
    padding:0 .875rem;
    font-size:.875rem;
    color:#fff;
    transition:all .15s;
}
.field:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.2); }
.field-disabled { background:#1f2937; color:#6b7280; cursor:not-allowed; }
select.field { background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%236b7280'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right .6rem center; background-size:1.25rem; padding-right:2.5rem; appearance:none; }

.hint { font-size:.7rem; color:#6b7280; margin-top:.2rem; }

::-webkit-scrollbar { width:6px; height:6px; }
::-webkit-scrollbar-track { background:#0f1117; }
::-webkit-scrollbar-thumb { background:#374151; border-radius:3px; }
.table-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.section-label {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .12em;
    color: rgb(96 165 250);
    font-weight: 700;
    padding: .75rem 0 .4rem;
}
.section-label:first-child { border-top:none; margin-top:0; }
.copy-btn { cursor:pointer; transition:color .12s; }
.copy-btn:hover { color:#60a5fa; }
</style>
</head>
<body class="bg-gray-900 min-h-screen text-white">

<?php include_once __DIR__ . '/../../../components/nav.php'; ?>

<main class="max-w-7xl mx-auto p-4 sm:p-6 space-y-5">

  <div class="rounded-lg p-8 mb-6 bg-gradient-to-r from-slate-800 to-slate-900 border border-gray-700 shadow-lg">
      <p><span class="tracking-[0.25em] text-sm text-blue-300">BDPA AIRPORTS - Root Dashboard</span>👑</p>
      <h1 class="text-4xl font-bold mt-2"><?= htmlspecialchars($selfName) ?></h1>
      <p class="text-gray-400 mt-4">Complete access: administrators, customers, tickets, and analytics.</p>
  </div>

  <div id="flashMsg" class="<?= $updateMsg ? '' : 'hidden' ?> bg-emerald-950 border border-emerald-700 rounded-lg px-5 py-3 text-emerald-400 text-sm flex items-center gap-2">
    <span>✓</span><span id="flashMsgText"><?= htmlspecialchars($updateMsg ?? '') ?></span>
  </div>
  <div id="errorMsgBox" class="<?= $errorMsg ? '' : 'hidden' ?> bg-red-950 border border-red-700 rounded-lg px-5 py-3 text-red-400 text-sm flex items-center gap-2">
    <span>⚠</span><span id="errorMsgText"><?= htmlspecialchars($errorMsg ?? '') ?></span>
  </div>

  <div class="mb-6 bg-gray-800 border border-gray-700 rounded-lg p-1">
    <div class="flex gap-2 overflow-x-auto sm:overflow-visible whitespace-nowrap sm:flex-wrap">
    <?php
    $tabs = [
      'overview'  => 'Overview',
      'admins'    => 'Administrators',
      'customers' => 'Customers',
      'tickets'   => 'Tickets',
    ];
    foreach ($tabs as $key => $label):
      $cls = ($activeTab === $key) ? 'tab-active' : 'tab-inactive';
    ?>
    <a href="?tab=<?= $key ?>" class="px-4 py-2 rounded-md text-sm font-medium transition flex-shrink-0 <?= $cls ?>"><?= $label ?></a>
    <?php endforeach; ?>
    </div>
  </div>

  <?php if ($activeTab === 'overview'): ?>

  <div class="flex gap-2 flex-wrap">
    <?php foreach (['day' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year', 'all' => 'All Time'] as $k => $label): ?>
    <button onclick="setPeriod('<?= $k ?>')" id="period-<?= $k ?>"
        class="period-btn px-4 py-2 rounded-lg text-sm font-semibold border border-gray-700 text-gray-400 hover:bg-gray-800"
        data-tickets="<?= $ticketStats[$k] ?>"
        data-profit="<?= $profitStats[$k] ?>">
      <?= $label ?>
    </button>
    <?php endforeach; ?>
  </div>

  <div class="grid xl:grid-cols-4 md:grid-cols-2 gap-4">
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 overflow-hidden transition duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-blue-500">
      <p class="text-gray-400 text-sm">Tickets Sold</p>
      <h2 class="text-4xl font-bold mt-2 tabular-nums" id="stat-tickets">—</h2>
      <p class="text-gray-500 text-sm mt-2" id="stat-period-label">Select a period above</p>
    </div>
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 overflow-hidden transition duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-blue-500">
      <p class="text-gray-400 text-sm">Gross Profit</p>
      <h2 class="text-3xl font-extrabold mt-3 tabular-nums text-emerald-400" id="stat-profit">—</h2>
      <p class="text-gray-600 text-xs mt-2">All tickets</p>
    </div>
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 overflow-hidden transition duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-blue-500">
      <p class="text-gray-400 text-sm">Administrators</p>
      <h2 class="text-4xl font-extrabold mt-3 tabular-nums"><?= count($admins) ?></h2>
      <p class="text-gray-600 text-xs mt-2">Admin + Root accounts</p>
    </div>
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 overflow-hidden transition duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-blue-500">
      <p class="text-gray-400 text-sm">Customers</p>
      <h2 class="text-4xl font-extrabold mt-3 tabular-nums"><?= count($customers) ?></h2>
      <p class="text-gray-600 text-xs mt-2">Registered customers</p>
    </div>
  </div>

  <div class="grid md:grid-cols-3 gap-4">
    <a href="?tab=admins" class="bg-gray-800 border border-gray-700 rounded-lg p-6 overflow-hidden transition duration-300 hover:shadow-xl hover:border-blue-600 block group">
      <div class="text-2xl mb-3">🛡️</div>
      <h3 class="font-bold group-hover:text-blue-400 transition">Manage Admins</h3>
      <p class="text-gray-500 text-sm mt-1">Create, edit, and delete administrator accounts.</p>
    </a>
    <a href="?tab=customers" class="bg-gray-800 border border-gray-700 rounded-lg p-6 overflow-hidden transition duration-300 hover:shadow-xl hover:border-blue-600 block group">
      <div class="text-2xl mb-3">👤</div>
      <h3 class="font-bold group-hover:text-blue-400 transition">Customers</h3>
      <p class="text-gray-500 text-sm mt-1">Create, view and manage all customer accounts.</p>
    </a>
    <a href="?tab=tickets" class="bg-gray-800 border border-gray-700 rounded-lg p-6 overflow-hidden transition duration-300 hover:shadow-xl hover:border-blue-600 block group">
      <div class="text-2xl mb-3">🎫</div>
      <h3 class="font-bold group-hover:text-blue-400 transition">Tickets</h3>
      <p class="text-gray-500 text-sm mt-1">Search and manage all tickets.</p>
    </a>
  </div>

  <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden">
    <div class="p-5 border-b border-gray-700 flex items-center justify-between">
      <h2 class="text-lg font-bold">Administrator Accounts</h2>
      <a href="?tab=admins" class="text-sm text-blue-400 hover:text-blue-300">Manage</a>
    </div>
    <div class="table-wrap">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-700/50 text-gray-400 text-sm">
            <th class="text-left px-5 py-3">Name</th>
            <th class="text-left px-5 py-3">Email</th>
            <th class="text-left px-5 py-3">Role</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach (array_slice($admins, 0, 5) as $a): ?>
        <tr class="border-t border-gray-700 hover:bg-gray-700/40 transition">
          <td class="px-5 py-4 font-semibold"><?= htmlspecialchars(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))) ?></td>
          <td class="px-5 py-4 text-gray-300"><?= htmlspecialchars($a['email'] ?? '—') ?></td>
          <td class="px-5 py-4"><?= roleBadge($a['role'] ?? 'Admin') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($admins)): ?>
        <tr><td colspan="3" class="px-5 py-8 text-center text-gray-500">No administrators.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php endif; ?>

  <?php if ($activeTab === 'admins'): ?>

  <?php
  $adminSearch = trim($_GET['asearch'] ?? '');
  $filteredAdmins = $admins;
  if ($adminSearch !== '') {
    $q = strtolower($adminSearch);
    $filteredAdmins = array_filter($admins, fn($a) =>
      str_contains(strtolower(
        ($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '') . ' ' . ($a['email'] ?? '')
      ), $q)
    );
  }
  $adminsPerPage = 10;
  $adminPage = max(1, (int)($_GET['apage'] ?? 1));
  $totalAdminPages = max(1, ceil(count($filteredAdmins) / $adminsPerPage));
  $filteredAdmins = array_values($filteredAdmins);
  $filteredAdmins = array_slice($filteredAdmins, ($adminPage - 1) * $adminsPerPage, $adminsPerPage);

  $editAdmin = null;
  if (isset($_GET['edit'])) {
    foreach ($admins as $a) {
      if ((string)($a['user_id'] ?? '') === (string)$_GET['edit']) { $editAdmin = $a; break; }
    }
  }
  ?>

  <div class="grid lg:grid-cols-2 gap-5">
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 shadow-sm">
      <h2 class="text-xl font-bold mb-1">Create New Administrator🛡️</h2>
      <p class="text-sm text-gray-400 mb-5">Create an account with full admin-level access.</p>
      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="create_admin">
        <p class="section-label">Required</p>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-gray-400 mb-1">First Name*</label>
            <input type="text" name="first_name" required class="field">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Middle Name (Optional)</label>
            <input type="text" name="middle_name" class="field">
          </div>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Last Name*</label>
          <input type="text" name="last_name" required class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Email*</label>
          <input type="email" name="email" required class="field">
        </div>
        <div>
          <label class="block text-sm text-gray-400 mb-1">Password* <span class="text-gray-600">(min 10 chars)</span></label>
          <input type="password" name="password" required minlength="10" class="field" oninput="checkPw(this.value)">
          <p class="mt-2 text-sm text-gray-400">Password strength: <span id="cpw-hint">—</span></p>
        </div>
        <button type="submit" class="w-full h-11 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-semibold transition shadow-md hover:shadow-lg">
          Create Administrator
        </button>
      </form>
    </div>

    <?php if ($editAdmin): ?>
    <div class="bg-gray-800 border border-blue-600 rounded-lg p-6 shadow-sm">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold">Edit — <?= htmlspecialchars(trim(($editAdmin['first_name'] ?? '') . ' ' . ($editAdmin['last_name'] ?? ''))) ?>✏️</h2>
        <a href="?tab=admins" class="text-xs text-gray-500 hover:text-gray-300">✕</a>
      </div>
      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="update_admin">
        <input type="hidden" name="user_id" value="<?= htmlspecialchars($editAdmin['user_id'] ?? '') ?>">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-gray-400 mb-1">First Name</label>
            <input type="text" name="first_name" value="<?= htmlspecialchars($editAdmin['first_name'] ?? '') ?>" class="field">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Middle Name</label>
            <input type="text" name="middle_name" value="<?= htmlspecialchars($editAdmin['middle_name'] ?? '') ?>" class="field">
          </div>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Last Name</label>
          <input type="text" name="last_name" value="<?= htmlspecialchars($editAdmin['last_name'] ?? '') ?>" class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($editAdmin['email'] ?? '') ?>" class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">New Password <span class="text-gray-600">(leave blank to keep)</span></label>
          <input type="password" name="password" placeholder="Leave blank to keep current" class="field">
        </div>
        <button type="submit" class="w-full h-10 bg-blue-600 hover:bg-blue-500 rounded-lg text-sm font-semibold transition">Save Changes</button>
      </form>
    </div>
    <?php else: ?>
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-6 flex flex-col items-center justify-center text-center gap-2">
      <span class="text-3xl">✏️</span>
      <p class="text-gray-500 text-sm">Click <strong class="text-gray-400">Edit</strong> on an admin row to modify their details.</p>
    </div>
    <?php endif; ?>
  </div>

  <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden">
    <div class="p-5 border-b border-gray-700 flex flex-wrap items-center justify-between gap-4">
      <h2 class="text-lg font-bold">All Administrators 🛡️</h2>
      <form method="GET" class="flex gap-2 flex-wrap">
        <input type="hidden" name="tab" value="admins">
        <input type="text" name="asearch" value="<?= htmlspecialchars($adminSearch) ?>"
          placeholder="Search name, email…" class="field h-10 w-64">
        <button type="submit" class="h-10 px-5 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-semibold transition">Search</button>
        <?php if ($adminSearch): ?>
        <a href="?tab=admins" class="h-10 px-4 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-semibold transition flex items-center">Clear</a>
        <?php endif; ?>
      </form>
    </div>
    <div class="table-wrap">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-700/50 text-gray-400 text-sm">
            <th class="text-left px-5 py-3">Name</th>
            <th class="text-left px-5 py-3">Email</th>
            <th class="text-left px-5 py-3">Role</th>
            <th class="text-left px-5 py-3">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($filteredAdmins as $a):
          $isRoot = strtolower($a['role'] ?? '') === 'root';
        ?>
        <tr class="border-t border-gray-700 hover:bg-gray-700/40 transition">
          <td class="px-5 py-4 font-semibold"><?= htmlspecialchars(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))) ?></td>
          <td class="px-5 py-4 text-gray-300"><?= htmlspecialchars($a['email'] ?? '—') ?></td>
          <td class="px-5 py-4"><?= roleBadge($a['role'] ?? 'Admin') ?></td>
          <td class="px-5 py-4 flex items-center gap-4">
            <a href="?tab=admins&edit=<?= urlencode($a['user_id'] ?? '') ?>" class="text-blue-400 hover:text-blue-300 text-sm font-semibold transition">Edit</a>
            <?php if (!$isRoot): ?>
            <form method="POST" class="inline delete-admin-form" data-user-id="<?= htmlspecialchars($a['user_id'] ?? '') ?>">
              <input type="hidden" name="action" value="delete_admin">
              <input type="hidden" name="user_id" value="<?= htmlspecialchars($a['user_id'] ?? '') ?>">
              <button type="submit"
                data-skip-loader
                onclick="return confirm('Delete admin <?= htmlspecialchars(addslashes(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')))) ?>? This cannot be undone.')"
                class="text-sm text-red-400 hover:text-red-300 font-semibold transition">Delete</button>
            </form>
            <?php else: ?>
            <span class="text-sm text-gray-700">Protected</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($filteredAdmins)): ?>
        <tr><td colspan="4" class="px-5 py-8 text-center text-gray-500">No administrators found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="px-5 py-4 border-t border-gray-700 flex items-center justify-between">
      <span class="text-xs text-gray-500">Page <?= $adminPage ?> of <?= $totalAdminPages ?></span>
      <div class="flex gap-2">
        <?php if ($adminPage > 1): ?>
        <a href="?tab=admins&asearch=<?= urlencode($adminSearch) ?>&apage=<?= $adminPage - 1 ?>" class="px-3 py-1 bg-gray-800 rounded text-sm hover:bg-gray-700">Previous</a>
        <?php endif; ?>
        <?php if ($adminPage < $totalAdminPages): ?>
        <a href="?tab=admins&asearch=<?= urlencode($adminSearch) ?>&apage=<?= $adminPage + 1 ?>" class="px-3 py-1 bg-blue-600 rounded text-sm hover:bg-blue-500">Next</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php endif; ?>

  <?php if ($activeTab === 'customers'): ?>

  <?php
  $editCustomer = null;
  if (isset($_GET['edit']) && $_GET['edit'] !== '') {
    $editId = (string)$_GET['edit'];
    foreach ($customers as $u) {
      if ((string)($u['user_id'] ?? '') === $editId) { $editCustomer = $u; break; }
    }
  }

  $customerSearch = trim($_GET['csearch'] ?? '');
  $filteredUsers  = $customers;
  if ($customerSearch !== '') {
    $q = strtolower($customerSearch);
    $filteredUsers = array_filter($customers, fn($u) =>
      str_contains(strtolower(
        ($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '') . ' ' .
        ($u['email'] ?? '') . ' ' . ($u['phone'] ?? '') . ' ' .
        ($u['city'] ?? '') . ' ' . ($u['street_address'] ?? '') . ' ' .
        ($u['state'] ?? '') . ' ' . ($u['zip_code'] ?? '') . ' ' .
        ($u['country'] ?? '')
      ), $q)
    );
  }
  $customersPerPage = 10;
  $customerPage = max(1, (int)($_GET['cpage'] ?? 1));
  $totalCustomersPages = max(1, ceil(count($filteredUsers) / $customersPerPage));
  $filteredUsers = array_values($filteredUsers);
  $filteredUsers = array_slice($filteredUsers, ($customerPage - 1) * $customersPerPage, $customersPerPage);
  ?>

  <div class="grid lg:grid-cols-2 gap-5">

    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 shadow-sm">
      <h2 class="text-xl font-bold mb-1">Create Customer📝</h2>
      <p class="text-sm text-gray-400 mb-5">Register a new customer account and profile information.</p>
      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="create_customer">

        <p class="section-label">Required</p>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-gray-400 mb-1">First Name*</label>
            <input type="text" name="first_name" required class="field">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Middle Name (Optional)</label>
            <input type="text" name="middle_name" class="field">
          </div>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Last Name*</label>
          <input type="text" name="last_name" required class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Email Address*</label>
          <input type="email" name="email" required pattern="^[^\s@]+@[^\s@]+\.[^\s@]+$"
            title="Email must contain &quot;@&quot; and &quot;.&quot;" class="field">
        </div>
        <div>
          <label class="block text-sm text-gray-400 mb-1">Password</label>
          <input type="password" name="password" class="field" oninput="checkCustomerPw(this.value)" required>
          <p class="mt-2 text-sm text-gray-400">Password strength: <span id="cpw-hint-customer">—</span></p>
        </div>

        <p class="section-label">Security Questions (used for account recovery)</p>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Question 1*</label>
          <input type="text" name="question1" required class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Answer 1*</label>
          <input type="text" name="answer1" required class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Question 2*</label>
          <input type="text" name="question2" required class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Answer 2*</label>
          <input type="text" name="answer2" required class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Question 3*</label>
          <input type="text" name="question3" required class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Answer 3*</label>
          <input type="text" name="answer3" required class="field">
        </div>

        <p class="section-label">Optional Fields</p>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Phone Number</label>
          <input type="tel" name="phone" class="field" inputmode="numeric" oninput="autoFormatPhone(this)">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-gray-400 mb-1">Date of Birth</label>
            <input type="date" name="dob" class="field">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Sex/Gender</label>
            <select name="sex" class="field">
              <option value="">Select</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="nonbinary">Non-binary</option>
              <option value="other">Other</option>
              <option value="prefer-not-to-say">Prefer not to say</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Street Address</label>
          <input type="text" name="street" class="field">
        </div>
        <div class="grid grid-cols-3 gap-2">
          <div>
            <label class="block text-xs text-gray-400 mb-1">City</label>
            <input type="text" name="city" class="field">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">State</label>
            <input type="text" name="state" class="field">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">ZIP</label>
            <input type="text" name="zip" class="field">
          </div>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Country</label>
          <input type="text" name="country" class="field">
        </div>

        <button type="submit" class="w-full h-11 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-semibold transition shadow-md hover:shadow-lg">
          Create Customer
        </button>
      </form>
    </div>

    <?php if ($editCustomer): ?>
    <div class="bg-gray-800 border border-blue-600 rounded-lg p-6 shadow-sm">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold">
          Edit — <?= htmlspecialchars(trim(($editCustomer['first_name'] ?? '') . ' ' . ($editCustomer['last_name'] ?? ''))) ?>✏️
        </h2>
        <a href="?tab=customers" class="text-xs text-gray-500 hover:text-gray-300">✕</a>
      </div>
      <form method="POST" class="space-y-3 update-customer-form">
        <input type="hidden" name="action" value="update_customer">
        <input type="hidden" name="user_id" value="<?= htmlspecialchars((string)($editCustomer['user_id'] ?? '')) ?>">

        <p class="section-label">Identity (read-only)</p>
        <div class="grid grid-cols-3 gap-2">
          <div>
            <label class="block text-xs text-gray-400 mb-1">First Name</label>
            <input type="text" value="<?= htmlspecialchars($editCustomer['first_name'] ?? '') ?>" disabled class="field field-disabled">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Middle Name</label>
            <input type="text" value="<?= htmlspecialchars($editCustomer['middle_name'] ?? '—') ?>" disabled class="field field-disabled">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Last Name</label>
            <input type="text" value="<?= htmlspecialchars($editCustomer['last_name'] ?? '') ?>" disabled class="field field-disabled">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block text-xs text-gray-400 mb-1">Date of Birth</label>
            <input type="text" value="<?= htmlspecialchars($editCustomer['date_birth'] ?? $editCustomer['date_of_birth'] ?? '—') ?>" disabled class="field field-disabled">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Sex/Gender</label>
            <input type="text" value="<?= htmlspecialchars($editCustomer['sex'] ?? '—') ?>" disabled class="field field-disabled">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block text-xs text-gray-400 mb-1">Title</label>
            <input type="text" value="<?= htmlspecialchars($editCustomer['title'] ?? '—') ?>" disabled class="field field-disabled">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Suffix</label>
            <input type="text" value="<?= htmlspecialchars($editCustomer['suffix'] ?? '—') ?>" disabled class="field field-disabled">
          </div>
        </div>

        <p class="section-label">Contact (editable)</p>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Email Address</label>
          <input type="email" name="email" value="<?= htmlspecialchars($editCustomer['email'] ?? '') ?>"
            pattern="^[^\s@]+@[^\s@]+\.[^\s@]+$" title="Email must contain &quot;@&quot; and &quot;.&quot;" class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Phone Number</label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($editCustomer['phone'] ?? '') ?>"
            placeholder="(555) 555-5555" class="field" inputmode="numeric" oninput="autoFormatPhone(this)">
        </div>

        <p class="section-label">Address (editable)</p>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Street Address</label>
          <input type="text" name="street" value="<?= htmlspecialchars($editCustomer['street_address'] ?? '') ?>" class="field">
        </div>
        <div class="grid grid-cols-3 gap-2">
          <div>
            <label class="block text-xs text-gray-400 mb-1">City</label>
            <input type="text" name="city" value="<?= htmlspecialchars($editCustomer['city'] ?? '') ?>" class="field">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">State</label>
            <input type="text" name="state" value="<?= htmlspecialchars($editCustomer['state'] ?? '') ?>" class="field">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">ZIP</label>
            <input type="text" name="zip" value="<?= htmlspecialchars($editCustomer['zip_code'] ?? '') ?>" class="field">
          </div>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Country</label>
          <input type="text" name="country" value="<?= htmlspecialchars($editCustomer['country'] ?? '') ?>" class="field">
        </div>

        <button type="submit" class="w-full h-10 bg-blue-600 hover:bg-blue-500 rounded-lg text-sm font-semibold transition">
          Save Changes
        </button>
      </form>
    </div>
    <?php else: ?>
    <div class="bg-gray-800 border border-gray-700 rounded-xl p-6 flex flex-col items-center justify-center text-center gap-2">
      <span class="text-3xl">✏️</span>
      <p class="text-gray-500 text-sm">Click <strong class="text-gray-400">Edit</strong> on a customer row to modify their details.</p>
    </div>
    <?php endif; ?>

  </div>

  <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden">
    <div class="p-5 border-b border-gray-700 flex flex-wrap items-center justify-between gap-4">
      <h2 class="text-lg font-bold">All Customers 👥</h2>
      <form method="GET" class="flex gap-2 flex-wrap">
        <input type="hidden" name="tab" value="customers">
        <input type="text" name="csearch" value="<?= htmlspecialchars($customerSearch) ?>"
          placeholder="Search name, email, address…" class="field h-10 w-64">
        <button type="submit" class="h-10 px-5 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-semibold transition">Search</button>
        <?php if ($customerSearch): ?>
        <a href="?tab=customers" class="h-10 px-4 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-semibold transition flex items-center">Clear</a>
        <?php endif; ?>
      </form>
    </div>
    <div class="table-wrap">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-700/50 text-gray-400 text-sm">
            <th class="text-left px-5 py-3">ID</th>
            <th class="text-left px-5 py-3">Name</th>
            <th class="text-left px-5 py-3">Email</th>
            <th class="text-left px-5 py-3">Phone</th>
            <th class="text-left px-5 py-3">City</th>
            <th class="text-left px-5 py-3">Country</th>
            <th class="text-left px-5 py-3">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($filteredUsers as $u): ?>
        <tr class="border-t border-gray-700 hover:bg-gray-700/40 transition">
          <td class="px-5 py-4"><code class="text-xs bg-gray-700 px-2 py-1 rounded text-blue-300">#<?= (int)($u['user_id'] ?? 0) ?></code></td>
          <td class="px-5 py-4 font-semibold"><?= htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?></td>
          <td class="px-5 py-4 text-gray-300"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
          <td class="px-5 py-4 text-gray-300"><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
          <td class="px-5 py-4 text-gray-300"><?= htmlspecialchars($u['city'] ?? '—') ?></td>
          <td class="px-5 py-4 text-gray-300"><?= htmlspecialchars($u['country'] ?? '—') ?></td>
          <td class="px-5 py-4 flex items-center gap-4">
            <a href="?tab=customers&edit=<?= urlencode((string)($u['user_id'] ?? '')) ?>"
               class="text-blue-400 hover:text-blue-300 transition text-sm font-semibold">Edit</a>
            <form method="POST" class="inline delete-customer-form" data-user-id="<?= htmlspecialchars((string)($u['user_id'] ?? '')) ?>">
              <input type="hidden" name="action" value="delete_customer">
              <input type="hidden" name="user_id" value="<?= htmlspecialchars((string)($u['user_id'] ?? '')) ?>">
              <button type="submit"
                data-skip-loader
                onclick="return confirm('Delete customer <?= htmlspecialchars(addslashes(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')))) ?>? This cannot be undone.')"
                class="text-sm text-red-400 hover:text-red-300 font-semibold transition">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($filteredUsers)): ?>
        <tr><td colspan="7" class="px-5 py-10 text-center text-gray-500">No customers found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="px-5 py-4 border-t border-gray-700 flex items-center justify-between">
      <span class="text-xs text-gray-500">Page <?= $customerPage ?> of <?= $totalCustomersPages ?></span>
      <div class="flex gap-2">
        <?php if ($customerPage > 1): ?>
        <a href="?tab=customers&csearch=<?= urlencode($customerSearch) ?>&cpage=<?= $customerPage - 1 ?>" class="px-3 py-1 bg-gray-800 rounded text-sm hover:bg-gray-700">Previous</a>
        <?php endif; ?>
        <?php if ($customerPage < $totalCustomersPages): ?>
        <a href="?tab=customers&csearch=<?= urlencode($customerSearch) ?>&cpage=<?= $customerPage + 1 ?>" class="px-3 py-1 bg-blue-600 rounded text-sm hover:bg-blue-500">Next</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php endif; ?>

  <?php if ($activeTab === 'tickets'): ?>

  <?php
  $ticketSearch = trim($_GET['tsearch'] ?? '');
  $confLookup   = trim($_GET['conf'] ?? '');
  $lookupTicket = null;
  $lookupFlight = null;

  if ($confLookup !== '') {
    foreach ($allTickets as $t) {
      if (strtoupper($t['confirmation_code'] ?? '') === strtoupper($confLookup)) {
        $lookupTicket = $t;
        $lookupFlight = getFlightInfo($t['flight_id'] ?? '', $flightMap, $api);
        break;
      }
    }
  }

  $filteredTickets = $allTickets;
  if ($ticketSearch !== '') {
    $q = strtolower($ticketSearch);
    $filteredTickets = array_filter($allTickets, function($t) use ($q, $flightMap) {
      $f = $flightMap[$t['flight_id'] ?? ''] ?? [];
      return str_contains(strtolower(
        ($t['confirmation_code'] ?? '') . ' ' . ($t['name_last'] ?? '') . ' ' .
        ($t['name_first'] ?? '') . ' ' . ($t['flight_id'] ?? '') . ' ' .
        ($t['status'] ?? '') . ' ' .
        ($f['destination'] ?? '') . ' ' . ($f['flightNumber'] ?? '')
      ), $q);
    });
  }
  $ticketsPerPage = 10;
  $ticketPage = max(1, (int)($_GET['tpage'] ?? 1));
  $totalTicketPages = max(1, ceil(count($filteredTickets) / $ticketsPerPage));
  $filteredTickets = array_values($filteredTickets);
  $filteredTickets = array_slice($filteredTickets, ($ticketPage - 1) * $ticketsPerPage, $ticketsPerPage);
  ?>

  <div class="grid lg:grid-cols-2 gap-5">

    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 shadow-sm">
      <h2 class="font-bold text-base mb-3">Create New Ticket 🎫</h2>
      <form id="ticketForm" class="space-y-3">

        <p class="section-label">Flight &amp; Seat (required)</p>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Flight ID*</label>
          <input type="text" name="flight_id" id="tFlightId" required
            class="field font-mono text-xs" onblur="onFlightBlur(this.value)">
          <p id="flightInfo" class="text-xs mt-1 hidden"></p>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Seat*</label>
          <input type="text" name="seat" id="tSeat" required maxlength="3"
            placeholder="ex. 5A, 7H"
            class="field uppercase" oninput="this.value=this.value.toUpperCase()" onblur="onSeatBlur(this.value)">
          <p class="hint">Rows 1–10 · Columns A–I</p>
          <p id="seatErr" class="text-red-400 text-xs mt-1 hidden"></p>
        </div>

        <p class="section-label">Bags &amp; Pricing</p>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-gray-400 mb-1">Carry-On Bags</label>
            <select id="tCarryOn" class="field" onchange="onFlightBlur(document.getElementById('tFlightId').value)">
              <option value="0">0 Carry-on (Free)</option>
              <option value="1">1 Carry-on (Free)</option>
              <option value="2">2 Carry-on ($30 extra)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Checked Bags</label>
            <select id="tChecked" class="field" onchange="onFlightBlur(document.getElementById('tFlightId').value)">
              <option value="0">0 Checked (Free)</option>
              <option value="1">1 Checked (Free)</option>
              <option value="2">2 Checked ($50 extra)</option>
              <option value="3">3 Checked ($150 extra)</option>
              <option value="4">4 Checked ($250 extra)</option>
              <option value="5">5 Checked ($350 extra)</option>
            </select>
          </div>
        </div>

        <div class="bg-gray-800/60 border border-gray-700 rounded-lg p-4 text-sm space-y-1 mt-3">
          <div class="flex justify-between text-gray-300"><span>Seat Price</span><span id="priceSeatBase">$0.00</span></div>
          <div class="flex justify-between text-gray-300"><span>Bag Fees</span><span id="priceBagFees">$0.00</span></div>
          <div class="flex justify-between font-bold text-white border-t border-gray-700 pt-2 mt-1"><span>Total</span><span id="priceTotal" class="text-emerald-400">$0.00</span></div>
        </div>
        <input type="hidden" name="price" id="tPrice" value="0">
        <input type="hidden" name="destination" id="tDestination" value="">
        <input type="hidden" name="bags" id="tBags" value="0">

        <p class="section-label">Passenger (required)</p>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-gray-400 mb-1">First Name*</label>
            <input type="text" name="name_first" required class="field">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Last Name*</label>
            <input type="text" name="name_last" required class="field">
          </div>
        </div>

        <p class="section-label">Optional passenger details</p>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-gray-400 mb-1">Date of Birth</label>
            <input type="date" name="dob" class="field">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Sex/Gender</label>
            <select name="sex" class="field">
              <option value="">Select</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="nonbinary">Non-binary</option>
              <option value="other">Other</option>
              <option value="prefer-not-to-say">Prefer not to say</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Phone Number</label>
          <input type="tel" name="phone" class="field" inputmode="numeric" oninput="autoFormatPhone(this)">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Email Address</label>
          <input type="email" name="email" pattern="^[^\s@]+@[^\s@]+\.[^\s@]+$"
            title="Email must contain &quot;@&quot; and &quot;.&quot;" class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">User ID (If Customer) <span class="text-gray-600">(optional)</span></label>
          <input type="text" name="user_id" placeholder="Leave blank for guest" class="field" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'')">
        </div>

        <button type="submit" class="w-full h-11 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-semibold transition shadow-md hover:shadow-lg" data-skip-loader>
          Create Ticket
        </button>
      </form>
    </div>

    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 shadow-sm">
      <h2 class="font-bold text-base mb-4">Lookup by Confirmation Code</h2>
      <form method="GET" class="flex gap-2 mb-4">
        <input type="hidden" name="tab" value="tickets">
        <input type="text" name="conf" value="<?= htmlspecialchars($confLookup) ?>"
          class="field flex-1 h-9">
        <button type="submit" class="h-9 px-4 bg-blue-600 hover:bg-blue-500 rounded-lg text-sm font-semibold transition">Look Up</button>
        <?php if ($confLookup): ?>
        <a href="?tab=tickets" class="h-9 px-3 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-semibold flex items-center transition">Clear</a>
        <?php endif; ?>
      </form>

      <?php if ($confLookup !== ''): ?>
        <?php if ($lookupTicket): ?>
        <div class="space-y-0 text-sm divide-y divide-gray-800">
          <?php
          $lf = $lookupFlight;
          $rows = [
            'Confirmation' => '<code class="bg-gray-800 px-2 py-0.5 rounded font-mono text-blue-300">' . htmlspecialchars($lookupTicket['confirmation_code']) . '</code>',
            'Passenger'    => htmlspecialchars(trim(($lookupTicket['name_first'] ?? '') . ' ' . ($lookupTicket['name_last'] ?? '—'))),
            'Flight ID'    => '<span class="inline-flex items-center gap-2"><code class="bg-gray-800 px-2 py-0.5 rounded font-mono text-xs text-gray-300">' . htmlspecialchars($lookupTicket['flight_id'] ?? '—') . '</code>' . (!empty($lookupTicket['flight_id']) ? '<span class="copy-btn text-gray-500 text-xs" onclick="copyToClipboard(\'' . htmlspecialchars(addslashes($lookupTicket['flight_id']), ENT_QUOTES) . '\', this)" title="Copy flight ID">📋</span>' : '') . '</span>',
            'Flight #'     => '<code class="bg-gray-800 px-2 py-0.5 rounded font-mono text-xs text-gray-300">' . htmlspecialchars($lf['flightNumber'] ?? '—') . '</code>',
            'Route'        => htmlspecialchars('SMN → ' . strtoupper($lookupTicket['destination'] ?? '—')),
            'Destination'  => htmlspecialchars(strtoupper($lookupTicket['destination'] ?? '—')),
            'Seat'         => htmlspecialchars($lookupTicket['seat'] ?? '—'),
            'Price'        => '$' . number_format(parseTicketPrice($lookupTicket['price'] ?? 0), 2),
            'Status'       => statusBadge($lookupTicket['status'] ?? 'unknown'),
          ];
          foreach ($rows as $label => $val): ?>
          <div class="flex justify-between items-center py-2">
            <span class="text-gray-500"><?= $label ?></span>
            <span><?= $val ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if (strtolower($lookupTicket['status'] ?? '') !== 'cancelled'): ?>
        <form method="POST" class="mt-4 cancel-ticket-form" data-ticket-id="<?= htmlspecialchars($lookupTicket['ticket_id']) ?>">
          <input type="hidden" name="action"    value="cancel_ticket">
          <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($lookupTicket['ticket_id']) ?>">
          <button type="submit" onclick="return confirm('Cancel this ticket? This cannot be undone.')"
            class="w-full h-9 bg-red-600/80 hover:bg-red-600 rounded-lg text-sm font-semibold transition">
            Cancel This Ticket
          </button>
        </form>
        <?php endif; ?>
        <?php else: ?>
        <p class="text-red-400 text-sm bg-red-950 border border-red-800 rounded-lg px-4 py-3">
          No ticket found for "<strong><?= htmlspecialchars($confLookup) ?></strong>".
        </p>
        <?php endif; ?>
      <?php else: ?>
      <p class="text-gray-600 text-sm">Enter a confirmation code above to find any ticket.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden">
    <div class="p-5 border-b border-gray-700 flex flex-wrap items-center justify-between gap-4">
      <h2 class="text-lg font-bold">All Tickets 🎟️</h2>
      <form method="GET" class="flex gap-2 flex-wrap">
        <input type="hidden" name="tab" value="tickets">
        <input type="text" name="tsearch" value="<?= htmlspecialchars($ticketSearch) ?>"
          placeholder="Search flight, route, name, code…" class="field h-9 w-64">
        <button type="submit" class="h-9 px-4 bg-blue-600 hover:bg-blue-500 rounded-lg text-sm font-semibold transition">Search</button>
        <?php if ($ticketSearch): ?>
        <a href="?tab=tickets" class="h-9 px-3 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-semibold flex items-center transition">Clear</a>
        <?php endif; ?>
      </form>
    </div>
    <div class="table-wrap">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-700/50 text-gray-400 text-sm">
            <th class="text-left px-4 py-3">Confirmation</th>
            <th class="text-left px-4 py-3">Passenger</th>
            <th class="text-left px-4 py-3">Flight #</th>
            <th class="text-left px-4 py-3">Flight ID</th>
            <th class="text-left px-4 py-3">Route</th>
            <th class="text-left px-4 py-3">Destination</th>
            <th class="text-left px-4 py-3">Seat</th>
            <th class="text-left px-4 py-3">Price</th>
            <th class="text-left px-4 py-3">Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($filteredTickets as $t):
          $fid = $t['flight_id'] ?? '';
          $f   = getFlightInfo($fid, $flightMap, $api) ?? [];
          $isCancelled = strtolower($t['status'] ?? '') === 'cancelled';
          $passenger   = trim(($t['name_first'] ?? '') . ' ' . ($t['name_last'] ?? ''));
          if (!$passenger) $passenger = '—';
          $safeP = '$' . number_format(parseTicketPrice($t['price'] ?? '0'), 2);
          $destination = $t['destination'] ?? '—';
          $route = 'SMN → ' . strtoupper($destination);
        ?>
        <tr class="border-t border-gray-700 hover:bg-gray-700/40 transition <?= $isCancelled ? 'cancelled-row' : '' ?>"
            data-ticket-id="<?= htmlspecialchars($t['ticket_id']) ?>">
          <td class="px-4 py-3">
            <code class="text-xs bg-gray-800 px-2 py-1 rounded text-blue-300 font-mono"><?= htmlspecialchars($t['confirmation_code'] ?? '—') ?></code>
          </td>
          <td class="px-4 py-3 text-gray-300"><?= htmlspecialchars($passenger) ?></td>
          <td class="px-4 py-3 font-semibold text-xs"><?= htmlspecialchars($f['flightNumber'] ?? '—') ?></td>
          <td class="px-4 py-3 text-xs">
            <span class="inline-flex items-center gap-1">
              <code class="bg-gray-800 px-1.5 py-0.5 rounded text-gray-400 font-mono"><?= htmlspecialchars($fid ?: '—') ?></code>
              <?php if ($fid): ?>
              <span class="copy-btn text-gray-500" onclick="copyToClipboard('<?= htmlspecialchars(addslashes($fid), ENT_QUOTES) ?>', this)" title="Copy flight ID">📋</span>
              <?php endif; ?>
            </span>
          </td>
          <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap"><?= $route ?></td>
          <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap"><?= htmlspecialchars(strtoupper($destination)) ?></td>
          <td class="px-4 py-3 text-gray-400 text-xs"><?= htmlspecialchars($t['seat'] ?? '—') ?></td>
          <td class="px-4 py-3 text-gray-300 text-xs font-mono"><?= $safeP ?></td>
          <td class="px-4 py-3 status-cell">
            <?php if (!$isCancelled): ?>
            <form method="POST" class="inline cancel-ticket-form" data-ticket-id="<?= htmlspecialchars($t['ticket_id']) ?>">
              <input type="hidden" name="action"    value="cancel_ticket">
              <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($t['ticket_id']) ?>">
              <button type="submit"
                onclick="return confirm('Cancel ticket <?= htmlspecialchars(addslashes($t['confirmation_code'] ?? '')) ?>?')"
                class="text-xs text-red-400 hover:text-red-300 font-semibold transition">
                Cancel
              </button>
            </form>
            <?php else: ?>
            <span class="text-xs text-gray-700">Cancelled</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($filteredTickets)): ?>
        <tr><td colspan="9" class="px-5 py-10 text-center text-gray-600">No tickets found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="px-5 py-4 border-t border-gray-700 flex items-center justify-between">
      <span class="text-xs text-gray-500">Page <?= $ticketPage ?> of <?= $totalTicketPages ?></span>
      <div class="flex gap-2">
        <?php if ($ticketPage > 1): ?>
        <a href="?tab=tickets&tsearch=<?= urlencode($ticketSearch) ?>&tpage=<?= $ticketPage - 1 ?>" class="px-3 py-1 bg-gray-800 rounded text-sm hover:bg-gray-700">Previous</a>
        <?php endif; ?>
        <?php if ($ticketPage < $totalTicketPages): ?>
        <a href="?tab=tickets&tsearch=<?= urlencode($ticketSearch) ?>&tpage=<?= $ticketPage + 1 ?>" class="px-3 py-1 bg-blue-600 rounded text-sm hover:bg-blue-500">Next</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php endif; ?>

</main>

<script>
const periodLabels = { day:'Today', week:'This Week', month:'This Month', year:'This Year', all:'All Time' };

function setPeriod(key) {
  document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('period-active'));
  const btn = document.getElementById('period-' + key);
  if (!btn) return;
  btn.classList.add('period-active');
  document.getElementById('stat-tickets').textContent =
    parseInt(btn.dataset.tickets || 0).toLocaleString('en-US');
  document.getElementById('stat-profit').textContent =
    '$' + parseFloat(btn.dataset.profit || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
  const lbl = document.getElementById('stat-period-label');
  if (lbl) lbl.textContent = 'Active tickets · ' + (periodLabels[key] || key);
}
document.addEventListener('DOMContentLoaded', () => setPeriod('day'));

function copyToClipboard(text, el) {
  navigator.clipboard.writeText(text).then(() => {
    if (!el) return;
    const original = el.textContent;
    el.textContent = '✓';
    setTimeout(() => { el.textContent = original; }, 1200);
  });
}

function autoFormatPhone(el) {
  let d = el.value.replace(/\D/g, '');
  if (d.length > 11) d = d.slice(0, 11);
  if (d.length === 0) { el.value = ''; return; }
  if (d.length <= 3)  { el.value = '(' + d; return; }
  if (d.length <= 6)  { el.value = '(' + d.slice(0,3) + ') ' + d.slice(3); return; }
  if (d.length <= 10) { el.value = '(' + d.slice(0,3) + ') ' + d.slice(3,6) + '-' + d.slice(6); return; }
  el.value = '+' + d[0] + ' (' + d.slice(1,4) + ') ' + d.slice(4,7) + '-' + d.slice(7);
}

function checkPw(val) {
  const hint = document.getElementById('cpw-hint');
  if (!hint) return;
  if (val.length <= 10) { hint.className = 'text-xs mt-1 text-red-400'; hint.textContent = 'Weak password (10 characters or fewer)'; }
  else if (val.length <= 17) { hint.className = 'text-xs mt-1 text-yellow-400'; hint.textContent = 'Medium password'; }
  else { hint.className = 'text-xs mt-1 text-emerald-400'; hint.textContent = 'Strong password'; }
}

function checkCustomerPw(val) {
  const hint = document.getElementById('cpw-hint-customer');
  if (!hint) return;
  if (val.length <= 10) { hint.className = 'text-xs mt-1 text-red-400'; hint.textContent = 'Weak password (10 characters or fewer)'; }
  else if (val.length <= 17) { hint.className = 'text-xs mt-1 text-yellow-400'; hint.textContent = 'Medium password'; }
  else { hint.className = 'text-xs mt-1 text-emerald-400'; hint.textContent = 'Strong password'; }
}

function showFlash(msg) {
  const el = document.getElementById('flashMsg');
  const txt = document.getElementById('flashMsgText');
  document.getElementById('errorMsgBox').classList.add('hidden');
  txt.textContent = msg;
  el.classList.remove('hidden');
}
function showError(msg) {
  const el = document.getElementById('errorMsgBox');
  const txt = document.getElementById('errorMsgText');
  document.getElementById('flashMsg').classList.add('hidden');
  txt.textContent = msg;
  el.classList.remove('hidden');
}

const takenSeats   = <?= json_encode($takenSeatsByFlight) ?>;
const dbTakenSeats = <?php
  $dbTaken = [];
  foreach ($allTickets as $t) {
    if (strtolower($t['status'] ?? '') === 'cancelled') continue;
    $fid = $t['flight_id'] ?? '';
    if (!$fid) continue;
    $dbTaken[$fid][] = strtoupper($t['seat'] ?? '');
  }
  echo json_encode($dbTaken);
?>;

async function onFlightBlur(val) {
  const infoEl  = document.getElementById('flightInfo');
  const carryOn = document.getElementById('tCarryOn').value;
  const checked = document.getElementById('tChecked').value;

  if (!val) {
    infoEl.classList.add('hidden');
    document.getElementById('priceSeatBase').textContent = '$0.00';
    document.getElementById('priceBagFees').textContent  = '$0.00';
    document.getElementById('priceTotal').textContent    = '$0.00';
    document.getElementById('tPrice').value       = '0';
    document.getElementById('tDestination').value = '';
    document.getElementById('tBags').value        = '0';
    return;
  }

  try {
    const res  = await fetch(`flight_lookup.php?flight_id=${encodeURIComponent(val)}&carry_on=${carryOn}&checked=${checked}`);
    const data = await res.json();

    if (data.error) {
      infoEl.textContent = data.error;
      infoEl.className   = 'text-xs mt-1 text-red-400';
      infoEl.classList.remove('hidden');
      document.getElementById('priceSeatBase').textContent = '$0.00';
      document.getElementById('priceBagFees').textContent  = '$0.00';
      document.getElementById('priceTotal').textContent    = '$0.00';
      document.getElementById('tPrice').value       = '0';
      document.getElementById('tDestination').value = '';
      return;
    }

    infoEl.textContent = `${data.flightNumber} · ${data.airline} → ${data.destination}`;
    infoEl.className   = 'text-xs mt-1 text-emerald-400';
    infoEl.classList.remove('hidden');

    document.getElementById('priceSeatBase').textContent = '$' + data.seatPrice.toFixed(2);
    document.getElementById('priceBagFees').textContent  = '$' + data.bagCost.toFixed(2);
    document.getElementById('priceTotal').textContent    = '$' + data.total.toFixed(2);
    document.getElementById('tPrice').value       = data.total;
    document.getElementById('tDestination').value = data.destination;
    document.getElementById('tBags').value        = (parseInt(carryOn) + parseInt(checked));
  } catch (e) {
    infoEl.textContent = 'Could not look up flight.';
    infoEl.className   = 'text-xs mt-1 text-red-400';
    infoEl.classList.remove('hidden');
  }
}

function onSeatBlur(val) {
  const errEl    = document.getElementById('seatErr');
  const flightId = document.getElementById('tFlightId').value.trim();
  if (!val) { errEl.classList.add('hidden'); return; }
  if (!/^([1-9]|10)[A-Ia-i]$/.test(val)) {
    errEl.textContent = 'Invalid seat. Must be row 1–10, column A–I (Ex. 5A, 10I).';
    errEl.classList.remove('hidden'); return;
  }
  const apiTaken = takenSeats[flightId] || [];
  const dbTaken  = dbTakenSeats[flightId] || [];
  const seatUp   = val.toUpperCase();
  if (apiTaken.includes(seatUp) || dbTaken.includes(seatUp)) {
    errEl.textContent = 'Seat ' + seatUp + ' is already taken on this flight.';
    errEl.classList.remove('hidden'); return;
  }
  errEl.classList.add('hidden');
}

document.getElementById('ticketForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const errEl = document.getElementById('seatErr');
  if (!errEl.classList.contains('hidden')) {
    alert('Please fix the highlighted errors before submitting.');
    return;
  }
  const formData = new FormData(this);
  formData.append('action', 'create_ticket');
  formData.append('ajax', '1');
  try {
    const res  = await fetch(window.location.pathname + '?tab=tickets', { method:'POST', body:formData });
    const data = await res.json();
    if (data.success) {
      if (data.redirect) { window.location.href = data.redirect; }
      else {
        showFlash(data.message);
        this.reset();
        document.getElementById('flightInfo').classList.add('hidden');
        document.getElementById('priceSeatBase').textContent = '$0.00';
        document.getElementById('priceBagFees').textContent  = '$0.00';
        document.getElementById('priceTotal').textContent    = '$0.00';
        setTimeout(() => window.location.reload(), 900);
      }
    } else { showError(data.message || 'Something went wrong.'); }
  } catch (err) { showError('Something went wrong. Please try again.'); }
});

document.querySelectorAll('.cancel-ticket-form').forEach(form => {
  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    if (!confirm('Cancel this ticket? This cannot be undone.')) return;
    const formData = new FormData(this);
    formData.append('ajax', '1');
    try {
      const res  = await fetch(window.location.pathname + '?tab=tickets', { method:'POST', body:formData });
      const data = await res.json();
      if (data.success) {
        showFlash(data.message);
        const tid = this.dataset.ticketId;
        const row = document.querySelector(`tr[data-ticket-id="${tid}"]`);
        if (row) {
          row.classList.add('cancelled-row');
          const sc = row.querySelector('.status-cell');
          if (sc) sc.innerHTML = '<span class="text-xs text-gray-700">Cancelled</span>';
        }
      } else { showError(data.message || 'Something went wrong.'); }
    } catch (err) { showError('Something went wrong. Please try again.'); }
  });
});

document.querySelectorAll('.update-customer-form').forEach(form => {
  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('ajax', '1');
    try {
      const res  = await fetch(window.location.pathname + '?tab=customers', { method:'POST', body:formData });
      const data = await res.json();
      if (data.success) {
        showFlash(data.message);
        setTimeout(() => window.location.href = '?tab=customers', 900);
      } else { showError(data.message || 'Something went wrong.'); }
    } catch (err) { showError('Something went wrong. Please try again.'); }
  });
});

document.querySelectorAll('.delete-customer-form').forEach(form => {
  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('ajax', '1');
    try {
      const res  = await fetch(window.location.pathname + '?tab=customers', { method:'POST', body:formData });
      const data = await res.json();
      if (data.success) {
        showFlash(data.message);
        const row = this.closest('tr');
        if (row) row.remove();
      } else { showError(data.message || 'Something went wrong.'); }
    } catch (err) { showError('Something went wrong. Please try again.'); }
  });
});

document.querySelectorAll('.delete-admin-form').forEach(form => {
  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('ajax', '1');
    try {
      const res  = await fetch(window.location.pathname + '?tab=admins', { method:'POST', body:formData });
      const data = await res.json();
      if (data.success) {
        showFlash(data.message);
        const row = this.closest('tr');
        if (row) row.remove();
      } else { showError(data.message || 'Something went wrong.'); }
    } catch (err) { showError('Something went wrong. Please try again.'); }
  });
});
</script>

</body>
</html>