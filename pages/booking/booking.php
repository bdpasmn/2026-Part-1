<?php
    // Starts session + api, database
    session_start();
    require_once "../../api/api.php";
    require_once "../../api/key.php";
    require_once "../../database/db.php";
    
    // Get user role from session (if logged in)
    $role = $_SESSION['role'] ?? null;

    // Redirect admins/root users to their dashboard
    if ($role == 'Admin' || $role == 'Root') {
        if (in_array($role, ['Admin', 'Root'])) {
            header("Location: ../dashboard/{$role}/{$role}.php");
        }
        
        exit;
    }

    // Initialize Airports API client
    $api = new AirportsAPI(AIRPORTS_API_KEY);

    // Get current logged in user ID (if available)
    $userId = $_SESSION['user_id'] ?? null;
    
    // Fetch saved payment cards for this user
    $stmt = $pdo->prepare('SELECT * FROM "Saved Cards" WHERE user_id = ? ORDER BY created_at');
    $stmt->execute([$userId]);
    $savedCards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get flight ID from URL
    $flightId = $_GET['flight_id'] ?? null;
    $flight = null;

    // Ensure a flight was selected
    if (!$flightId) {
        header("Location: bookingFailed.php?" . http_build_query(['message' => 'No flight was selected.']));
        exit;
    }
    
    // Fetch flight details from API
    $flight = $api->getFlightById($flightId);
    // Validate flight exists
    if (!$flight || empty($flight['flightNumber'])) {
        header("Location: bookingFailed.php?" . http_build_query(['message' => 'The selected flight does not exist or is no longer available.']));
        exit;
    }

    // Prevent booking cancelled flights
    if ($flight['status'] == 'cancelled') {
        header("Location: bookingFailed.php?" . http_build_query(['message' => 'The selected flight was cancelled, and can no longer be booked.']));
        exit;
    }
    
    // Prevent booking departed flights
    if ($flight['status'] == 'departed') {
        header("Location: bookingFailed.php?" . http_build_query(['message' => 'The selected flight has departed, and can no longer be booked.']));
        exit;
    }

    // Extract departure timestamp (API returns ms)
    $departureTimestamp = $flight['departFromReceiver'] ?? null;
    // Ensure booking is made at least 24 hours before departure
    if (
        !$departureTimestamp ||
        ($departureTimestamp / 1000) < (time() + 86400)
    ) {
        header("Location: bookingFailed.php?" . http_build_query(['message' => 'Flights must be booked at least 24 hours before departure.']));
        exit;
    }
?>
<html>
    <head>
        <title>Flight Booking</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-900 min-h-screen text-white">
        <!-- Navigation -->
        <?php include "../../components/nav.php"; ?>

        <main class="max-w-7xl mx-auto p-6">
            <!-- Flight Summary Section -->
            <div class="bg-gray-800 border border-gray-700 rounded-lg p-5 mb-0 transition-all duration-300 hover:bg-gray-750 hover:border-blue-400 hover:shadow-lg">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-bold text-white">Flight Summary ✈️ - <?= htmlspecialchars(ucfirst($flight['status'] ?? 'Status')) ?></h2>
                        <p class="text-gray-400">
                            <?= htmlspecialchars($flight['flightNumber'] ?? 'Flight') ?>
                            |
                            <?= htmlspecialchars($flight['landingAt'] ?? '___') ?>
                            →
                            <?= htmlspecialchars($flight['departingTo'] ?? '___') ?>
                            |
                            <?php $timestamp = $flight['departFromReceiver'] ?? null; ?>
                            <?= $timestamp ? date("D, M j Y g:i A", $timestamp / 1000) : "N/A" ?>
                            |
                            <?= htmlspecialchars($flight['airline'] ?? '___') ?> Airlines
                        </p>
                    </div>
                    <div>
                        <span class="text-2xl font-bold text-white">$<?= htmlspecialchars($flight['seatPrice'] ?? '0') ?></span>
                    </div>
                </div>
            </div>

            <!-- Step Progress Navigation -->
            <div class="sticky top-0 z-10 bg-gray-900 pt-6 pb-6">
                <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <!-- Step 1 -->
                        <a href="#step-1" class="step-link flex items-center flex-1">
                            <div class="step-indicator w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold">1</div>
                            <span class="ml-2 text-white">Passenger Info</span>
                            <div class="flex-1 h-px bg-gray-700 mx-4"></div>
                        </a>

                        <!-- Step 2 -->
                        <a href="#step-2" class="step-link flex items-center flex-1">
                            <div class="step-indicator w-8 h-8 rounded-full border border-gray-600 text-gray-400 flex items-center justify-center text-sm">2</div>
                            <span class="ml-2 text-gray-400">Seating</span>
                            <div class="flex-1 h-px bg-gray-700 mx-4"></div>
                        </a>

                        <!-- Step 3 -->
                        <a href="#step-3" class="step-link flex items-center flex-1">
                            <div class="step-indicator w-8 h-8 rounded-full border border-gray-600 text-gray-400 flex items-center justify-center text-sm">3</div>
                            <span class="ml-2 text-gray-400">Baggage</span>
                            <div class="flex-1 h-px bg-gray-700 mx-4"></div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Step 1 -->
            <section id="step-1" class="scroll-mt-52 mb-6">
                <?php include_once('./passengerInfo.php'); ?>
            </section>

            <!-- Step 2 -->
            <section id="step-2" class="scroll-mt-52 mb-6">
                <?php include_once('./seats.php'); ?>
            </section>

            <!-- Step 3 -->
            <section id="step-3" class="scroll-mt-52 mb-6">
                <?php include_once('./bags.php'); ?>
            </section>

            <input type="hidden" id="seatInput" value="">
            <section class="scroll-mt-52 mb-6">
                <div class="bg-gray-800 p-6 rounded-lg border border-gray-700">
                    <div class="flex justify-between">
                        <a href="<?= htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'searchFlights.php') ?>" class="px-6 h-12 flex items-center bg-gray-700 hover:bg-gray-600 text-white rounded-lg border border-gray-600">Cancel</a>
                        <div class="relative">
                            <button id="reviewButton" onclick="openReviewModal()" disabled onmouseenter="showTooltip()" onmouseleave="hideTooltip()" class="px-6 h-12 bg-gray-600 text-gray-400 rounded-lg cursor-not-allowed transition">Review Purchase 📝</button>
                            <div id="reviewTooltip" class="hidden absolute bottom-full right-0 mb-2 w-72 bg-gray-800 border border-gray-700 rounded-lg p-3 text-sm text-gray-300 shadow-lg">
                                Complete all required passenger information and select a seat before reviewing your purchase.
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <?php include_once('reviewModal.php'); ?>

        <script>
            // All sections that have an ID 
            const sections = document.querySelectorAll("section[id]");
            const stepLinks = document.querySelectorAll(".step-link");

            // Updates UI styling for step navigation based on which section is active
            function setActive(id) {
                stepLinks.forEach(link => {
                    const target = link.getAttribute("href").replace("#", "");
                    const indicator = link.querySelector(".step-indicator");
                    const label = link.querySelector("span");

                    const isActive = target == id;

                    if (isActive) {
                        // Active step styling
                        indicator.className = "step-indicator w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold transition transform hover:scale-110 hover:bg-blue-500 cursor-pointer";
                        label.classList.add("text-white");
                        label.classList.remove("text-gray-400");
                    } else {
                        // Inactive step styling
                        indicator.className = "step-indicator w-8 h-8 rounded-full border border-gray-600 text-gray-400 flex items-center justify-center text-sm transition transform hover:scale-110 hover:border-gray-400 hover:text-white cursor-pointer";
                        label.classList.add("text-gray-400");
                        label.classList.remove("text-white");
                    }
                });
            }

            // Detects which section is currently in viewport
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {if (entry.isIntersecting) setActive(entry.target.id);});
            }, { threshold: 0.4 });

            // Attach observer to each step section
            sections.forEach(section => observer.observe(section));

            // Opens review modal and copies all form data into hidden purchase fields
            window.openReviewModal = function () {
                // Save card option + card metadata
                document.getElementById("purchaseSaveCard").value = document.getElementById("saveCardCheckbox")?.checked ? 1 : 0;
                document.getElementById("purchaseCardName").value = document.getElementById("cardName")?.value || "";

                // Payment details
                document.getElementById("purchaseCardholderName").value = document.getElementById("cardholderName").value;
                document.getElementById("purchaseCardNumber").value = document.getElementById("cardNumber").value;
                document.getElementById("purchaseExpirationDate").value = document.getElementById("expirationDate").value;
                document.getElementById("purchaseCvc").value = document.getElementById("cvc").value;
                document.getElementById("purchaseBillingAddress").value = document.getElementById("billingAddress").value;
                document.getElementById("purchaseZipCode").value = document.getElementById("zipCode").value;

                // Seat selection
                document.getElementById("modalSeatInput").value = document.getElementById("seatInput").value;

                // Baggage selection
                document.getElementById("purchaseCarryOn").value = document.getElementById("carryOnSelect").value;
                document.getElementById("purchaseChecked").value = document.getElementById("checkedSelect").value;

                // Passenger info
                document.getElementById("purchaseFirstName").value = document.getElementById("firstName").value;
                document.getElementById("purchaseMiddleName").value = document.getElementById("middleName").value;
                document.getElementById("purchaseLastName").value = document.getElementById("lastName").value;
                document.getElementById("purchaseSex").value = document.getElementById("sex").value;
                document.getElementById("purchaseDob").value = document.getElementById("dob").value;
                document.getElementById("purchasePhone").value = document.getElementById("phone").value;
                document.getElementById("purchaseEmail").value = document.getElementById("email").value;

                // Full passenger display name
                const first = document.getElementById("firstName").value;
                const middle = document.getElementById("middleName").value;
                const last = document.getElementById("lastName").value;

                document.getElementById("reviewPassengerName").innerText = `${first} ${middle} ${last}`.replace(/\s+/g, " ").trim();
                document.getElementById("reviewPassengerSex").innerText = document.getElementById("sex").value;
                document.getElementById("reviewPassengerDob").innerText = document.getElementById("dob").value;
                document.getElementById("reviewPassengerPhone").innerText = document.getElementById("phone").value;
                document.getElementById("reviewPassengerEmail").innerText = document.getElementById("email").value;

                // Show selected seat in review modal
                document.getElementById("reviewSeat").innerText = document.getElementById("seatInput").value;

                // Display baggage summary
                document.getElementById("reviewCarryOn").innerText = document.getElementById("carryOnSelect").value + " Carry-On Bag(s)";
                document.getElementById("reviewChecked").innerText = document.getElementById("checkedSelect").value + " Checked Bag(s)";

                // Seat price from PHP
                const seatPrice = <?= json_encode($flight['seatPrice'] ?? 0) ?>;

                const carryOn = parseInt(document.getElementById("carryOnSelect").value);
                const checked = parseInt(document.getElementById("checkedSelect").value);

                let bagCost = 0;
                // Carry-on bag pricing
                if (carryOn == 2) {
                    bagCost = bagCost + 30;
                }

                // Base checked bag pricing 
                if (checked >= 2) {
                    bagCost = bagCost + 50;
                }

                // Additional checked bags pricing 
                if (checked > 2) {
                    bagCost = bagCost + (checked - 2) * 100;
                }

                // Final total calculation
                const total = seatPrice + bagCost;

                // Update modal cost display
                document.getElementById("reviewBagCost").innerText = "$" + bagCost;
                document.getElementById("reviewTotal").innerText = "$" + total;

                // Store final price for backend
                document.getElementById("purchasePrice").value = total.toFixed(2);

                // Show modal
                document.getElementById("reviewModal").classList.remove("hidden");
            };

            // Closes review modal
            window.closeReviewModal = function () {
                document.getElementById("reviewModal").classList.add("hidden");
            };

            // Save card checkbox + optional card nickname field
            const saveCardCheckbox = document.getElementById("saveCardCheckbox");
            const cardNameContainer = document.getElementById("cardNameContainer");

            // Visibility of card name input when saving card
            saveCardCheckbox?.addEventListener("change", function() {
                if (this.checked) {
                    cardNameContainer.classList.remove("hidden");
                } else {
                    cardNameContainer.classList.add("hidden");
                    document.getElementById("cardName").value = "";
                }
            });

            // Tooltip when save card is disabled
            function showSaveCardTooltip() {
                const checkbox = document.getElementById("saveCardCheckbox");
                if (checkbox && checkbox.disabled) {
                    document.getElementById("saveCardTooltip").classList.remove("hidden");
                }
            }

            // Hide save card tooltip
            function hideSaveCardTooltip() {
                const tooltip = document.getElementById("saveCardTooltip");
                if (tooltip) {
                    tooltip.classList.add("hidden");
                }
            }

            // Form validation before allowing booking
            function validateBooking() {
                let valid = true;
                // Ensure all required booking fields are filled
                document.querySelectorAll(".required-booking").forEach(field => {
                    if (!field.value.trim()) {
                        valid = false;
                    }
                });

                // Ensure seat is selected
                if (!document.getElementById("seatInput").value) {
                    valid = false;
                }

                // Review button
                const button = document.getElementById("reviewButton");

                // Enable or disable review button based on form validation state
                if (valid) {
                    button.disabled = false;

                    // Active button styling
                    button.classList.remove("bg-gray-600", "text-gray-400", "cursor-not-allowed");
                    button.classList.add("bg-blue-600", "hover:bg-blue-700", "text-white");
                } else {
                    button.disabled = true;
                    // Disabled button styling
                    button.classList.remove("bg-blue-600", "hover:bg-blue-700", "text-white");
                    button.classList.add("bg-gray-600", "text-gray-400", "cursor-not-allowed");
                }
            }

                // Attach validation to all required booking inputs
                document.querySelectorAll(".required-booking").forEach(field => {
                    field.addEventListener("input", validateBooking);
                    field.addEventListener("change", validateBooking);
                });

                // Show tooltip when hovering disabled review button
                function showTooltip() {
                    const button = document.getElementById("reviewButton");
                    if (button.disabled) {
                        document.getElementById("reviewTooltip").classList.remove("hidden");
                    }
                }

                // Hide tooltip when mouse leaves button
                function hideTooltip() {
                    document.getElementById("reviewTooltip").classList.add("hidden");
                }

                // Autofill payment form when a saved card is selected
                document.getElementById("savedCard")?.addEventListener("change", function () {
                    const option = this.options[this.selectedIndex];
                    // Ignore empty selection
                    if (!this.value) {
                        return;
                    }

                    // Fill payment fields from selected saved card dataset
                    document.getElementById("cardholderName").value = option.dataset.name || "";
                    document.getElementById("cardNumber").value = option.dataset.number || "";
                    document.getElementById("expirationDate").value = option.dataset.exp || "";
                    document.getElementById("cvc").value = option.dataset.cvc || "";
                    document.getElementById("billingAddress").value = option.dataset.address || "";
                    document.getElementById("zipCode").value = option.dataset.zip || "";

                    // Re-run validation after autofill
                    validateBooking();
                });

                // Ensures Save Card option is only enabled when payment form is complete
                function validateSaveCardOption() {
                    const checkbox = document.getElementById("saveCardCheckbox");
                    if (!checkbox) {
                        return;
                    }

                    // Required fields for saving card
                    const requiredPaymentFields = ["cardholderName", "cardNumber", "expirationDate", "cvc", "billingAddress", "zipCode"];
                        let complete = true;
                        // Check if all payment fields are filled
                        requiredPaymentFields.forEach(id => {
                            const field = document.getElementById(id);
                            if (!field || !field.value.trim()) {
                                complete = false;
                            }
                        });

                        // Disable save-card option if payment info incomplete
                        checkbox.disabled = !complete;
                        // Uncheck if it becomes invalid
                        if (!complete) {
                            checkbox.checked = false;
                        }
                    }

                    // Attach validation listeners to payment fields
                    ["cardholderName", "cardNumber", "expirationDate", "cvc", "billingAddress", "zipCode"].forEach(id => {
                    const field = document.getElementById(id);

                    if (field) {
                        field.addEventListener("input", validateSaveCardOption);
                        field.addEventListener("change", validateSaveCardOption);
                    }
                });

                // Save-card state
                validateSaveCardOption();

                // If navigate back to page
                window.addEventListener("pageshow", function (event) {
                // Detect cache restore
                if (event.persisted || performance.getEntriesByType("navigation")[0]?.type === "back_forward") {
                    // Close modal if open
                    closeReviewModal();

                    // Reset selected seat state
                    selectedSeat = null;
                    document.getElementById("seatInput").value = "";
                    document.getElementById("modalSeatInput").value = "";
                    document.getElementById("selectedSeat").innerText = "None selected";
                    document.getElementById("selectedPrice").innerText = "$0";

                    // Reset all seat button styles
                    document.querySelectorAll(".seat-btn").forEach(btn => {
                        btn.classList.remove("bg-blue-500");
                        btn.classList.remove("border-blue-300");
                        btn.classList.add("bg-slate-600");
                        btn.classList.add("border-gray-500");
                    });

                    // Reset save card
                    const saveCardCheckbox = document.getElementById("saveCardCheckbox");
                    const cardNameContainer = document.getElementById("cardNameContainer");
                    const cardName = document.getElementById("cardName");

                    if (saveCardCheckbox) {
                        saveCardCheckbox.checked = false;
                    }

                    if (cardNameContainer) {
                        cardNameContainer.classList.add("hidden");
                    }

                    if (cardName) {
                        cardName.value = "";
                    }

                    // Revalidate form
                    validateBooking();
                }
            });
        </script>
    </body>
</html>