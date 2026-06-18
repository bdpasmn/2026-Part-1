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

$usersStmt = $pdo->query('SELECT * FROM "Users"');
$allUsers  = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$admins    = array_values(array_filter($allUsers, fn($u) => in_array(strtolower($u['role'] ?? ''), ['admin', 'root'])));
$customers = array_values(array_filter($allUsers, fn($u) => strtolower($u['role'] ?? '') === 'customer'));


$now      = time();
$daySec   = 86400;
$weekSec  = 7  * $daySec;
$monthSec = 30 * $daySec;
$yearSec  = 365 * $daySec;

$ticketStats = ['day' => 0, 'week' => 0, 'month' => 0, 'year' => 0, 'all' => 0];
$profitStats = ['day' => 0.0, 'week' => 0.0, 'month' => 0.0, 'year' => 0.0, 'all' => 0.0];

foreach ($allTickets as $t) {
    if (strtolower($t['status'] ?? '') === 'cancelled') continue;
    $rawPrice = $t['price'] ?? '0';
    $price    = 0.0;
    if (is_numeric($rawPrice)) {
        $p = (float)$rawPrice;
        if ($p >= 0 && $p <= 100000) $price = $p;
    }
    $created = !empty($t['created_at']) ? strtotime($t['created_at']) : $now;
    $age     = $now - $created;

    $ticketStats['all']++;
    $profitStats['all'] += $price;
    if ($age <= $daySec)   { $ticketStats['day']++;   $profitStats['day']   += $price; }
    if ($age <= $weekSec)  { $ticketStats['week']++;  $profitStats['week']  += $price; }
    if ($age <= $monthSec) { $ticketStats['month']++; $profitStats['month'] += $price; }
    if ($age <= $yearSec)  { $ticketStats['year']++;  $profitStats['year']  += $price; }
}

$activeTab = $_GET['tab'] ?? 'overview';
$updateMsg = null;
$errorMsg  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'create_admin') {
        $fn    = trim($_POST['first_name']  ?? '');
        $mn    = trim($_POST['middle_name'] ?? '');
        $ln    = trim($_POST['last_name']   ?? '');
        $email = trim($_POST['email']       ?? '');
        $pw    = trim($_POST['password']    ?? '');
        if ($fn && $ln && $email && strlen($pw) >= 11) {
            $hash = password_hash($pw, PASSWORD_BCRYPT);
            $ins  = $pdo->prepare(
                'INSERT INTO "Users" (first_name, middle_name, last_name, email, password, role)
                 VALUES (?,?,?,?,?,?)'
            );
            $ins->execute([$fn, $mn ?: null, $ln, $email, $hash, 'Admin']);
            $updateMsg = "Admin {$fn} {$ln} created.";
        } elseif (strlen($pw) < 11) {
            $errorMsg = 'Password must be at least 11 characters.';
        } else {
            $errorMsg = 'First name, last name, email, and password are required.';
        }
        $usersStmt = $pdo->query('SELECT * FROM "Users"');
        $allUsers  = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
        $admins    = array_values(array_filter($allUsers, fn($u) => in_array(strtolower($u['role'] ?? ''), ['admin', 'root'])));
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
        $usersStmt = $pdo->query('SELECT * FROM "Users"');
        $allUsers  = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
        $admins    = array_values(array_filter($allUsers, fn($u) => in_array(strtolower($u['role'] ?? ''), ['admin', 'root'])));
        $activeTab = 'admins';
    }
}

function fmtTs($ts): string {
    if (!$ts) return '—';
    return is_numeric($ts) ? date('Y-m-d H:i', (int)$ts) : date('Y-m-d H:i', strtotime((string)$ts));
}

function roleBadge(string $role): string {
    $cls = match(strtolower($role)) {
        'root'  => 'bg-pink-600/20 text-pink-400 border border-pink-700',
        'admin' => 'bg-blue-600/20 text-blue-400 border border-blue-700',
        default => 'bg-gray-600/20 text-gray-400 border border-gray-600',
    };
    return "<span class=\"px-3 py-1 rounded-full text-xs font-semibold {$cls}\">" . htmlspecialchars($role) . "</span>";
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Root Dashboard — BDPA Airports</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
body { font-family: 'Inter', sans-serif; background: #0f1117; color: #e2e8f0; }
.tab-active   { background: #2563eb; color: white; }
.tab-inactive { color: #9ca3af; }
.tab-inactive:hover { color: white; background: #374151; }
.period-btn { transition: all .15s; }
.period-active { background: #2563eb !important; color: white !important; border-color: #2563eb !important; }

.stat-card { background:#161b27; border:1px solid #1f2937; border-radius:.75rem; padding:1.5rem; }

.badge { display:inline-flex; align-items:center; padding: 2px 10px; border-radius:9999px; font-size:.72rem; font-weight:600; white-space:nowrap; }

tbody tr { border-top: 1px solid #1f2937; transition: background .12s; }
tbody tr:hover { background: rgba(55,65,81,.45); }

.field { width:100%; height:2.375rem; background:#1f2937; border:1px solid #374151; border-radius:.5rem;
         padding:0 .875rem; font-size:.875rem; color:#f1f5f9; outline:none; transition: border-color .15s; }
.field:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.2); }

::-webkit-scrollbar { width:6px; height:6px; }
::-webkit-scrollbar-track { background:#0f1117; }
::-webkit-scrollbar-thumb { background:#374151; border-radius:3px; }

.table-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; }
</style>
</head>
<body class="min-h-screen">



<main class="max-w-7xl mx-auto p-4 sm:p-6 space-y-5">

  <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
    <h1 class="text-3xl font-extrabold">Root Dashboard</h1>
    <p class="text-gray-400 text-sm mt-1">Complete  access: administrators, customers, tickets, and analytics thingys.</p>
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
    $tabs = [
      'overview'  => '📊 Overview',
      'admins'    => '👨‍💼 Administrators',
      'customers' => '👤 Customers',
      'tickets'   => '🎫 Tickets',
    ];
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

  <div class="grid xl:grid-cols-5 md:grid-cols-3 gap-4">
    <div class="stat-card">
      <p class="text-gray-500 text-xs uppercase tracking-wider font-semibold">Tickets Sold</p>
      <h2 class="text-4xl font-extrabold mt-3 tabular-nums" id="stat-tickets">—</h2>
      <p class="text-gray-600 text-xs mt-2" id="stat-period-label">Select a period above</p>
    </div>
    <div class="stat-card">
      <p class="text-gray-500 text-xs uppercase tracking-wider font-semibold">Gross Profit</p>
      <h2 class="text-3xl font-extrabold mt-3 tabular-nums text-emerald-400" id="stat-profit">—</h2>
      <p class="text-gray-600 text-xs mt-2">Active tickets</p>
    </div>
    <div class="stat-card">
      <p class="text-gray-500 text-xs uppercase tracking-wider font-semibold">Administrators</p>
      <h2 class="text-4xl font-extrabold mt-3 tabular-nums"><?= count($admins) ?></h2>
      <p class="text-gray-600 text-xs mt-2">Admin + Root accounts</p>
    </div>
    <div class="stat-card">
      <p class="text-gray-500 text-xs uppercase tracking-wider font-semibold">Customers</p>
      <h2 class="text-4xl font-extrabold mt-3 tabular-nums"><?= count($customers) ?></h2>
      <p class="text-gray-600 text-xs mt-2">Registered customers</p>
    </div>
    <div class="stat-card">
      <p class="text-gray-500 text-xs uppercase tracking-wider font-semibold">Total Flights</p>
      <h2 class="text-4xl font-extrabold mt-3 tabular-nums"><?= count($allFlights) ?></h2>
      <p class="text-gray-600 text-xs mt-2">From API</p>
    </div>
  </div>

  <div class="grid lg:grid-cols-4 gap-4">
    <a href="?tab=admins" class="bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-pink-600 hover:bg-gray-800/60 transition block group">
      <div class="text-2xl mb-3">👨‍💼</div>
      <h3 class="font-bold group-hover:text-pink-400 transition">Manage Admins</h3>
      <p class="text-gray-500 text-sm mt-1">Create, edit, and delete administrator accounts.</p>
    </a>
    <a href="?tab=customers" class="bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-blue-600 hover:bg-gray-800/60 transition block group">
      <div class="text-2xl mb-3">👤</div>
      <h3 class="font-bold group-hover:text-blue-400 transition">Customers</h3>
      <p class="text-gray-500 text-sm mt-1">View and manage all customer accounts.</p>
    </a>
    <a href="?tab=tickets" class="bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-blue-600 hover:bg-gray-800/60 transition block group">
      <div class="text-2xl mb-3">🎫</div>
      <h3 class="font-bold group-hover:text-blue-400 transition">Tickets</h3>
      <p class="text-gray-500 text-sm mt-1">Search and manage all tickets.</p>
    </a>
    <a href="?tab=overview" class="bg-gray-900 border border-gray-800 rounded-xl p-6 hover:border-emerald-600 hover:bg-gray-800/60 transition block group">
      <div class="text-2xl mb-3">📈</div>
      <h3 class="font-bold group-hover:text-emerald-400 transition">Revenue Reports</h3>
      <p class="text-gray-500 text-sm mt-1">Ticket sales and gross profit by period.</p>
    </a>
  </div>

  <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
    <div class="p-4 border-b border-gray-800 flex items-center justify-between">
      <h2 class="font-bold">Administrator Accounts</h2>
      <a href="?tab=admins" class="text-sm text-blue-400 hover:text-blue-300">Manage</a>
    </div>
    <div class="table-wrap">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-800/60 text-gray-400 text-xs uppercase tracking-wider">
            <th class="text-left px-5 py-3">Name</th>
            <th class="text-left px-5 py-3">Email</th>
            <th class="text-left px-5 py-3">Role</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($admins as $a): ?>
        <tr>
          <td class="px-5 py-3 font-semibold"><?= htmlspecialchars(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))) ?></td>
          <td class="px-5 py-3 text-gray-400"><?= htmlspecialchars($a['email'] ?? '—') ?></td>
          <td class="px-5 py-3"><?= roleBadge($a['role'] ?? 'Admin') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($admins)): ?>
        <tr><td colspan="3" class="px-5 py-8 text-center text-gray-600">No administrators.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php endif; ?>

  <?php if ($activeTab === 'admins'): ?>

  <?php
  $editAdmin = null;
  if (isset($_GET['edit'])) {
    foreach ($admins as $a) {
      if ((string)($a['user_id'] ?? '') === (string)$_GET['edit']) { $editAdmin = $a; break; }
    }
  }
  ?>

  <div class="grid lg:grid-cols-2 gap-5">
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
      <h2 class="font-bold text-base mb-4">Create New Administrator</h2>
      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="create_admin">
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
          <label class="block text-xs text-gray-400 mb-1">Password <span class="text-red-400">*</span> <span class="text-gray-600">(min 11 chars)</span></label>
          <input type="password" name="password" required minlength="11" class="field">
        </div>
        <button type="submit" class="w-full h-10 bg-pink-600 hover:bg-pink-500 rounded-lg text-sm font-semibold transition">
          Create Administrator
        </button>
      </form>
    </div>

    <?php if ($editAdmin): ?>
    <div class="bg-gray-900 border border-pink-700 rounded-xl p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-bold text-base">
          Edit — <?= htmlspecialchars(trim(($editAdmin['first_name'] ?? '') . ' ' . ($editAdmin['last_name'] ?? ''))) ?>
        </h2>
        <a href="?tab=admins" class="text-xs text-gray-500 hover:text-gray-300">✕ Close</a>
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
        <button type="submit" class="w-full h-10 bg-blue-600 hover:bg-blue-500 rounded-lg text-sm font-semibold transition">
          Save Changes
        </button>
      </form>
    </div>
    <?php else: ?>
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 flex flex-col items-center justify-center text-center gap-2">
      <span class="text-3xl">✏️</span>
      <p class="text-gray-500 text-sm">Click <strong class="text-gray-400">Edit</strong> on an admin row to modify their details.</p>
    </div>
    <?php endif; ?>
  </div>

  <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
    <div class="p-4 border-b border-gray-800">
      <h2 class="font-bold">All Administrators
        <span class="text-gray-600 font-normal text-sm ml-1">(<?= count($admins) ?>)</span>
      </h2>
    </div>
    <div class="table-wrap">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-800/60 text-gray-400 text-xs uppercase tracking-wider">
            <th class="text-left px-5 py-3">Name</th>
            <th class="text-left px-5 py-3">Email</th>
            <th class="text-left px-5 py-3">Role</th>
            <th class="text-left px-5 py-3">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($admins as $a):
          $isRoot = strtolower($a['role'] ?? '') === 'root';
        ?>
        <tr>
          <td class="px-5 py-3 font-semibold"><?= htmlspecialchars(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))) ?></td>
          <td class="px-5 py-3 text-gray-400"><?= htmlspecialchars($a['email'] ?? '—') ?></td>
          <td class="px-5 py-3"><?= roleBadge($a['role'] ?? 'Admin') ?></td>
          <td class="px-5 py-3 flex items-center gap-4">
            <a href="?tab=admins&edit=<?= urlencode($a['user_id'] ?? '') ?>" class="text-blue-400 hover:text-blue-300 text-xs font-semibold transition">Edit</a>
            <?php if (!$isRoot): ?>
            <form method="POST" class="inline">
              <input type="hidden" name="action"  value="delete_admin">
              <input type="hidden" name="user_id" value="<?= htmlspecialchars($a['user_id'] ?? '') ?>">
              <button type="submit"
                onclick="return confirm('Delete admin <?= htmlspecialchars(addslashes(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')))) ?>?')"
                class="text-xs text-red-400 hover:text-red-300 font-semibold transition">Delete</button>
            </form>
            <?php else: ?>
            <span class="text-xs text-gray-700">Protected</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($admins)): ?>
        <tr><td colspan="4" class="px-5 py-8 text-center text-gray-600">No administrators found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="px-5 py-3 border-t border-gray-800 text-xs text-gray-600">
      <?= count($admins) ?> administrator<?= count($admins) !== 1 ? 's' : '' ?>
    </div>
  </div>

  <?php endif; ?>

  <?php if ($activeTab === 'customers'): ?>

  <?php
  $customerSearch = trim($_GET['csearch'] ?? '');
  $filteredUsers  = $customers;
  if ($customerSearch !== '') {
    $q = strtolower($customerSearch);
    $filteredUsers = array_filter($customers, fn($u) =>
      str_contains(strtolower(
        ($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '') . ' ' .
        ($u['email'] ?? '') . ' ' . ($u['phone'] ?? '') . ' ' .
        ($u['city'] ?? '') . ' ' . ($u['street_address'] ?? '') . ' ' .
        ($u['state'] ?? '') . ' ' . ($u['zip_code'] ?? '')
      ), $q)
    );
  }
  ?>

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
            <th class="text-left px-5 py-3">State</th>
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
          <td class="px-5 py-3 text-gray-400"><?= htmlspecialchars($u['state'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($filteredUsers)): ?>
        <tr><td colspan="6" class="px-5 py-10 text-center text-gray-600">No customers found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="px-5 py-3 border-t border-gray-800 text-xs text-gray-600">
      <?= count($filteredUsers) ?> customer<?= count($filteredUsers) !== 1 ? 's' : '' ?> shown
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
        ($t['confirmation_code'] ?? '') . ' ' . ($t['name_last'] ?? '') . ' ' .
        ($t['name_first'] ?? '') . ' ' . ($t['flight_id'] ?? '') . ' ' .
        ($f['destination'] ?? '') . ' ' . ($f['flightNumber'] ?? '')
      ), $q);
    });
  }
  ?>

  <div class="bg-gray-900 border border-gray-800 rounded-xl p-5">
    <h2 class="font-bold text-base mb-4">Ticket Search &amp; Lookup</h2>
    <form method="GET" class="flex flex-wrap gap-3">
      <input type="hidden" name="tab" value="tickets">
      <input type="text" name="conf" value="<?= htmlspecialchars($confLookup) ?>"
        placeholder="Look up by confirmation code…" class="field flex-1 min-w-[200px] h-9">
      <input type="text" name="tsearch" value="<?= htmlspecialchars($ticketSearch) ?>"
        placeholder="Search by flight, route, name…" class="field flex-1 min-w-[200px] h-9">
      <button type="submit" class="h-9 px-4 bg-blue-600 hover:bg-blue-500 rounded-lg text-sm font-semibold transition">Search</button>
      <?php if ($ticketSearch || $confLookup): ?>
      <a href="?tab=tickets" class="h-9 px-3 bg-gray-700 hover:bg-gray-600 rounded-lg text-sm font-semibold transition flex items-center">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if ($confLookup !== ''): ?>
  <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="font-bold">Lookup — <?= htmlspecialchars(strtoupper($confLookup)) ?></h2>
      <a href="?tab=tickets" class="text-xs text-gray-500 hover:text-gray-300">✕ Close</a>
    </div>
    <?php if ($lookupTicket): ?>
    <div class="grid md:grid-cols-2 gap-3 text-sm">
      <?php
      $detail = [
        'Confirmation' => '<code class="bg-gray-800 px-2 py-0.5 rounded text-blue-300">' . htmlspecialchars($lookupTicket['confirmation_code']) . '</code>',
        'Passenger'    => htmlspecialchars(trim(($lookupTicket['name_first'] ?? '') . ' ' . ($lookupTicket['name_last'] ?? '—'))),
        'Flight ID'    => '<code class="text-xs bg-gray-800 px-2 py-0.5 rounded text-gray-300">' . htmlspecialchars($lookupTicket['flight_id'] ?? '—') . '</code>',
        'Route'        => $lookupFlight ? htmlspecialchars(($lookupFlight['origin'] ?? '?') . ' → ' . ($lookupFlight['destination'] ?? '?')) : '—',
        'Departure'    => $lookupFlight ? fmtTs($lookupFlight['departureTime'] ?? 0) : '—',
        'Seat'         => htmlspecialchars($lookupTicket['seat'] ?? '—'),
        'Price'        => isset($lookupTicket['price']) ? '$' . number_format((float)$lookupTicket['price'], 2) : '—',
      ];
      foreach ($detail as $k => $v): ?>
      <div class="bg-gray-800 rounded-lg p-3">
        <p class="text-gray-500 text-xs mb-1"><?= $k ?></p>
        <p class="font-semibold"><?= $v ?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="text-red-400 text-sm bg-red-950 border border-red-800 rounded-lg px-4 py-3">
      No ticket found for confirmation code "<strong><?= htmlspecialchars($confLookup) ?></strong>".
    </p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
    <div class="p-4 border-b border-gray-800">
      <h2 class="font-bold">All Tickets
        <span class="text-gray-600 font-normal text-sm ml-1">(<?= count($filteredTickets) ?>)</span>
      </h2>
    </div>
    <div class="table-wrap">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-800/60 text-gray-400 text-xs uppercase tracking-wider">
            <th class="text-left px-5 py-3">Confirmation</th>
            <th class="text-left px-5 py-3">Passenger</th>
            <th class="text-left px-5 py-3">Flight #</th>
            <th class="text-left px-5 py-3">Route</th>
            <th class="text-left px-5 py-3">Departure</th>
            <th class="text-left px-5 py-3">Seat</th>
            <th class="text-left px-5 py-3">Price</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($filteredTickets as $t):
          $f           = $flightMap[$t['flight_id'] ?? ''] ?? [];
          $rawP        = $t['price'] ?? '0';
          $safeP       = (is_numeric($rawP) && (float)$rawP >= 0 && (float)$rawP <= 100000)
                          ? '$' . number_format((float)$rawP, 2) : '—';
          $passenger   = trim(($t['name_first'] ?? '') . ' ' . ($t['name_last'] ?? ''));
        ?>
        <tr class="<?= $isCancelled ? 'opacity-50' : '' ?>">
          <td class="px-5 py-3">
            <code class="text-xs bg-gray-800 px-2 py-1 rounded text-blue-300 font-mono"><?= htmlspecialchars($t['confirmation_code'] ?? '—') ?></code>
          </td>
          <td class="px-5 py-3 text-gray-300"><?= htmlspecialchars($passenger ?: '—') ?></td>
          <td class="px-5 py-3 font-semibold text-xs"><?= htmlspecialchars($f['flightNumber'] ?? ($t['flight_id'] ? '…' . substr($t['flight_id'], -5) : '—')) ?></td>
          <td class="px-5 py-3 text-gray-400 text-xs whitespace-nowrap"><?= htmlspecialchars(($f['origin'] ?? '?') . ' → ' . ($f['destination'] ?? '?')) ?></td>
          <td class="px-5 py-3 text-gray-400 text-xs whitespace-nowrap"><?= fmtTs((int)($f['departureTime'] ?? 0)) ?></td>
          <td class="px-5 py-3 text-gray-400 text-xs"><?= htmlspecialchars($t['seat'] ?? '—') ?></td>
          <td class="px-5 py-3 text-gray-300 text-xs font-mono"><?= $safeP ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($filteredTickets)): ?>
        <tr><td colspan="8" class="px-5 py-10 text-center text-gray-600">No tickets found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="px-5 py-3 border-t border-gray-800 text-xs text-gray-600">
      <?= count($filteredTickets) ?> ticket<?= count($filteredTickets) !== 1 ? 's' : '' ?> shown
    </div>
  </div>

  <?php endif; ?>

</main>

<script>
const periodLabels = {
  day:'Today', week:'This Week', month:'This Month', year:'This Year', all:'All Time'
};
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
</script>

</body>
</html>