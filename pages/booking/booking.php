<?php
    session_start();

    require_once "../../api/api.php";
    require_once "../../api/key.php";
    require_once "../../database/db.php";
    
    $role = $_SESSION['role'] ?? null;

    if ($role == 'admin' || $role == 'root') {
        header("Location: ../../index.php");
        exit;
    }

    $api = new AirportsAPI(AIRPORTS_API_KEY);

    $userId = $_SESSION['user_id'] ?? null;

    $stmt = $pdo->prepare('SELECT * FROM "Saved Cards" WHERE user_id = ? ORDER BY created_at');

    $stmt->execute([$userId]);

    $savedCards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $flightId = $_GET['flight_id'] ?? null;
    $flight = null;

    if ($flightId) {
        $flight = $api->getFlightById($flightId);
    }
?>

<html>
    <head>
        <title>Flight Booking</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-900 min-h-screen text-white">
        <?php include "../../components/nav.php"; ?>

        <main class="max-w-7xl mx-auto p-6">
            <div class="bg-gray-800 border border-gray-700 rounded-lg p-5 mb-0 transition-all duration-300 hover:bg-gray-750 hover:border-blue-400 hover:shadow-lg">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-bold text-white">Flight Summary</h2>
                        <p class="text-gray-400">
                            <?= htmlspecialchars($flight['flightNumber'] ?? 'Flight') ?>
                            |
                            <?= htmlspecialchars($flight['landingAt'] ?? '___') ?>
                            →
                            <?= htmlspecialchars($flight['departingTo'] ?? '___') ?>
                            |
                            <?= htmlspecialchars($flight['airline'] ?? 'Airline') ?>
                        </p>
                    </div>

                    <div>
                        <span class="text-2xl font-bold text-white">
                            $<?= htmlspecialchars($flight['seatPrice'] ?? '0') ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="sticky top-0 z-10 bg-gray-900 pt-6 pb-6">
                <div class="bg-gray-800 border border-gray-700 rounded-lg p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <a href="#step-1" class="step-link flex items-center flex-1">
                            <div class="step-indicator w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold">1</div>
                            <span class="ml-2 text-white">Passenger Info</span>
                            <div class="flex-1 h-px bg-gray-700 mx-4"></div>
                        </a>
                        <a href="#step-2" class="step-link flex items-center flex-1">
                            <div class="step-indicator w-8 h-8 rounded-full border border-gray-600 text-gray-400 flex items-center justify-center text-sm">2</div>
                            <span class="ml-2 text-gray-400">Seating</span>
                            <div class="flex-1 h-px bg-gray-700 mx-4"></div>
                        </a>
                        <a href="#step-3" class="step-link flex items-center flex-1">
                            <div class="step-indicator w-8 h-8 rounded-full border border-gray-600 text-gray-400 flex items-center justify-center text-sm">3</div>
                            <span class="ml-2 text-gray-400">Baggage</span>
                            <div class="flex-1 h-px bg-gray-700 mx-4"></div>
                        </a>
                    </div>
                </div>
            </div>

            <section id="step-1" class="scroll-mt-52 mb-6">
                <?php include_once('./passengerInfo.php'); ?>
            </section>

            <section id="step-2" class="scroll-mt-52 mb-6">
                <?php include_once('./seats.php'); ?>
            </section>

            <section id="step-3" class="scroll-mt-52 mb-6">
                <?php include_once('./bags.php'); ?>
            </section>

            <input type="hidden" id="seatInput" value="">

            <section class="scroll-mt-52 mb-6">
                <div class="bg-gray-800 p-6 rounded-lg border border-gray-700">
                    <div class="flex justify-between">
                        <a href="searchFlights.php" class="px-6 h-12 flex items-center bg-gray-700 hover:bg-gray-600 text-white rounded-lg border border-gray-600">Cancel</a>
                        <div class="relative">
                            <button id="reviewButton" onclick="openReviewModal()" disabled onmouseenter="showTooltip()" onmouseleave="hideTooltip()" class="px-6 h-12 bg-gray-600 text-gray-400 rounded-lg cursor-not-allowed transition">Review Purchase</button>

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
            const sections = document.querySelectorAll("section[id]");
            const stepLinks = document.querySelectorAll(".step-link");

            function setActive(id) {
                stepLinks.forEach(link => {
                    const target = link.getAttribute("href").replace("#", "");
                    const indicator = link.querySelector(".step-indicator");
                    const label = link.querySelector("span");

                    const isActive = target == id;

                    if (isActive) {
                        indicator.className = "step-indicator w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold transition transform hover:scale-110 hover:bg-blue-500 cursor-pointer";
                        label.classList.add("text-white");
                        label.classList.remove("text-gray-400");
                    } else {
                        indicator.className = "step-indicator w-8 h-8 rounded-full border border-gray-600 text-gray-400 flex items-center justify-center text-sm transition transform hover:scale-110 hover:border-gray-400 hover:text-white cursor-pointer";
                        label.classList.add("text-gray-400");
                        label.classList.remove("text-white");
                    }
                });
            }

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) setActive(entry.target.id);
                });
            }, { threshold: 0.4 });

            sections.forEach(section => observer.observe(section));

            window.openReviewModal = function () {
                document.getElementById("purchaseSaveCard").value = document.getElementById("saveCardCheckbox")?.checked ? 1 : 0;
                document.getElementById("purchaseCardName").value = document.getElementById("cardName")?.value || "";
                document.getElementById("purchaseCardholderName").value = document.getElementById("cardholderName").value;
                document.getElementById("purchaseCardNumber").value = document.getElementById("cardNumber").value;
                document.getElementById("purchaseExpirationDate").value = document.getElementById("expirationDate").value;
                document.getElementById("purchaseCvc").value = document.getElementById("cvc").value;
                document.getElementById("purchaseBillingAddress").value = document.getElementById("billingAddress").value;
                document.getElementById("purchaseZipCode").value = document.getElementById("zipCode").value;
                document.getElementById("modalSeatInput").value = document.getElementById("seatInput").value;
                document.getElementById("purchaseCarryOn").value = document.getElementById("carryOnSelect").value;
                document.getElementById("purchaseChecked").value = document.getElementById("checkedSelect").value;
                document.getElementById("purchaseFirstName").value = document.getElementById("firstName").value;
                document.getElementById("purchaseMiddleName").value = document.getElementById("middleName").value;
                document.getElementById("purchaseLastName").value = document.getElementById("lastName").value;
                document.getElementById("purchaseSex").value = document.getElementById("sex").value;
                document.getElementById("purchaseDob").value = document.getElementById("dob").value;
                document.getElementById("purchasePhone").value = document.getElementById("phone").value;
                document.getElementById("purchaseEmail").value = document.getElementById("email").value;

                const first = document.getElementById("firstName").value;
                const middle = document.getElementById("middleName").value;
                const last = document.getElementById("lastName").value;

                document.getElementById("reviewPassengerName").innerText = `${first} ${middle} ${last}`.replace(/\s+/g, " ").trim();
                document.getElementById("reviewPassengerSex").innerText = document.getElementById("sex").value;
                document.getElementById("reviewPassengerDob").innerText = document.getElementById("dob").value;
                document.getElementById("reviewPassengerPhone").innerText = document.getElementById("phone").value;
                document.getElementById("reviewPassengerEmail").innerText = document.getElementById("email").value;
                document.getElementById("reviewSeat").innerText = document.getElementById("seatInput").value;

                document.getElementById("reviewCarryOn").innerText = document.getElementById("carryOnSelect").value + " Carry-On Bag(s)";
                document.getElementById("reviewChecked").innerText = document.getElementById("checkedSelect").value + " Checked Bag(s)";

                const seatPrice = <?= json_encode($flight['seatPrice'] ?? 0) ?>;
                const carryOn = parseInt(document.getElementById("carryOnSelect").value);
                const checked = parseInt(document.getElementById("checkedSelect").value);

                let bagCost = 0;

                if (carryOn == 2) {
                    bagCost = bagCost + 30;
                }

                if (checked >= 2) {
                    bagCost = bagCost + 50;
                }

                if (checked > 2) {
                    bagCost + bagShot + (checked - 2) * 100;
                }

                const total = seatPrice + bagCost;

                document.getElementById("reviewBagCost").innerText = "$" + bagCost;
                document.getElementById("reviewTotal").innerText = "$" + total;
                document.getElementById("purchasePrice").value = total.toFixed(2);
                document.getElementById("reviewModal").classList.remove("hidden");
            };

            window.closeReviewModal = function () {
                document.getElementById("reviewModal").classList.add("hidden");
            };

            const saveCardCheckbox = document.getElementById("saveCardCheckbox");
            const cardNameContainer = document.getElementById("cardNameContainer");

            saveCardCheckbox?.addEventListener("change", function() {
                if (this.checked) {
                    cardNameContainer.classList.remove("hidden");
                } else {
                    cardNameContainer.classList.add("hidden");
                    document.getElementById("cardName").value = "";
                }
            });

            function showSaveCardTooltip() {
                const checkbox = document.getElementById("saveCardCheckbox");
                if (checkbox && checkbox.disabled) {
                    document.getElementById("saveCardTooltip").classList.remove("hidden");
                }
            }

            function hideSaveCardTooltip() {
                const tooltip =
                    document.getElementById("saveCardTooltip");
                if (tooltip) {
                    tooltip.classList.add("hidden");
                }
            }

            function validateBooking() {
                let valid = true;

                document.querySelectorAll(".required-booking").forEach(field => {
                    if (!field.value.trim()) {
                        valid = false;
                    }
                });

                if (!document.getElementById("seatInput").value) {
                    valid = false;
                }

                const button = document.getElementById("reviewButton");

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

            document.querySelectorAll(".required-booking").forEach(field => {
                field.addEventListener("input", validateBooking);
                field.addEventListener("change", validateBooking);
            });

            function showTooltip() {
                const button = document.getElementById("reviewButton");
                if (button.disabled) {
                    document.getElementById("reviewTooltip").classList.remove("hidden");
                }
            }

            function hideTooltip() {
                document.getElementById("reviewTooltip").classList.add("hidden");
            }

            document.getElementById("savedCard")?.addEventListener("change", function () {
                const option = this.options[this.selectedIndex];

                if (!this.value) {
                    return;
                }

                document.getElementById("cardholderName").value = option.dataset.name || "";
                document.getElementById("cardNumber").value = option.dataset.number || "";
                document.getElementById("expirationDate").value = option.dataset.exp || "";
                document.getElementById("cvc").value = option.dataset.cvc || "";
                document.getElementById("billingAddress").value = option.dataset.address || "";
                document.getElementById("zipCode").value = option.dataset.zip || "";

                validateBooking();
            });

            function validateSaveCardOption() {
                const checkbox = document.getElementById("saveCardCheckbox");
                if (!checkbox) {
                    return;
                }

                const requiredPaymentFields = ["cardholderName", "cardNumber", "expirationDate", "cvc", "billingAddress", "zipCode"];
                let complete = true;

                requiredPaymentFields.forEach(id => {
                    const field = document.getElementById(id);
                    if (!field || !field.value.trim()) {
                        complete = false;
                    }
                });

                checkbox.disabled = !complete;
                if (!complete) {
                    checkbox.checked = false;
                }
            }

            ["cardholderName", "cardNumber", "expirationDate", "cvc", "billingAddress", "zipCode"].forEach(id => {
                const field = document.getElementById(id);

                if (field) {
                    field.addEventListener("input", validateSaveCardOption);
                    field.addEventListener("change", validateSaveCardOption);
                }
            });

            validateSaveCardOption();
        </script>
    </body>
</html>