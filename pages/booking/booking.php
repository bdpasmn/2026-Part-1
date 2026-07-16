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
                        $roleLower = strtolower($role);
            header("Location: ./pages/dashboard/{$roleLower}/{$roleLower}.php");
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

    $stmt = $pdo->prepare('SELECT ffm FROM "Users" WHERE user_id = ?');
    $stmt->execute([$userId]);
    $userFfmBalance = (int) $stmt->fetchColumn();

    $canUseFfm = (bool) $userId;

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

    $flightFfmEarn = (int) ($flight['ffms']);
    $flightFfmCost = (int) ($flight['seats']['economy']['priceFfms']);

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

                    <div class="text-right">
                        <div class="relative inline-block group">
                        <span class="text-2xl font-bold text-white cursor-help">$<?= htmlspecialchars($flight['seats']['economy']['priceDollars'] ?? 0) ?></span>
                            <div class="absolute left-1/2 -translate-x-1/2 mt-2 w-max px-3 py-2 text-xs text-white bg-gray-900 rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10">Base ticket price (economy)</div>
                        </div>
                        <?php if ($canUseFfm): ?>
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($flightFfmCost) ?> FFMs</p>
                        <?php endif; ?>
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

                        <!-- Step 4 -->
                        <a href="#step-4" class="step-link flex items-center">
                            <div class="step-indicator w-8 h-8 rounded-full border border-gray-600 text-gray-400 flex items-center justify-center text-sm">4</div>
                            <span class="ml-2 text-gray-400">Extras</span>
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

            <!-- Step 4 -->
            <section id="step-4" class="scroll-mt-52 mb-6">
                <?php include_once('./extras.php'); ?>
            </section>

            <input type="hidden" id="seatInput" value="">
            <section class="scroll-mt-52 mb-6">
                <div class="bg-gray-800 p-6 rounded-lg border border-gray-700">
                    <div class="flex justify-between">
                        <a href="<?= htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'searchFlights.php') ?>" class="px-6 h-12 flex items-center bg-gray-700 hover:bg-gray-600 text-white rounded-lg border border-gray-600">Cancel</a>
                        <div class="relative">
                            <button id="reviewButton" onclick="openReviewModal()" disabled onmouseenter="showTooltip()" onmouseleave="hideTooltip()" class="px-6 h-12 bg-gray-600 text-gray-400 rounded-lg cursor-not-allowed transition">Review Booking Details 📝</button>
                            <div id="reviewTooltip" class="hidden absolute bottom-full right-0 mb-2 w-72 bg-gray-800 border border-gray-700 rounded-lg p-3 text-sm text-gray-300 shadow-lg">
                                Complete all required passenger information and select a seat before reviewing your purchase.
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <?php include_once('reviewModal.php'); ?>
        <?php include_once('paymentModal.php'); ?>

        <script>
            // Whether this user is allowed to pay with frequent flier miles at all (guests can't)
            const canUseFfmJs = <?= json_encode($canUseFfm) ?>;

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

            function getBagCost() {
                const carryIndex = parseInt(document.getElementById("carryOnSelect").value || 0);
                const checkedIndex = parseInt(document.getElementById("checkedSelect").value || 0);

                const carryCost = carryIndex == 0 ? 0 : (carryPrices[carryIndex - 1] || 0);
                const checkedCost = checkedIndex == 0 ? 0 : (checkedPrices[checkedIndex - 1] || 0);

                return carryCost + checkedCost;
            }

            // Opens review modal and copies all form data into hidden purchase fields
            // (payment fields are handled separately in the payment modal, after review)
            window.openReviewModal = function () {
                // Seat selection
                document.getElementById("modalSeatInput") && (document.getElementById("modalSeatInput").value = document.getElementById("seatInput").value);
                if (document.getElementById("purchaseSeat")) {
                    document.getElementById("purchaseSeat").value = document.getElementById("seatInput").value;
                }

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

                // Selected in-flight extras
                const selectedExtras = [];
                let extrasCost = 0;

                document.querySelectorAll(".extra-checkbox:checked").forEach(extra => {
                    selectedExtras.push(extra.dataset.name.replace(/\b\w/g, c => c.toUpperCase()).replace("Wifi", "WiFi"));
                    extrasCost += parseFloat(extra.dataset.price);
                });

                // Display extras in review modal
                document.getElementById("reviewExtras").innerText = selectedExtras.length ? selectedExtras.join(", ") : "None";

                // Display extras cost
                document.getElementById("reviewExtrasCost").innerText = "$" + extrasCost.toFixed(2);

                // Pass extras to purchase.php
                document.getElementById("purchaseExtras").value = JSON.stringify(selectedExtras);

                const bagCost = getBagCost();

                // Extras are dollar-only for now — TODO: once extras.php supports per-item
                // FFM/money selection, split extrasCost the same way the ticket is split below.
                const seatId = document.getElementById("seatInput").value;
                const selectedSeatInfo = (typeof seatTypes !== 'undefined') ? seatTypes[seatId] : null;
                const seatPrice = selectedSeatInfo ? selectedSeatInfo.priceDollars : 0;

                const total = seatPrice + bagCost + extrasCost;

                document.getElementById("reviewFlightCost").innerText = "$" + seatPrice.toFixed(2);
                document.getElementById("reviewFlightSummaryPrice") && (document.getElementById("reviewFlightSummaryPrice").innerText = "$" + seatPrice.toFixed(2));
                document.getElementById("reviewBagCost").innerText = "$" + bagCost;
                document.getElementById("reviewTotal").innerText = "$" + total;

                // Update modal cost display
                document.getElementById("reviewBagCost").innerText = "$" + bagCost;
                document.getElementById("reviewTotal").innerText = "$" + total;

                // The review modal always previews the "pay with money" total. The actual
                // money/FFM split is chosen on the payment modal via refreshPaymentTotals().
                document.getElementById("purchasePrice").value = total.toFixed(2);

                // Show modal
                document.getElementById("reviewModal").classList.remove("hidden");
            };

            // Builds one payment-method row (money vs FFM) per selected extra inside the
            // payment modal, reading data-name/data-price/data-ffms from extras.php's
            // checkboxes. Remembers the user's previous choice per extra across re-renders.
            window.extraPaymentChoices = window.extraPaymentChoices || {};

            function slugify(name) {
                return name.replace(/[^a-z0-9]+/gi, "_");
            }

            window.renderExtrasPaymentRows = function () {
                const rowsContainer = document.getElementById("extrasPaymentRows");
                const section = document.getElementById("extrasPaymentSection");
                if (!rowsContainer || !section) return;

                const selected = Array.from(document.querySelectorAll(".extra-checkbox:checked"));
                rowsContainer.innerHTML = "";

                if (!selected.length) {
                    section.classList.add("hidden");
                    return;
                }

                section.classList.remove("hidden");

                selected.forEach(extra => {
                    const name = extra.dataset.name;
                    const slug = slugify(name);
                    const price = parseFloat(extra.dataset.price);
                    const ffms = parseInt(extra.dataset.ffms || 0);
                    const label = name.replace(/\b\w/g, c => c.toUpperCase()).replace("Wifi", "WiFi");
                    const chosen = window.extraPaymentChoices[slug] || "money";

                    const row = document.createElement("div");
                    row.className = "flex items-center justify-between text-sm";
                    row.dataset.extraRow = slug;
                    row.innerHTML = `
                        <span class="text-gray-300">${label}</span>
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-1 cursor-pointer">
                                <input type="radio" name="extraPayment_${slug}" value="money" class="h-3.5 w-3.5" ${chosen === "money" ? "checked" : ""}>
                                <span class="text-gray-400">$${price.toFixed(2)}</span>
                            </label>
                            <label class="flex items-center gap-1 ${canUseFfmJs ? "cursor-pointer" : "cursor-not-allowed opacity-50"}">
                                <input type="radio" name="extraPayment_${slug}" value="ffm" class="h-3.5 w-3.5" ${chosen === "ffm" ? "checked" : ""} ${canUseFfmJs ? "" : "disabled"}>
                                <span class="text-gray-400">${ffms.toLocaleString()} FFMs</span>
                            </label>
                        </div>
                    `;
                    rowsContainer.appendChild(row);
                });

                // Delegate change events so newly-created radios are covered
                rowsContainer.querySelectorAll('input[type="radio"]').forEach(radio => {
                    radio.addEventListener("change", function () {
                        const row = this.closest("[data-extra-row]");
                        if (row) window.extraPaymentChoices[row.dataset.extraRow] = this.value;
                        refreshPaymentTotals();
                    });
                });
            };

            window.refreshPaymentTotals = function () {
                const seatId = document.getElementById("seatInput").value;
                const selectedSeatInfo = (typeof seatTypes !== 'undefined') ? seatTypes[seatId] : null;
                const seatPrice = selectedSeatInfo ? selectedSeatInfo.priceDollars : 0;
                const ticketFfmCost = selectedSeatInfo ? selectedSeatInfo.priceFfms : 0;

                const ffmEarn = <?= json_encode($flightFfmEarn) ?>;
                const ffmBalance = <?= json_encode($userFfmBalance) ?>;

                const bagCost = getBagCost();

                // Baggage fees are card-only (no FFM pricing in the API spec)
                let moneyDue = bagCost;
                let ffmDue = 0;

                const ticketMoneyLabel = document.getElementById("ticketMoneyLabel");
                const ticketFfmLabel = document.getElementById("ticketFfmLabel");
                if (ticketMoneyLabel) ticketMoneyLabel.innerText = "Pay with card - $" + seatPrice.toFixed(2);
                if (ticketFfmLabel) ticketFfmLabel.innerText = "Pay with Frequent Flier Miles - " + ticketFfmCost.toLocaleString() + " FFMs";

                // Ticket
                const ticketMethod = document.querySelector('input[name="ticketPaymentMethod"]:checked')?.value || "money";
                let ffmEarned = ffmEarn;
                if (ticketMethod === "ffm") {
                    ffmDue += ticketFfmCost;
                    ffmEarned = 0;
                } else {
                    moneyDue += seatPrice;
                }

                // Extras — split per item based on each extra's own radio choice
                const extrasPayload = [];
                document.querySelectorAll(".extra-checkbox:checked").forEach(extra => {
                    const name = extra.dataset.name;
                    const slug = slugify(name);
                    const price = parseFloat(extra.dataset.price);
                    const ffms = parseInt(extra.dataset.ffms || 0);
                    const method = document.querySelector(`input[name="extraPayment_${slug}"]:checked`)?.value || "money";

                    if (method === "ffm") {
                        ffmDue += ffms;
                    } else {
                        moneyDue += price;
                    }

                    extrasPayload.push({ name, price, ffm: ffms, payment_method: method });
                });

                // Track globally so validatePayment() knows whether card details are required
                window.moneyDueNow = moneyDue;

                // Update payment modal display
                const moneyDueEl = document.getElementById("paymentMoneyDue");
                const ffmDueEl = document.getElementById("paymentFfmDue");
                const ffmEarnedEl = document.getElementById("paymentFfmEarned");
                if (moneyDueEl) moneyDueEl.innerText = "$" + moneyDue.toFixed(2);
                if (ffmDueEl) ffmDueEl.innerText = ffmDue + " FFMs";
                if (ffmEarnedEl) ffmEarnedEl.innerText = ffmEarned + " FFMs";


                const warningEl = document.getElementById("ffmBalanceWarning");
                window.ffmOverBalance = canUseFfmJs && ffmDue > ffmBalance;
                if (warningEl) {
                    if (window.ffmOverBalance) {
                        warningEl.classList.remove("hidden");
                        warningEl.innerText = `You need ${ffmDue.toLocaleString()} FFMs but only have ${ffmBalance.toLocaleString()}. Switch something back to card to continue.`;
                    } else {
                        warningEl.classList.add("hidden");
                    }
                }

                // Update hidden fields submitted to purchase.php
                // NOTE: purchase.php must independently recompute/verify these amounts
                // server-side (against the flight/extras data and the user's actual FFM
                // balance) — never trust client-submitted price/FFM values for the real charge.
                const priceField = document.getElementById("purchasePrice");
                const ffmChargeField = document.getElementById("purchaseFfmCharge");
                const ticketMethodField = document.getElementById("purchaseTicketPaymentMethod");
                const ffmEarnedField = document.getElementById("purchaseFfmEarned");
                const extrasField = document.getElementById("purchaseExtras");
                if (priceField) priceField.value = moneyDue.toFixed(2);
                if (ffmChargeField) ffmChargeField.value = ffmDue;
                if (ticketMethodField) ticketMethodField.value = ticketMethod;
                if (ffmEarnedField) ffmEarnedField.value = ffmEarned;
                if (extrasField) extrasField.value = JSON.stringify(extrasPayload);

                validatePayment();
            };

            // Closes review modal
            window.closeReviewModal = function () {
                document.getElementById("reviewModal").classList.add("hidden");
            };

            // Hands off from the review modal to the payment modal
            window.goToPayment = function () {
                closeReviewModal();
                document.getElementById("paymentModal").classList.remove("hidden");
                renderExtrasPaymentRows();
                refreshPaymentTotals();
            };

            // Recalculate whenever the ticket payment method changes
            document.querySelectorAll('input[name="ticketPaymentMethod"]').forEach(radio => {
                radio.addEventListener("change", refreshPaymentTotals);
            });

            // Closes payment modal
            window.closePaymentModal = function () {
                document.getElementById("paymentModal").classList.add("hidden");
            };

            // Goes back from payment to the review modal
            window.backToReview = function () {
                closePaymentModal();
                document.getElementById("reviewModal").classList.remove("hidden");
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

            // Form validation before allowing the review step (passenger info + seat only —
            // payment is validated separately in the payment modal)
            function validateBooking() {
                let valid = true;
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

            // Form validation before allowing the final purchase submit in the payment modal.
            // Card fields are only required when money is actually owed — a ticket paid
            // fully with FFMs (and no dollar-cost extras/bags) doesn't need a card.
            function validatePayment() {
                let valid = true;
                if ((window.moneyDueNow ?? 0) > 0) {
                    document.querySelectorAll(".required-payment").forEach(field => {
                        if (!field.value.trim()) {
                            valid = false;
                        }
                    });
                }

                // Can't submit if the chosen FFM combination exceeds the user's balance
                if (window.ffmOverBalance) {
                    valid = false;
                }

                const button = document.getElementById("confirmPurchaseButton");
                if (!button) return;

                if (valid) {
                    button.disabled = false;
                    button.classList.remove("bg-gray-600", "text-gray-400", "cursor-not-allowed");
                    button.classList.add("bg-blue-600", "hover:bg-blue-700", "text-white");
                } else {
                    button.disabled = true;
                    button.classList.remove("bg-blue-600", "hover:bg-blue-700", "text-white");
                    button.classList.add("bg-gray-600", "text-gray-400", "cursor-not-allowed");
                }
            }

                // Attach validation to all required booking inputs
                document.querySelectorAll(".required-booking").forEach(field => {
                    field.addEventListener("input", validateBooking);
                    field.addEventListener("change", validateBooking);
                });

                // Attach validation to all required payment inputs
                document.querySelectorAll(".required-payment").forEach(field => {
                    field.addEventListener("input", validatePayment);
                    field.addEventListener("change", validatePayment);
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
                    document.getElementById("cardHolderName").value = option.dataset.name || "";
                    document.getElementById("cardNumber").value = option.dataset.number || "";
                    document.getElementById("expirationDate").value = option.dataset.exp || "";
                    document.getElementById("cvc").value = option.dataset.cvc || "";
                    document.getElementById("billingAddress").value = option.dataset.address || "";
                    document.getElementById("zipCode").value = option.dataset.zip || "";

                    // Re-run validation after autofill
                    validatePayment();
                    validateSaveCardOption();
                });

                // Ensures Save Card option is only enabled when payment form is complete
                function validateSaveCardOption() {
                    const checkbox = document.getElementById("saveCardCheckbox");
                    if (!checkbox) {
                        return;
                    }

                    // Required fields for saving card
                    const requiredPaymentFields = ["cardHolderName", "cardNumber", "expirationDate", "cvc", "billingAddress", "zipCode"];
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
                            const flag = document.getElementById("purchaseSaveCardFlag");
                            if (flag) flag.value = "0";
                        }
                    }

                    // Attach validation listeners to payment fields
                    ["cardHolderName", "cardNumber", "expirationDate", "cvc", "billingAddress", "zipCode"].forEach(id => {
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
                    // Close modals if open
                    closeReviewModal();
                    closePaymentModal();

                    // Reset selected seat state
                    selectedSeat = null;
                    document.getElementById("seatInput").value = "";
                    if (document.getElementById("purchaseSeat")) document.getElementById("purchaseSeat").value = "";
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

                    // Reset ticket payment method back to the default
                    const moneyRadio = document.querySelector('input[name="ticketPaymentMethod"][value="money"]');
                    if (moneyRadio) moneyRadio.checked = true;

                    // Reset extras payment choices
                    window.extraPaymentChoices = {};
                    const extrasRows = document.getElementById("extrasPaymentRows");
                    if (extrasRows) extrasRows.innerHTML = "";
                    document.getElementById("extrasPaymentSection")?.classList.add("hidden");

                    // Revalidate forms
                    validateBooking();
                    refreshPaymentTotals();
                }
            });
        </script>
    </body>
</html>