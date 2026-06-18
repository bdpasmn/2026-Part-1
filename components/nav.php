<?php
    require_once __DIR__ . "/../database/db.php";

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    $parts = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
    $baseUrl = '/' . $parts[0] . '/' . $parts[1];
    define('BASE_URL', $baseUrl);

    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $currentTab = $_GET['tab'] ?? null;

    function isActivePath($path) {
        global $currentPath;
        return $currentPath == $path;
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
<!-- loading overlay -->
<div id="loading-overlay" class="fixed inset-0 z-50 hidden bg-gray-900/70 backdrop-blur-sm flex items-center justify-center">
    <div class="flex flex-col items-center gap-4">
        <div class="w-12 h-12 border-4 border-blue-400 border-t-transparent rounded-full animate-spin"></div>

        <p id="loading-text"
           class="text-blue-400 font-semibold tracking-widest uppercase text-sm">
            Loading...
        </p>
    </div>
</div>

<header class="h-16 bg-gray-800 border-b border-gray-700 relative z-50">
<div class="h-full px-8 flex items-center justify-between relative">

        <a href="<?= BASE_URL ?>/index.php"
           class="text-white font-bold text-xl tracking-wide hover:text-blue-300 transition">
            BDPA Airports
        </a>

        <!-- DESKTOP NAV (UNCHANGED) -->
        <nav class="hidden md:flex items-center gap-8 text-sm text-gray-300">

            <?php if (!$isLoggedIn): ?>
                <?php $active = isActivePath(BASE_URL . '/pages/auth/create.php'); ?>
                <a href="<?= BASE_URL ?>/pages/auth/create.php" class="relative group">
                    <span class="<?= $active ? 'text-white' : 'group-hover:text-white' ?>"data-loader="page">Create Account</span>
                    <span class="absolute left-0 -bottom-1 h-[2px] bg-blue-400 transition-all duration-300 <?= $active ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                </a>
            <?php else: ?>
                <?php $active = str_contains($currentPath, '/dashboard/'); ?>
                <a href="<?= $dashboardLink ?>" class="relative group">
                    <span class="<?= $active ? 'text-white' : 'group-hover:text-white' ?>" data-loader="page">Dashboard</span>
                    <span class="absolute left-0 -bottom-1 h-[2px] bg-blue-400 transition-all duration-300 <?= $active ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                </a>
            <?php endif; ?>

            <?php if ($role == 'Customer'): ?>
                <?php $active = (
                    str_contains($currentPath, '/customer/customer.php')
                    && $currentTab == 'flights'
                ); ?>

                <a href="<?= BASE_URL ?>/pages/dashboard/customer/customer.php?tab=flights" class="relative group">
                <?php $active = isActive('flights'); ?>
                <a href="<?= BASE_URL ?>/pages/dashboard/customer/customer.php?tab=flights" class="relative group" data-loader="page">
                    <span class="<?= $active ? 'text-white' : 'group-hover:text-white' ?>">My Flights</span>
                    <span class="absolute left-0 -bottom-1 h-[2px] bg-blue-400 transition-all duration-300 <?= $active ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                </a>
            <?php endif; ?>

            <?php $active = isActivePath(BASE_URL . '/pages/flights/flights.php'); ?>
            <a href="<?= BASE_URL ?>/pages/flights/flights.php" class="relative group" data-loader="page">
                <span class="<?= $active ? 'text-white' : 'group-hover:text-white' ?>">Browse Flights</span>
                <span class="absolute left-0 -bottom-1 h-[2px] bg-blue-400 transition-all duration-300 <?= $active ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
            </a>

            <?php if (!$isLoggedIn || $role == 'Customer'): ?>
                <?php $active = str_contains($currentPath, '/booking/searchFlights.php'); ?>
                <a href="<?= BASE_URL ?>/pages/booking/searchFlights.php" class="relative group" data-loader="page">
                    <span class="<?= $active ? 'text-white' : 'group-hover:text-white' ?>">Book Flight</span>
                    <span class="absolute left-0 -bottom-1 h-[2px] bg-blue-400 transition-all duration-300 <?= $active ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                </a>
            <?php endif; ?>

            <?php if (!$isLoggedIn): ?>
                <?php $active = isActivePath(BASE_URL . '/pages/ticket/viewTicket.php'); ?>
                <a href="<?= BASE_URL ?>/pages/ticket/viewTicket.php" class="relative group" data-loader="page">
                    <span class="<?= $active ? 'text-white' : 'group-hover:text-white' ?>">View Tickets</span>
                    <span class="absolute left-0 -bottom-1 h-[2px] bg-blue-400 transition-all duration-300 <?= $active ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                </a>
            <?php endif; ?>

            <?php if ($role == 'root'): ?>
                <?php $active = $currentTab == 'admins'; ?>
                <a href="<?= BASE_URL ?>/pages/dashboard/root/root.php?tab=admins" class="relative group" data-loader="page">
                    <span class="<?= $active ? 'text-white' : 'group-hover:text-white' ?>">Administrators</span>
                    <span class="absolute left-0 -bottom-1 h-[2px] bg-blue-400 transition-all duration-300 <?= $active ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                </a>
            <?php endif; ?>

            <?php if ($role == 'admin' || $role == 'root'): ?>
                <?php $active = $currentTab == 'customers'; ?>
                <a href="<?= BASE_URL ?>/pages/dashboard/<?= $role ?>/<?= $role ?>.php?tab=customers" class="relative group" data-loader="page">
                    <span class="<?= $active ? 'text-white' : 'group-hover:text-white' ?>">Customers</span>
                    <span class="absolute left-0 -bottom-1 h-[2px] bg-blue-400 transition-all duration-300 <?= $active ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                </a>

                <?php $active = $currentTab == 'tickets'; ?>
                <a href="<?= BASE_URL ?>/pages/dashboard/<?= $role ?>/<?= $role ?>.php?tab=tickets" class="relative group" data-loader="page">
                    <span class="<?= $active ? 'text-white' : 'group-hover:text-white' ?>">Tickets</span>
                    <span class="absolute left-0 -bottom-1 h-[2px] bg-blue-400 transition-all duration-300 <?= $active ? 'w-full' : 'w-0 group-hover:w-full' ?>"></span>
                </a>
            <?php endif; ?>

        </nav>

        <!-- RIGHT SIDE -->
        <div class="flex items-center gap-3">

            <?php if ($isLoggedIn): ?>
                <div class="hidden sm:block text-gray-300 text-sm">Welcome, <span class="text-white font-medium"><?= htmlspecialchars($userFullName) ?></span></div>
                <span class="hidden sm:inline px-2 py-1 text-xs rounded bg-gray-700 border border-gray-600 text-gray-300"><?= strtoupper($role) ?></span>
                <a href="<?= BASE_URL ?>/pages/auth/logout.php" data-loader="page" class="px-4 py-1.5 text-sm bg-red-600 rounded-lg hover:bg-red-700 transition">Logout</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/pages/auth/auth.php" data-loader="page"
                   class="px-4 py-1.5 text-sm bg-gray-700 border border-gray-600 rounded-lg hover:bg-gray-600 transition">
                    Login
                </a>

                <a href="<?= BASE_URL ?>/pages/auth/create.php" data-loader="page"
                   class="px-4 py-1.5 text-sm bg-blue-600 rounded-lg hover:bg-blue-700 transition">
                    Register
                </a>
            <?php endif; ?>

            <!-- HAMBURGER (ONLY MOBILE) -->
            <button id="navToggle"
                    class="md:hidden w-10 h-10 flex items-center justify-center rounded-lg bg-gray-700 hover:bg-gray-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="w-5 h-5 text-white"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

        </div>

    </div>

    <!-- MOBILE MENU (SAME LINKS, JUST STACKED) -->
    <div id="mobileMenu"
     class="md:hidden hidden absolute left-0 top-full w-full bg-gray-800 border-b border-gray-700 shadow-lg z-50">

        <div class="flex flex-col text-sm text-gray-300">

            <?php if (!$isLoggedIn): ?>
                <a class="px-6 py-3 hover:bg-gray-700"
                   href="<?= BASE_URL ?>/pages/auth/create.php">Create Account</a>
            <?php else: ?>
                <a class="px-6 py-3 hover:bg-gray-700"
                   href="<?= $dashboardLink ?>">Dashboard</a>
            <?php endif; ?>

            <?php if ($role == 'Customer'): ?>
                <a class="px-6 py-3 hover:bg-gray-700"
                   href="<?= BASE_URL ?>/pages/dashboard/customer/customer.php?tab=flights">My Flights</a>
            <?php endif; ?>

            <a class="px-6 py-3 hover:bg-gray-700"
               href="<?= BASE_URL ?>/pages/flights/flights.php">Browse Flights</a>

            <?php if (!$isLoggedIn || $role == 'Customer'): ?>
                <a class="px-6 py-3 hover:bg-gray-700"
                   href="<?= BASE_URL ?>/pages/booking/searchFlights.php">Book Flight</a>
            <?php endif; ?>

            <?php if (!$isLoggedIn): ?>
                <a class="px-6 py-3 hover:bg-gray-700"
                   href="<?= BASE_URL ?>/pages/ticket/viewTicket.php">View Tickets</a>
            <?php endif; ?>

            <?php if ($role == 'root'): ?>
                <a class="px-6 py-3 hover:bg-gray-700"
                   href="<?= BASE_URL ?>/pages/dashboard/root/root.php?tab=admins">Administrators</a>
            <?php endif; ?>

            <?php if ($role == 'admin' || $role == 'root'): ?>
                <a class="px-6 py-3 hover:bg-gray-700"
                   href="<?= BASE_URL ?>/pages/dashboard/<?= $role ?>/<?= $role ?>.php?tab=customers">Customers</a>

                <a class="px-6 py-3 hover:bg-gray-700"
                   href="<?= BASE_URL ?>/pages/dashboard/<?= $role ?>/<?= $role ?>.php?tab=tickets">Tickets</a>
            <?php endif; ?>

        </div>
    </div>

</header>
<script>
    //loading overlay loader
const Loader = {
    get overlay() {
        return document.getElementById('loading-overlay');
    },

    get text() {
        return document.getElementById('loading-text');
    },

    slowTimer: null,

    show(message = "Loading...") {
        const overlay = this.overlay;
        if (!overlay) return;

        const text = this.text;
        if (text) text.textContent = message;

        overlay.classList.remove('hidden');
        //slow time detection
        this.slowTimer = setTimeout(() => {
            const t = this.text;
            if (t) t.textContent = "Hmm... this is taking longer than usual";
        }, 5000);
    },

    hide() {
        const overlay = this.overlay;
        if (!overlay) return;

        overlay.classList.add('hidden');

        clearTimeout(this.slowTimer);
        this.slowTimer = null;

        const text = this.text;
        if (text) text.textContent = "Loading...";
    }
};

window.addEventListener('pageshow', () => Loader.hide());
window.addEventListener('pagehide', () => Loader.hide());

document.addEventListener('click', (e) => {
    const link = e.target.closest('a');
    if (!link) return;

    const href = link.getAttribute('href');
    if (!href || href.startsWith('#')) return;

    if (link.target === '_blank') return;

    if (link.dataset.loader === "page") {
        Loader.show();
    }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const btn = document.getElementById("navToggle");
    const menu = document.getElementById("mobileMenu");

    btn?.addEventListener("click", () => {
        menu.classList.toggle("hidden");
    });
});
</script>