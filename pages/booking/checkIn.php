<?php
session_start();
require_once "../../database/db.php";

// Initialize variables
$ticket = null;
$error = "";
$canCheckIn = false;

// Get confirmation code and last name from query string or form
$confirmation = $_GET["confirmation"] ?? ($_POST["confirmation"] ?? null);
$lastName = $_POST["last_name"] ?? null;

// Handle check-in submission
if (isset($_POST["checkin"])) {
    if ($confirmation && $lastName) {
        // Verify booking exists in database
        $stmt = $pdo->prepare("
            SELECT ticket_id
            FROM \"Tickets\"
            WHERE confirmation_code = ?
            AND LOWER(name_last) = LOWER(?)
            LIMIT 1
        ");

        $stmt->execute([$confirmation, $lastName]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ticket) {
            // Mark ticket as checked in
            $stmt = $pdo->prepare("
                UPDATE \"Tickets\"
                SET checked_in = TRUE
                WHERE ticket_id = ?
            ");

            $stmt->execute([$ticket["ticket_id"]]);
        }

        // Redirect to ticket view page
        header(
            "Location: ../ticket/ticket.php?confirmation=" .
                urlencode($confirmation)
        );
        exit();
    }
}

// Handle booking cancellation
if (isset($_POST["cancel"])) {
    if ($confirmation && $lastName) {
        // Mark booking as cancelled
        $stmt = $pdo->prepare("
            UPDATE \"Tickets\"
            SET status = 'cancelled'
            WHERE confirmation_code = ?
            AND LOWER(name_last) = LOWER(?)
        ");

        $stmt->execute([$confirmation, $lastName]);

        // Redirect based on user role
        $role = $_SESSION["role"] ?? "";

        if (in_array($role, ["Customer", "Admin", "Root"])) {
            $roleLower = strtolower($role);
            header("Location: ../dashboard/{$roleLower}/{$roleLower}.php");
        } else {
            header("Location: searchFlights.php");
        }

        exit();
    }
}

// Fetch ticket details if confirmation and last name provided
if ($confirmation && $lastName) {
    $stmt = $pdo->prepare("
        SELECT * FROM \"Tickets\"
        WHERE confirmation_code = ?
        AND LOWER(name_last) = LOWER(?)
        LIMIT 1
    ");

    $stmt->execute([$confirmation, $lastName]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $error = "Booking not found.";
    } else {
        // Check ticket status and redirect if already checked in
        if ($ticket["status"] === "cancelled") {
            $error = "This booking has been cancelled.";
            $ticket = null;
        } elseif ($ticket["checked_in"]) {
            header(
                "Location: ../ticket/ticket.php?confirmation=" .
                    urlencode($confirmation)
            );
            exit();
        }

        // Check if check-in window is open (hardocded departure time inplace for api, usually has to be within 24 hours of departure)
        $departureTime = strtotime("2026-07-04 14:30:00");
        $canCheckIn = time() >= $departureTime - 86400;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Check-In</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-900 text-white">
    <?php include __DIR__ . "/../../components/nav.php"; ?>

<main class="max-w-4xl mx-auto">

<!-- Header section -->
<section class="p-6">
    <div class="max-w-3xl mx-auto bg-gray-800 border border-gray-700 rounded-xl p-8 shadow-lg">

        <p class="tracking-[0.25em] text-sm text-blue-300 mb-3">AIRLINE CHECK-IN ✈️</p>
        <h2 class="text-4xl font-bold mb-3">Check In to Your Flight</h2>
        <p class="text-gray-400 mb-4">
            Enter your last name and confirmation code to access your booking.
        </p>

        <!-- Error message display -->
        <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 bg-red-600/20 border border-red-500 text-red-300 rounded-lg transition-all duration-300 hover:-translate-y-1 hover:border-red-400 cursor-default">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Success message when booking found -->
        <?php if (!empty($ticket) && !$error): ?>
            <div class="mb-4 p-3 bg-green-600/20 border border-green-500 text-green-300 rounded-lg">
                Booking found for
                <?= htmlspecialchars(
                    $ticket["name_first"] . " " . $ticket["name_last"]
                ) ?>
            </div>
        <?php endif; ?>

        <!-- Search form - only show if no booking found -->
        <?php if (!$ticket): ?>
            <form method="POST" class="space-y-6">

                <!-- Last name input -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Last Name</label>
                    <input type="text" name="last_name" required
                           placeholder="Enter your last name"
                           class="w-full h-12 px-4 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
                </div>

                <!-- Confirmation code input -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Confirmation Code</label>
                    <input type="text" name="confirmation"
                           required placeholder="Enter confirmation code"
                           class="w-full h-12 px-4 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
                </div>

                <!-- Search button -->
                <button type="submit"
                        class="w-full h-12 bg-blue-600 text-white rounded-lg font-medium transition duration-200 hover:bg-blue-700 hover:shadow-md">
                    Find Booking
                </button>

            </form>
        <?php endif; ?>

        <!-- Booking details display - only show if ticket found -->
        <?php if ($ticket): ?>

            <div class="mt-6 space-y-6">

                <!-- Confirmation code display -->
                <div>
                    <p class="text-gray-400">Confirmation</p>
                    <h3 class="text-2xl font-bold text-blue-400">
                        <?= htmlspecialchars($ticket["confirmation_code"]) ?>
                    </h3>
                </div>

                <!-- Passenger name display -->
                <div>
                    <p class="text-gray-400">Passenger</p>
                    <p class="text-xl">
                        <?= htmlspecialchars(
                            $ticket["name_first"] . " " . $ticket["name_last"]
                        ) ?>
                    </p>
                </div>

                <!-- Check-in availability status -->
                <div class="p-4 rounded-lg border
                    <?= $canCheckIn
                        ? "border-green-600 bg-green-900/20"
                        : "border-yellow-600 bg-yellow-900/20" ?>">

                    <?php if ($canCheckIn): ?>
                        <p>✅ You may now check in for this flight.</p>
                    <?php else: ?>
                        <p>⏳ Check-in opens 24 hours before departure.</p>
                    <?php endif; ?>

                </div>

                <!-- Action buttons - only show if check-in is available -->
                <?php if ($canCheckIn): ?>

                    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">

                        <!-- Check-in button -->
                        <form method="POST" class="w-full">

                            <input type="hidden" name="confirmation"
                                   value="<?= htmlspecialchars(
                                       $ticket["confirmation_code"]
                                   ) ?>">

                            <input type="hidden" name="last_name"
                                   value="<?= htmlspecialchars($ticket["name_last"]) ?>">

                            <button name="checkin"
                                    class="w-full h-12 bg-blue-600 text-white rounded-lg font-medium transition duration-200 hover:bg-blue-700 hover:shadow-md active:scale-[0.99]">
                                Check In
                            </button>

                        </form>

                        <!-- Cancel booking button -->
                        <form method="POST" class="w-full">

                            <input type="hidden" name="confirmation"
                                   value="<?= htmlspecialchars(
                                       $ticket["confirmation_code"]
                                   ) ?>">

                            <input type="hidden" name="last_name"
                                   value="<?= htmlspecialchars($ticket["name_last"]) ?>">

                            <button name="cancel"
                                    onclick="return confirm('Cancel booking?')"
                                    class="w-full h-12 bg-red-600 text-white rounded-lg font-medium transition duration-200 hover:bg-red-700 hover:shadow-md active:scale-[0.99]">
                                Cancel Booking
                            </button>

                        </form>

                    </div>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>
</section>
</main>

</body>
</html>