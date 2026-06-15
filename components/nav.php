<?php
    require_once __DIR__ . "/../database/db.php";

    $parts = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
    $baseUrl = '/' . $parts[0] . '/' . $parts[1];
    define('BASE_URL', $baseUrl);


    $current = $_SERVER['REQUEST_URI'];

    function isActive($needle) { 
        return str_contains($_SERVER['REQUEST_URI'], $needle);
    }

    $isLoggedIn = isset($_SESSION['user_id']);
    $userFullName = "Guest";
    $role = $_SESSION['role'] ?? 'guest';

    if ($isLoggedIn) {
        $stmt = $pdo->prepare('SELECT first_name, last_name FROM "Users" WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $userFullName = trim(($user['first_name'] ?? '') . " " . ($user['last_name'] ?? ''));
            if ($userFullName == '') $userFullName = "User";
        }
    }

    $dashboardLink = BASE_URL . "/pages/dashboard/customer/customer.php";

    if ($role == "admin") {
        $dashboardLink = BASE_URL . "/pages/dashboard/admin/admin.php";
    } elseif ($role == "root") {
        $dashboardLink = BASE_URL . "/pages/dashboard/root/root.php";
    }
?>

<header class="h-16 bg-gray-800 border-b border-gray-700">
    <div class="h-full px-8 flex items-center justify-between">
        <a href="<?= BASE_URL ?>/index.php" class="text-white font-bold text-xl tracking-wide hover:text-blue-300 transition">BDPA Airports</a>

        <nav class="hidden md:flex items-center gap-8 text-sm text-gray-300">
            <?php if (!$isLoggedIn): ?>
                <?php $active = isActive('/auth/create.php'); ?>
                <a href="<?= BASE_URL ?>/pages/auth/create.php" class="relative group">
                    <span class="<?= $active ? 'text-white' : 'group-hover:text-white' ?>">Create Account</span>
                    <span class="absolute left-0 -bottom-1 h-[2px] bg-blue-400 transition-all duration-300 <?= $active ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                </a>
            <?php else: ?>
                <?php $active = isActive('dashboard'); ?>
                <a href="<?= $dashboardLink ?>" class="relative group">
                    <span class="<?= $active ? 'text-white' : 'group-hover:text-white' ?>">Dashboard</span>
                    <span class="absolute left-0 -bottom-1 h-[2px] bg-blue-400 transition-all duration-300 <?= $active ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                </a>
            <?php endif; ?>

            <?php if ($role == 'Customer'): ?>
                <?php $active = isActive('flights'); ?>
                <a href="<?= BASE_URL ?>/pages/dashboard/customer/customer.php?tab=flights" class="relative group">
                    <span class="<?= $active ? 'text-white' : 'group-hover:text-white' ?>">My Flights</span>
                    <span class="absolute left-0 -bottom-1 h-[2px] bg-blue-400 transition-all duration-300 <?= $active ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                </a>
            <?php endif; ?>

            <?php $active = isActive('/flights/'); ?>
            <a href="<?= BASE_URL ?>/pages/flights/flights.php" class="relative group">
                <span class="<?= $active ? 'text-white' : 'group-hover:text-white' ?>">Browse Flights</span>
                <span class="absolute left-0 -bottom-1 h-[2px] bg-blue-400 transition-all duration-300 <?= $active ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
            </a>

            <?php if (!$isLoggedIn || $role == 'Customer'): ?>
                <?php $active = isActive('searchFlights'); ?>
                <a href="<?= BASE_URL ?>/pages/booking/searchFlights.php" class="relative group">
                    <span class="<?= $active ? 'text-white' : 'group-hover:text-white' ?>">Book Flight</span>
                    <span class="absolute left-0 -bottom-1 h-[2px] bg-blue-400 transition-all duration-300 <?= $active ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                </a>
            <?php endif; ?>

            <?php if (!$isLoggedIn): ?>
                <?php $active = isActive('viewTicket'); ?>
                <a href="<?= BASE_URL ?>/pages/ticket/viewTicket.php" class="relative group">
                    <span class="<?= $active ? 'text-white' : 'group-hover:text-white' ?>">View Tickets</span>
                    <span class="absolute left-0 -bottom-1 h-[2px] bg-blue-400 transition-all duration-300 <?= $active ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                </a>
            <?php endif; ?>

            <?php if ($role == 'root'): ?>
                <?php $active = isActive('admins'); ?>
                <a href="<?= BASE_URL ?>/pages/dashboard/root/root.php?tab=admins" class="relative group">
                    <span class="<?= $active ? 'text-white' : 'group-hover:text-white' ?>">Administrators</span>
                    <span class="absolute left-0 -bottom-1 h-[2px] bg-blue-400 transition-all duration-300 <?= $active ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                </a>
            <?php endif; ?>

            <?php if ($role == 'admin' || $role == 'root'): ?>
                <?php $active = isActive('customers'); ?>
                <a href="<?= BASE_URL ?>/pages/dashboard/<?= $role ?>/<?= $role ?>.php?tab=customers" class="relative group">
                    <span class="<?= $active ? 'text-white' : 'group-hover:text-white' ?>">Customers</span>
                    <span class="absolute left-0 -bottom-1 h-[2px] bg-blue-400 transition-all duration-300 <?= $active ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                </a>

                <?php $active = isActive('tickets'); ?>
                <a href="<?= BASE_URL ?>/pages/dashboard/<?= $role ?>/<?= $role ?>.php?tab=tickets" class="relative group">
                    <span class="<?= $active ? 'text-white' : 'group-hover:text-white' ?>">Tickets</span>
                    <span class="absolute left-0 -bottom-1 h-[2px] bg-blue-400 transition-all duration-300 <?= $active ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                </a>
            <?php endif; ?>
        </nav>

        <div class="flex items-center gap-3">
            <?php if ($isLoggedIn): ?>
                <div class="hidden sm:block text-gray-300 text-sm">Welcome, <span class="text-white font-medium"><?= htmlspecialchars($userFullName) ?></span></div>
                <span class="hidden sm:inline px-2 py-1 text-xs rounded bg-gray-700 border border-gray-600 text-gray-300"><?= strtoupper($role) ?></span>
                <a href="<?= BASE_URL ?>/logout.php" class="px-4 py-1.5 text-sm bg-red-600 rounded-lg hover:bg-red-700 transition">Logout</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/pages/auth/auth.php" class="px-4 py-1.5 text-sm bg-gray-700 border border-gray-600 rounded-lg hover:bg-gray-600 transition">Login</a>
                <a href="<?= BASE_URL ?>/pages/auth/create.php" class="px-4 py-1.5 text-sm bg-blue-600 rounded-lg hover:bg-blue-700 transition">Register</a>
            <?php endif; ?>
        </div>
        
    </div>
</header>