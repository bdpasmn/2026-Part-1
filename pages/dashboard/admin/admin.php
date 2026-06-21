<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../../api/key.php';
require_once '../../../api/api.php';
require_once '../../../database/db.php';
/** 
$_SESSION['user_id'] = 25;
$_SESSION['role'] = 'customer';

$stmt = $pdo->prepare('SELECT * FROM "Users" WHERE user_id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dbUser) {
    die("User not found.");
}

if (strtolower($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: ../../../index.php');
    exit;
}
*/
$sessionUserId = $_SESSION['user_id'] ?? null;

if (!$sessionUserId) {
    header('Location: ../../../index.php');
    exit;
}

$selfStmt = $pdo->prepare('SELECT * FROM "Users" WHERE user_id = ? LIMIT 1');
$selfStmt->execute([$sessionUserId]);
$selfUser = $selfStmt->fetch(PDO::FETCH_ASSOC);

if (!$selfUser) {
    header('Location: ../../../index.php');
    exit;
}

if (($selfUser['role'] ?? '') !== 'Admin') {
    header('Location: ../../../index.php');
    exit;
}
$selfName = trim(($selfUser['first_name'] ?? '') . ' ' . ($selfUser['last_name'] ?? ''));
if ($selfName === '') $selfName = 'Admin';

$api = new AirportsAPI(AIRPORTS_API_KEY);

$airportResults = $api->getAirports();
$airports = $airportResults['airports'] ?? [];

$airportLookup = [];
foreach ($airports as $airport) {
    $airportLookup[strtolower($airport['shortName'])] = $airport;
}

$allFlightsData = $api->getAllFlights();
$allFlights     = $allFlightsData['flights'] ?? [];


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

$flightMap = [];
foreach ($allFlights as $f) {
$fid = $f['flight_id']
    ?? $f['flightId']
    ?? $f['id']
    ?? '';    
    if ($fid) $flightMap[$fid] = $f;
}

$ticketsStmt = $pdo->query('SELECT * FROM "Tickets"');
$allTickets  = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);

$usersStmt = $pdo->query('SELECT * FROM "Users" WHERE LOWER(role) = \'customer\' ORDER BY user_id ASC');
$allUsers  = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$daySec   = 86400;
$weekSec  = 7  * $daySec;
$monthSec = 30 * $daySec;
$yearSec  = 365 * $daySec;

$ticketStats = ['day' => 0, 'week' => 0, 'month' => 0, 'year' => 0, 'all' => 0];
$profitStats = ['day' => 0.0, 'week' => 0.0, 'month' => 0.0, 'year' => 0.0, 'all' => 0.0];

$utc = new DateTimeZone('UTC');
$nowUtc = new DateTime('now', $utc);
$now = $nowUtc->getTimestamp();

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

function validSeat(string $seat): bool {
    return (bool)preg_match('/^([1-9]|10)[A-Ia-i]$/', $seat);
}

function isSeatTakenInDb(string $flightId, string $seat, array $allTickets): bool {
    $seat = strtoupper($seat);
    foreach ($allTickets as $t) {
        if (strtolower($t['status'] ?? '') === 'cancelled') continue;
        if (($t['flight_id'] ?? '') !== $flightId) continue;
        if (strtoupper($t['seat'] ?? '') === $seat) return true;
    }
    return false;
}

function isOnNoFlyList(string $fn, string $ln, array $noFlyList): bool {
    $fn = strtolower(trim($fn));
    $ln = strtolower(trim($ln));
    foreach ($noFlyList as $nfl) {
        if ($nfl['first'] === $fn && $nfl['last'] === $ln) return true;
    }
    return false;
}

function formatPhone(string $raw): string {
    $d = preg_replace('/\D/', '', $raw);
    if (strlen($d) === 10) return '(' . substr($d,0,3) . ') ' . substr($d,3,3) . '-' . substr($d,6);
    if (strlen($d) === 11 && $d[0] === '1') return '+1 (' . substr($d,1,3) . ') ' . substr($d,4,3) . '-' . substr($d,7);
    return $raw;
}

/**
 * True if $raw contains any alphabetic letters. Used to reject phone numbers
 * that have letters mixed in (e.g. "555-CALL-NOW").
 */
function phoneHasLetters(string $raw): bool {
    return (bool)preg_match('/[A-Za-z]/', $raw);
}

/**
 * Minimal structural email check: must contain '@' and '.' in a sane order
 * (something@something.something), in addition to filter_var's check.
 */
function isValidEmail(string $email): bool {
    if ($email === '') return false;
    if (!str_contains($email, '@') || !str_contains($email, '.')) return false;
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function fmtTs($ts): string {
    if (!$ts) return '—';
    return is_numeric($ts) ? date('M j, Y H:i', (int)$ts) : date('M j, Y H:i', strtotime((string)$ts));
}

function statusBadge(string $status): string {
    $cls = match(strtolower($status)) {
        'active'               => 'badge-active',
        'cancelled','refunded' => 'badge-cancelled',
        default                => 'badge-other',
    };
    return "<span class=\"badge {$cls}\">" . htmlspecialchars(ucfirst($status)) . "</span>";
}

function flightDestination(array $f, array $airportLookup): string {

    $code =
        $f['departingTo']
        ?? $f['landingAt']
        ?? $f['destination']
        ?? $f['arrivalCode']
        ?? $f['arrival']
        ?? '';

    if (!$code) {
        return '—';
    }

    $airport = $airportLookup[strtolower($code)] ?? null;

    if ($airport) {
        $city = $airport['city']
             ?? $airport['cityName']
             ?? $airport['location']
             ?? '';

        if ($city !== '') {
            return $city . ' (' . strtoupper($code) . ')';
        }
    }

    return strtoupper($code);
}

/**
 * Resolve a ticket's destination string the exact same way it's resolved
 * for display (flightDestination()): pull the destination code off the
 * flight record and pass it through the airport lookup. Falls back to the
 * raw posted destination only if the flight can't be found at all.
 */
function resolveTicketDestination(?array $flight, array $airportLookup, string $postedDestination): string {
    if ($flight) {
        return flightDestination($flight, $airportLookup);
    }
    return strtoupper(trim($postedDestination));
}

$updateMsg = null;
$errorMsg  = null;
$activeTab = $_GET['tab'] ?? 'overview';

$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'create_customer') {
        $fn     = trim($_POST['first_name']  ?? '');
        $mn     = trim($_POST['middle_name'] ?? '');
        $ln     = trim($_POST['last_name']   ?? '');
        $email  = trim($_POST['email']       ?? '');
        $phone  = trim($_POST['phone']       ?? '');
        $pw     = trim($_POST['password']    ?? '');
        $dob    = trim($_POST['dob']         ?? '');
        $sex    = trim($_POST['sex']         ?? '');
        $street = trim($_POST['street']      ?? '');
        $city   = trim($_POST['city']        ?? '');
        $state  = trim($_POST['state']       ?? '');
        $zip    = trim($_POST['zip']         ?? '');
        $country= trim($_POST['country']     ?? '');

      if (!$fn || !$ln || !$email) {
          $errorMsg = 'First name, last name, and email are required.';
      } elseif (!isValidEmail($email)) {
          $errorMsg = 'Please enter a valid email address (must contain "@" and ".").';
      } elseif ($phone !== '' && phoneHasLetters($phone)) {
          $errorMsg = 'Phone number cannot contain letters.';
      } elseif (strlen($pw) < 8) {
          $errorMsg = 'Password must be at least 8 characters.';
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
            $updateMsg = "Customer {$fn} {$ln} created successfully.";
            $usersStmt = $pdo->query('SELECT * FROM "Users" WHERE LOWER(role) = \'customer\' ORDER BY user_id ASC');
            $allUsers  = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
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
            $phoneFormatted = $phone ? formatPhone($phone) : null;

            $upd = $pdo->prepare(
                'UPDATE "Users"
                 SET email=?, phone=?, street_address=?, city=?, state=?, zip_code=?, country=?
                 WHERE user_id=? AND LOWER(role)=\'customer\''
            );
            $upd->execute([$email, $phoneFormatted, $street, $city, $state, $zip, $country, $uid]);
            $updateMsg = 'Customer updated successfully.';

            $usersStmt = $pdo->query('SELECT * FROM "Users" WHERE LOWER(role) = \'customer\' ORDER BY user_id ASC');
            $allUsers  = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            if ($errorMsg) {
                echo json_encode(['success' => false, 'message' => $errorMsg]);
            } else {
                echo json_encode(['success' => true, 'message' => $updateMsg]);
            }
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
            $errorMsg = 'Invalid seat. Must be row 1–10 and column A–I (e.g. 5A, 10I).';
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
                    echo json_encode(['success' => true, 'message' => $updateMsg, 'confirmation_code' => $code]);
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

    if ($_POST['action'] === 'delete_unhashed_admins') {
        $delStmt = $pdo->query('SELECT user_id, password FROM "Users" WHERE LOWER(role) = \'admin\'');
        $adminsAll = $delStmt->fetchAll(PDO::FETCH_ASSOC);
        $deleted = 0;
        foreach ($adminsAll as $a) {
            $pw = $a['password'] ?? '';
            if (substr($pw, 0, 4) !== '$2y$' && substr($pw, 0, 4) !== '$2b$') {
                $del = $pdo->prepare('DELETE FROM "Users" WHERE user_id = ? AND LOWER(role) = \'admin\'');
                $del->execute([$a['user_id']]);
                $deleted++;
            }
        }
        $updateMsg = "Deleted {$deleted} admin account(s) with unhashed passwords.";
        $activeTab = 'customers';
    }
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Inter', sans-serif; background: #0f1117; color: #e2e8f0; }

  .tab-active   { background: #2563eb; color: #fff; }
  .tab-inactive { color: #9ca3af; }
  .tab-inactive:hover { color: #fff; background: #374151; }
  .period-btn { transition: all .15s; }
  .period-active { background: #2563eb !important; color: #fff !important; border-color: #2563eb !important; }

  .badge { display:inline-flex; align-items:center; padding:2px 10px; border-radius:9999px; font-size:.72rem; font-weight:600; white-space:nowrap; }
  .badge-active    { background:rgba(16,185,129,.15); color:#34d399; border:1px solid rgba(16,185,129,.3); }
  .badge-cancelled { background:rgba(239,68,68,.15);  color:#f87171; border:1px solid rgba(239,68,68,.3); }
  .badge-other     { background:rgba(99,102,241,.15); color:#a5b4fc; border:1px solid rgba(99,102,241,.3); }

  tbody tr { border-top:1px solid #1f2937; transition:background .12s; }
  tbody tr:hover { background:rgba(55,65,81,.45); }
  .cancelled-row { opacity:.5; }

  .field { width:100%; height:2.375rem; background:#1f2937; border:1px solid #374151; border-radius:.5rem;
           padding:0 .875rem; font-size:.875rem; color:#f1f5f9; outline:none; transition:border-color .15s; }
  .field:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.2); }
  .field-error { border-color:#ef4444 !important; }
  select.field { background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%236b7280'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right .6rem center; background-size:1.25rem; padding-right:2.5rem; appearance:none; }

  .stat-card { background:#161b27; border:1px solid #1f2937; border-radius:.75rem; padding:1.5rem; }
  .hint { font-size:.7rem; color:#6b7280; margin-top:.2rem; }

  ::-webkit-scrollbar { width:6px; height:6px; }
  ::-webkit-scrollbar-track { background:#0f1117; }
  ::-webkit-scrollbar-thumb { background:#374151; border-radius:3px; }
  .table-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; }
  .section-label { font-size:.65rem; text-transform:uppercase; letter-spacing:.08em; color:#6b7280; font-weight:600; padding:.5rem 0 .2rem; border-top:1px solid #1f2937; margin-top:.5rem; }
  .section-label:first-child { border-top:none; margin-top:0; }
</style>
</head>
<body class="min-h-screen">
<?php include_once __DIR__ . '/../../../components/nav.php'; ?>
<main class="max-w-7xl mx-auto p-4 sm:p-6 space-y-5">

  <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
    <p class="text-xs uppercase tracking-widest text-blue-500 font-semibold mb-1">Airport BDPA</p>
    <h1 class="text-3xl font-extrabold">Admin Dashboard</h1>
    <p class="text-gray-400 text-sm mt-1">Manage customers, tickets, and airport operations.</p>
  </div>

  <div id="flashMsg" class="<?= $updateMsg ? '' : 'hidden' ?> bg-emerald-950 border border-emerald-700 rounded-lg px-5 py-3 text-emerald-400 text-sm flex items-center gap-2">
    <span>✓</span><span id="flashMsgText"><?= htmlspecialchars($updateMsg ?? '') ?></span>
  </div>
  <div id="errorMsgBox" class="<?= $errorMsg ? '' : 'hidden' ?> bg-red-950 border border-red-700 rounded-lg px-5 py-3 text-red-400 text-sm flex items-center gap-2">
    <span>⚠</span><span id="errorMsgText"><?= htmlspecialchars($errorMsg ?? '') ?></span>
  </div>

  <div class="flex gap-1 bg-gray-900 border border-gray-800 rounded-xl p-1 flex-wrap">
    <?php
    $tabs = ['overview' => '📊 Overview', 'customers' => '👤 Customers', 'tickets' => '🎫 Tickets'];
    foreach ($tabs as $key => $label):
      $cls = ($activeTab === $key) ? 'tab-active' : 'tab-inactive';
    ?>
    <a href="?tab=<?= $key ?>" class="px-4 py-2 rounded-lg text-sm font-medium transition <?= $cls ?>"><?= $label ?></a>
    <?php endforeach; ?>
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
    <div class="stat-card">
      <p class="text-gray-500 text-xs uppercase tracking-wider font-semibold">Tickets Sold</p>
      <h2 class="text-4xl font-extrabold mt-3 tabular-nums" id="stat-tickets">—</h2>
      <p class="text-gray-600 text-xs mt-2" id="stat-period-label">Select a period above</p>
    </div>
    <div class="stat-card">
      <p class="text-gray-500 text-xs uppercase tracking-wider font-semibold">Gross Profit</p>
      <h2 class="text-3xl font-extrabold mt-3 tabular-nums text-emerald-400" id="stat-profit">—</h2>
      <p class="text-gray-600 text-xs mt-2">All tickets</p>
    </div>
    <div class="stat-card">
      <p class="text-gray-500 text-xs uppercase tracking-wider font-semibold">Total Customers</p>
      <h2 class="text-4xl font-extrabold mt-3 tabular-nums"><?= count($allUsers) ?></h2>
      <p class="text-gray-600 text-xs mt-2">Registered customer accounts</p>
    </div>
    <div class="stat-card">
      <p class="text-gray-500 text-xs uppercase tracking-wider font-semibold">Total Tickets</p>
      <h2 class="text-4xl font-extrabold mt-3 tabular-nums"><?= count($allTickets) ?></h2>
      <p class="text-gray-600 text-xs mt-2">All time, all statuses</p>
    </div>
  </div>

  <div class="grid md:grid-cols-3 gap-4">
    <a href="?tab=customers" class="bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-blue-600 hover:bg-gray-800/60 transition block group">
      <div class="text-2xl mb-3">👤</div>
      <h3 class="font-bold group-hover:text-blue-400 transition">Customers</h3>
      <p class="text-gray-500 text-sm mt-1">View, create, and modify customer accounts.</p>
    </a>
    <a href="?tab=tickets" class="bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-blue-600 hover:bg-gray-800/60 transition block group">
      <div class="text-2xl mb-3">🎫</div>
      <h3 class="font-bold group-hover:text-blue-400 transition">Tickets</h3>
      <p class="text-gray-500 text-sm mt-1">Create, cancel, and look up tickets.</p>
    </a>
    <a href="?tab=tickets&conf=" class="bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-blue-600 hover:bg-gray-800/60 transition block group">
      <div class="text-2xl mb-3">🔍</div>
      <h3 class="font-bold group-hover:text-blue-400 transition">Ticket Lookup</h3>
      <p class="text-gray-500 text-sm mt-1">Find any ticket by confirmation code.</p>
    </a>
  </div>

  <?php endif; ?>

  <?php if ($activeTab === 'customers'): ?>

  <?php
  $editUser = null;
  if (isset($_GET['edit']) && $_GET['edit'] !== '') {
    $editId = (string)$_GET['edit'];
    foreach ($allUsers as $u) {
      if ((string)($u['user_id'] ?? '') === $editId) { $editUser = $u; break; }
    }
  }

  $customerSearch = trim($_GET['csearch'] ?? '');
  $filteredUsers  = $allUsers;
  if ($customerSearch !== '') {
    $q = strtolower($customerSearch);
    $filteredUsers = array_filter($allUsers, fn($u) =>
      str_contains(strtolower(
        ($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '') . ' ' .
        ($u['email'] ?? '') . ' ' . ($u['phone'] ?? '') . ' ' .
        ($u['city'] ?? '') . ' ' . ($u['street_address'] ?? '') . ' ' .
        ($u['zip_code'] ?? '') . ' ' . ($u['country'] ?? '')
      ), $q)
    );
  }
$customersPerPage = 10;
$customerPage = max(1, (int)($_GET['cpage'] ?? 1));
$totalCustomersPages = max(1, ceil(count($filteredUsers) / $customersPerPage));

$filteredUsers = array_values($filteredUsers);

$filteredUsers = array_slice(
    $filteredUsers,
    ($customerPage - 1) * $customersPerPage,
    $customersPerPage
);
  ?>

  <div class="grid lg:grid-cols-2 gap-5">

    <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
      <h2 class="font-bold text-base mb-4">Create New Customer</h2>
      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="create_customer">

        <p class="section-label">Required</p>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-gray-400 mb-1">First Name <span class="text-red-400">*</span></label>
            <input type="text" name="first_name" required class="field">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Middle Name</label>
            <input type="text" name="middle_name" class="field">
          </div>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Last Name <span class="text-red-400">*</span></label>
          <input type="text" name="last_name" required class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Email <span class="text-red-400">*</span></label>
          <input type="email" name="email" required pattern="^[^\s@]+@[^\s@]+\.[^\s@]+$"
            title="Email must contain &quot;@&quot; and &quot;.&quot;" class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Password <span class="text-red-400">*</span></label>
          <input type="password" name="password" required minlength="8" id="cpw" class="field"
            oninput="checkPw(this.value)">
          <p class="hint">Minimum 8 characters</p>
          <p id="cpw-hint" class="text-xs mt-1 hidden"></p>
        </div>

        <p class="section-label">Optional</p>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Phone</label>
          <input type="tel" name="phone" placeholder="(555) 555-5555" class="field"
            inputmode="numeric" oninput="autoFormatPhone(this)">
          <p class="hint">Numbers only — no letters</p>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-gray-400 mb-1">Date of Birth</label>
            <input type="date" name="dob" class="field">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Sex</label>
            <select name="sex" class="field">
              <option value="">— select —</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
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

        <button type="submit" class="w-full h-10 bg-blue-600 hover:bg-blue-500 rounded-lg text-sm font-semibold transition mt-1">
          Create Customer
        </button>
      </form>
    </div>

    <?php if ($editUser): ?>
    <div class="bg-gray-900 border border-blue-700 rounded-xl p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-bold text-base">
          Edit — <?= htmlspecialchars(trim(($editUser['first_name'] ?? '') . ' ' . ($editUser['last_name'] ?? ''))) ?>
        </h2>
        <a href="?tab=customers" class="text-xs text-gray-500 hover:text-gray-300">✕ Close</a>
      </div>
      <form method="POST" class="space-y-3 update-customer-form">
        <input type="hidden" name="action" value="update_customer">
        <input type="hidden" name="user_id" value="<?= htmlspecialchars((string)($editUser['user_id'] ?? '')) ?>">
        <div>
          <label class="block text-xs text-gray-400 mb-1">Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($editUser['email'] ?? '') ?>"
            pattern="^[^\s@]+@[^\s@]+\.[^\s@]+$" title="Email must contain &quot;@&quot; and &quot;.&quot;" class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Phone</label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($editUser['phone'] ?? '') ?>"
            placeholder="(555) 555-5555" class="field" inputmode="numeric" oninput="autoFormatPhone(this)">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Street Address</label>
          <input type="text" name="street" value="<?= htmlspecialchars($editUser['street_address'] ?? '') ?>" class="field">
        </div>
        <div class="grid grid-cols-3 gap-2">
          <div>
            <label class="block text-xs text-gray-400 mb-1">City</label>
            <input type="text" name="city" value="<?= htmlspecialchars($editUser['city'] ?? '') ?>" class="field">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">State</label>
            <input type="text" name="state" value="<?= htmlspecialchars($editUser['state'] ?? '') ?>" class="field">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">ZIP</label>
            <input type="text" name="zip" value="<?= htmlspecialchars($editUser['zip_code'] ?? '') ?>" class="field">
          </div>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Country</label>
          <input type="text" name="country" value="<?= htmlspecialchars($editUser['country'] ?? '') ?>" class="field">
        </div>
        <button type="submit" class="w-full h-10 bg-blue-600 hover:bg-blue-500 rounded-lg text-sm font-semibold transition">
          Save Changes
        </button>
      </form>
    </div>
    <?php else: ?>
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 flex flex-col items-center justify-center text-center gap-2">
      <span class="text-3xl">✏️</span>
      <p class="text-gray-500 text-sm">Click <strong class="text-gray-400">Edit</strong> on a customer row to modify their details.</p>

  
    </div>
    <?php endif; ?>
  </div>

  <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
    <div class="p-4 border-b border-gray-800 flex flex-wrap items-center gap-3">
      <h2 class="font-bold flex-1">All Customers
        <span class="text-gray-600 font-normal text-sm ml-1">(<?= count($filteredUsers) ?>)</span>
      </h2>
      <form method="GET" class="flex gap-2 flex-wrap">
        <input type="hidden" name="tab" value="customers">
        <input type="text" name="csearch" value="<?= htmlspecialchars($customerSearch) ?>"
          placeholder="Search name, email, address…" class="field h-9 w-64">
        <button type="submit" class="h-9 px-4 bg-blue-600 hover:bg-blue-500 rounded-lg text-sm font-semibold transition">Search</button>
        <?php if ($customerSearch): ?>
        <a href="?tab=customers" class="h-9 px-3 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-semibold transition flex items-center">Clear</a>
        <?php endif; ?>
      </form>
    </div>
    <div class="table-wrap">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-800/60 text-gray-400 text-xs uppercase tracking-wider">
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
        <tr>
          <td class="px-5 py-3 text-gray-500 font-mono text-xs"><?= (int)($u['user_id'] ?? 0) ?></td>
          <td class="px-5 py-3 font-semibold"><?= htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?></td>
          <td class="px-5 py-3 text-gray-400"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
          <td class="px-5 py-3 text-gray-400"><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
          <td class="px-5 py-3 text-gray-400"><?= htmlspecialchars($u['city'] ?? '—') ?></td>
          <td class="px-5 py-3 text-gray-400"><?= htmlspecialchars($u['country'] ?? '—') ?></td>
          <td class="px-5 py-3">
            <a href="?tab=customers&edit=<?= urlencode((string)($u['user_id'] ?? '')) ?>"
               class="text-blue-400 hover:text-blue-300 transition text-xs font-semibold">Edit</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($filteredUsers)): ?>
        <tr><td colspan="7" class="px-5 py-10 text-center text-gray-600">No customers found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="px-4 py-3 border-t border-gray-800 flex justify-between items-center">
    <span class="text-xs text-gray-500">
        Page <?= $customerPage ?> of <?= $totalCustomersPages ?>
    </span>

    <div class="flex gap-2">
        <?php if ($customerPage > 1): ?>
            <a href="?tab=customers&csearch=<?= urlencode($customerSearch) ?>&cpage=<?= $customerPage - 1 ?>"
               class="px-3 py-1 bg-gray-800 rounded text-sm hover:bg-gray-700">
                Previous
            </a>
        <?php endif; ?>

        <?php if ($customerPage < $totalCustomersPages): ?>
            <a href="?tab=customers&csearch=<?= urlencode($customerSearch) ?>&cpage=<?= $customerPage + 1 ?>"
               class="px-3 py-1 bg-blue-600 rounded text-sm hover:bg-blue-500">
                Next
            </a>
        <?php endif; ?>
    </div>
</div>

</div>

  <?php endif; ?>

  <?php if ($activeTab === 'tickets'): ?>

  <?php
  $ticketSearch = trim($_GET['tsearch'] ?? '');
  $confLookup   = trim($_GET['conf']    ?? '');
  $lookupTicket = null;
  $lookupFlight = null;

  if ($confLookup !== '') {
    foreach ($allTickets as $t) {
      if (strtoupper($t['confirmation_code'] ?? '') === strtoupper($confLookup)) {
        $lookupTicket = $t;
        $lookupFlight = $flightMap[$t['flight_id'] ?? ''] ?? null;
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
        ($t['confirmation_code'] ?? '') . ' ' .
        ($t['name_last']   ?? '') . ' ' .
        ($t['name_first']  ?? '') . ' ' .
        ($t['flight_id']   ?? '') . ' ' .
        ($t['status']      ?? '') . ' ' .
        ($f['origin']       ?? '') . ' ' .
        ($f['destination']  ?? '') . ' ' .
        ($f['flightNumber'] ?? '')
      ), $q);
    });
  }
  $ticketsPerPage = 10;
$ticketPage = max(1, (int)($_GET['tpage'] ?? 1));
$totalTicketPages = max(1, ceil(count($filteredTickets) / $ticketsPerPage));

$filteredTickets = array_values($filteredTickets);

$filteredTickets = array_slice(
    $filteredTickets,
    ($ticketPage - 1) * $ticketsPerPage,
    $ticketsPerPage
);
  ?>

  <div class="grid lg:grid-cols-2 gap-5">

    <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
      <h2 class="font-bold text-base mb-3">Create New Ticket</h2>
      <form id="ticketForm" class="space-y-3">

        <p class="section-label">Flight &amp; Seat (required)</p>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Flight ID <span class="text-red-400">*</span></label>
          <input type="text" name="flight_id" id="tFlightId" required
            placeholder="e.g. 6a2b68204b07f8ac"
            class="field font-mono text-xs" onblur="onFlightBlur(this.value)">
          <p id="flightInfo" class="text-xs mt-1 hidden"></p>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Seat <span class="text-red-400">*</span></label>
          <input type="text" name="seat" id="tSeat" required maxlength="3"
            placeholder="e.g. 5A"
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

        <div class="bg-gray-800/60 border border-gray-700 rounded-lg p-4 text-sm space-y-1">
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
            <label class="block text-xs text-gray-400 mb-1">First Name <span class="text-red-400">*</span></label>
            <input type="text" name="name_first" required class="field">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Last Name <span class="text-red-400">*</span></label>
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
            <label class="block text-xs text-gray-400 mb-1">Sex</label>
            <select name="sex" class="field">
              <option value="">— select —</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Phone</label>
          <input type="tel" name="phone" placeholder="(555) 555-5555" class="field"
            inputmode="numeric" oninput="autoFormatPhone(this)">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Email</label>
          <input type="email" name="email" pattern="^[^\s@]+@[^\s@]+\.[^\s@]+$"
            title="Email must contain &quot;@&quot; and &quot;.&quot;" class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">User ID (If Customer)<span class="text-gray-600">(optional)</span></label>
          <input type="text" name="user_id" placeholder="Leave blank for guest" class="field">
        </div>

        <button type="submit" class="w-full h-10 bg-blue-600 hover:bg-blue-500 rounded-lg text-sm font-semibold transition mt-1">
          Create Ticket
        </button>
      </form>
    </div>

    <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
      <h2 class="font-bold text-base mb-4">Lookup by Confirmation Code</h2>
      <form method="GET" class="flex gap-2 mb-4">
        <input type="hidden" name="tab" value="tickets">
        <input type="text" name="conf" value="<?= htmlspecialchars($confLookup) ?>"
          placeholder="e.g. E5920205" class="field flex-1 h-9">
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
            'Flight ID'    => '<code class="bg-gray-800 px-2 py-0.5 rounded font-mono text-xs text-gray-300">' . htmlspecialchars($lookupTicket['flight_id'] ?? '—') . '</code>',
                'Route' => htmlspecialchars(
                'SMN → ' . strtoupper($lookupTicket['destination'] ?? '—')
            ),
       'Departure' => '<code class="bg-gray-800 px-2 py-0.5 rounded font-mono text-xs text-gray-300">' . 'SMN' . '</code>',            'Seat'         => htmlspecialchars($lookupTicket['seat'] ?? '—'),
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

  <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
    <div class="p-4 border-b border-gray-800 flex flex-wrap items-center gap-3">
      <h2 class="font-bold flex-1">All Tickets
        <span class="text-gray-600 font-normal text-sm ml-1">(<?= count($filteredTickets) ?>)</span>
      </h2>
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
          <tr class="bg-gray-800/60 text-gray-400 text-xs uppercase tracking-wider">
            <th class="text-left px-4 py-3">Confirmation</th>
            <th class="text-left px-4 py-3">Passenger</th>
            <th class="text-left px-4 py-3">Flight #</th>
            <th class="text-left px-4 py-3">Route</th>
            <th class="text-left px-4 py-3">Departure</th>
            <th class="text-left px-4 py-3">Seat</th>
            <th class="text-left px-4 py-3">Price</th>
            <th class="text-left px-4 py-3">Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($filteredTickets as $t):
          $f           = $flightMap[$t['flight_id'] ?? ''] ?? [];
          $depTs       = (int)($f['departureTime'] ?? 0);
          $isCancelled = strtolower($t['status'] ?? '') === 'cancelled';
          $passenger   = trim(($t['name_first'] ?? '') . ' ' . ($t['name_last'] ?? ''));
          if (!$passenger) $passenger = '—';
          $safeP = '$' . number_format(parseTicketPrice($t['price'] ?? '0'), 2);
        $destination = $t['destination'] ?? '—';
$route = 'SMN → ' . strtoupper($destination);

$dep = 'SMN';
        ?>
        <tr class="<?= $isCancelled ? 'cancelled-row' : '' ?>" data-ticket-id="<?= htmlspecialchars($t['ticket_id']) ?>">
          <td class="px-4 py-3">
            <code class="text-xs bg-gray-800 px-2 py-1 rounded text-blue-300 font-mono"><?= htmlspecialchars($t['confirmation_code'] ?? '—') ?></code>
          </td>
          <td class="px-4 py-3 text-gray-300"><?= htmlspecialchars($passenger) ?></td>
          <td class="px-4 py-3 font-semibold text-xs">
            <?= htmlspecialchars($f['flightNumber'] ?? ($t['flight_id'] ? '…' . substr($t['flight_id'], -5) : '—')) ?>
          </td>
          <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap"><?= $route ?></td>
          <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap"><?= $dep ?></td>
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
        <tr><td colspan="8" class="px-5 py-10 text-center text-gray-600">No tickets found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="px-4 py-3 border-t border-gray-800 flex justify-between items-center">
    <span class="text-xs text-gray-500">
        Page <?= $ticketPage ?> of <?= $totalTicketPages ?>
    </span>

    <div class="flex gap-2">
        <?php if ($ticketPage > 1): ?>
            <a href="?tab=tickets&tsearch=<?= urlencode($ticketSearch) ?>&tpage=<?= $ticketPage - 1 ?>"
               class="px-3 py-1 bg-gray-800 rounded text-sm hover:bg-gray-700">
                Previous
            </a>
        <?php endif; ?>

        <?php if ($ticketPage < $totalTicketPages): ?>
            <a href="?tab=tickets&tsearch=<?= urlencode($ticketSearch) ?>&tpage=<?= $ticketPage + 1 ?>"
               class="px-3 py-1 bg-blue-600 rounded text-sm hover:bg-blue-500">
                Next
            </a>
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

function autoFormatPhone(el) {
  let d = el.value.replace(/\D/g, '');
  if (d.length > 11) d = d.slice(0, 11);
  if (d.length === 0) { el.value = ''; return; }
  if (d.length <= 3)       { el.value = '(' + d; return; }
  if (d.length <= 6)       { el.value = '(' + d.slice(0,3) + ') ' + d.slice(3); return; }
  if (d.length <= 10)      { el.value = '(' + d.slice(0,3) + ') ' + d.slice(3,6) + '-' + d.slice(6); return; }
  el.value = '+' + d[0] + ' (' + d.slice(1,4) + ') ' + d.slice(4,7) + '-' + d.slice(7);
}

function checkPw(val) {
  const hint = document.getElementById('cpw-hint');
  if (!hint) return;

  if (val.length < 8) {
    hint.className = 'text-xs mt-1 text-amber-400';
    hint.textContent = 'Password must be at least 8 characters.';
    hint.classList.remove('hidden');
  } else {
    hint.className = 'text-xs mt-1 text-emerald-400';
    hint.textContent = '✓ Password looks good';
    hint.classList.remove('hidden');
  }
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

const knownFlights  = <?= json_encode(array_keys($flightMap)) ?>;
const takenSeats    = <?= json_encode($takenSeatsByFlight) ?>;
const dbTakenSeats  = <?php
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
  const infoEl = document.getElementById('flightInfo');
  const carryOn = document.getElementById('tCarryOn').value;
  const checked = document.getElementById('tChecked').value;

  if (!val) {
    infoEl.classList.add('hidden');
    document.getElementById('priceSeatBase').textContent = '$0.00';
    document.getElementById('priceBagFees').textContent = '$0.00';
    document.getElementById('priceTotal').textContent = '$0.00';
    document.getElementById('tPrice').value = '0';
    document.getElementById('tDestination').value = '';
    document.getElementById('tBags').value = '0';
    return;
  }

  try {
    const res = await fetch(`flight_lookup.php?flight_id=${encodeURIComponent(val)}&carry_on=${carryOn}&checked=${checked}`);
    const data = await res.json();

    if (data.error) {
      infoEl.textContent = data.error;
      infoEl.className = 'text-xs mt-1 text-red-400';
      infoEl.classList.remove('hidden');
      document.getElementById('priceSeatBase').textContent = '$0.00';
      document.getElementById('priceBagFees').textContent = '$0.00';
      document.getElementById('priceTotal').textContent = '$0.00';
      document.getElementById('tPrice').value = '0';
      document.getElementById('tDestination').value = '';
      return;
    }

    infoEl.textContent = `${data.flightNumber} · ${data.airline} → ${data.destination}`;
    infoEl.className = 'text-xs mt-1 text-emerald-400';
    infoEl.classList.remove('hidden');

    document.getElementById('priceSeatBase').textContent = '$' + data.seatPrice.toFixed(2);
    document.getElementById('priceBagFees').textContent = '$' + data.bagCost.toFixed(2);
    document.getElementById('priceTotal').textContent = '$' + data.total.toFixed(2);
    document.getElementById('tPrice').value = data.total;
    document.getElementById('tDestination').value = data.destination;
    document.getElementById('tBags').value = (parseInt(carryOn) + parseInt(checked));
  } catch (e) {
    infoEl.textContent = 'Could not look up flight.';
    infoEl.className = 'text-xs mt-1 text-red-400';
    infoEl.classList.remove('hidden');
  }
}

function onSeatBlur(val) {
  const errEl   = document.getElementById('seatErr');
  const flightId = document.getElementById('tFlightId').value.trim();
  if (!val) { errEl.classList.add('hidden'); return; }
  if (!/^([1-9]|10)[A-Ia-i]$/.test(val)) {
    errEl.textContent = 'Invalid seat. Must be row 1–10, column A–I (e.g. 5A, 10I).';
    errEl.classList.remove('hidden'); return;
  }
  const apiTaken = takenSeats[flightId] || [];
  const dbTaken  = dbTakenSeats[flightId] || [];
  const seatUp = val.toUpperCase();
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
    const res = await fetch(window.location.pathname + '?tab=tickets', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    if (data.success) {
      showFlash(data.message);
      this.reset();
      document.getElementById('flightInfo').classList.add('hidden');
      document.getElementById('priceSeatBase').textContent = '$0.00';
      document.getElementById('priceBagFees').textContent = '$0.00';
      document.getElementById('priceTotal').textContent = '$0.00';
      setTimeout(() => window.location.reload(), 900);
    } else {
      showError(data.message || 'Something went wrong.');
    }
  } catch (err) {
    showError('Something went wrong. Please try again.');
  }
});

document.querySelectorAll('.cancel-ticket-form').forEach(form => {
  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    if (!confirm('Cancel this ticket? This cannot be undone.')) return;

    const formData = new FormData(this);
    formData.append('ajax', '1');

    try {
      const res = await fetch(window.location.pathname + '?tab=tickets', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();
      if (data.success) {
        showFlash(data.message);
        const tid = this.dataset.ticketId;
        const row = document.querySelector(`tr[data-ticket-id="${tid}"]`);
        if (row) {
          row.classList.add('cancelled-row');
          const statusCell = row.querySelector('.status-cell');
          if (statusCell) statusCell.innerHTML = '<span class="text-xs text-gray-700">Cancelled</span>';
        }
      } else {
        showError(data.message || 'Something went wrong.');
      }
    } catch (err) {
      showError('Something went wrong. Please try again.');
    }
  });
});

document.querySelectorAll('.update-customer-form').forEach(form => {
  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('ajax', '1');

    try {
      const res = await fetch(window.location.pathname + '?tab=customers', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();
      if (data.success) {
        showFlash(data.message);
        setTimeout(() => window.location.href = '?tab=customers', 900);
      } else {
        showError(data.message || 'Something went wrong.');
      }
    } catch (err) {
      showError('Something went wrong. Please try again.');
    }
  });
});
</script>
</body>
</html>