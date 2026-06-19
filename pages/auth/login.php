<?php
    session_start();
    require_once "../../database/db.php";
    require_once __DIR__ . '/../../components/config.php';
    $message = '';
    $attempts_left = 3;
    if (!isset($_SESSION['id']) && isset($_COOKIE['remember_me'])) {
        [$cookieId, $cookieToken] = explode('|', $_COOKIE['remember_me'], 2);
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
                $_SESSION['id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user'] = $user['title'];
                $_SESSION['name'] = $user['first_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['remembered'] = true;
                header("Location: " . BASE_URL . "/pages/dashboard/customer/customer.php");
                exit();
            }
        }
        setcookie('remember_me', '', time() - 3600, '/');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $first = trim($_POST['first'] ?? '');
        $last = trim($_POST['last'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($first) || empty($last) || empty($email) || empty($password)) {
            $message = "Please fill in all fields.";
        } else {
            $statement = $pdo->prepare('
                SELECT password, user_id, title, role, email, first_name, failed_attempts, last_failed_at
                FROM "Users"
                WHERE first_name = ? AND last_name = ? AND email = ?
            ');
            $statement->execute([$first, $last, $email]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $attempts_left = max(0, $attempts_left - 1);
                $message = "Invalid credentials. Please try again.";
            } else {
                $timeSinceFail = $row['last_failed_at'] ? time() - strtotime($row['last_failed_at']) : 3601;
                $attempts_left = $timeSinceFail >= 3600 ? 3 : max(0, 3 - $row['failed_attempts']);
                if ($row['failed_attempts'] >= 3 && $timeSinceFail < 3600) {
                    $minutesLeft = ceil((3600 - $timeSinceFail) / 60);
                    $attempts_left = 0;
                    $message = "Too many failed attempts. Try again in $minutesLeft minute(s).";
                } else if (!password_verify($password, $row['password'])) {
                    $pdo->prepare('
                        UPDATE "Users"
                        SET failed_attempts = failed_attempts + 1, last_failed_at = NOW()
                        WHERE user_id = ?
                    ')->execute([$row['user_id']]);
                    $attempts_left = max(0, 3 - ($row['failed_attempts'] + 1));
                    $message = $attempts_left <= 0 ? "Too many failed attempts. You are locked out for 1 hour." : "Invalid credentials. $attempts_left attempt(s) left.";
                } else {
                    session_regenerate_id(true);
                    $_SESSION['email'] = $row['email'];
                    $_SESSION['user'] = $row['title'];
                    $_SESSION['name'] = $row['first_name'];
                    $_SESSION['id'] = $row['user_id'];
                    $_SESSION['role'] = $row['role'];
                    $pdo->prepare('
                        UPDATE "Users" SET failed_attempts = 0, last_failed_at = NULL
                        WHERE user_id = ?
                    ')->execute([$row['user_id']]);
                    if (isset($_POST['remember'])) {
                        $_SESSION['remembered'] = true;
                        $token = hash_hmac('sha256', $row['user_id'], SECRET_KEY);
                        setcookie(
                            'remember_me',
                            $row['user_id'] . '|' . $token,
                            time() + 60 * 60 * 24 * 30,
                            '/',
                            '',
                            false,
                            true
                        );
                    } else {
                        $_SESSION['remembered'] = false;
                    }
                header("Location: " . BASE_URL . "/pages/dashboard/customer/customer.php");
                exit();
            }
        }
    }
?>
<!DOCTYPE html>
<html>
    <head>
        <title> Login </title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"> </script>
    </head>
    <body class="flex flex-col min-h-screen bg-gray-900 text-white">
        <?php require_once "../../components/nav.php"; ?>
        <main class="flex flex-grow items-center justify-center bg-gradient-to-r from-slate-900 to-slate-800 p-6">
            <div class="w-full max-w-3xl space-y-6">
                <div class="text-center bg-gray-800 border border-gray-700 rounded-xl p-8 shadow-lg">
                    <h1 class="text-2xl font-bold"> BDPA Airlines </h1>
                    <h2 class="text-blue-300 mt-2"> Please Login </h2>
                    <br>
                    <h3 class="mb-3 text-red-100"> Attempts Remaining:
                        <span id="incorrect"> 
                            <?= htmlspecialchars($_SESSION['attempts_left'] ?? '3') ?>
                        </span>
                    </h3>
                    <?php if (!empty($message)): ?>
                    <div class="mb-4 text-red-300">
                        <?= htmlspecialchars($message) ?>
                    </div>
                    <?php endif; ?>
                    <a class="mb-3 text-red-100 hover:text-red-300" href="<?= BASE_URL ?>/pages/accountRecovery/recovery.php"> Forgot Your Password? </a>
                </div>
                <div class="text-center bg-gray-800 border border-gray-700 rounded-xl p-8 shadow-lg">
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="email" class="text-xs text-gray-400"> Email </label>
                            <input class="w-full mt-1 h-10 bg-gray-700 border border-gray-600 rounded-lg px-3 text-sm" type="email" name="email" required>
                        </div>
                        <div>
                            <label class="text-xs text-gray-400"> First Name </label>
                            <input class="w-full mt-1 h-10 bg-gray-700 border border-gray-600 rounded-lg px-3 text-sm" type="text" name="first" required>
                        </div>
                        <div>
                            <label class="text-xs text-gray-400"> Last Name </label>
                            <input class="w-full mt-1 h-10 bg-gray-700 border border-gray-600 rounded-lg px-3 text-sm" type="text" name="last" required>
                        </div>
                        <div>
                            <label class="text-xs text-gray-400"> Password </label>
                            <input class="w-full mt-1 h-10 bg-gray-700 border border-gray-600 rounded-lg px-3 text-sm" type="password" name="password" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="flex justify-center text-xs text-gray-400"> Remember Me? </label>
                            <input class="mt-1 h-10 bg-gray-700 border border-gray-600 rounded-lg px-3 scale-150" type="checkbox" name="remember" value="yes">
                        </div>
                        <div class="text-center md:col-span-2 mt-4">
                            <input class="bg-blue-600 text-white px-6 py-2 rounded transition duration-200 hover:bg-blue-700 hover:shadow-md active:scale-95" type="submit" value="Login">
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </body>
</html>