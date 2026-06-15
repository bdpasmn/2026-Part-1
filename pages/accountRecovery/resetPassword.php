<?php
    require_once "../../database/db.php";

    $message = "";
    $success = "";

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $password = trim($_POST["password"] ?? "");
        $confirm = trim($_POST["confirm_password"] ?? "");

        if ($password !== $confirm) {
            $message = "Passwords do not match.";
        } elseif (strlen($password) <= 10) {
            $message = "Password too weak. Must be more than 10 characters.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $email = $_GET["email"] ?? null;

            if (!$email) {
                $message = "Missing account identifier.";
            } else {
                $stmt = $pdo->prepare("UPDATE \"Users\" SET password = :password WHERE LOWER(email) = LOWER(:email)");
                $stmt->execute([":password" => $hashedPassword, ":email" => $email]);

                if ($stmt->rowCount() > 0) {
                    header("Location: ../auth/auth.php");
                    exit;
                } else {
                    $message = "Account wasn't found. Try again?";
                }
            }
        }
    }
?>
<html>
    <head>
        <title>Reset Password</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-900 min-h-screen text-white">
        <div class="w-full min-h-screen bg-gray-900">
            <?php include "../../components/nav.php"; ?>

            <section class="p-6">
                <div class="max-w-3xl mx-auto bg-gray-800 border border-gray-700 rounded-xl p-8 shadow-lg">
                    <p class="tracking-[0.25em] text-sm text-blue-300 mb-3">ACCOUNT RECOVERY</p>
                    <h2 class="text-4xl font-bold mb-3">Reset Password</h2>
                    <p class="text-gray-400 mb-6">Create a new password for your account.</p>

                    <?php if (!empty($message)): ?>
                        <div class="mb-4 p-3 bg-red-600/20 border border-red-500 text-red-300 rounded-lg transition-all duration-300 hover:-translate-y-1">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="mb-4 p-3 bg-green-600/20 border border-green-500 text-green-300 rounded-lg transition-all duration-300 hover:-translate-y-1 hover:shadow-lg hover:shadow-green-500/20">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">New Password</label>
                            <input type="password" id="password" name="password" required placeholder="Enter your new password" class="w-full h-12 px-4 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
                            <p id="strengthText" class="mt-2 text-sm text-gray-400">Password Strength: Not entered</p>
                            <p class="mt-1 text-xs text-gray-400">Weak (≤10 chars) rejected • Strong (>17 chars) recommended</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Confirm Password</label>
                            <input type="password" name="confirm_password" required placeholder="Confirm your new password" class="w-full h-12 px-4 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
                        </div>

                        <button type="submit" class="w-full h-12 bg-blue-600 text-white rounded-lg font-medium transition duration-200 hover:bg-blue-700 hover:shadow-md">Update Password</button>
                    </form>
                </div>
            </section>
        </div>

        <script>
            const passwordInput = document.getElementById('password');
            const strengthText = document.getElementById('strengthText');

            passwordInput.addEventListener('input', () => {
                const len = passwordInput.value.length;

                if (len == 0) {
                    strengthText.textContent = "Password Strength: Not entered";
                    strengthText.className = "mt-2 text-sm text-gray-400";
                }
                else if (len <= 10) {
                    strengthText.textContent = "Password Strength: Weak (Rejected)";
                    strengthText.className = "mt-2 text-sm text-red-400";
                }
                else if (len <= 17) {
                    strengthText.textContent = "Password Strength: Medium";
                    strengthText.className = "mt-2 text-sm text-yellow-400";
                }
                else {
                    strengthText.textContent = "Password Strength: Strong";
                    strengthText.className = "mt-2 text-sm text-green-400";
                }
            });
        </script>
    </body>
</html>