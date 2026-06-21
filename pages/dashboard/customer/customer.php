<?php
session_start();

require_once '../../../api/key.php';
require_once '../../../api/api.php';
require_once '../../../database/db.php';

if (strtolower($_SESSION['role'] ?? '') !== 'customer') {
  header('Location: ../../../index.php');
  exit;
}

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
function formatCard($num) {
  $num = preg_replace('/\D/', '', $num); // safety
  return trim(chunk_split($num, 4, ' '));
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

function decodePrefs($raw): array {
    $defaults = [
        'flight_sort'  => 'time_asc',
        'auto_logout'  => '15',
    ];
    if (!$raw) return $defaults;
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return $defaults;
    return array_merge($defaults, $decoded);
}

$prefs = decodePrefs($dbUser['sort_preference'] ?? null);

$currentUser = [
    'id'                  => $_SESSION['user_id'],
    'name'                => trim(($dbUser['first_name'] ?? '') . ' ' . ($dbUser['last_name'] ?? '')),
    'first_name'          => $dbUser['first_name'] ?? '',
    'last_name'           => $dbUser['last_name']  ?? '',
    'email'               => $dbUser['email']       ?? '',
    'phone'               => $dbUser['phone']       ?? '',
    'last_login_ip'       => $_SESSION['last_login_ip'],
    'last_login_datetime' => $_SESSION['last_login_datetime'],
    'auto_logout'         => $prefs['auto_logout'],
    'flight_sort'         => $prefs['flight_sort'],
];

// ---- Force-complete-profile check ----
$requiredProfileFields = [
    'email'          => $dbUser['email']          ?? '',
    'phone'          => $dbUser['phone']           ?? '',
    'street_address' => $dbUser['street_address']  ?? '',
    'city'           => $dbUser['city']            ?? '',
    'state'          => $dbUser['state']           ?? '',
    'zip_code'       => $dbUser['zip_code']         ?? '',
    'date_birth'     => $dbUser['date_birth']       ?? ($dbUser['date_of_birth'] ?? ''),
];
$missingProfileFields = [];
foreach ($requiredProfileFields as $key => $val) {
    if (trim((string)$val) === '') {
        $missingProfileFields[] = $key;
    }
}
$missingPassword = trim((string)($dbUser['password'] ?? '')) === '';
$profileIncomplete = !empty($missingProfileFields) || $missingPassword;

$activeTab = $_GET['tab'] ?? 'overview';
$needsFlightDetails = in_array($activeTab, ['overview', 'flights'], true);

$api = new AirportsAPI(AIRPORTS_API_KEY);

$airlinesData   = $api->getAirlines();
$airportsData   = $api->getAirports();
$allFlightsData = $needsFlightDetails ? $api->getAllFlights() : null;

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
$linkedFlightIds = [];

if ($needsFlightDetails) {
    foreach ($userTickets as $ticket) {
      if (strtolower($ticket['status'] ?? '') == 'cancelled') {
        continue;
      }
      $fid = $ticket['flight_id'] ?? '';
      if ($fid) $linkedFlightIds[] = $fid;
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
} else {
    foreach ($userTickets as $ticket) {
        if (strtolower($ticket['status'] ?? '') == 'cancelled') continue;
        $fid = $ticket['flight_id'] ?? '';
        if ($fid) $linkedFlightIds[] = $fid;
    }
}

$sortKey = $currentUser['flight_sort'];

function sortFlightRows(array $rows, string $sortKey): array {
    usort($rows, function ($a, $b) use ($sortKey) {
        $fa = $a['flight'];
        $fb = $b['flight'];

        return match ($sortKey) {
            'time_asc' =>
                ($fa['departureTime'] ?? 0) <=> ($fb['departureTime'] ?? 0),

            'time_desc' =>
                ($fb['departureTime'] ?? 0) <=> ($fa['departureTime'] ?? 0),

            'airline_asc' =>
                ($fa['airline'] ?? '') <=> ($fb['airline'] ?? ''),

            'airline_desc' =>
                ($fb['airline'] ?? '') <=> ($fa['airline'] ?? ''),

            'gate_asc' =>
                ($fa['gate'] ?? '') <=> ($fb['gate'] ?? ''),

            'gate_desc' =>
                ($fb['gate'] ?? '') <=> ($fa['gate'] ?? ''),

            default =>
                ($fa['departureTime'] ?? 0) <=> ($fb['departureTime'] ?? 0),
        };
    });
    return $rows;
}

$upcomingFlights = sortFlightRows($upcomingFlights, $sortKey);

usort($pastFlights, function ($a, $b) {
  return ($b['flight']['departureTime'] ?? 0)
      <=> ($a['flight']['departureTime'] ?? 0);
});

// Force incomplete profiles onto the profile tab
if ($profileIncomplete && $activeTab !== 'profile') {
    header('Location: ?tab=profile');
    exit;
}

$updateMsg  = $_SESSION['flash_msg']          ?? null;
$guestMsg   = $_SESSION['flash_guest_msg']    ?? null;
$guestError = $_SESSION['flash_guest_error']  ?? null;
unset($_SESSION['flash_msg'], $_SESSION['flash_guest_msg'], $_SESSION['flash_guest_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'update_profile') {
        $email      = trim($_POST['email']      ?? '');
        $phone      = trim($_POST['phone']       ?? '');
        $street     = trim($_POST['street']      ?? '');
        $city       = trim($_POST['city']        ?? '');
        $state      = trim($_POST['state']       ?? '');
        $zip        = trim($_POST['zip']         ?? '');
        $dob        = trim($_POST['date_birth']  ?? '');
        $newPass    = trim($_POST['new_password']     ?? '');
        $confirmPass = trim($_POST['confirm_password'] ?? '');

        $errors = [];
        if ($email === '')  $errors[] = 'Email is required.';
        if ($phone === '')  $errors[] = 'Phone is required.';
        if ($street === '') $errors[] = 'Street address is required.';
        if ($city === '')   $errors[] = 'City is required.';
        if ($state === '')  $errors[] = 'State is required.';
        if ($zip === '')    $errors[] = 'ZIP code is required.';
        if ($dob === '')    $errors[] = 'Date of birth is required.';

        if ($missingPassword) {
            if ($newPass === '' || $confirmPass === '') {
                $errors[] = 'Password is required.';
            } elseif ($newPass !== $confirmPass) {
                $errors[] = 'Passwords do not match.';
            } elseif (strlen($newPass) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            }
        }

        if (!empty($errors)) {
            $_SESSION['flash_profile_error'] = implode(' ', $errors);
            header('Location: ?tab=profile');
            exit;
        }

        if ($missingPassword && $newPass !== '') {
            $upd = $pdo->prepare(
                'UPDATE "Users" SET email=?, phone=?, street_address=?, city=?, state=?, zip_code=?, date_birth=?, password=? WHERE user_id=?'
            );
            $upd->execute([$email, $phone, $street, $city, $state, $zip, $dob, $newPass, $_SESSION['user_id']]);
        } else {
            $upd = $pdo->prepare(
                'UPDATE "Users" SET email=?, phone=?, street_address=?, city=?, state=?, zip_code=?, date_birth=? WHERE user_id=?'
            );
            $upd->execute([$email, $phone, $street, $city, $state, $zip, $dob, $_SESSION['user_id']]);
        }

        $_SESSION['flash_msg'] = 'Profile updated successfully.';
        header('Location: ?tab=profile');
        exit;
    }

    if ($_POST['action'] === 'update_preferences') {
        $newPrefs = $prefs;

        if (in_array($_POST['auto_logout'] ?? '', ['5', '15', '60'])) {
            $newPrefs['auto_logout'] = $_POST['auto_logout'];
        }
        $sortOpts = ['time_asc', 'time_desc', 'airline_asc', 'airline_desc', 'gate_asc', 'gate_desc'];
        if (in_array($_POST['flight_sort'] ?? '', $sortOpts)) {
            $newPrefs['flight_sort'] = $_POST['flight_sort'];
        }

        $upd = $pdo->prepare('UPDATE "Users" SET sort_preference = ? WHERE user_id = ?');
        $upd->execute([json_encode($newPrefs), $_SESSION['user_id']]);

        $_SESSION['flash_msg'] = 'Preferences saved.';
        header('Location: ?tab=preferences');
        exit;
    }

    if ($_POST['action'] === 'add_guest_flight') {
        $conf = trim($_POST['confirmation_number'] ?? '');

        if ($conf === '') {
            $_SESSION['flash_guest_error'] = 'Please enter a confirmation code.';
        } else {
            $tStmt = $pdo->prepare('SELECT * FROM "Tickets" WHERE UPPER(confirmation_code) = UPPER(?) LIMIT 1');
            $tStmt->execute([$conf]);
            $foundTicket = $tStmt->fetch(PDO::FETCH_ASSOC);

            if (!$foundTicket) {
                $_SESSION['flash_guest_error'] = "No booking found with confirmation code \"{$conf}\".";
            } elseif ((int)($foundTicket['user_id'] ?? 0) === (int)$_SESSION['user_id']) {
                $_SESSION['flash_guest_error'] = 'This booking is already linked to your account.';
            } elseif (!empty($foundTicket['user_id'])) {
                $_SESSION['flash_guest_error'] = 'This booking is already linked to another account.';
            } else {
                $upd = $pdo->prepare('UPDATE "Tickets" SET user_id = ? WHERE ticket_id = ? AND user_id IS NULL');
                $upd->execute([$_SESSION['user_id'], $foundTicket['ticket_id']]);
                $_SESSION['flash_guest_msg'] = "Booking {$conf} has been linked to your account.";
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
      $cardNumber = formatCard($_POST['card_number'] ?? '');
            $expiry     = htmlspecialchars(trim($_POST['card_expiry']     ?? ''));

        if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry)) {
            $_SESSION['flash_msg'] = 'Invalid expiration format. Use MM/YY.';
            header('Location: ?tab=payment');
            exit;
        }
        
        [$expMonth, $expYear] = explode('/', $expiry);
        $expMonth = (int)$expMonth;
        $expYear  = (int)("20" . $expYear); 
        
        $currentYear  = (int)date('Y');
        $currentMonth = (int)date('m');
        
        if ($expYear < $currentYear || ($expYear === $currentYear && $expMonth < $currentMonth)) {
            $_SESSION['flash_msg'] = 'Card is expired. Please use a valid card.';
            header('Location: ?tab=payment');
            exit;
        }

        $cvc        = preg_replace('/\D/', '', trim($_POST['card_cvc']  ?? ''));
        $billing    = htmlspecialchars(trim($_POST['billing_address']  ?? ''));
        $zip        = htmlspecialchars(trim($_POST['billing_zip']      ?? ''));

        $cardName = trim($_POST['card_name'] ?? '');
        if ($cardName === '') {
            $cardName = $currentUser['name'];
        }
        $cardName = htmlspecialchars($cardName);
        $rawCard = preg_replace('/\D/', '', $_POST['card_number'] ?? '');

        if (strlen($rawCard) < 13 || strlen($rawCard) > 19) {
            $_SESSION['flash_msg'] = 'Please enter a valid card number.';
            header('Location: ?tab=payment');
            exit;
        }
        
        $cardNumber = formatCard($rawCard);


        $ins = $pdo->prepare(
            'INSERT INTO "Saved Cards" (user_id, card_number, expiration_date, cvc, billing_address, zip_code, card_name)
            VALUES (?,?,?,?,?,?,?)'
        );
        
        $ins->execute([
            $_SESSION['user_id'],
            $cardNumber, 
            $expiry,
            $cvc,
            $billing,
            $zip,
            $cardName
        ]);
        $_SESSION['flash_msg'] = 'Card saved.';
        header('Location: ?tab=payment');
        exit;
    }

    if ($_POST['action'] === 'delete_account') {
        $uid = $_SESSION['user_id'];

        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM "Saved Cards" WHERE user_id = ?')->execute([$uid]);
            $pdo->prepare('DELETE FROM "Tickets" WHERE user_id = ?')->execute([$uid]);
            $pdo->prepare('DELETE FROM "Users" WHERE user_id = ?')->execute([$uid]);
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_msg'] = 'Could not delete account. Please try again.';
            header('Location: ?tab=preferences');
            exit;
        }

        session_unset();
        session_destroy();
        header('Location: ../../../index.php');
        exit;
    }
}

$profileError = $_SESSION['flash_profile_error'] ?? null;
unset($_SESSION['flash_profile_error']);

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
.tab-disabled { color: rgb(75 85 99); pointer-events: none; cursor: not-allowed; }
</style>
</head>
<body class="bg-gray-900 min-h-screen text-white">

<script>
window.__autoLogoutMinutes = <?= (int)$currentUser['auto_logout'] ?>;
</script>

<?php include_once __DIR__ . '/../../../components/nav.php'; ?>


<main class="max-w-7xl mx-auto p-6">

  <div class="rounded-lg p-8 mb-6 bg-gradient-to-r from-slate-800 to-slate-900 border border-gray-700 shadow-lg">
    <p><span class="tracking-[0.25em] text-sm text-blue-300 mb-4">WELCOME BACK</span>👋</p>
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

  <?php if ($profileIncomplete): ?>
  <div class="mb-4 bg-amber-900/30 border border-amber-700 rounded-lg px-5 py-3 text-amber-400 text-sm">
    ⚠ Please complete your profile<?= $missingPassword ? ' and set a password' : '' ?> before continuing.
  </div>
  <?php endif; ?>

  <?php if ($updateMsg): ?>
  <div class="mb-4 bg-blue-900/30 border border-blue-700 rounded-lg px-5 py-3 text-blue-400 text-sm">
    <?= htmlspecialchars($updateMsg) ?>
  </div>
  <?php endif; ?>

<div class="mb-6 bg-gray-800 border border-gray-700 rounded-lg p-1">
  <div class="flex gap-2 overflow-x-auto sm:overflow-visible whitespace-nowrap sm:flex-wrap">    <?php
    $tabs = [
      'overview'    => 'Overview',
      'flights'     => 'My Flights',
      'profile'     => 'Profile',
      'payment'     => 'Payment',
      'preferences' => 'Preferences',
    ];
    foreach ($tabs as $key => $label):
      $isLocked = $profileIncomplete && $key !== 'profile';
      $cls = $isLocked ? 'tab-disabled' : (($activeTab === $key) ? 'tab-active' : 'tab-inactive');
    ?>
    <a href="<?= $isLocked ? '#' : '?tab=' . $key ?>"
      class="px-4 py-2 rounded-md text-sm font-medium transition flex-shrink-0 sm:flex-shrink <?= $cls ?>">
      <?= $label ?>
    </a>
    <?php endforeach; ?>
  </div>
    </div>

  <?php if ($activeTab === 'overview'): ?>

  <div class="grid xl:grid-cols-3 md:grid-cols-2 gap-4 mb-6">
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 overflow-hidden transition duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-blue-500">
      <p class="text-gray-400 text-sm">Upcoming Flights</p>
      <h2 class="text-4xl font-bold mt-2"><?= count($upcomingFlights) ?></h2>
    </div>
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 overflow-hidden transition duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-blue-500">
      <p class="text-gray-400 text-sm">Past Flights</p>
      <h2 class="text-4xl font-bold mt-2"><?= count($pastFlights) ?></h2>
    </div>
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 overflow-hidden transition duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-blue-500">
      <p class="text-gray-400 text-sm">Saved Cards</p>
      <h2 class="text-4xl font-bold mt-2"><?= count($savedCards) ?></h2>
    </div>
  </div>

  <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden mb-6">
    <div class="p-5 border-b border-gray-700 flex items-center justify-between">
      <h2 class="text-lg font-bold">Upcoming Flights ✈️</h2>
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
        <tbody id="overview-flights-body">
<?php if (!empty($upcomingFlights)): ?>

    <?php foreach (array_slice($upcomingFlights, 0, 5) as $row):

        $f = $row['flight'];
        $ticket = $row['ticket'];
        $fid = $f['flight_id'] ?? '';
        $isPast = (int)($f['departureTime'] ?? 0) > 0 && (int)$f['departureTime'] < $now;
    ?>

    <tr data-flight-id="<?= htmlspecialchars($fid) ?>"
        class="border-t border-gray-700 hover:bg-gray-700/40 transition <?= $isPast ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' ?>"
        <?php if (!$isPast): ?>onclick="window.location='../../ticket/ticket.php?confirmation=<?= urlencode($ticket['confirmation_code'] ?? '') ?>'"<?php endif; ?>>

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
    <div class="mb-4 bg-blue-900/30 border border-blue-700 rounded-lg px-5 py-3 text-blue-400 text-sm"><?= htmlspecialchars($guestMsg) ?></div>
  <?php endif; ?>
  <?php if ($guestError): ?>
  <div class="mb-4 bg-red-900/30 border border-red-700 rounded-lg px-5 py-3 text-red-400 text-sm">⚠ <?= htmlspecialchars($guestError) ?></div>
  <?php endif; ?>

  <div class="bg-gray-800 border border-gray-700 rounded-lg p-5 mb-6">
  <h3 class="font-semibold mb-4 flex items-center gap-2">
  Link a Booking by Confirmation Code

  <div class="relative group cursor-help">
    <span class="text-gray-400 hover:text-gray-200">?</span>

    <div class="absolute left-1/2 -translate-x-1/2 top-6 w-72 bg-gray-900 border border-gray-700 text-xs text-gray-300 rounded-lg p-3 shadow-lg opacity-0 group-hover:opacity-100 pointer-events-none transition z-50">If you have ever booked a flight as a guest, you can enter that confirmation number here and the flight will be linked to your account as if it was purchased while logged in.</div>
  </div>
</h3>
    <form method="POST" id="guest-flight-form" class="flex flex-wrap gap-3">
      <input type="hidden" name="action" value="add_guest_flight">
      <input type="text" name="confirmation_number" placeholder="Confirmation code (e.g. E5920205)" required
        class="flex-1 min-w-[180px] h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
      <button type="submit" class="h-10 px-5 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-semibold transition">
        Link Booking
      </button>
    </form>
    <p id="guest-flight-msg" class="mt-3 text-sm hidden"></p>
  </div>

  <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden mb-6">
    <div class="p-5 border-b border-gray-700 flex items-center justify-between">
      <h2 class="text-lg font-bold">Upcoming Flights 🎫</h2>
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
        <tbody id="upcoming-flights-body">
<?php if (!empty($upcomingFlights)): ?>

    <?php foreach ($upcomingFlights as $row):

        $f = $row['flight'];
        $ticket = $row['ticket'];
        $fid = $f['flight_id'] ?? '';
        $isPast = (int)($f['departureTime'] ?? 0) > 0 && (int)$f['departureTime'] < $now;
    ?>
    
    <tr data-flight-id="<?= htmlspecialchars($fid) ?>"
    class="border-t border-gray-700 hover:bg-gray-700/40 transition <?= $isPast ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' ?>"
    <?php if (!$isPast): ?>onclick="window.location='../../ticket/ticket.php?confirmation=<?= urlencode($ticket['confirmation_code'] ?? '') ?>'"<?php endif; ?>>

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
      <td colspan="8" class="px-5 py-8 text-center text-gray-500">
        No upcoming flights found.
      </td>
    </tr>

<?php endif; ?>
</tbody>
      </table>
    </div>
    <div id="upcoming-flights-pagination" class="flex items-center justify-between px-5 py-3 border-t border-gray-700 text-sm text-gray-400"></div>
  </div>

  <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden mb-6">
    <div class="p-5 border-b border-gray-700">
      <h2 class="text-lg font-bold">Past Flights 🎫</h2>
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
        <tbody id="past-flights-body">
        <?php foreach ($pastFlights as $row):

        $f = $row['flight'];
        $ticket = $row['ticket'];
        $fid = $f['flight_id'] ?? '';
        ?>
        <tr data-flight-id="<?= htmlspecialchars($fid) ?>"
    class="border-t border-gray-700 hover:bg-gray-700/40 transition opacity-50 cursor-not-allowed"
    title="This flight has already departed">

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
        <tr><td colspan="8" class="px-5 py-8 text-center text-gray-500">No past flights found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div id="past-flights-pagination" class="flex items-center justify-between px-5 py-3 border-t border-gray-700 text-sm text-gray-400"></div>
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

  // Client-side pagination
  function paginateTable(tbodyId, paginationId, perPage = 10) {
      const tbody = document.getElementById(tbodyId);
      const pager = document.getElementById(paginationId);
      if (!tbody || !pager) return;

      const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.querySelector('td[colspan]'));
      if (rows.length <= perPage) {
          pager.innerHTML = '';
          return;
      }

      let currentPage = 1;
      const totalPages = Math.ceil(rows.length / perPage);

      function render() {
          rows.forEach((row, i) => {
              const page = Math.floor(i / perPage) + 1;
              row.style.display = (page === currentPage) ? '' : 'none';
          });

          pager.innerHTML = `
            <span>Page ${currentPage} of ${totalPages}</span>
            <div class="flex gap-2">
              <button type="button" data-dir="-1" class="px-3 py-1 rounded bg-gray-700 hover:bg-gray-600 disabled:opacity-40 disabled:cursor-not-allowed" ${currentPage === 1 ? 'disabled' : ''}>Prev</button>
              <button type="button" data-dir="1" class="px-3 py-1 rounded bg-gray-700 hover:bg-gray-600 disabled:opacity-40 disabled:cursor-not-allowed" ${currentPage === totalPages ? 'disabled' : ''}>Next</button>
            </div>
          `;

          pager.querySelectorAll('button').forEach(btn => {
              btn.addEventListener('click', () => {
                  currentPage += parseInt(btn.dataset.dir, 10);
                  currentPage = Math.max(1, Math.min(totalPages, currentPage));
                  render();
              });
          });
      }

      render();
  }

  paginateTable('upcoming-flights-body', 'upcoming-flights-pagination', 10);
  paginateTable('past-flights-body', 'past-flights-pagination', 10);

  // AJAX guest flight linking
  const guestForm = document.getElementById('guest-flight-form');
  const guestMsgEl = document.getElementById('guest-flight-msg');
  guestForm?.addEventListener('submit', async function (e) {
      e.preventDefault();
      const formData = new FormData(guestForm);
      guestMsgEl.classList.add('hidden');
      try {
          const res = await fetch(window.location.pathname + '?tab=flights', {
              method: 'POST',
              body: formData,
              headers: { 'X-Requested-With': 'XMLHttpRequest' },
              redirect: 'manual'
          });
          // Server still redirects via header(); reload to pick up flash message reliably.
          window.location.href = '?tab=flights';
      } catch (err) {
          guestMsgEl.textContent = 'Something went wrong. Please try again.';
          guestMsgEl.className = 'mt-3 text-sm text-red-400';
      }
  });
  </script>

  <?php endif; ?>

  <?php if ($activeTab === 'profile'): ?>

  <?php if ($profileError): ?>
  <div class="mb-4 bg-red-900/30 border border-red-700 rounded-lg px-5 py-3 text-red-400 text-sm">⚠ <?= htmlspecialchars($profileError) ?></div>
  <?php endif; ?>

  <div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
      <h2 class="text-lg font-bold mb-5">Personal Information 👤</h2>
      <form method="POST" id="profile-form" class="space-y-4" novalidate>
        <input type="hidden" name="action" value="update_profile">
        <div>
          <label class="block text-sm text-gray-400 mb-1">Full Name</label>
          <input type="text" value="<?= htmlspecialchars($currentUser['name']) ?>" disabled
            class="w-full h-10 bg-gray-700/50 border border-gray-600 rounded-lg px-4 text-gray-400 text-sm cursor-not-allowed">
        </div>
        <div>
          <label class="block text-sm text-gray-400 mb-1">Email Address*</label>
          <input type="email" name="email" required value="<?= htmlspecialchars($currentUser['email']) ?>"
            class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
        <label class="block text-sm text-gray-400 mb-1">Phone Number*</label>
        <input 
          type="text" 
          name="phone" 
          required 
          maxlength="15"
          inputmode="tel"
          value="<?= htmlspecialchars($currentUser['phone']) ?>"
          oninput="this.value = this.value.replace(/[^0-9()-]/g, '').slice(0, 15)"
          class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        </div>
        <div>
          <label class="block text-sm text-gray-400 mb-1">Date of Birth*</label>
          <input type="date" name="date_birth" required value="<?= htmlspecialchars($dbUser['date_birth'] ?? $dbUser['date_of_birth'] ?? '') ?>"
            class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm text-gray-400 mb-1">Street Address*</label>
          <input type="text" name="street" required value="<?= htmlspecialchars($dbUser['street_address'] ?? '') ?>"
            class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="block text-sm text-gray-400 mb-1">City*</label>
            <input type="text" name="city" required value="<?= htmlspecialchars($dbUser['city'] ?? '') ?>"
              class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>
          <div>
            <label class="block text-sm text-gray-400 mb-1">State*</label>
            <input type="text" name="state" required value="<?= htmlspecialchars($dbUser['state'] ?? '') ?>"
              class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>
          <div>
            <label class="block text-sm text-gray-400 mb-1">ZIP Code*</label>
            <input type="text" name="zip" required value="<?= htmlspecialchars($dbUser['zip_code'] ?? '') ?>"
              class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>
        </div>

        <?php if ($missingPassword): ?>
        <div class="border-t border-gray-700 pt-4 mt-2">
          <p class="text-sm text-amber-400 mb-3">A password is required for your account.</p>
          <div class="space-y-3">
            <div>
              <label class="block text-sm text-gray-400 mb-1">New Password*</label>
              <input type="password" name="new_password" required minlength="8"
                class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm text-gray-400 mb-1">Confirm Password*</label>
              <input type="password" name="confirm_password" required minlength="8"
                class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
          </div>
        </div>
        <?php endif; ?>

        <button type="submit" class="w-full h-10 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-semibold transition">
          Save Changes
        </button>
      </form>
    </div>

    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
  <h2 class="text-lg font-bold mb-5">Account Details ⚙️</h2>

  <div class="space-y-1">
    <?php
    $details = [
      'Customer ID'   => '<code class="text-sm bg-gray-700 px-2 py-0.5 rounded text-gray-300">' . htmlspecialchars($currentUser['id']) . '</code>',
      'Last Login'    => htmlspecialchars($currentUser['last_login_datetime']),
      'Last Login IP' => '<code class="text-sm bg-gray-700 px-2 py-0.5 rounded text-gray-300">' . htmlspecialchars($currentUser['last_login_ip']) . '</code>',
      'Date of Birth'  => htmlspecialchars($dbUser['date_birth'] ?? $dbUser['date_of_birth'] ?? '—'),
      'Role'          => htmlspecialchars($dbUser['role'] ?? '—'),
      'Auto-Logout'   => $currentUser['auto_logout'] === '60' ? '1 hour' : $currentUser['auto_logout'] . ' minutes',
    ];

    foreach ($details as $label => $val): ?>
      <div class="flex justify-between items-center py-3 px-3 rounded-md border-b border-gray-700 last:border-0 transition-all duration-200 hover:bg-gray-700/40  hover:shadow-sm">
        
        <span class="text-gray-400 text-sm transition-colors duration-200 group-hover:text-gray-300">
          <?= $label ?>
        </span>

        <span class="text-sm text-gray-200">
          <?= $val ?>
        </span>
      </div>
    <?php endforeach; ?>
  </div>
</div>
  <script>
  const profileForm = document.getElementById('profile-form');
  profileForm?.addEventListener('submit', function (e) {
      const required = profileForm.querySelectorAll('[required]');
      let firstInvalid = null;
      for (const field of required) {
          if (!field.value || !field.value.trim()) {
              if (!firstInvalid) firstInvalid = field;
          }
      }
      const newPass = profileForm.querySelector('[name="new_password"]');
      const confirmPass = profileForm.querySelector('[name="confirm_password"]');
      if (newPass && confirmPass && newPass.value !== confirmPass.value) {
          e.preventDefault();
          alert('Passwords do not match.');
          confirmPass.focus();
          return;
      }
      if (firstInvalid) {
          e.preventDefault();
          firstInvalid.focus();
          firstInvalid.classList.add('ring-2', 'ring-red-500');
      }
  });
  </script>

  <?php endif; ?>

  <?php if ($activeTab === 'payment'): ?>

  <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 mb-6">
    <h2 class="text-lg font-bold mb-5">Saved Cards 🏦</h2>
    <div class="space-y-3 mb-6" id="saved-cards-list">
      <?php foreach ($savedCards as $card): ?>
        <div class="flex items-center justify-between bg-gray-700/50 border border-gray-600 rounded-lg px-5 py-4 transition-all duration-200 ease-in-out hover:bg-gray-700/80 hover:border-gray-500 hover:-translate-y-0.5 hover:shadow-md">
        <div class="flex items-center gap-4">
          <div class="w-10 h-7 bg-gray-600 rounded flex items-center justify-center text-xs font-bold text-gray-300">
            💳
          </div>
          <div>  <!-- -->
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
    <div id="saved-cards-pagination" class="flex items-center justify-between text-sm text-gray-400 mb-2"></div>

    <div class="border-t border-gray-700 pt-5">
      <h3 class="font-semibold mb-4 text-sm text-gray-300">Save New Card 📌</h3>
      <form method="POST" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <input type="hidden" name="action" value="add_card">
        <div class="sm:col-span-2">
          <label class="block text-xs text-gray-400 mb-1">Cardholder Name</label>
          <input type="text" name="card_name" placeholder="Name on card"
            value="<?= htmlspecialchars($currentUser['name']) ?>"
            class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="sm:col-span-2">
        <label class="block text-xs text-gray-400 mb-1">Card Name (Optional)</label>
        <input type="text" name="card_name" placeholder="ex. <?= htmlspecialchars($currentUser['name']) ?>'s card" class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>
        <div class="sm:col-span-2">
          <label class="block text-xs text-gray-400 mb-1">Card Number</label>
          <input type="text" id="cardNumber" name="card_number" maxlength="19"
              oninput="this.value=this.value.replace(/\D/g,'').replace(/(.{4})/g,'$1 ').trim()"
            class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Expiration Date (MM/YY)</label>
          <input type="text" name="card_expiry" placeholder="MM/YY" maxlength="5"
            class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">CVC</label>
          <input type="text" name="card_cvc" maxlength="4"
            class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Billing Address</label>
          <input type="text" name="billing_address" 
            class="w-full h-10 bg-gray-700 border border-gray-600 rounded-lg px-4 text-sm text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">ZIP Code</label>
          <input type="text" name="billing_zip" maxlength="10"
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

  <script>
    const cardInput = document.getElementById("cardNumber");

cardInput?.addEventListener("input", function () {
    let value = this.value.replace(/\D/g, ""); 
    value = value.substring(0, 16);

    this.value = value.replace(/(.{4})/g, "$1 ").trim();
});
  (function () {
      const perPage = 10;
      const list = document.getElementById('saved-cards-list');
      const pager = document.getElementById('saved-cards-pagination');
      if (!list || !pager) return;

      const items = Array.from(list.querySelectorAll('.saved-card-item'));
      if (items.length <= perPage) return;

      let currentPage = 1;
      const totalPages = Math.ceil(items.length / perPage);

      function render() {
          items.forEach((item, i) => {
              const page = Math.floor(i / perPage) + 1;
              item.style.display = (page === currentPage) ? '' : 'none';
          });

          pager.innerHTML = `
            <span>Page ${currentPage} of ${totalPages}</span>
            <div class="flex gap-2">
              <button type="button" data-dir="-1" class="px-3 py-1 rounded bg-gray-700 hover:bg-gray-600 disabled:opacity-40 disabled:cursor-not-allowed" ${currentPage === 1 ? 'disabled' : ''}>Prev</button>
              <button type="button" data-dir="1" class="px-3 py-1 rounded bg-gray-700 hover:bg-gray-600 disabled:opacity-40 disabled:cursor-not-allowed" ${currentPage === totalPages ? 'disabled' : ''}>Next</button>
            </div>
          `;

          pager.querySelectorAll('button').forEach(btn => {
              btn.addEventListener('click', () => {
                  currentPage += parseInt(btn.dataset.dir, 10);
                  currentPage = Math.max(1, Math.min(totalPages, currentPage));
                  render();
              });
          });
      }

      render();
  })();
  </script>

  <?php endif; ?>

  <?php if ($activeTab == 'preferences'): ?>

<div class="grid lg:grid-cols-2 gap-6">

  <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
    <h2 class="text-lg font-bold mb-5">Preferences ⚙️</h2>

    <form method="POST" class="space-y-8">
      <input type="hidden" name="action" value="update_preferences">

      <div>
        <label class="block text-sm text-gray-400 mb-3">
          Default Flight Sort Order
        </label>

        <div class="grid sm:grid-cols-2 gap-2">
          <?php
          $sortOptions = [
            'time_asc'     => 'Time ↑ (Earliest First)',
            'time_desc'    => 'Time ↓ (Latest First)',
            'airline_asc'  => 'Airline A → Z',
            'airline_desc' => 'Airline Z → A',
            'gate_asc'     => 'Gate A → Z',
            'gate_desc'    => 'Gate Z → A',
          ];
          foreach ($sortOptions as $val => $label):
            $checked = $currentUser['flight_sort'] === $val ? 'checked' : '';
          ?>
            <label class="flex items-center gap-3 bg-gray-700/40 border border-gray-600 rounded-lg px-3 py-2 hover:bg-gray-700 transition cursor-pointer">
              <input type="radio" name="flight_sort" value="<?= $val ?>" <?= $checked ?> class="accent-blue-500">
              <span class="text-sm"><?= $label ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div>
        <label class="block text-sm text-gray-400 mb-3">
          Auto-Logout Time
        </label>

        <div class="grid sm:grid-cols-3 gap-2">
          <?php
          $logoutOptions = [
            '5'  => '5 min',
            '15' => '15 min',
            '60' => '1 hour'
          ];
          foreach ($logoutOptions as $val => $label):
            $checked = $currentUser['auto_logout'] === $val ? 'checked' : '';
          ?>
            <label class="flex items-center justify-center gap-2 bg-gray-700/40 border border-gray-600 rounded-lg px-3 py-2 hover:bg-gray-700 transition cursor-pointer">
              <input type="radio" name="auto_logout" value="<?= $val ?>" <?= $checked ?> class="accent-blue-500">
              <span class="text-sm"><?= $label ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <button type="submit"
        class="w-full h-11 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-semibold transition">
        Save Preferences
      </button>
    </form>
  </div>

  <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 flex flex-col justify-between transform transition-all duration-300 ease-in-out hover:scale-[1.005] hover:border-red-700 hover:shadow">    <div>
      <h2 class="text-lg font-bold text-red-400">Delete Your Account 🗑️</h2>
      <p class="text-sm text-gray-400 mt-2">
        Permanently delete your account and all associated data. This action can never be undone, so think carefully.
      </p>
    </div>

    <form method="POST" id="delete-account-form" class="mt-6">
      <input type="hidden" name="action" value="delete_account">

      <button type="submit"
        class="w-full h-11 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-semibold transition">
        Delete My Account
      </button>
    </form>
  </div>

</div>

<script>
document.getElementById('delete-account-form')?.addEventListener('submit', function (e) {
    if (!confirm('Are you sure you want to permanently delete your account? This cannot be undone.')) {
        e.preventDefault();
    }
});
</script>

<?php endif; ?>
</main>

</body>
</html>