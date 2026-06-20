<?php
session_start();

require_once '../../../api/key.php';
require_once '../../../api/api.php';
require_once '../../../database/db.php';


if (!$_SESSION['user_id']) {
  header('Location: ../../../index.php');
  exit;
}

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

if (!isset($_SESSION['last_login_ip'])) {
    $_SESSION['last_login_ip']       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $_SESSION['last_login_datetime'] = date('Y-m-d H:i:s');
}

$currentUser = [
    'id'                  => $_SESSION['user_id'],
    'name'                => trim(($dbUser['first_name'] ?? '') . ' ' . ($dbUser['last_name'] ?? '')),
    'first_name'          => $dbUser['first_name'] ?? '',
    'last_name'           => $dbUser['last_name']  ?? '',
    'email'               => $dbUser['email']       ?? '',
    'phone'               => $dbUser['phone']       ?? '',
    'last_login_ip'       => $_SESSION['last_login_ip'],
    'last_login_datetime' => $_SESSION['last_login_datetime'],
    'auto_logout'         => $_SESSION['auto_logout_' . $_SESSION['user_id']]  ?? '15',
    'flight_sort'         => $_SESSION['flight_sort_' . $_SESSION['user_id']]  ?? 'date_asc',
];

$api = new AirportsAPI(AIRPORTS_API_KEY);

$airlinesData   = $api->getAirlines();
$airportsData   = $api->getAirports();
$allFlightsData = $api->getAllFlights();

$airlinesMap = [];
if ($airlinesData && isset($airlinesData['airlines'])) {
    foreach ($airlinesData['airlines'] as $a) {
        $key = $a['id'] ?? $a['name'] ?? '';
        $airlinesMap[$key] = $a['name'] ?? $key;
    }
}

$airportsMap = [];
if ($airportsData && isset($airportsData['airports'])) {
    foreach ($airportsData['airports'] as $ap) {
        $code = $ap['iata'] ?? $ap['id'] ?? '';
        $airportsMap[$code] = $ap['name'] ?? $code;
    }
}

$allFlights = [];
$flightMap  = [];
if ($allFlightsData && isset($allFlightsData['flights'])) {
    $allFlights = $allFlightsData['flights'];
    foreach ($allFlights as $f) {
      $f = normalizeFlight($f);
      $fid = $f['flight_id'] ?? '';
      if ($fid) $flightMap[$fid] = $f;
    }
}

$cardsStmt = $pdo->prepare('SELECT * FROM "Saved Cards" WHERE user_id = ?');
$cardsStmt->execute([$_SESSION['user_id']]);
$savedCards = $cardsStmt->fetchAll(PDO::FETCH_ASSOC);

$ticketsStmt = $pdo->prepare('SELECT * FROM "Tickets" WHERE user_id = ?');
$ticketsStmt->execute([$_SESSION['user_id']]);
$userTickets = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);

function normalizeFlight(array $f): array {
  return [
      'flight_id'     => $f['flight_id'] ?? '',
      'flightNumber'  => $f['flightNumber'] ?? '—',
      'airline'       => $f['airline'] ?? '—',
      'destination'   => $f['departingTo'] ?? '—',
      'departureTime' => (int)($f['departFromSender'] ?? 0),
      'arrivalTime'   => (int)($f['arriveAtReceiver'] ?? 0),
      'status'        => $f['status'] ?? '—',
      'gate'          => $f['gate'] ?? '—',
      'bookable'      => $f['bookable'] ?? 0,
      'seatPrice'     => $f['seatPrice'] ?? 0,
  ];
}

function getFlightById(AirportsAPI $api, $flightId) {
  if (!$flightId) return null;

  $res = $api->searchFlights(
      ['flight_id' => $flightId],
      null,
      'desc'
  );

  return $res['flights'][0] ?? null;
}

$now = time();

$upcomingFlights = [];
$pastFlights = [];

foreach ($userTickets as $ticket) {
  if (strtolower($ticket['status'] ?? '') == 'cancelled') {
    continue;
  }
  $fid = $ticket['flight_id'] ?? '';
  $flightRaw = getFlightById($api, $fid);

  if ($flightRaw) {
      $flight = normalizeFlight($flightRaw);
  } else {
      $flight = [
          'flight_id' => $fid,
          'flightNumber' => $fid,
          'destination' => '—',
          'departureTime' => 0,
          'arrivalTime' => 0,
          'airline' => '—',
          'status' => '—',
      ];
  }

  $row = [
      'ticket' => $ticket,
      'flight' => $flight,
  ];

  $depTs = (int)($flight['departureTime'] ?? 0);

  if ($depTs >= $now || $depTs === 0) {
      $upcomingFlights[] = $row;
  } else {
      $pastFlights[] = $row;
  }
}

$sortKey = $currentUser['flight_sort'];

usort($upcomingFlights, function ($a, $b) use ($sortKey) {

  $fa = $a['flight'];
  $fb = $b['flight'];

  return match ($sortKey) {
      'date_desc' =>
          ($fb['departureTime'] ?? 0) <=> ($fa['departureTime'] ?? 0),

      'flight_asc' =>
          ($fa['flightNumber'] ?? '') <=> ($fb['flightNumber'] ?? ''),

      'airline_asc' =>
          ($fa['airline'] ?? '') <=> ($fb['airline'] ?? ''),

      default =>
          ($fa['departureTime'] ?? 0) <=> ($fb['departureTime'] ?? 0),
  };
});

usort($pastFlights, function ($a, $b) {
  return ($b['flight']['departureTime'] ?? 0)
      <=> ($a['flight']['departureTime'] ?? 0);
});

$activeTab  = $_GET['tab'] ?? 'overview';
$updateMsg  = $_SESSION['flash_msg']          ?? null;
$guestMsg   = $_SESSION['flash_guest_msg']    ?? null;
$guestError = $_SESSION['flash_guest_error']  ?? null;
unset($_SESSION['flash_msg'], $_SESSION['flash_guest_msg'], $_SESSION['flash_guest_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'update_profile') {
        $email  = trim($_POST['email']  ?? '');
        $phone  = trim($_POST['phone']  ?? '');
        $street = trim($_POST['street'] ?? '');
        $city   = trim($_POST['city']   ?? '');
        $state  = trim($_POST['state']  ?? '');
        $zip    = trim($_POST['zip']    ?? '');
        $upd = $pdo->prepare(
            'UPDATE "Users" SET email=?, phone=?, street_address=?, city=?, state=?, zip_code=? WHERE user_id=?'
        );
        $upd->execute([$email, $phone, $street, $city, $state, $zip, $_SESSION['user_id']]);
        $_SESSION['flash_msg'] = 'Profile updated successfully.';
        header('Location: ?tab=profile');
        exit;
    }

    if ($_POST['action'] === 'update_preferences') {
        if (in_array($_POST['auto_logout'] ?? '', ['5', '15', '60'])) {
            $_SESSION['auto_logout_' . $_SESSION['user_id']] = $_POST['auto_logout'];
        }
        $sortOpts = ['date_asc', 'date_desc', 'flight_asc', 'airline_asc'];
        if (in_array($_POST['flight_sort'] ?? '', $sortOpts)) {
            $_SESSION['flight_sort_' . $_SESSION['user_id']] = $_POST['flight_sort'];
        }
        $_SESSION['flash_msg'] = 'Preferences saved.';
        header('Location: ?tab=preferences');
        exit;
    }

    if ($_POST['action'] === 'add_guest_flight') {
        $conf     = trim($_POST['confirmation_number'] ?? '');
        $lastName = strtolower(trim($_POST['last_name'] ?? ''));
        $userLast = strtolower($dbUser['last_name'] ?? '');

        if ($conf === '' || $lastName === '') {
            $_SESSION['flash_guest_error'] = 'Please fill in both fields.';
        } elseif ($lastName !== $userLast) {
            $_SESSION['flash_guest_error'] = 'Last name does not match the name on the ticket.';
        } else {
            $tStmt = $pdo->prepare('SELECT * FROM "Tickets" WHERE UPPER(confirmation_code) = UPPER(?) LIMIT 1');
            $tStmt->execute([$conf]);
            $foundTicket = $tStmt->fetch(PDO::FETCH_ASSOC);

            if ($foundTicket) {
                $upd = $pdo->prepare('UPDATE "Tickets" SET user_id = ? WHERE ticket_id = ? AND (user_id IS NULL OR user_id = ?)');
                $upd->execute([$_SESSION['user_id'], $foundTicket['ticket_id'], $_SESSION['user_id']]);
                $_SESSION['flash_guest_msg'] = "Booking {$conf} has been linked to your account.";
            } else {
                $_SESSION['flash_guest_error'] = "No booking found with confirmation code \"{$conf}\".";
            }
        }
        header('Location: ?tab=flights');
        exit;
    }

    if ($_POST['action'] === 'remove_card') {
        $removeId = $_POST['card_id'] ?? '';
        $del = $pdo->prepare('DELETE FROM "Saved Cards" WHERE card_id = ? AND user_id = ?');
        $del->execute([$removeId, $_SESSION['user_id']]);
        $_SESSION['flash_msg'] = 'Card removed.';
        header('Location: ?tab=payment');
        exit;
    }

    if ($_POST['action'] === 'add_card') {
        $cardNumber = preg_replace('/\D/', '', trim($_POST['card_number'] ?? ''));
        $expiry     = htmlspecialchars(trim($_POST['card_expiry']     ?? ''));
        $cvc        = preg_replace('/\D/', '', trim($_POST['card_cvc']  ?? ''));
        $billing    = htmlspecialchars(trim($_POST['billing_address']  ?? ''));
        $zip        = htmlspecialchars(trim($_POST['billing_zip']      ?? ''));
        $cardName   = htmlspecialchars(trim($_POST['card_name']        ?? $currentUser['name']));

        if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
            $_SESSION['flash_msg'] = 'Please enter a valid card number (13–19 digits).';
            header('Location: ?tab=payment');
            exit;
        }

        $masked = str_repeat('*', strlen($cardNumber) - 4) . substr($cardNumber, -4);

        $ins = $pdo->prepare(
            'INSERT INTO "Saved Cards" (user_id, card_number, expiration_date, cvc, billing_address, zip_code, card_name)
             VALUES (?,?,?,?,?,?,?)'
        );
        $ins->execute([$_SESSION['user_id'], $masked, $expiry, $cvc, $billing, $zip, $cardName]);
        $_SESSION['flash_msg'] = 'Card saved.';
        header('Location: ?tab=payment');
        exit;
    }
}

function dashFormatTs($ts): string {
  if (!$ts) return '—';

  if ($ts > 1000000000000) {
      $ts = (int)($ts / 1000);
  }

  return date('Y-m-d H:i', $ts);
}

function ticketPassengerName($ticket): string {
  $first = $ticket['name_first'] ?? '';
  $last  = $ticket['name_last'] ?? '';

  $full = trim("$first $last");

  return $full !== '' ? $full : ($ticket['passenger_name'] ?? '—');
}

function dashFlightAirline(array $flight, array $airlinesMap): string {
    $id = $flight['airline'] ?? $flight['airlineId'] ?? '';
    return htmlspecialchars($airlinesMap[$id] ?? $id ?: '—');
}

function getTicketForFlight(string $fid, array $userTickets): ?array {
    foreach ($userTickets as $t) {
        if (($t['flight_id'] ?? '') === $fid) return $t;
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
body { font-family: 'Inter', sans-serif; }
.tab-active   { background: rgb(37 99 235); color: white; }
.tab-inactive { color: rgb(156 163 175); }
.tab-inactive:hover { color: white; background: rgb(55 65 81); }
</style>
</head>
<body class="bg-gray-900 min-h-screen text-white">

<?php include_once __DIR__ . '/../../../components/nav.php'; ?>


<main class="max-w-7xl mx-auto p-6">

  <div class="bg-gray-800 border border-gray-700 rounded-lg p-8 mb-6">
    <p class="uppercase tracking-widest text-gray-400 text-sm">Welcome back</p>
    <h1 class="text-4xl font-bold mt-2"><?= htmlspecialchars($currentUser['name']) ?></h1>
    <div class="flex flex-wrap gap-6 mt-4 text-sm text-gray-400">
      <span>
        <span class="text-gray-500 mr-1">Last login:</span>
        <?= htmlspecialchars($currentUser['last_login_datetime']) ?>
      </span>
      <span>
        <span class="text-gray-500 mr-1">From IP:</span>
        <code class="bg-gray-700 px-2 py-0.5 rounded text-gray-300"><?= htmlspecialchars($currentUser['last_login_ip']) ?></code>
      </span>
    </div>
  </div>

  <?php if ($updateMsg): ?>
  <div class="mb-4 bg-emerald-900/30 border border-emerald-700 rounded-lg px-5 py-3 text-emerald-400 text-sm">
    ✓ <?= htmlspecialchars($updateMsg) ?>
  </div>
  <?php endif; ?>

  <div class="flex gap-2 mb-6 bg-gray-800 border border-gray-700 rounded-lg p-1 flex-wrap">
    <?php
    $tabs = [
      'overview'    => 'Overview',
      'flights'     => 'My Flights',
      'profile'     => 'Profile',
      'payment'     => 'Payment',
      'preferences' => 'Preferences',
    ];
    foreach ($tabs as $key => $label):
      $cls = ($activeTab === $key) ? 'tab-active' : 'tab-inactive';
    ?>
    <a href="?tab=<?= $key ?>" class="px-4 py-2 rounded-md text-sm font-medium transition <?= $cls ?>">
      <?= $label ?>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if ($activeTab === 'overview'): ?>

  <div class="grid xl:grid-cols-3 md:grid-cols-2 gap-4 mb-6">
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
      <p class="text-gray-400 text-sm">Upcoming Flights</p>
      <h2 class="text-4xl font-bold mt-2"><?= count($upcomingFlights) ?></h2>
    </div>
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
      <p class="text-gray-400 text-sm">Past Flights</p>
      <h2 class="text-4xl font-bold mt-2"><?= count($pastFlights) ?></h2>
    </div>
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
      <p class="text-gray-400 text-sm">Saved Cards</p>
      <h2 class="text-4xl font-bold mt-2"><?= count($savedCards) ?></h2>
    </div>
  </div>

  <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden mb-6">
    <div class="p-5 border-b border-gray-700 flex items-center justify-between">
      <h2 class="text-lg font-bold">Upcoming Flights</h2>
      <a href="?tab=flights" class="text-sm text-blue-400 hover:text-blue-300">View all</a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-700/50 text-gray-400 text-sm">
            <th class="text-left px-5 py-3">Flight</th>
            <th class="text-left px-5 py-3">Passenger</th>
            <th class="text-left px-5 py-3">Route</th>
            <th class="text-left px-5 py-3">Seat</th>
            <th class="text-left px-5 py-3">Confirmation</th>
            <th class="text-left px-5 py-3">Departure</th>
          </tr>
        </thead>
        <tbody>
<?php if (!empty($upcomingFlights)): ?>

    <?php foreach (array_slice($upcomingFlights, 0, 5) as $row):

        $f = $row['flight'];
        $ticket = $row['ticket'];
        $fid = $f['flight_id'] ?? '';
    ?>

    <tr data-flight-id="<?= htmlspecialchars($fid) ?>"
        class="border-t border-gray-700 hover:bg-gray-700/40 transition cursor-pointer"
        onclick="window.location='../../ticket/ticket.php?confirmation=<?= urlencode($ticket['confirmation_code'] ?? '') ?>'">

        <td class="px-5 py-4 font-semibold">
            <?= htmlspecialchars($f['flightNumber'] ?? $fid ?: '—') ?>
        </td>

        <td class="px-5 py-4 text-gray-300">
            <?= htmlspecialchars(ticketPassengerName($ticket)) ?>
        </td>

        <td class="px-5 py-4 text-gray-300">
            <?= htmlspecialchars($f['destination'] ?? '—') ?>
        </td>

        <td class="px-5 py-4 text-gray-300">
            <?= htmlspecialchars($ticket['seat'] ?? '—') ?>
        </td>

        <td class="px-5 py-4">
            <code class="text-xs bg-gray-700 px-2 py-1 rounded text-blue-300">
                <?= htmlspecialchars($ticket['confirmation_code'] ?? '—') ?>
            </code>
        </td>

        <td class="px-5 py-4 text-gray-300">
            <?= dashFormatTs((int)($f['departureTime'] ?? 0)) ?>
        </td>

    </tr>

    <?php endforeach; ?>

<?php else: ?>

    <tr>
        <td colspan="6" class="px-5 py-8 text-center text-gray-500">
            No upcoming flights found.
        </td>
    </tr>

<?php endif; ?>
</tbody>
      </table>
    </div>
  </div>

  <?php endif; ?>

  <?php if ($activeTab === 'flights'): ?>

  <?php if ($guestMsg): ?>
  <div class="mb-4 bg-emerald-900/30 border border-emerald-700 rounded-lg px-5 py-3 text-emerald-400 text-sm">✓ <?= htmlspecialchars($guestMsg) ?></div>
  <?php endif; ?>
  <?php if ($guestError): ?>
  <div class="mb-4 bg-red-900/30 border border-red-700 rounded-lg px-5 py-3 text-red-400 text-sm">⚠ <?= htmlspecialchars($guestError) ?></div>
  <?php endif; ?>

  <div class="bg-gray-800 border border-gray-700 rounded-lg p-5 mb-6">
  <h3 class="font-semibold mb-4 flex items-center gap-2">
  Link a Booking by Confirmation Code

  <div class="relative group cursor-help">
    <span class="text-gray-400 hover:text-gray-200">?</span>

    <div class="absolute left-1/2 -translate-x-1/2 top-6 w-72 bg-gray-900 border border-gray-700 text-xs text-gray-300 rounded-lg p-3 shadow-lg opacity-0 group-hover:opacity-100 pointer-events-none transition z-50">If you have ever booked a flight as a guest, you can enter that confirmation number here. If your last name matches the ticket, the flight will be linked to your account as if it was purchased while logged in.</div>
  </div>
</h3>
    <form method="POST" class="flex flex-wrap gap-3">
      <input type="hidden" name="action" value="add_guest_flight">
      <input type="text" name="confirmation_number" placeholder="Confirmation code (e.g. E5920205)"
        class="flex-1 min-w-[180px] h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
      <input type="text" name="last_name" placeholder="Last name on ticket"
        class="flex-1 min-w-[160px] h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
      <button type="submit" class="h-10 px-5 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-semibold transition">
        Link Booking
      </button>
    </form>
  </div>

  <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden mb-6">
    <div class="p-5 border-b border-gray-700 flex items-center justify-between">
      <h2 class="text-lg font-bold">Upcoming Flights</h2>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-700/50 text-gray-400 text-sm">
            <th class="text-left px-5 py-3">Flight</th>
            <th class="text-left px-5 py-3">Airline</th>
            <th class="text-left px-5 py-3">Passenger</th>
            <th class="text-left px-5 py-3">Destination</th>
            <th class="text-left px-5 py-3">Seat</th>
            <th class="text-left px-5 py-3">Confirmation</th>
            <th class="text-left px-5 py-3">Departure</th>
            <th class="text-left px-5 py-3">Arrival</th>
          </tr>
        </thead>
        <tbody>
<?php if (!empty($upcomingFlights)): ?>

    <?php foreach (array_slice($upcomingFlights, 0, 5) as $row):

        $f = $row['flight'];
        $ticket = $row['ticket'];
        $fid = $f['flight_id'] ?? '';
    ?>
    
    <tr data-flight-id="<?= htmlspecialchars($fid) ?>"
    class="border-t border-gray-700 hover:bg-gray-700/40 transition cursor-pointer"
    onclick="window.location='../../ticket/ticket.php?confirmation=<?= urlencode($ticket['confirmation_code'] ?? '') ?>'">

  <td class="px-5 py-4 font-semibold">
    <?= htmlspecialchars($f['flightNumber'] ?? $fid ?: '—') ?>
  </td>

  <td class="px-5 py-4 text-gray-300">
    <?= dashFlightAirline($f, $airlinesMap) ?>
  </td>

  <td class="px-5 py-4 text-gray-300">
    <?= htmlspecialchars(ticketPassengerName($ticket)) ?>
  </td>

  <td class="px-5 py-4 text-gray-300">
    <?= htmlspecialchars($f['destination'] ?? '—') ?>
  </td>

  <td class="px-5 py-4 text-gray-300">
    <?= htmlspecialchars($ticket['seat'] ?? '—') ?>
  </td>

  <td class="px-5 py-4">
    <code class="text-xs bg-gray-700 px-2 py-1 rounded text-blue-300">
      <?= htmlspecialchars($ticket['confirmation_code'] ?? '—') ?>
    </code>
  </td>

  <td class="px-5 py-4 text-gray-300 cell-dep">
    <?= dashFormatTs((int)($f['departureTime'] ?? 0)) ?>
  </td>

  <td class="px-5 py-4 text-gray-300 cell-arr">
    <?= dashFormatTs((int)($f['arrivalTime'] ?? 0)) ?>
  </td>

</tr>

    <?php endforeach; ?>

<?php else: ?>

    <tr>
      <td colspan="6" class="px-5 py-8 text-center text-gray-500">
        No upcoming flights found.
      </td>
    </tr>

<?php endif; ?>
</tbody>
      </table>
    </div>
  </div>

  <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden mb-6">
    <div class="p-5 border-b border-gray-700">
      <h2 class="text-lg font-bold">Past Flights</h2>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-700/50 text-gray-400 text-sm">
            <th class="text-left px-5 py-3">Flight</th>
            <th class="text-left px-5 py-3">Airline</th>
            <th class="text-left px-5 py-3">Passenger</th>
            <th class="text-left px-5 py-3">Destination</th>
            <th class="text-left px-5 py-3">Seat</th>
            <th class="text-left px-5 py-3">Confirmation</th>
            <th class="text-left px-5 py-3">Departure</th>
            <th class="text-left px-5 py-3">Arrival</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($pastFlights as $row):

        $f = $row['flight'];
        $ticket = $row['ticket'];
        $fid = $f['flight_id'] ?? '';
        ?>
        <tr data-flight-id="<?= htmlspecialchars($fid) ?>"
    class="border-t border-gray-700 hover:bg-gray-700/40 transition cursor-pointer"
    onclick="window.location='../../ticket/ticket.php?confirmation=<?= urlencode($ticket['confirmation_code'] ?? '') ?>'">

  <td class="px-5 py-4 font-semibold">
    <?= htmlspecialchars($f['flightNumber'] ?? $fid ?: '—') ?>
  </td>

  <td class="px-5 py-4 text-gray-300">
    <?= dashFlightAirline($f, $airlinesMap) ?>
  </td>

  <td class="px-5 py-4 text-gray-300">
    <?= htmlspecialchars(ticketPassengerName($ticket)) ?>
  </td>

  <td class="px-5 py-4 text-gray-300">
    <?= htmlspecialchars($f['destination'] ?? '—') ?>
  </td>

  <td class="px-5 py-4 text-gray-300">
    <?= htmlspecialchars($ticket['seat'] ?? '—') ?>
  </td>

  <td class="px-5 py-4">
    <code class="text-xs bg-gray-700 px-2 py-1 rounded text-blue-300">
      <?= htmlspecialchars($ticket['confirmation_code'] ?? '—') ?>
    </code>
  </td>

  <td class="px-5 py-4 text-gray-300 cell-dep">
    <?= dashFormatTs((int)($f['departureTime'] ?? 0)) ?>
  </td>

  <td class="px-5 py-4 text-gray-300 cell-arr">
    <?= dashFormatTs((int)($f['arrivalTime'] ?? 0)) ?>
  </td>

</tr>
        <?php endforeach; ?>
        <?php if (empty($pastFlights)): ?>
        <tr><td colspan="9" class="px-5 py-8 text-center text-gray-500">No past flights found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    const upcomingIds = <?= json_encode(
      array_values(
          array_unique(
              array_map(
                  fn($row) => $row['flight']['flight_id'] ?? '',
                  $upcomingFlights
              )
          )
      )
  ) ?>;

  
function fmtTs(ts) {
  if (!ts) return '—';

  const d = new Date(ts > 1e12 ? ts : ts * 1000);

  return d.toISOString().slice(0, 16).replace('T', ' ');
}

  async function pollUpcoming() {
    if (!upcomingIds.length) return;
    try {
      const res = await fetch(`flight_poll.php?ids=${encodeURIComponent(JSON.stringify(upcomingIds))}`);
      if (!res.ok) return;
      const data = await res.json();
      if (!data || !data.flights) return;
      for (const flight of data.flights) {
        const fid = flight.flight_id;
        const row = document.querySelector(`tr[data-flight-id="${fid}"]`);
        if (!row) continue;
        const dep = row.querySelector('.cell-dep');
        const arr = row.querySelector('.cell-arr');
        if (dep) dep.textContent   = fmtTs(flight.departureTime);
        if (arr) arr.textContent   = fmtTs(flight.arrivalTime);
      }
    } catch (e) {}
  }

  setInterval(pollUpcoming, 30000);
  </script>

  <?php endif; ?>

  <?php if ($activeTab === 'profile'): ?>

  <div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
      <h2 class="text-lg font-bold mb-5">Personal Information</h2>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" value="update_profile">
        <div>
          <label class="block text-sm text-gray-400 mb-1">Full Name</label>
          <input type="text" value="<?= htmlspecialchars($currentUser['name']) ?>" disabled
            class="w-full h-10 bg-gray-700/50 border border-gray-600 rounded-lg px-4 text-gray-400 text-sm cursor-not-allowed">
        </div>
        <div>
          <label class="block text-sm text-gray-400 mb-1">Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($currentUser['email']) ?>"
            class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm text-gray-400 mb-1">Phone</label>
          <input type="text" name="phone" value="<?= htmlspecialchars($currentUser['phone']) ?>"
            class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm text-gray-400 mb-1">Street Address</label>
          <input type="text" name="street" value="<?= htmlspecialchars($dbUser['street_address'] ?? '') ?>"
            class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="block text-sm text-gray-400 mb-1">City</label>
            <input type="text" name="city" value="<?= htmlspecialchars($dbUser['city'] ?? '') ?>"
              class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>
          <div>
            <label class="block text-sm text-gray-400 mb-1">State</label>
            <input type="text" name="state" value="<?= htmlspecialchars($dbUser['state'] ?? '') ?>"
              class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>
          <div>
            <label class="block text-sm text-gray-400 mb-1">ZIP</label>
            <input type="text" name="zip" value="<?= htmlspecialchars($dbUser['zip_code'] ?? '') ?>"
              class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>
        </div>
        <button type="submit" class="w-full h-10 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-semibold transition">
          Save Changes
        </button>
      </form>
    </div>

    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
      <h2 class="text-lg font-bold mb-5">Account Details</h2>
      <div class="space-y-1">
        <?php
        $details = [
          'Customer ID'        => '<code class="text-sm bg-gray-700 px-2 py-0.5 rounded text-gray-300">' . htmlspecialchars($currentUser['id']) . '</code>',
          'Last Login'         => htmlspecialchars($currentUser['last_login_datetime']),
          'Last Login IP'      => '<code class="text-sm bg-gray-700 px-2 py-0.5 rounded text-gray-300">' . htmlspecialchars($currentUser['last_login_ip']) . '</code>',
          'Date of Birth'      => htmlspecialchars($dbUser['date_birth'] ?? $dbUser['date_of_birth'] ?? '—'),
          'Role'               => htmlspecialchars($dbUser['role'] ?? '—'),
          'Airlines in System' => count($airlinesMap),
          'Airports in System' => count($airportsMap),
          'Auto-Logout'        => $currentUser['auto_logout'] === '60' ? '1 hour' : $currentUser['auto_logout'] . ' minutes',
        ];
        foreach ($details as $label => $val): ?>
        <div class="flex justify-between items-center py-3 border-b border-gray-700 last:border-0">
          <span class="text-gray-400 text-sm"><?= $label ?></span>
          <span class="text-sm"><?= $val ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php endif; ?>

  <?php if ($activeTab === 'payment'): ?>

  <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 mb-6">
    <h2 class="text-lg font-bold mb-5">Saved Cards</h2>
    <div class="space-y-3 mb-6">
      <?php foreach ($savedCards as $card): ?>
      <div class="flex items-center justify-between bg-gray-700/50 border border-gray-600 rounded-lg px-5 py-4">
        <div class="flex items-center gap-4">
          <div class="w-10 h-7 bg-gray-600 rounded flex items-center justify-center text-xs font-bold text-gray-300">
            💳
          </div>
          <div>
            <div class="font-semibold text-sm">
              <?= htmlspecialchars($card['card_name'] ?? ('Card ending ···· ' . substr($card['card_number'] ?? '****', -4))) ?>
              — ending ····<?= htmlspecialchars(substr($card['card_number'] ?? '****', -4)) ?>
            </div>
            <div class="text-xs text-gray-400">Expires <?= htmlspecialchars($card['expiration_date'] ?? '—') ?></div>
          </div>
        </div>
        <form method="POST">
          <input type="hidden" name="action" value="remove_card">
          <input type="hidden" name="card_id" value="<?= htmlspecialchars($card['card_id'] ?? $card['id'] ?? '') ?>">
          <button type="submit" class="text-sm text-red-400 hover:text-red-300 transition">Remove</button>
        </form>
      </div>
      <?php endforeach; ?>
      <?php if (empty($savedCards)): ?>
      <p class="text-gray-500 text-sm">No saved cards.</p>
      <?php endif; ?>
    </div>

    <div class="border-t border-gray-700 pt-5">
      <h3 class="font-semibold mb-4 text-sm text-gray-300">Add New Card</h3>
      <form method="POST" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <input type="hidden" name="action" value="add_card">
        <div class="sm:col-span-2">
          <label class="block text-xs text-gray-400 mb-1">Cardholder Name</label>
          <input type="text" name="card_name" placeholder="Name on card"
            value="<?= htmlspecialchars($currentUser['name']) ?>"
            class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="sm:col-span-2">
          <label class="block text-xs text-gray-400 mb-1">Card Number</label>
          <input type="text" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19"
            oninput="this.value=this.value.replace(/\D/g,'').replace(/(.{4})/g,'$1 ').trim()"
            class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Expiry (MM/YY)</label>
          <input type="text" name="card_expiry" placeholder="MM/YY" maxlength="5"
            class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">CVC</label>
          <input type="text" name="card_cvc" placeholder="123" maxlength="4"
            class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Billing Address</label>
          <input type="text" name="billing_address" placeholder="123 Main St"
            class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Billing ZIP</label>
          <input type="text" name="billing_zip" placeholder="55901" maxlength="10"
            class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="sm:col-span-2">
          <button type="submit" class="w-full h-10 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-semibold transition">
            Save Card
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php endif; ?>

  <?php if ($activeTab === 'preferences'): ?>

  <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 max-w-lg">
    <h2 class="text-lg font-bold mb-5">Preferences</h2>
    <form method="POST" class="space-y-6">
      <input type="hidden" name="action" value="update_preferences">
      <div>
        <label class="block text-sm text-gray-400 mb-3">Default Flight Sort Order</label>
        <div class="space-y-2">
          <?php
          $sortOptions = [
            'date_asc'    => 'Date (Earliest first)',
            'date_desc'   => 'Date (Latest first)',
            'flight_asc'  => 'Flight Number (A–Z)',
            'airline_asc' => 'Airline (A–Z)',
          ];
          foreach ($sortOptions as $val => $label):
            $checked = $currentUser['flight_sort'] === $val ? 'checked' : '';
          ?>
          <label class="flex items-center gap-3 cursor-pointer">
            <input type="radio" name="flight_sort" value="<?= $val ?>" <?= $checked ?> class="accent-blue-500">
            <span class="text-sm"><?= $label ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <label class="block text-sm text-gray-400 mb-3">Auto-Logout Time</label>
        <div class="space-y-2">
          <?php
          $logoutOptions = ['5' => '5 minutes', '15' => '15 minutes', '60' => '1 hour'];
          foreach ($logoutOptions as $val => $label):
            $checked = $currentUser['auto_logout'] === $val ? 'checked' : '';
          ?>
          <label class="flex items-center gap-3 cursor-pointer">
            <input type="radio" name="auto_logout" value="<?= $val ?>" <?= $checked ?> class="accent-blue-500">
            <span class="text-sm"><?= $label ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="submit" class="w-full h-10 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-semibold transition">
        Save Preferences
      </button>
    </form>
  </div>

  <?php endif; ?>

</main>
</body>
</html>