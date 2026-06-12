<?php
require_once '../../../api/key.php';
require_once '../../../api/api.php';
require_once '../../../database/db.php';

$api = new AirportsAPI(AIRPORTS_API_KEY);

$allFlightsData = $api->getAllFlights();
$allFlights     = $allFlightsData['flights'] ?? [];

$flightMap = [];
foreach ($allFlights as $f) {
    $fid = $f['flightId'] ?? $f['id'] ?? '';
    if ($fid) $flightMap[$fid] = $f;
}

$ticketsStmt = $pdo->query('SELECT * FROM "Tickets"');
$allTickets  = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);

$usersStmt = $pdo->query('SELECT * FROM "Users" WHERE LOWER(role) = \'customer\' ORDER BY user_id ASC');
$allUsers  = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$now      = time();
$daySec   = 86400;
$weekSec  = 7  * $daySec;
$monthSec = 30 * $daySec;
$yearSec  = 365 * $daySec;

$ticketStats = ['day' => 0, 'week' => 0, 'month' => 0, 'year' => 0, 'all' => 0];
$profitStats = ['day' => 0.0, 'week' => 0.0, 'month' => 0.0, 'year' => 0.0, 'all' => 0.0];

foreach ($allTickets as $t) {
    if (strtolower($t['status'] ?? '') === 'cancelled') continue;

    $created = isset($t['created_at']) ? strtotime($t['created_at']) : 0;

    $rawPrice = $t['price'] ?? '0';
    $price    = is_numeric($rawPrice) ? (float)$rawPrice : 0.0;
    if ($price > 100000) $price = 0.0;

    $age = $now - $created;

    $ticketStats['all']++;
    $profitStats['all'] += $price;
    if ($age <= $daySec)   { $ticketStats['day']++;   $profitStats['day']   += $price; }
    if ($age <= $weekSec)  { $ticketStats['week']++;  $profitStats['week']  += $price; }
    if ($age <= $monthSec) { $ticketStats['month']++; $profitStats['month'] += $price; }
    if ($age <= $yearSec)  { $ticketStats['year']++;  $profitStats['year']  += $price; }
}

$updateMsg = null;
$errorMsg  = null;
$activeTab = $_GET['tab'] ?? 'overview';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'create_customer') {
        $fn    = trim($_POST['first_name']  ?? '');
        $mn    = trim($_POST['middle_name'] ?? '');
        $ln    = trim($_POST['last_name']   ?? '');
        $email = trim($_POST['email']       ?? '');
        $phone = trim($_POST['phone']       ?? '');
        $pw    = trim($_POST['password']    ?? '');

        if ($fn && $ln && $email) {
            if (strlen($pw) < 8) $pw = 'ChangeMe1!';

            $hashed = password_hash($pw, PASSWORD_BCRYPT);

            $ins = $pdo->prepare(
                'INSERT INTO "Users" (first_name, middle_name, last_name, email, phone, password, role)
                 VALUES (?,?,?,?,?,?,?)'
            );
            $ins->execute([$fn, $mn ?: null, $ln, $email, $phone ?: null, $hashed, 'Customer']);

            $updateMsg = "Customer {$fn} {$ln} created successfully.";

            $usersStmt = $pdo->query('SELECT * FROM "Users" WHERE LOWER(role) = \'customer\' ORDER BY user_id ASC');
            $allUsers  = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $errorMsg = 'First name, last name, and email are required.';
        }
        $activeTab = 'customers';
    }

    if ($_POST['action'] === 'update_customer') {
        $uid    = $_POST['user_id']        ?? '';
        $email  = trim($_POST['email']     ?? '');
        $phone  = trim($_POST['phone']     ?? '');
        $street = trim($_POST['street']    ?? '');
        $city   = trim($_POST['city']      ?? '');
        $state  = trim($_POST['state']     ?? '');
        $zip    = trim($_POST['zip']       ?? '');
        $country= trim($_POST['country']   ?? '');

        $upd = $pdo->prepare(
            'UPDATE "Users"
             SET email=?, phone=?, street_address=?, city=?, state=?, zip_code=?, country=?
             WHERE id=? AND LOWER(role)=\'customer\''
        );
        $upd->execute([$email, $phone, $street, $city, $state, $zip, $country, $uid]);
        $updateMsg = 'Customer updated successfully.';

        $usersStmt = $pdo->query('SELECT * FROM "Users" WHERE LOWER(role) = \'customer\' ORDER BY id ASC');
        $allUsers  = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
        $activeTab = 'customers';
    }

    if ($_POST['action'] === 'create_ticket') {
        $fid   = trim($_POST['flight_id']  ?? '');
        $ln    = trim($_POST['last_name']  ?? '');
        $seat  = trim($_POST['seat']       ?? '');
        $price = trim($_POST['price']      ?? '0');
        $uid   = trim($_POST['user_id']    ?? '');

        if ($fid && $ln) {
            $code = strtoupper(bin2hex(random_bytes(4)));
            $ins = $pdo->prepare(
                'INSERT INTO "Tickets" (flight_id, name_last, confirmation_code, seat, price, user_id, status, created_at)
                 VALUES (?,?,?,?,?,?,?,NOW())'
            );
            $ins->execute([$fid, $ln, $code, $seat ?: null, $price, $uid ?: null, 'active']);
            $updateMsg = "Ticket created. Confirmation: {$code}";

            $ticketsStmt = $pdo->query('SELECT * FROM "Tickets"');
            $allTickets  = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $errorMsg = 'Flight ID and last name are required.';
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
        $activeTab = 'tickets';
    }
}

function fmtTs($ts): string {
    if (!$ts) return '—';
    return is_numeric($ts) ? date('M j, Y H:i', (int)$ts) : date('M j, Y H:i', strtotime($ts));
}

function statusBadge(string $status): string {
    $cls = match(strtolower($status)) {
        'active'               => 'badge-active',
        'cancelled','refunded' => 'badge-cancelled',
        default                => 'badge-other',
    };
    return "<span class=\"badge {$cls}\">" . htmlspecialchars(ucfirst($status)) . "</span>";
}

function flightStatusBadge(string $status): string {
    $cls = match(strtolower($status)) {
        'scheduled'           => 'badge-scheduled',
        'on time'             => 'badge-active',
        'boarding'            => 'badge-boarding',
        'departed'            => 'badge-other',
        'cancelled'           => 'badge-cancelled',
        'delayed'             => 'badge-delayed',
        'landed','arrived'    => 'badge-landed',
        'past'                => 'badge-past',
        default               => 'badge-other',
    };
    return "<span class=\"badge {$cls}\">" . htmlspecialchars(ucfirst($status)) . "</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — BDPA Airports</title>
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

  .badge { display:inline-flex; align-items:center; padding: 2px 10px; border-radius:9999px; font-size:.72rem; font-weight:600; white-space:nowrap; }
  .badge-active    { background:rgba(16,185,129,.15); color:#34d399; border:1px solid rgba(16,185,129,.3); }
  .badge-cancelled { background:rgba(239,68,68,.15);  color:#f87171; border:1px solid rgba(239,68,68,.3); }
  .badge-other     { background:rgba(99,102,241,.15); color:#a5b4fc; border:1px solid rgba(99,102,241,.3); }
  .badge-scheduled { background:rgba(59,130,246,.15); color:#93c5fd; border:1px solid rgba(59,130,246,.3); }
  .badge-boarding  { background:rgba(245,158,11,.15); color:#fcd34d; border:1px solid rgba(245,158,11,.3); }
  .badge-delayed   { background:rgba(249,115,22,.15); color:#fdba74; border:1px solid rgba(249,115,22,.3); }
  .badge-landed    { background:rgba(16,185,129,.15); color:#6ee7b7; border:1px solid rgba(16,185,129,.3); }
  .badge-past      { background:rgba(107,114,128,.15);color:#9ca3af; border:1px solid rgba(107,114,128,.3); }

  tbody tr { border-top: 1px solid #1f2937; transition: background .12s; }
  tbody tr:hover { background: rgba(55,65,81,.45); }
  .cancelled-row { opacity:.5; }

  .field { width:100%; height:2.375rem; background:#1f2937; border:1px solid #374151; border-radius:.5rem;
           padding:0 .875rem; font-size:.875rem; color:#f1f5f9; outline:none; transition: border-color .15s; }
  .field:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.2); }

  .stat-card { background:#161b27; border:1px solid #1f2937; border-radius:.75rem; padding:1.5rem; }

  ::-webkit-scrollbar { width:6px; height:6px; }
  ::-webkit-scrollbar-track { background:#0f1117; }
  ::-webkit-scrollbar-thumb { background:#374151; border-radius:3px; }

  .table-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; }
</style>
</head>
<body class="min-h-screen">

<header class="h-14 bg-gray-900 border-b border-gray-800 flex items-center px-6 justify-between sticky top-0 z-40">
  <div class="flex items-center gap-3">
    <span class="text-gray-400 text-sm hidden sm:block">Placeholder Admin Dashboard</span>
  </div>
  <span class="text-xs text-gray-500"><?= date('D, M j Y · H:i') ?></span>
</header>

<main class="max-w-7xl mx-auto p-4 sm:p-6 space-y-5">

  <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
    <p class="text-xs uppercase tracking-widest text-blue-500 font-semibold mb-1">Airport BDPA</p>
    <h1 class="text-3xl font-extrabold">Admin Dashboard</h1>
    <p class="text-gray-400 text-sm mt-1">Manage customers, tickets, and airport operations.</p>
  </div>

  <?php if ($updateMsg): ?>
  <div class="bg-emerald-950 border border-emerald-700 rounded-lg px-5 py-3 text-emerald-400 text-sm flex items-center gap-2">
    <span>✓</span><?= htmlspecialchars($updateMsg) ?>
  </div>
  <?php endif; ?>
  <?php if ($errorMsg): ?>
  <div class="bg-red-950 border border-red-700 rounded-lg px-5 py-3 text-red-400 text-sm flex items-center gap-2">
    <span>⚠</span><?= htmlspecialchars($errorMsg) ?>
  </div>
  <?php endif; ?>

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
        data-profit="<?= number_format($profitStats[$k], 2) ?>">
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
      <p class="text-gray-600 text-xs mt-2">Active tickets only</p>
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

  <?php endif;?>

  <?php if ($activeTab === 'customers'): ?>

  <?php
  $editUser = null;
  if (isset($_GET['edit'])) {
    foreach ($allUsers as $u) {
      if ((string)$u['user_id'] === (string)$_GET['edit']) { $editUser = $u; break; }
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
  ?>

  <div class="grid lg:grid-cols-2 gap-5">
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
      <h2 class="font-bold text-base mb-4">Create New Customer</h2>
      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="create_customer">
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
          <input type="email" name="email" required class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Phone</label>
          <input type="text" name="phone" class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Password <span class="text-gray-600">(min 8 chars)</span></label>
          <input type="text" name="password" placeholder="Leave blank for default" class="field">
        </div>
        <button type="submit" class="w-full h-10 bg-blue-600 hover:bg-blue-500 rounded-lg text-sm font-semibold transition">
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
      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="update_customer">
        <input type="hidden" name="user_id" value="<?= htmlspecialchars($editUser['id']) ?>">
        <div>
          <label class="block text-xs text-gray-400 mb-1">Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($editUser['email'] ?? '') ?>" class="field">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Phone</label>
          <input type="text" name="phone" value="<?= htmlspecialchars($editUser['phone'] ?? '') ?>" class="field">
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
          placeholder="Search name, email, address…"
          class="field h-9 w-64">
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
          <td class="px-5 py-3 text-gray-500 font-mono text-xs"><?= (int)$u['user_id'] ?></td>
          <td class="px-5 py-3 font-semibold"><?= htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?></td>
          <td class="px-5 py-3 text-gray-400"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
          <td class="px-5 py-3 text-gray-400"><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
          <td class="px-5 py-3 text-gray-400"><?= htmlspecialchars($u['city'] ?? '—') ?></td>
          <td class="px-5 py-3 text-gray-400"><?= htmlspecialchars($u['country'] ?? '—') ?></td>
          <td class="px-5 py-3">
            <a href="?tab=customers&edit=<?= urlencode($u['user_id']) ?>" class="text-blue-400 hover:text-blue-300 transition text-xs font-semibold">Edit</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($filteredUsers)): ?>
        <tr><td colspan="7" class="px-5 py-10 text-center text-gray-600">No customers found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
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
        ($t['name_last']         ?? '') . ' ' .
        ($t['name_first']        ?? '') . ' ' .
        ($t['flight_id']         ?? '') . ' ' .
        ($t['status']            ?? '') . ' ' .
        ($f['origin']            ?? '') . ' ' .
        ($f['destination']       ?? '') . ' ' .
        ($f['flightNumber']      ?? '')
      ), $q);
    });
  }
  ?>

  <div class="grid lg:grid-cols-2 gap-5">
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
      <h2 class="font-bold text-base mb-4">Create New Ticket</h2>
      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="create_ticket">
        <div>
          <label class="block text-xs text-gray-400 mb-1">Flight ID <span class="text-red-400">*</span></label>
          <input type="text" name="flight_id" required class="field" placeholder="e.g. 6a2b68204b07f8ac">
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Last Name on Ticket <span class="text-red-400">*</span></label>
          <input type="text" name="last_name" required class="field">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-gray-400 mb-1">Seat</label>
            <input type="text" name="seat" placeholder="e.g. 14A" class="field">
          </div>
          <div>
            <label class="block text-xs text-gray-400 mb-1">Price ($)</label>
            <input type="number" name="price" step="0.01" min="0" placeholder="0.00" class="field">
          </div>
        </div>
        <div>
          <label class="block text-xs text-gray-400 mb-1">Customer User ID <span class="text-gray-600">(optional)</span></label>
          <input type="text" name="user_id" placeholder="Leave blank for guest ticket" class="field">
        </div>
        <button type="submit" class="w-full h-10 bg-blue-600 hover:bg-blue-500 rounded-lg text-sm font-semibold transition">
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
          $rows = [
            'Confirmation' => '<code class="bg-gray-800 px-2 py-0.5 rounded font-mono text-blue-300">' . htmlspecialchars($lookupTicket['confirmation_code']) . '</code>',
            'Passenger'    => htmlspecialchars(trim(($lookupTicket['name_first'] ?? '') . ' ' . ($lookupTicket['name_last'] ?? '—'))),
            'Flight ID'    => '<code class="bg-gray-800 px-2 py-0.5 rounded font-mono text-xs text-gray-300">' . htmlspecialchars($lookupTicket['flight_id'] ?? '—') . '</code>',
            'Seat'         => htmlspecialchars($lookupTicket['seat'] ?? '—'),
            'Price'        => '$' . number_format((float)($lookupTicket['price'] ?? 0), 2),
            'Status'       => statusBadge($lookupTicket['status'] ?? 'unknown'),
          ];
          if ($lookupFlight) {
            $rows['Route']     = htmlspecialchars(($lookupFlight['origin'] ?? '?') . ' → ' . ($lookupFlight['destination'] ?? '?'));
            $rows['Departure'] = fmtTs($lookupFlight['departureTime'] ?? 0);
          }
          foreach ($rows as $label => $val): ?>
          <div class="flex justify-between items-center py-2">
            <span class="text-gray-500"><?= $label ?></span>
            <span><?= $val ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if (strtolower($lookupTicket['status'] ?? '') !== 'cancelled'): ?>
        <form method="POST" class="mt-4">
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
          No ticket found for confirmation code "<strong><?= htmlspecialchars($confLookup) ?></strong>".
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
          placeholder="Search flight, route, name, code…"
          class="field h-9 w-64">
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
            <th class="text-left px-4 py-3">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($filteredTickets as $t):
          $f          = $flightMap[$t['flight_id'] ?? ''] ?? [];
          $depTs      = (int)($f['departureTime'] ?? 0);
          $isUpcoming = $depTs >= $now || $depTs === 0; // allow cancel if flight unknown
          $isCancelled= strtolower($t['status'] ?? '') === 'cancelled';
          $passengerName = trim(($t['name_first'] ?? '') . ' ' . ($t['name_last'] ?? ''));
          if (!$passengerName) $passengerName = '—';
          $rawP = $t['price'] ?? '0';
          $safeP = (is_numeric($rawP) && (float)$rawP < 100000) ? '$' . number_format((float)$rawP, 2) : '—';
        ?>
        <tr class="<?= $isCancelled ? 'cancelled-row' : '' ?>">
          <td class="px-4 py-3">
            <code class="text-xs bg-gray-800 px-2 py-1 rounded text-blue-300 font-mono"><?= htmlspecialchars($t['confirmation_code'] ?? '—') ?></code>
          </td>
          <td class="px-4 py-3 text-gray-300"><?= htmlspecialchars($passengerName) ?></td>
          <td class="px-4 py-3 font-semibold text-xs">
            <?= htmlspecialchars($f['flightNumber'] ?? ($t['flight_id'] ? '…' . substr($t['flight_id'], -5) : '—')) ?>
          </td>
          <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">
            <?= htmlspecialchars(($f['origin'] ?? '?') . ' → ' . ($f['destination'] ?? '?')) ?>
          </td>
          <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap"><?= $depTs ? fmtTs($depTs) : '—' ?></td>
          <td class="px-4 py-3 text-gray-400 text-xs"><?= htmlspecialchars($t['seat'] ?? '—') ?></td>
          <td class="px-4 py-3 text-gray-300 text-xs font-mono"><?= $safeP ?></td>
          <td class="px-4 py-3"><?= statusBadge($t['status'] ?? 'unknown') ?></td>
          <td class="px-4 py-3">
            <?php if (!$isCancelled): ?>
            <form method="POST" class="inline">
              <input type="hidden" name="action"    value="cancel_ticket">
              <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($t['ticket_id']) ?>">
              <button type="submit"
                onclick="return confirm('Cancel ticket <?= htmlspecialchars($t['confirmation_code'] ?? '') ?>?')"
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
    <div class="px-4 py-3 border-t border-gray-800 text-xs text-gray-600">
      <?= count($filteredTickets) ?> ticket<?= count($filteredTickets) !== 1 ? 's' : '' ?> shown
    </div>
  </div>

  <?php endif; ?>

</main>

<script>
const periodLabels = {
  day: 'Today', week: 'This Week', month: 'This Month', year: 'This Year', all: 'All Time'
};

function setPeriod(key) {
  document.querySelectorAll('.period-btn').forEach(b => {
    b.classList.remove('period-active');
  });
  const btn = document.getElementById('period-' + key);
  if (!btn) return;
  btn.classList.add('period-active');

  const tickets = parseInt(btn.dataset.tickets || 0);
  const profit  = parseFloat(btn.dataset.profit  || 0);

  document.getElementById('stat-tickets').textContent =
    tickets.toLocaleString('en-US');
  document.getElementById('stat-profit').textContent =
    '$' + profit.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  document.getElementById('stat-period-label').textContent =
    'Active tickets · ' + (periodLabels[key] || key);
}

document.addEventListener('DOMContentLoaded', () => setPeriod('day'));
</script>

</body>
</html>