<?php
    session_start();
    require_once "../../database/db.php";

    if (isset($_SESSION['role']) && ($_SESSION['role'] == 'Admin' || $_SESSION['role'] == 'Root')) {
        header("Location: ../../index.php");
        exit;
    }

    $message = "";

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $lastName = trim($_POST["last_name"] ?? "");
        $confirmationCode = trim($_POST["confirmation_code"] ?? "");

        $stmt = $pdo->prepare("SELECT * FROM \"Tickets\" WHERE LOWER(name_last) = LOWER(:last_name) AND confirmation_code = :confirmation_code LIMIT 1");
        $stmt->execute([":last_name" => $lastName, ":confirmation_code" => $confirmationCode]);

        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ticket) {
            header("Location: ticket.php?confirmation=" . urlencode($ticket["confirmation_code"]));
            exit;
        }

        $message = "No ticket found matching that last name and confirmation code.";
    }
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Ticket Lookup</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-900 min-h-screen text-white">
        <div class="w-full min-h-screen bg-gray-900">
            <?php include "../../components/nav.php"; ?>

            <section class="p-6">
                <div class="max-w-3xl mx-auto bg-gray-800 border border-gray-700 rounded-xl p-8 shadow-lg">
                    <p class="tracking-[0.25em] text-sm text-blue-300 mb-3">TICKET LOOKUP🔎</p>
                    <h2 class="text-4xl font-bold mb-3">View Your Ticket</h2>
                    <p class="text-gray-400 mb-4">Enter the last name and confirmation code to view your ticket.</p>

                    <?php if (!empty($message)): ?>
                        <div class="mb-4 p-3 bg-red-600/20 border border-red-500 text-red-300 rounded-lg transition-all duration-300 hover:-translate-y-1 hover:border-red-400 cursor-default">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Last Name</label>
                            <input type="text" name="last_name" required placeholder="Enter your last name" class="w-full h-12 px-4 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Confirmation Code</label>
                            <input type="text" name="confirmation_code" required placeholder="Enter confirmation code" class="w-full h-12 px-4 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
                        </div>

                        <button type="submit" class="w-full h-12 bg-blue-600 text-white rounded-lg font-medium transition duration-200 hover:bg-blue-700 hover:shadow-md">Find Ticket</button>
                    </form>
                </div>
            </section>
        </div>
    </body>
</html>