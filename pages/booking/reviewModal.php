<!-- Review booking modal -->
<!-- baggage fees wrong -->
<div id="reviewModal" class="fixed inset-0 hidden z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/30" onclick="closeReviewModal()"></div>
    <div class="relative min-h-screen flex items-center justify-center p-6">
        <!-- Modal box -->
        <div class="relative w-full max-w-4xl bg-gray-800 border border-gray-700 rounded-xl shadow-2xl overflow-hidden">
            <!-- Header -->
            <div class="px-8 py-6 border-b border-gray-700">
                <h2 class="text-2xl font-bold text-white">Review Your Booking 📝</h2>
                <p class="text-sm text-gray-300 mt-1">Verify your information, then continue to payment.</p>
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
                            <div id="reviewFlightSummaryPrice" class="text-3xl font-bold text-white mt-1">$<?= htmlspecialchars($flight['seats']['economy']['priceDollars'] ?? 0) ?></div>
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

                        <div class="bg-slate-600 rounded-lg p-4 md:col-span-3">
                            <div class="text-gray-300 text-sm mb-2">In-Flight Extras</div>
                            <div id="reviewExtras" class="text-white font-semibold">None</div>
                        </div>
                    </div>
                </div>

                <!-- Price summary -->
                <div class="bg-gray-700 border border-gray-600 rounded-xl p-6 transition duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-gray-500">
                    <h3 class="font-semibold text-white text-lg mb-5">Price Summary 🏷️</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-200">Flight Fare</span>
                            <span id="reviewFlightCost" class="text-white font-medium">$<?= htmlspecialchars($flight['seats']['economy']['priceDollars'] ?? 0) ?></span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-gray-200">Baggage Fees</span>
                            <span id="reviewBagCost" class="text-white font-medium">$0</span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-gray-200">In-Flight Extras</span>
                            <span id="reviewExtrasCost" class="text-white font-medium">$0</span>
                        </div>

                        <div class="border-t border-gray-800 pt-5 flex justify-between items-center">
                            <span class="text-2xl font-bold text-white">Total</span>
                            <span id="reviewTotal" class="text-3xl font-bold text-blue-400">$0</span>
                        </div>

                        <?php if ($canUseFfm): ?>
                            <p class="text-xs text-gray-400 pt-1">Shown assuming your ticket is paid with money. You can choose to pay with FFMs.</p>
                        <?php endif; ?>
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

                    <!-- Hands off to the payment modal instead of submitting here -->
                    <button onclick="goToPayment()" class="w-full sm:w-auto px-8 h-12 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition shadow-lg shadow-blue-900/30">
                        Continue to Payment 💳
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>