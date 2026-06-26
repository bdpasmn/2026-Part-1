<!-- Review booking modal -->
<div id="reviewModal" class="fixed inset-0 hidden z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/30" onclick="closeReviewModal()"></div>
    <div class="relative min-h-screen flex items-center justify-center p-6">
        <!-- Modal box -->
        <div class="relative w-full max-w-4xl bg-gray-800 border border-gray-700 rounded-xl shadow-2xl overflow-hidden">
            <!-- Header -->
            <div class="px-8 py-6 border-b border-gray-700">
                <h2 class="text-2xl font-bold text-white">Review Your Booking 📝</h2>
                <p class="text-sm text-gray-300 mt-1">Verify your information before confirming your purchase.</p>
            </div>

            <!-- Scrollable content -->
            <div class="p-8 space-y-6 max-h-[55vh] overflow-y-auto">
                <!-- Flight summary card -->
                <div class="bg-gray-700 border border-gray-600 rounded-xl p-6 transition duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-gray-500">
                    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-6">
                        <div>
                            <h3 class="font-bold text-white text-2xl"><?= htmlspecialchars($flight['flightNumber'] ?? 'Flight') ?> ✈️</h3>
                            <p class="text-gray-200 mt-2"><?= htmlspecialchars($flight['landingAt'] ?? '') ?>→<?= htmlspecialchars($flight['departingTo'] ?? '') ?></p>
                            <p class="text-gray-300 text-sm mt-1"><?= htmlspecialchars($flight['airline'] ?? '') ?> Airlines</p>
                            <!-- Departure time -->
                            <?php $timestamp = $flight['departFromReceiver'] ?? null; ?>
                            <p class="text-gray-300 text-sm mt-1"><?= $timestamp ? date("D, M j Y g:i A", $timestamp / 1000) : "N/A" ?></p>
                        </div>
                        <div class="md:text-right">
                            <div class="text-3xl font-bold text-white mt-1">$<?= htmlspecialchars($flight['seatPrice'] ?? 0) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Passenger info section -->
                <div class="bg-gray-700 border border-gray-600 rounded-xl p-6 transition duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-gray-500">
                    <h3 class="font-semibold text-white text-lg mb-5">Passenger Information 📌</h3>
                    <div class="grid md:grid-cols-2 gap-5 text-sm">
                        <div>
                            <div class="text-gray-300 mb-1">Passenger</div>
                            <div id="reviewPassengerName" class="text-white font-medium"></div>
                        </div>

                        <div>
                            <div class="text-gray-300 mb-1">Gender/Sex</div>
                            <div id="reviewPassengerSex" class="text-white font-medium"></div>
                        </div>

                        <div>
                            <div class="text-gray-300 mb-1">Date of Birth</div>
                            <div id="reviewPassengerDob" class="text-white font-medium"></div>
                        </div>

                        <div>
                            <div class="text-gray-300 mb-1">Phone Number</div>
                            <div id="reviewPassengerPhone" class="text-white font-medium"></div>
                        </div>

                        <div class="md:col-span-2">
                            <div class="text-gray-300 mb-1">Email Address</div>
                            <div id="reviewPassengerEmail" class="text-white font-medium break-all"></div>
                        </div>
                    </div>
                </div>

                <!-- Travel details section -->
                <div class="bg-gray-700 border border-gray-600 rounded-xl p-6 transition duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-gray-500">
                    <h3 class="font-semibold text-white text-lg mb-5">Travel Details 🎫</h3>
                    <div class="grid md:grid-cols-3 gap-5">
                        <div class="bg-slate-600 rounded-lg p-4">
                            <div class="text-gray-300 text-sm mb-2">Seat</div>
                            <div id="reviewSeat" class="text-2xl font-bold text-blue-400"></div>
                        </div>

                        <div class="bg-slate-600 rounded-lg p-4">
                            <div class="text-gray-300 text-sm mb-2">Carry-On Bags</div>
                            <div id="reviewCarryOn" class="text-xl font-semibold text-white"></div>
                        </div>

                        <div class="bg-slate-600 rounded-lg p-4">
                            <div class="text-gray-300 text-sm mb-2">Checked Bags</div>
                            <div id="reviewChecked" class="text-xl font-semibold text-white"></div>
                        </div>
                    </div>
                </div>

                <!-- Price summary -->
                <div class="bg-gray-700 border border-gray-600 rounded-xl p-6 transition duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-gray-500">
                    <h3 class="font-semibold text-white text-lg mb-5">Price Summary 🏷️</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-200">Flight Fare</span>
                            <span class="text-white font-medium">$<?= htmlspecialchars($flight['seatPrice'] ?? 0) ?></span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-gray-200">Baggage Fees</span>
                            <span id="reviewBagCost" class="text-white font-medium">$0</span>
                        </div>

                        <div class="border-t border-gray-800 pt-5 flex justify-between items-center">
                            <span class="text-2xl font-bold text-white">Total</span>
                            <span id="reviewTotal" class="text-3xl font-bold text-blue-400">$0</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer actions -->
            <div class="px-8 py-6 border-t border-gray-700 bg-gray-800">
                <div class="flex flex-col sm:flex-row justify-between gap-4">
                    <!-- Back button -->
                    <button onclick="closeReviewModal()" class="px-6 h-12 bg-gray-700 hover:bg-gray-600 text-white rounded-lg border border-gray-600 transition">
                        Back
                    </button>

                    <!-- Purchase form -->
                    <form action="purchase.php" method="POST">

                        <!-- Hidden booking data -->
                        <input type="hidden" name="flight_id" value="<?= $flightId ?>">
                        <input type="hidden" id="modalSeatInput" name="seat">
                        <input type="hidden" name="bags_carried" id="purchaseCarryOn">
                        <input type="hidden" name="bags_checked" id="purchaseChecked">

                        <!-- Passenger info -->
                        <input type="hidden" name="first_name" id="purchaseFirstName">
                        <input type="hidden" name="middle_name" id="purchaseMiddleName">
                        <input type="hidden" name="last_name" id="purchaseLastName">
                        <input type="hidden" name="sex" id="purchaseSex">
                        <input type="hidden" name="dob" id="purchaseDob">
                        <input type="hidden" name="phone" id="purchasePhone">
                        <input type="hidden" name="email" id="purchaseEmail">

                        <!-- Pricing -->
                        <input type="hidden" name="price" id="purchasePrice">

                        <!-- Save card option -->
                        <input type="hidden" name="save_card" id="purchaseSaveCard">
                        <input type="hidden" name="card_name" id="purchaseCardName">

                        <!-- Payment info -->
                        <input type="hidden" name="cardholder_name" id="purchaseCardholderName">
                        <input type="hidden" name="card_number" id="purchaseCardNumber">
                        <input type="hidden" name="expiration_date" id="purchaseExpirationDate">
                        <input type="hidden" name="cvc" id="purchaseCvc">
                        <input type="hidden" name="billing_address" id="purchaseBillingAddress">
                        <input type="hidden" name="zip_code" id="purchaseZipCode">

                        <!-- Submit -->
                        <button type="submit" class="w-full sm:w-auto px-8 h-12 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition shadow-lg shadow-blue-900/30">
                            Confirm Purchase
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>