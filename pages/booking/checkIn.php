<?php
session_start();
require_once "../../database/db.php";
require_once __DIR__ . "/../../api/api.php";
require_once __DIR__ . "/../../api/key.php";

$api = new AirportsAPI(AIRPORTS_API_KEY);

// Initialize variables
$ticket = null;
$pageError = null;
$canCheckIn = false;

// Get confirmation code and last name from query string or form
$confirmation = $_GET["confirmation"] ?? ($_POST["confirmation"] ?? null);
$lastName = $_POST["last_name"] ?? null;
$confirmationFromUrl = isset($_GET["confirmation"]) ? true : false;

// Check if confirmation code is empty
if ($confirmationFromUrl && empty($confirmation)) {
    $pageError = "missing_confirmation";
}

// Handle search submission (when no confirmation in URL)
if (isset($_POST["search"]) && !$confirmationFromUrl) {
    if (!$confirmation || !$lastName) {
        $pageError = "missing_fields";
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM \"Tickets\"
            WHERE confirmation_code = ?
            AND LOWER(name_last) = LOWER(?)
            LIMIT 1
        ");

        $stmt->execute([$confirmation, $lastName]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            $pageError = "booking_not_found";
        } elseif ($ticket["status"] === "cancelled") {
            $pageError = "booking_cancelled";
        } elseif ($ticket["checked_in"]) {
            // Already checked in, redirect to ticket page
            header(
                "Location: ../ticket/ticket.php?confirmation=" .
                    urlencode($ticket["confirmation_code"])
            );
            exit();
        } else {
            // Fetch flight details for check-in availability
            $flightId = $ticket['flight_id'] ?? null;
            
            if (!$flightId) {
                $pageError = "flight_not_found";
            } else {
                $flightData = $api->getFlightById($flightId);
                
                if (!$flightData) {
                    $pageError = "flight_not_found";
                } else {
                    $departureTimestamp = $flightData['departFromReceiver'] ?? null;
                    
                    if ($departureTimestamp) {
                        $departureSeconds = $departureTimestamp / 1000;
                        $hoursUntilDeparture = ($departureSeconds - time()) / 3600;
                        $canCheckIn = $hoursUntilDeparture <= 24 && $hoursUntilDeparture > 0;
                        
                        if ($hoursUntilDeparture <= 0) {
                            $pageError = "flight_departed";
                        }
                    }
                }
            }
        }
    }
}

// Auto-fetch booking if confirmation code in URL
if ($confirmationFromUrl && $confirmation && !$pageError) {
    $stmt = $pdo->prepare("
        SELECT * FROM \"Tickets\"
        WHERE confirmation_code = ?
        LIMIT 1
    ");
    $stmt->execute([$confirmation]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        $pageError = "booking_not_found";
    } elseif ($ticket["status"] === "cancelled") {
        $pageError = "booking_cancelled";
    } elseif ($ticket["checked_in"]) {
        // Already checked in, redirect to ticket page
        header(
            "Location: ../ticket/ticket.php?confirmation=" .
                urlencode($confirmation)
        );
        exit();
    } else {
        // Fetch flight details for check-in availability
        $flightId = $ticket['flight_id'] ?? null;
        
        if (!$flightId) {
            $pageError = "flight_not_found";
        } else {
            $flightData = $api->getFlightById($flightId);
            
            if (!$flightData) {
                $pageError = "flight_not_found";
            } else {
                $departureTimestamp = $flightData['departFromReceiver'] ?? null;
                
                if ($departureTimestamp) {
                    $departureSeconds = $departureTimestamp / 1000;
                    $hoursUntilDeparture = ($departureSeconds - time()) / 3600;
                    $canCheckIn = $hoursUntilDeparture <= 24 && $hoursUntilDeparture > 0;
                    
                    if ($hoursUntilDeparture <= 0) {
                        $pageError = "flight_departed";
                    }
                }
            }
        }
    }
}

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
            
            // Redirect to ticket view page
            header(
                "Location: ../ticket/ticket.php?confirmation=" .
                    urlencode($confirmation)
            );
            exit();
        } else {
            $pageError = "booking_not_found";
        }
    }
}

// Handle booking cancellation
if (isset($_POST["cancel"])) {
    if ($confirmation && $ticket) {
        // Mark booking as cancelled
        $stmt = $pdo->prepare("
            UPDATE \"Tickets\"
            SET status = 'cancelled'
            WHERE confirmation_code = ?
        ");

        $stmt->execute([$confirmation]);

        // Redirect based on user role
        $role = $_SESSION["role"] ?? "";

        if (in_array($role, ["Customer", "Admin", "Root", 'Attendant'])) {
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

        // Check if check-in window is open (hardocded departure time inplace for api, usually has to be within 36 hours of departure)
        $departureTime = strtotime("2026-07-04 14:30:00");
        $canCheckIn = time() >= $departureTime - 129600; // 36 hours before departure
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
            <?php if ($confirmationFromUrl): ?>
                Check in for your flight
            <?php else: ?>
                Enter your last name and confirmation code to access your booking.
            <?php endif; ?>
        </p>

        <!-- Error display -->
        <?php if (!empty($pageError)): ?>
            <div class="mb-4 p-3 bg-red-600/20 border border-red-500 text-red-300 rounded-lg transition-all duration-300 hover:-translate-y-1 hover:border-red-400 cursor-default">
                <?php 
                $errorMessages = [
                    'missing_confirmation' => 'Confirmation code is required.',
                    'missing_fields' => 'Please enter both your last name and confirmation code.',
                    'booking_not_found' => 'Booking not found. Please check your confirmation code and last name.',
                    'booking_cancelled' => 'This booking has been cancelled.',
                    'flight_not_found' => 'The flight associated with this booking could not be found.',
                    'flight_departed' => 'This flight has already departed. Check-in is no longer available.'
                ];
                echo htmlspecialchars($errorMessages[$pageError] ?? 'An error occurred.');
                ?>
            </div>
        <?php endif; ?>

        <!-- Success message -->
        <?php if (!empty($ticket) && !$pageError): ?>
            <div class="mb-4 p-3 bg-green-600/20 border border-green-500 text-green-300 rounded-lg">
                Booking found for
                <?= htmlspecialchars(
                    $ticket["name_first"] . " " . $ticket["name_last"]
                ) ?>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <?php if (!$ticket || $pageError): ?>
            <?php if (!$confirmationFromUrl || $pageError): ?>
                <!-- No confirmation code in URL - show full search form, or show form if error occurred -->
                <form method="POST" class="space-y-6">

                    <!-- Last name input -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Last Name</label>
                        <input type="text" name="last_name" required
                               placeholder="Enter your last name"
                               value="<?= htmlspecialchars($_POST["last_name"] ?? "") ?>"
                               class="w-full h-12 px-4 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
                    </div>

                    <!-- Confirmation code input -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Confirmation Code</label>
                        <input type="text" name="confirmation" required
                               placeholder="Enter confirmation code"
                               value="<?= htmlspecialchars($_POST["confirmation"] ?? $_GET["confirmation"] ?? "") ?>"
                               class="w-full h-12 px-4 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
                    </div>

                    <!-- Search button -->
                    <button type="submit" name="search"
                            class="w-full h-12 bg-blue-600 text-white rounded-lg font-medium transition duration-200 hover:bg-blue-700 hover:shadow-md">
                        Find Booking
                    </button>

                </form>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Booking details -->
        <?php if ($ticket && !$pageError): ?>

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
                        <p>⏳ Check-in opens 36 hours before departure.</p>
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

                            <button name="checkin" type="submit"
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

                            <button name="cancel" type="submit"
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