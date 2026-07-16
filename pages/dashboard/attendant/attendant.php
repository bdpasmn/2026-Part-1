<?php
/**

 * 1. New role value stored in "Users".role is exactly 'Attendant'
 *    (matches the existing LOWER(role) = 'customer' / 'admin' pattern).
 * 2. "Users" needs a new nullable column to store which airline an
 *    attendant is assigned to:
  *
 *    Populated with one of the airline `name` values returned by
 *    $api->getAirlines() (e.g. "Delta", "American", "United",
 *    "Southwest", "Frontier", "Spirit") when an admin creates the
 *    attendant account (see admin.php / root.php additions).
 * 3. "Belongs to their airline" = $flight['airline'] matches the
 *    attendant's assigned airline (case-insensitive).
 * 4. "Customer information" for an attendant = the passengers who hold
 *    tickets on their airline's flights (derived from "Tickets" rows,
 *    optionally joined to "Users" by user_id when the ticket isn't a
 *    guest booking) — NOT the full customer roster. Attendants are
 *    view-only for this data (no edit/delete), per the spec.
 * 5. "Upcoming flight" (required to allow cancellation) = departFromSender
 *    timestamp is in the future AND flight status isn't already
 *    'past' or 'cancelled'.
 */

// Start session if one is not already active

if (session_status() === PHP_SESSION_NONE) session_start();
// Load API key, Airports API class, and database connection

require_once '../../../api/key.php';
require_once '../../../api/api.php';
require_once '../../../database/db.php';
// Get currently logged in user ID from session

$sessionUserId = $_SESSION['user_id'] ?? null;
// Redirect to login page if user is not logged in

if (!$sessionUserId) {
    header('Location: ../../../index.php');
    exit;
}
// Retrieve current user's information from database

$selfStmt = $pdo->prepare('SELECT * FROM "Users" WHERE user_id = ? LIMIT 1');
$selfStmt->execute([$sessionUserId]);
$selfUser = $selfStmt->fetch(PDO::FETCH_ASSOC);
// Redirect if user does not exist

if (!$selfUser) {
    header('Location: ../../../index.php');
    exit;
}
// Only attendants may access this page

if (($selfUser['role'] ?? '') !== 'Attendant') {
    header('Location: ../../../index.php');
    exit;
}
$selfName = trim(($selfUser['first_name'] ?? '') . ' ' . ($selfUser['last_name'] ?? ''));
if ($selfName === '') $selfName = 'Attendant';

// The airline this attendant is assigned to. If it's missing, there's
// nothing scoped for them to see — bail out to an error/contact-admin state.
$myAirline = trim($selfUser['airline'] ?? '');

// Create API object

$api = new AirportsAPI(AIRPORTS_API_KEY);
// Retrieve all airports and flights

$airportResults = $api->getAirports();
$airports = $airportResults['airports'] ?? [];

$airportLookup = [];
foreach ($airports as $airport) {
    $airportLookup[strtolower($airport['shortName'])] = $airport;
}

$allFlightsData = $api->getAllFlights();
$allFlights     = $allFlightsData['flights'] ?? [];

// Retrieve no-fly list from API and normalize names

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

// Store flights by flight ID for quick access, and build the set of
// flight IDs that belong to this attendant's airline.

$flightMap = [];
$myAirlineFlightIds = [];
foreach ($allFlights as $f) {
    $fid = $f['flight_id'] ?? $f['flightId'] ?? $f['id'] ?? '';
    if ($fid) $flightMap[$fid] = $f;

    $flightAirline = strtolower(trim($f['airline'] ?? ''));
    if ($fid && $myAirline !== '' && $flightAirline === strtolower($myAirline)) {
        $myAirlineFlightIds[$fid] = true;
    }
}

/**
 * Check whether a flight_id belongs to this attendant's assigned airline.
 */
function isMyAirlineFlight(string $fid, array $myAirlineFlightIds): bool {
    return isset($myAirlineFlightIds[$fid]);
}

// Retrieve all tickets, then filter down to only this attendant's airline.

$ticketsStmt = $pdo->query('SELECT * FROM "Tickets"');
$allTicketsRaw = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);

$allTickets = array_values(array_filter($allTicketsRaw, function ($t) use ($myAirlineFlightIds) {
    return isMyAirlineFlight($t['flight_id'] ?? '', $myAirlineFlightIds);
}));

// Build a "customers" view from the filtered ticket set: distinct
// passengers who have booked a ticket on this airline, joined against
// "Users" by user_id where available (view-only — no edit here).

$ticketUserIds = array_values(array_unique(array_filter(array_map(
    fn($t) => $t['user_id'] ?? null,
    $allTickets
))));

$linkedUsersById = [];
if (!empty($ticketUserIds)) {
    $placeholders = implode(',', array_fill(0, count($ticketUserIds), '?'));
    $uStmt = $pdo->prepare("SELECT * FROM \"Users\" WHERE user_id IN ($placeholders)");
    $uStmt->execute($ticketUserIds);
    foreach ($uStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $linkedUsersById[$u['user_id']] = $u;
    }
}

// One row per distinct passenger (by name+email) seen in this airline's tickets.

$myCustomers = [];
foreach ($allTickets as $t) {
    $key = strtolower(trim(($t['name_first'] ?? '') . '|' . ($t['name_last'] ?? '') . '|' . ($t['email'] ?? '')));
    if ($key === '||') continue;
    if (!isset($myCustomers[$key])) {
        $linked = isset($t['user_id']) ? ($linkedUsersById[$t['user_id']] ?? null) : null;
        $myCustomers[$key] = [
            'first_name' => $t['name_first'] ?? '',
            'last_name'  => $t['name_last']  ?? '',
            'email'      => $t['email']      ?? ($linked['email']      ?? ''),
            'phone'      => $t['phone_number'] ?? ($linked['phone']    ?? ''),
            'city'       => $linked['city']       ?? '',
            'country'    => $linked['country']    ?? '',
            'user_id'    => $t['user_id'] ?? null,
            'is_registered' => $linked !== null,
        ];
    }
}
$myCustomers = array_values($myCustomers);

$daySec = 129600; // 36 hours in seconds
$utc = new DateTimeZone('UTC');
$nowUtc = new DateTime('now', $utc);
$now = $nowUtc->getTimestamp();

/**
 * Safely convert price into float. (Shared pattern with admin.php)
 */
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

/**
 * Verify seat format is valid. (Rows 1-10, columns A-I — same as admin.php)
 */
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

function phoneHasLetters(string $raw): bool {
    return (bool)preg_match('/[A-Za-z]/', $raw);
}

function isValidEmail(string $email): bool {
    if ($email === '') return false;
    if (!str_contains($email, '@') || !str_contains($email, '.')) return false;
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
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
    $code = $f['departingTo'] ?? $f['landingAt'] ?? $f['destination'] ?? $f['arrivalCode'] ?? $f['arrival'] ?? '';
    if (!$code) return '—';
    $airport = $airportLookup[strtolower($code)] ?? null;
    if ($airport) {
        $city = $airport['city'] ?? $airport['cityName'] ?? $airport['location'] ?? '';
        if ($city !== '') return $city . ' (' . strtoupper($code) . ')';
    }
    return strtoupper($code);
}

function resolveTicketDestination(?array $flight, array $airportLookup, string $postedDestination): string {
    if ($flight) return flightDestination($flight, $airportLookup);
    return strtoupper(trim($postedDestination));
}

/**
 * A flight is "upcoming" (cancellable) if it hasn't already departed
 * and isn't already flagged past/cancelled.
 */
function isUpcomingFlight(?array $f, int $now): bool {
    if (!$f) return false;
    $status = strtolower($f['status'] ?? '');
    if (in_array($status, ['past', 'cancelled'])) return false;
    $depart = $f['departFromSender'] ?? null;
    if ($depart !== null && is_numeric($depart)) {
        // departFromSender is ms since epoch per API docs
        return ((int)$depart / 1000) > $now;
    }
    return true; // unknown timing — don't block the attendant
}

function getFlightInfo(string $fid, array $flightMap, AirportsAPI $api): ?array {
    if (!$fid) return null;
    if (isset($flightMap[$fid])) return $flightMap[$fid];
    $f = $api->getFlightById($fid);
    return $f ?: null;
}

$updateMsg = null;
$errorMsg  = null;
$activeTab = $_GET['tab'] ?? 'overview';
$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // CREATE TICKET (scoped to this attendant's airline)
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

        if (!isMyAirlineFlight($fid, $myAirlineFlightIds)) {
            $errorMsg = 'That flight does not belong to your assigned airline.';
        } elseif (!$fid) {
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
        } elseif ($uid !== '' && !ctype_digit($uid)) {
            $errorMsg = 'User ID must contain numbers only.';
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
            } elseif (isSeatTakenInDb($fid, $seat, $allTicketsRaw)) {
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
                $allTicketsRaw = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);
                $allTickets = array_values(array_filter($allTicketsRaw, function ($t) use ($myAirlineFlightIds) {
                    return isMyAirlineFlight($t['flight_id'] ?? '', $myAirlineFlightIds);
                }));

                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => $updateMsg,
                        'confirmation_code' => $code,
                        'redirect' => '/pages/booking/confirmation.php?confirmation=' . urlencode($code)
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

    // CANCEL TICKET (only for upcoming flights on this attendant's airline)
    if ($_POST['action'] === 'cancel_ticket') {
        $tid = $_POST['ticket_id'] ?? '';

        $findStmt = $pdo->prepare('SELECT * FROM "Tickets" WHERE ticket_id = ? LIMIT 1');
        $findStmt->execute([$tid]);
        $ticket = $findStmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket || !isMyAirlineFlight($ticket['flight_id'] ?? '', $myAirlineFlightIds)) {
            $errorMsg = 'That ticket does not belong to a flight on your assigned airline.';
        } else {
            $flightInfo = getFlightInfo($ticket['flight_id'] ?? '', $flightMap, $api);
            if (!isUpcomingFlight($flightInfo, $now)) {
                $errorMsg = 'This flight has already departed — the ticket can no longer be cancelled.';
            } else {
                $upd = $pdo->prepare('UPDATE "Tickets" SET status=? WHERE ticket_id=?');
                $upd->execute(['cancelled', $tid]);
                $updateMsg = 'Ticket cancelled.';

                $ticketsStmt = $pdo->query('SELECT * FROM "Tickets"');
                $allTicketsRaw = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);
                $allTickets = array_values(array_filter($allTicketsRaw, function ($t) use ($myAirlineFlightIds) {
                    return isMyAirlineFlight($t['flight_id'] ?? '', $myAirlineFlightIds);
                }));
            }
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            if ($errorMsg) {
                echo json_encode(['success' => false, 'message' => $errorMsg]);
            } else {
                echo json_encode(['success' => true, 'message' => $updateMsg]);
            }24
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
<title>Attendant Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Inter', sans-serif; background: #0f1117; color: #e2e8f0; }
  .tab-active { background: rgb(37 99 235); color: white; }
  .tab-inactive { color: rgb(156 163 175); }
  .tab-inactive:hover { color: white; background: rgb(55 65 81); }
  .badge { display:inline-flex; align-items:center; padding:2px 10px; border-radius:9999px; font-size:.72rem; font-weight:600; white-space:nowrap; }
  .badge-active    { background:rgba(16,185,129,.15); color:#34d399; border:1px solid rgba(16,185,129,.3); }
  .badge-cancelled { background:rgba(239,68,68,.15);  color:#f87171; border:1px solid rgba(239,68,68,.3); }
  .badge-other     { background:rgba(99,102,241,.15); color:#a5b4fc; border:1px solid rgba(99,102,241,.3); }
  tbody tr { border-top:1px solid #1f2937; transition:background .12s; }
  tbody tr:hover { background:rgba(55,65,81,.45); }
  .cancelled-row { opacity:.5; }
  .field { width:100%; height:2.5rem; background:#374151; border:1px solid #4b5563; border-radius:.5rem; padding:0 .875rem; font-size:.875rem; color:#fff; transition:all .15s; }
  .field:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.2); }
  .hint { font-size:.7rem; color:#6b7280; margin-top:.2rem; }
  ::-webkit-scrollbar { width:6px; height:6px; }
  ::-webkit-scrollbar-track { background:#0f1117; }
  ::-webkit-scrollbar-thumb { background:#374151; border-radius:3px; }
  .table-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; }
  .section-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .12em; color: rgb(96 165 250); font-weight: 700; padding: .75rem 0 .4rem; }
  .copy-btn { cursor:pointer; transition:color .12s; }
  .copy-btn:hover { color:#60a5fa; }
  .readonly-note { font-size:.7rem; color:#6b7280; }
</style>
</head>
<body class="bg-gray-900 min-h-screen text-white">
  <?php include_once __DIR__ . '/../../../components/nav.php'; ?>
<main class="max-w-7xl mx-auto p-4 sm:p-6 space-y-5">

  <div class="rounded-lg p-8 mb-6 bg-gradient-to-r from-slate-800 to-slate-900 border border-gray-700 shadow-lg">
      <p>
          <span class="tracking-[0.25em] text-sm text-blue-300">
              BDPA AIRPORTS - Attendant Dashboard
          </span>✈️
      </p>
      <h1 class="text-4xl font-bold mt-2"><?= htmlspecialchars($selfName) ?></h1>
      <p class="text-gray-400 mt-4">
        <?php if ($myAirline !== ''): ?>
          Assigned airline: <span class="text-blue-300 font-semibold"><?= htmlspecialchars($myAirline) ?></span>
        <?php else: ?>
          <span class="text-red-400">No airline assigned to your account yet — contact an admin.</span>
        <?php endif; ?>
      </p>
  </div>

  <div id="flashMsg" class="<?= $updateMsg ? '' : 'hidden' ?> bg-emerald-950 border border-emerald-700 rounded-lg px-5 py-3 text-emerald-400 text-sm flex items-center gap-2">
    <span>✓</span><span id="flashMsgText"><?= htmlspecialchars($updateMsg ?? '') ?></span>
  </div>
  <div id="errorMsgBox" class="<?= $errorMsg ? '' : 'hidden' ?> bg-red-950 border border-red-700 rounded-lg px-5 py-3 text-red-400 text-sm flex items-center gap-2">
    <span>⚠</span><span id="errorMsgText"><?= htmlspecialchars($errorMsg ?? '') ?></span>
  </div>

  <?php if ($myAirline === ''): ?>
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-8 text-center text-gray-400">
      Your account isn't assigned to an airline yet, so there's nothing to show. Please contact an administrator.
    </div>
  <?php else: ?>

  <div class="mb-6 bg-gray-800 border border-gray-700 rounded-lg p-1">
      <div class="flex gap-2 overflow-x-auto sm:overflow-visible whitespace-nowrap sm:flex-wrap">
          <?php
          $tabs = ['overview' => 'Overview', 'customers' => 'Customers', 'tickets' => 'Tickets'];
          foreach ($tabs as $key => $label):
              $cls = ($activeTab === $key) ? 'tab-active' : 'tab-inactive';
          ?>
          <a href="?tab=<?= $key ?>" class="px-4 py-2 rounded-md text-sm font-medium transition flex-shrink-0 <?= $cls ?>"><?= $label ?></a>
          <?php endforeach; ?>
      </div>
  </div>

  <?php if ($activeTab === 'overview'): ?>
  <div class="grid xl:grid-cols-3 md:grid-cols-2 gap-4">
      <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
        <p class="text-gray-400 text-sm">Active Tickets (<?= htmlspecialchars($myAirline) ?>)</p>
        <h2 class="text-4xl font-bold mt-2 tabular-nums">
          <?= count(array_filter($allTickets, fn($t) => strtolower($t['status'] ?? '') !== 'cancelled')) ?>
        </h2>
      </div>
      <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
        <p class="text-gray-400 text-sm">Flights on Your Airline</p>
        <h2 class="text-4xl font-bold mt-2 tabular-nums"><?= count($myAirlineFlightIds) ?></h2>
      </div>
      <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
        <p class="text-gray-400 text-sm">Passengers Seen</p>
        <h2 class="text-4xl font-bold mt-2 tabular-nums"><?= count($myCustomers) ?></h2>
      </div>
  </div>
  <div class="grid md:grid-cols-2 gap-4">
    <a href="?tab=customers" class="bg-gray-800 border border-gray-700 rounded-lg p-6 hover:border-blue-600 block group">
      <div class="text-2xl mb-3">👤</div>
      <h3 class="font-bold group-hover:text-blue-400 transition">Customers</h3>
      <p class="text-gray-500 text-sm mt-1">View passengers flying your airline.</p>
    </a>
    <a href="?tab=tickets" class="bg-gray-800 border border-gray-700 rounded-lg p-6 hover:border-blue-600 block group">
      <div class="text-2xl mb-3">🎫</div>
      <h3 class="font-bold group-hover:text-blue-400 transition">Tickets</h3>
      <p class="text-gray-500 text-sm mt-1">Create, cancel, and look up tickets.</p>
    </a>
  </div>
  <?php endif; ?>

  <?php if ($activeTab === 'customers'): ?>
  <?php
  $customerSearch = trim($_GET['csearch'] ?? '');
  $filteredCustomers = $myCustomers;
  if ($customerSearch !== '') {
    $q = strtolower($customerSearch);
    $filteredCustomers = array_filter($myCustomers, fn($u) => str_contains(strtolower(
      ($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '') . ' ' .
      ($u['email'] ?? '') . ' ' . ($u['phone'] ?? '') . ' ' .
      ($u['city'] ?? '') . ' ' . ($u['country'] ?? '')
    ), $q));
  }
  $customersPerPage = 10;
  $customerPage = max(1, (int)($_GET['cpage'] ?? 1));
  $totalCustomersPages = max(1, ceil(count($filteredCustomers) / $customersPerPage));
  $filteredCustomers = array_values($filteredCustomers);
  $filteredCustomers = array_slice($filteredCustomers, ($customerPage - 1) * $customersPerPage, $customersPerPage);
  ?>
  <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden">
    <div class="p-5 border-b border-gray-700 flex flex-wrap items-center justify-between gap-4">
      <div>
        <h2 class="text-lg font-bold">Passengers — <?= htmlspecialchars($myAirline) ?> 👥</h2>
        <p class="readonly-note mt-1">View only — attendants cannot modify customer records.</p>
      </div>
      <form method="GET" class="flex gap-2 flex-wrap">
        <input type="hidden" name="tab" value="customers">
        <input type="text" name="csearch" value="<?= htmlspecialchars($customerSearch) ?>"
          placeholder="Search name, email, address..." class="w-64 h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
        <button type="submit" class="h-10 px-5 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-semibold transition">Search</button>
        <?php if ($customerSearch): ?>
        <a href="?tab=customers" class="h-10 px-4 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-semibold transition flex items-center">Clear</a>
        <?php endif; ?>
      </form>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-700/50 text-gray-400 text-sm">
            <th class="text-left px-5 py-3">Name</th>
            <th class="text-left px-5 py-3">Email</th>
            <th class="text-left px-5 py-3">Phone</th>
            <th class="text-left px-5 py-3">City</th>
            <th class="text-left px-5 py-3">Country</th>
            <th class="text-left px-5 py-3">Account</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($filteredCustomers as $u): ?>
          <tr>
            <td class="px-5 py-4 font-semibold"><?= htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?></td>
            <td class="px-5 py-4 text-gray-300"><?= htmlspecialchars($u['email'] ?: '—') ?></td>
            <td class="px-5 py-4 text-gray-300"><?= htmlspecialchars($u['phone'] ?: '—') ?></td>
            <td class="px-5 py-4 text-gray-300"><?= htmlspecialchars($u['city'] ?: '—') ?></td>
            <td class="px-5 py-4 text-gray-300"><?= htmlspecialchars($u['country'] ?: '—') ?></td>
            <td class="px-5 py-4 text-xs text-gray-500"><?= $u['is_registered'] ? 'Registered' : 'Guest' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($filteredCustomers)): ?>
          <tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">No passengers found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="px-5 py-4 border-t border-gray-700 flex items-center justify-between">
      <span class="text-sm text-gray-400">Page <?= $customerPage ?> of <?= $totalCustomersPages ?></span>
      <div class="flex gap-2">
        <?php if ($customerPage > 1): ?>
        <a href="?tab=customers&csearch=<?= urlencode($customerSearch) ?>&cpage=<?= $customerPage - 1 ?>" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-medium transition">Previous</a>
        <?php endif; ?>
        <?php if ($customerPage < $totalCustomersPages): ?>
        <a href="?tab=customers&csearch=<?= urlencode($customerSearch) ?>&cpage=<?= $customerPage + 1 ?>" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-medium transition">Next</a>
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
        ($t['confirmation_code'] ?? '') . ' ' . ($t['name_last'] ?? '') . ' ' . ($t['name_first'] ?? '') . ' ' .
        ($t['flight_id'] ?? '') . ' ' . ($t['status'] ?? '') . ' ' . ($t['destination'] ?? '') . ' ' .
        ($f['comingFrom'] ?? '') . ' ' . ($f['flightNumber'] ?? '')
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
      <p class="hint mb-3">Only flights operated by <?= htmlspecialchars($myAirline) ?> can be booked here.</p>
      <form id="ticketForm" class="space-y-3">
        <p class="section-label">Flight &amp; Seat (required)</p>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Flight ID*</label>
          <input type="text" name="flight_id" id="tFlightId" required class="field font-mono text-xs" onblur="onFlightBlur(this.value)">
          <p id="flightInfo" class="text-xs mt-1 hidden"></p>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Seat*</label>
          <input type="text" name="seat" id="tSeat" required maxlength="3" placeholder="ex. 5A, 7H" class="field uppercase" oninput="this.value=this.value.toUpperCase()" onblur="onSeatBlur(this.value)">
          <p class="hint">Rows 1–10 · Columns A–I</p>
          <p id="seatErr" class="text-red-400 text-xs mt-1 hidden"></p>
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
        <div>
          <label class="block text-xs text-gray-400 mb-1">Phone Number</label>
          <input type="tel" name="phone" class="field" inputmode="numeric" oninput="autoFormatPhone(this)">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Email Address</label>
          <input type="email" name="email" pattern="^[^\s@]+@[^\s@]+\.[^\s@]+$" title="Email must contain &quot;@&quot; and &quot;.&quot;" class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">User ID (If Customer) <span class="text-gray-600">(optional)</span></label>
          <input type="text" name="user_id" id="tUserId" placeholder="Leave blank for guest" class="field" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'')">
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
        <input type="text" name="conf" value="<?= htmlspecialchars($confLookup) ?>" class="field flex-1 h-9">
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
            'Flight #'     => '<code class="bg-gray-800 px-2 py-0.5 rounded font-mono text-xs text-gray-300">' . htmlspecialchars($lf['flightNumber'] ?? '—') . '</code>',
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
        <?php if (strtolower($lookupTicket['status'] ?? '') !== 'cancelled' && isUpcomingFlight($lf, $now)): ?>
        <form method="POST" class="mt-4 cancel-ticket-form" data-ticket-id="<?= htmlspecialchars($lookupTicket['ticket_id']) ?>">
          <input type="hidden" name="action" value="cancel_ticket">
          <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($lookupTicket['ticket_id']) ?>">
          <button type="submit" onclick="return confirm('Cancel this ticket? This cannot be undone.')" class="w-full h-9 bg-red-600/80 hover:bg-red-600 rounded-lg text-sm font-semibold transition">
            Cancel This Ticket
          </button>
        </form>
        <?php endif; ?>
        <?php else: ?>
        <p class="text-red-400 text-sm bg-red-950 border border-red-800 rounded-lg px-4 py-3">
          No ticket found for "<strong><?= htmlspecialchars($confLookup) ?></strong>" on your airline.
        </p>
        <?php endif; ?>
      <?php else: ?>
      <p class="text-gray-600 text-sm">Enter a confirmation code above to find a ticket on your airline.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden">
    <div class="p-5 border-b border-gray-700 flex flex-wrap items-center justify-between gap-4">
      <h2 class="text-lg font-bold">Tickets — <?= htmlspecialchars($myAirline) ?> 🎟️</h2>
      <form method="GET" class="flex gap-2 flex-wrap">
        <input type="hidden" name="tab" value="tickets">
        <input type="text" name="tsearch" value="<?= htmlspecialchars($ticketSearch) ?>" placeholder="Search flight, name, code…" class="field h-9 w-64">
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
          $canCancel   = !$isCancelled && isUpcomingFlight($f, $now);
          $passenger   = trim(($t['name_first'] ?? '') . ' ' . ($t['name_last'] ?? '')) ?: '—';
          $safeP = '$' . number_format(parseTicketPrice($t['price'] ?? '0'), 2);
          $destination = $t['destination'] ?? '—';
        ?>
        <tr class="border-t border-gray-700 hover:bg-gray-700/40 transition <?= $isCancelled ? 'cancelled-row' : '' ?>" data-ticket-id="<?= htmlspecialchars($t['ticket_id']) ?>">
          <td class="px-4 py-3"><code class="text-xs bg-gray-800 px-2 py-1 rounded text-blue-300 font-mono"><?= htmlspecialchars($t['confirmation_code'] ?? '—') ?></code></td>
          <td class="px-4 py-3 text-gray-300"><?= htmlspecialchars($passenger) ?></td>
          <td class="px-4 py-3 font-semibold text-xs"><?= htmlspecialchars($f['flightNumber'] ?? '—') ?></td>
          <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap"><?= htmlspecialchars(strtoupper($destination)) ?></td>
          <td class="px-4 py-3 text-gray-400 text-xs"><?= htmlspecialchars($t['seat'] ?? '—') ?></td>
          <td class="px-4 py-3 text-gray-300 text-xs font-mono"><?= $safeP ?></td>
          <td class="px-4 py-3 status-cell">
            <?php if ($canCancel): ?>
            <form method="POST" class="inline cancel-ticket-form" data-ticket-id="<?= htmlspecialchars($t['ticket_id']) ?>">
              <input type="hidden" name="action" value="cancel_ticket">
              <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($t['ticket_id']) ?>">
              <button type="submit" onclick="return confirm('Cancel ticket <?= htmlspecialchars(addslashes($t['confirmation_code'] ?? '')) ?>?')" class="text-xs text-red-400 hover:text-red-300 font-semibold transition">Cancel</button>
            </form>
            <?php elseif ($isCancelled): ?>
            <span class="text-xs text-gray-700">Cancelled</span>
            <?php else: ?>
            <span class="text-xs text-gray-600" title="Flight already departed">Departed</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($filteredTickets)): ?>
        <tr><td colspan="7" class="px-5 py-10 text-center text-gray-600">No tickets found.</td></tr>
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

  <?php endif; // myAirline check ?>

</main>

<script>
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

const takenSeats   = <?= json_encode(array_map(function($f) {
  $seats = [];
  foreach (($f['takenSeats'] ?? $f['taken_seats'] ?? []) as $ts) {
    $s = is_array($ts) ? ($ts['seat'] ?? '') : (string)$ts;
    if ($s) $seats[] = strtoupper($s);
  }
  return $seats;
}, array_intersect_key($flightMap, $myAirlineFlightIds))) ?>;
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
  const infoEl = document.getElementById('flightInfo');
  if (!val) {
    infoEl.classList.add('hidden');
    document.getElementById('tPrice').value = '0';
    document.getElementById('tDestination').value = '';
    return;
  }
  try {
    const res = await fetch(`flight_lookup.php?flight_id=${encodeURIComponent(val)}&carry_on=0&checked=0`);
    const data = await res.json();
    if (data.error) {
      infoEl.textContent = data.error;
      infoEl.className = 'text-xs mt-1 text-red-400';
      infoEl.classList.remove('hidden');
      document.getElementById('tPrice').value = '0';
      document.getElementById('tDestination').value = '';
      return;
    }
    // NOTE: flight_lookup.php should also verify the flight's airline
    // matches the attendant's assigned airline server-side; the final
    // create_ticket POST handler above already re-validates this.
    infoEl.textContent = `${data.flightNumber} · ${data.airline} → ${data.destination}`;
    infoEl.className = 'text-xs mt-1 text-emerald-400';
    infoEl.classList.remove('hidden');
    document.getElementById('tPrice').value = data.total;
    document.getElementById('tDestination').value = data.destination;
  } catch (e) {
    infoEl.textContent = 'Could not look up flight.';
    infoEl.className = 'text-xs mt-1 text-red-400';
    infoEl.classList.remove('hidden');
  }
}

function onSeatBlur(val) {
  const errEl = document.getElementById('seatErr');
  const flightId = document.getElementById('tFlightId').value.trim();
  if (!val) { errEl.classList.add('hidden'); return; }
  if (!/^([1-9]|10)[A-Ia-i]$/.test(val)) {
    errEl.textContent = 'Invalid seat. Must be row 1–10, column A–I (Ex. 5A, 10I).';
    errEl.classList.remove('hidden'); return;
  }
  const apiTaken = takenSeats[flightId] || [];
  const dbTaken = dbTakenSeats[flightId] || [];
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
    const res = await fetch(window.location.pathname + '?tab=tickets', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
      if (data.redirect) {
        window.location.href = data.redirect;
      } else {
        showFlash(data.message);
        this.reset();
        document.getElementById('flightInfo').classList.add('hidden');
        setTimeout(() => window.location.reload(), 900);
      }
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
      const res = await fetch(window.location.pathname + '?tab=tickets', { method: 'POST', body: formData });
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
</script>
</body>
</html>