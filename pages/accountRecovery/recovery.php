<?php 
    // Start the session and connect to the database/api
    session_start();
    require_once "../../database/db.php";
    require_once __DIR__ . "/../../api/api.php";

    // Redirect users who are already logged in
    if (isset($_SESSION['user_id'])) {
        $role = strtolower($_SESSION['role']) ?? '';
        if (in_array($role, ['customer', 'admin', 'root'])) {
            header("Location: ../dashboard/{$role}/{$role}.php");
        }
        exit;
    }

    // Display a one-time flash message (if one exists)
    $message = "";

    if (isset($_SESSION["flash_message"])) {
        $message = $_SESSION["flash_message"];
        unset($_SESSION["flash_message"]);
    }

    // Allow the user to restart account recovery with a different email
    if (isset($_GET["reset"]) && $_GET["reset"] == 1) {
        unset($_SESSION["recovery_email"]);
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }

    // Load the saved recovery email and its security questions
    $email = $_SESSION["recovery_email"] ?? "";
    $userSecurity = null;

    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT * FROM \"User Security Questions\" WHERE LOWER(email) = LOWER(:email) LIMIT 1");
        $stmt->execute([":email" => $email]);
        $userSecurity = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Handle form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $emailInput = strtolower(trim($_POST["email"] ?? ""));
        $answers = $_POST["answers"] ?? [];

        $_SESSION["recovery_email"] = $emailInput;

        // Find the account associated with the email
        $stmt = $pdo->prepare("SELECT * FROM \"User Security Questions\" WHERE LOWER(email) = LOWER(:email) LIMIT 1");
        $stmt->execute([":email" => $emailInput]);
        $userSecurityCheck = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userSecurityCheck) {
            $_SESSION["flash_message"] = "No account found with that email.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        // Ensure all three answers were submitted
        if (!isset($answers[1]) || !isset($answers[2]) || !isset($answers[3])) {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        // Compare submitted answers with the stored password hashes
        $a1 = $answers[1];
        $a2 = $answers[2];
        $a3 = $answers[3];

        $db1 = $userSecurityCheck["question1_answer"] ?? "";
        $db2 = $userSecurityCheck["question2_answer"] ?? "";
        $db3 = $userSecurityCheck["question3_answer"] ?? "";

        if (password_verify($a1, $db1) && password_verify($a2, $db2) && password_verify($a3, $db3)) {
            unset($_SESSION["recovery_email"]);
            header("Location: resetPassword.php?email=" . urlencode($emailInput));
            exit;
        } else {
            $_SESSION["flash_message"] = "Security answers are incorrect.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
?>
<html>
    <head>
        <title>Account Recovery</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>

    <body class="bg-gray-900 min-h-screen text-white">
        <div class="w-full min-h-screen bg-gray-900">

            <!-- Navigation -->
            <?php include "../../components/nav.php"; ?>

            <section class="p-6">
                <div class="max-w-3xl mx-auto bg-gray-800 border border-gray-700 rounded-xl p-8 shadow-lg">
                    <!-- Page heading -->
                    <p class="tracking-[0.25em] text-sm text-blue-300 mb-3">ACCOUNT RECOVER🔒</p>
                    <h2 class="text-4xl font-bold mb-3">Recover Your Account</h2>
                    <p class="text-gray-400 mb-4">Answer your security questions to verify your identity.</p>

                    <!-- Flash message -->
                    <?php if (!empty($message)): ?>
                    <div class="mb-4 p-3 bg-red-600/20 border border-red-500 text-red-300 rounded-lg transition-all duration-300 hover:-translate-y-1 hover:border-red-400 cursor-default">
                        <?= htmlspecialchars($message) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Recovery form -->
                    <form method="POST" class="space-y-6">

                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Email Address</label>
                            <input type="text" name="email" value="<?= htmlspecialchars($email) ?>" <?= !empty($userSecurity) ? "readonly class='w-full h-12 px-4 bg-gray-600 border border-gray-500 rounded-lg text-gray-300 cursor-not-allowed'" : "class='w-full h-12 px-4 bg-gray-700 border border-gray-600 rounded-lg text-white transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white'"?> required placeholder="Enter your email"></div>
                        <?php if (!empty($userSecurity)): ?>

                        <!-- Option to choose another email -->
                        <a href="?reset=1" class="inline-flex items-center gap-2 mb-4 px-4 py-2 text-sm font-medium text-gray-200 bg-gray-700 border border-gray-600 rounded-lg hover:bg-gray-600 hover:border-gray-500 hover:-translate-y-0.5 transition duration-200 shadow-sm">
                            ← Change Email
                        </a>

                        <!-- Security Question #1 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Security Question #1</label>

                            <div class="bg-gray-700 border border-gray-600 rounded-lg p-4 text-gray-200 transition-all duration-300 hover:border-blue-400 hover:shadow-lg">
                                <?= htmlspecialchars($userSecurity["question1"] ?? "") ?>
                            </div>

                            <input type="text" name="answers[1]" required class="w-full h-12 mt-3 px-4 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
                        </div>

                        <!-- Security Question #2 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Security Question #2</label>

                            <div class="bg-gray-700 border border-gray-600 rounded-lg p-4 text-gray-200 transition-all duration-300 hover:border-blue-400 hover:shadow-lg">
                                <?= htmlspecialchars($userSecurity["question2"] ?? "") ?>
                            </div>

                            <input type="text" name="answers[2]" required class="w-full h-12 mt-3 px-4 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
                        </div>

                        <!-- Security Question #3 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Security Question #3</label>

                            <div class="bg-gray-700 border border-gray-600 rounded-lg p-4 text-gray-200 transition-all duration-300 hover:border-blue-400 hover:shadow-lg">
                                <?= htmlspecialchars($userSecurity["question3"] ?? "") ?>
                            </div>

                            <input type="text" name="answers[3]" required class="w-full h-12 mt-3 px-4 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
                        </div>

                        <?php else: ?>

                        <!-- Prompt shown before an email is entered -->
                        <div class="text-gray-400">
                            Enter your email to load your security questions.
                        </div>

                        <?php endif; ?>

                        <!-- Submit -->
                        <button type="submit" class="w-full h-12 bg-blue-600 text-white rounded-lg font-medium transition duration-200 hover:bg-blue-700 hover:shadow-md">
                            Recover Account
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </body>
</html>