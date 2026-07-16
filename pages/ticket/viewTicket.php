<?php
    // Session start and database connection
    session_start();

    require_once "../../database/db.php";
    require_once __DIR__ . '/../../api/api.php';

    // Get user role from session (used for redirect protection)
    $role = $_SESSION['role'] ?? null;

    // Prevent Admin/Root from accessing this customer-only page
    if ($role == 'Admin' || $role == 'Root' || $role == 'Attendant') {
        // Redirect admins/roots to their dashboard
        if (in_array($role, ['Admin', 'Root', 'Attendant'])) {
            header("Location: ../dashboard/{$role}/{$role}.php");
        }

        exit;
    }

    // Message shown when lookup fails
    $message = "";

    // Handle form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Get and sanitize user inputs
        $lastName = trim($_POST["last_name"] ?? "");
        $confirmationCode = trim($_POST["confirmation_code"] ?? "");

        // Query ticket using:
        $stmt = $pdo->prepare("
            SELECT * 
            FROM \"Tickets\" 
            WHERE LOWER(name_last) = LOWER(:last_name) AND confirmation_code = :confirmation_code 
            LIMIT 1
        ");
        $stmt->execute([":last_name" => $lastName, ":confirmation_code" => $confirmationCode]);

        // Fetch ticket result
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        // If ticket found → redirect to ticket page
        if ($ticket) {
            header("Location: ticket.php?confirmation=" . urlencode($ticket["confirmation_code"]));
            exit;
        }

        // If no match → show error message
        $message = "No ticket found matching that last name and confirmation code.";
    }
?>
<html>
    <head>
        <title>Ticket Lookup</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-900 min-h-screen text-white">
        <div class="w-full min-h-screen bg-gray-900">
            <!-- Navigation bar -->
            <?php include "../../components/nav.php"; ?>

            <section class="p-6">
                <!-- Main lookup container -->
                <div class="max-w-3xl mx-auto bg-gray-800 border border-gray-700 rounded-xl p-8 shadow-lg">
                    <p class="tracking-[0.25em] text-sm text-blue-300 mb-3">TICKET LOOKUP 🔎</p>
                    <h2 class="text-4xl font-bold mb-3">View Your Ticket</h2>
                    <p class="text-gray-400 mb-4">Enter the last name and confirmation code to view your ticket.</p>

                    <!-- Error message display -->
                    <?php if (!empty($message)): ?>
                        <div class="mb-4 p-3 bg-red-600/20 border border-red-500 text-red-300 rounded-lg transition-all duration-300 hover:-translate-y-1 hover:border-red-400 cursor-default">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Lookup form -->
                    <form method="POST" class="space-y-6">
                        <!-- Last name input -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Last Name</label>
                            <input type="text" name="last_name" required placeholder="Enter your last name" class="w-full h-12 px-4 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
                        </div>

                        <!-- Confirmation code input -->
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Confirmation Code</label>
                            <input type="text" name="confirmation_code" required placeholder="Enter confirmation code" class="w-full h-12 px-4 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
                        </div>

                        <!-- Submit button -->
                        <button type="submit"
                            class="w-full h-12 bg-blue-600 text-white rounded-lg font-medium transition duration-200 hover:bg-blue-700 hover:shadow-md">
                            Find Ticket
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </body>
</html>