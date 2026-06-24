<?php
session_start();
require_once "../../database/db.php";
require_once __DIR__ . '/../../components/config.php';

if (isset($_SESSION['user_id'])) {
    $role = strtolower($_SESSION['role']) ?? '';
    
    if (in_array($role, ['customer', 'admin', 'root'])) {
        $roleLower = strtolower($role);

        header("Location: ../dashboard/{$roleLower}/{$roleLower}.php");
        exit;
    }
}

$message = '';
$first = $_POST['first'] ?? '';
$last = $_POST['last'] ?? '';
$email = $_POST['email'] ?? '';

function getDashboardUrl($role) {
    switch ($role) {
        case 'Admin':
            return BASE_URI . '/pages/dashboard/admin/admin.php';
        case 'Root':
            return BASE_URI . '/pages/dashboard/root/root.php';
        case 'Customer':
        default:
            return BASE_URI . '/pages/dashboard/customer/customer.php';
    }
}

if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {

    $parts = explode('|', $_COOKIE['remember_me'], 2);

    if (count($parts) === 2) {

        [$cookieId, $cookieToken] = $parts;
        $expectedToken = hash_hmac('sha256', $cookieId, SECRET_KEY);

        if (hash_equals($expectedToken, $cookieToken)) {

            $stmt = $pdo->prepare('
                SELECT user_id, title, role, email, first_name
                FROM "Users"
                WHERE user_id = ?
            ');
            $stmt->execute([$cookieId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user'] = $user['title'];
                $_SESSION['name'] = $user['first_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['remembered'] = true;

                header("Location: " . getDashboardUrl($user['role']));
                exit();
            }
        }
    }

    setcookie('remember_me', '', time() - 3600, '/');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first = trim($_POST['first'] ?? '');
    $last = trim($_POST['last'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$first || !$last || !$email || !$password) {
        $message = "Please fill in all fields.";
    } else {

        $stmt = $pdo->prepare('
            SELECT user_id, password, title, role, email, first_name,
                   failed_attempts, lock_until
            FROM "Users"
            WHERE first_name = ? AND last_name = ? AND email = ?
        ');
        $stmt->execute([$first, $last, $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $message = "Invalid credentials.";
        } else {

            $userId = $row['user_id'];
            $now = time();


            if ($row['lock_until'] && strtotime($row['lock_until']) > $now) {

                $minutesLeft = ceil((strtotime($row['lock_until']) - $now) / 60);
                $message = "Too many failed attempts. Try again in $minutesLeft minute(s).";

            } else {


                if ($row['lock_until'] && strtotime($row['lock_until']) <= $now) {
                    $pdo->prepare('
                        UPDATE "Users"
                        SET failed_attempts = 0,
                            lock_until = NULL
                        WHERE user_id = ?
                    ')->execute([$userId]);

                    $row['failed_attempts'] = 0;
                    $row['lock_until'] = null;
                }

                $attemptsLeft = max(0, 3 - $row['failed_attempts']);


                if (!password_verify($password, $row['password'])) {

                    $newAttempts = $row['failed_attempts'] + 1;

                    $lockUntil = null;

                    if ($newAttempts >= 3) {
                        $lockUntil = date('Y-m-d H:i:s', $now + 3600);
                        $message = "Too many failed attempts. Locked for 1 hour.";
                    } else {
                        $attemptsLeft = 3 - $newAttempts;
                        $message = "Invalid credentials. $attemptsLeft attempt(s) left.";
                    }

                    $pdo->prepare('
                        UPDATE "Users"
                        SET failed_attempts = ?,
                            lock_until = ?
                        WHERE user_id = ?
                    ')->execute([$newAttempts, $lockUntil, $userId]);

                } else {


                    session_regenerate_id(true);

                    $_SESSION['email'] = $row['email'];
                    $_SESSION['user'] = $row['title'];
                    $_SESSION['name'] = $row['first_name'];
                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['role'] = $row['role'];

                    $pdo->prepare('
                        UPDATE "Users"
                        SET failed_attempts = 0,
                            lock_until = NULL
                        WHERE user_id = ?
                    ')->execute([$userId]);

                    if (isset($_POST['remember'])) {

                        $_SESSION['remembered'] = true;

                        $token = hash_hmac('sha256', $userId, SECRET_KEY);

                        setcookie(
                            'remember_me',
                            $userId . '|' . $token,
                            time() + 60 * 60 * 24 * 30,
                            '/',
                            '',
                            false,
                            true
                        );

                    } else {
                        $_SESSION['remembered'] = false;
                    }

                    header("Location: " . getDashboardUrl($row['role']));
                    exit();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Sign In</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"> </script>
    </head>
    <body class="bg-gray-900 min-h-screen text-white flex flex-col">
        <?php include __DIR__ . '/../../components/nav.php'; ?>
        <div class="w-full min-h-screen bg-gray-900">
            <main class="flex-grow flex items-center justify-center p-6">
                <div class="w-full max-w-3xl space-y-6">
                    <div class="bg-gray-800 border border-gray-700 rounded-xl p-10 text-center relative overflow-hidden">
                        <div class="relative z-10 space-y-4">
                            <p class="tracking-[0.25em] text-xs text-blue-300">
                                BDPA AIRPORTS✈️
                            </p>
                            <h1 class="text-4xl md:text-5xl font-bold leading-tight">
                                Sign In 🔐
                            </h1>
                            <p class="text-gray-300 text-sm md:text-base max-w-2xl mx-auto">
                                Access your account to manage bookings, view flights, and continue your journey.
                            </p>
                        </div>
                    </div>
                    <!-- FORM CARD -->
                    <div class="bg-gray-800 border border-gray-700 rounded-lg p-10">
                        <?php if (!empty($message)): ?>
                            <div class="mb-6 bg-gray-900 border border-red-700 text-red-300 p-4 rounded-lg text-sm">
                                <?= htmlspecialchars($message) ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST" class="space-y-6">
                            <!-- EMAIL -->
                            <div>
                                <label class="text-xs text-gray-400">Email</label>
                                <input type="email" name="email" required value="<?= htmlspecialchars($email) ?>" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm hover:border-blue-500 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition">
                            </div>
                            <!-- FIRST + LAST NAME -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="text-xs text-gray-400">First Name</label>
                                    <input type="text" name="first" required value="<?= htmlspecialchars($first) ?>" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm hover:border-blue-500 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400">Last Name</label>
                                    <input type="text" name="last" required value="<?= htmlspecialchars($last) ?>" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm hover:border-blue-500 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition">
                                </div>
                            </div>
                            <!-- PASSWORD -->
                            <div>
                                <label class="text-xs text-gray-400">Password</label>
                                <input type="password" name="password" required class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm hover:border-blue-500 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition">
                            </div>
                            <!-- OPTIONS -->
                            <div class="flex justify-between items-center text-sm text-gray-400 pt-2">
                                <label class="flex items-center gap-2">
                                <input type="checkbox" name="remember" class="accent-blue-500">
                                    Remember me
                                </label> 
                                <a href="<?= BASE_URI ?>/pages/accountRecovery/recovery.php"
                                    class="text-blue-400 hover:text-blue-300">
                                    Forgot password?
                                </a>
                            </div>
                            <!-- BUTTON -->
                            <button type="submit"
                                class="w-full h-12 bg-blue-600 hover:bg-blue-700 border border-blue-600 rounded-lg transition">
                                Login
                            </button>
                        </form>
                        <div class="flex items-center my-4">
                            <div class="flex-1 h-px bg-gray-700"></div>
                        </div>
                        <div class="text-center text-sm text-gray-400 mb-4">
                            Don't have an account?
                            <a href="<?= BASE_URI ?>/pages/auth/create.php" class="text-blue-400 hover:text-blue-300 ml-1">
                                Sign up
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>