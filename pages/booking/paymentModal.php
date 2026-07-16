<!-- make card details its own card, only showif paying with card. glitch with hovring on save, it flickers. only money pay. the modal defaults the cost-->
<div id="paymentModal" class="fixed inset-0 hidden z-50 overflow-y-auto">
    <div class="fixed inset-0 bg-black/30" onclick="closePaymentModal()"></div>
    <div class="relative min-h-screen flex items-center justify-center p-6">
        <!-- Modal box -->
        <div class="relative w-full max-w-2xl bg-gray-800 border border-gray-700 rounded-xl shadow-2xl overflow-hidden">
            <!-- The actual purchase form lives here now -->
            <form action="purchase.php" method="POST">

                <!-- Header -->
                <div class="px-8 py-6 border-b border-gray-700">
                    <h2 class="text-2xl font-bold text-white">Payment Information 💳</h2>
                    <p class="text-sm text-gray-300 mt-1">Enter your payment details to complete the purchase.</p>
                </div>

                <!-- Scrollable content -->
                <div class="pt-2 pb-2 p-8 space-y-4 max-h-[55vh] overflow-y-auto">

                    <!-- Booking data carried over from the review step -->
                    <input type="hidden" name="flight_id" value="<?= $flightId ?>">
                    <input type="hidden" id="purchaseSeat" name="seat">
                    <input type="hidden" id="purchaseCarryOn" name="bags_carried">
                    <input type="hidden" id="purchaseChecked" name="bags_checked">
                    <input type="hidden" id="purchaseExtras" name="extras">
                    <input type="hidden" id="purchaseFirstName" name="first_name">
                    <input type="hidden" id="purchaseMiddleName" name="middle_name">
                    <input type="hidden" id="purchaseLastName" name="last_name">
                    <input type="hidden" id="purchaseSex" name="sex">
                    <input type="hidden" id="purchaseDob" name="dob">
                    <input type="hidden" id="purchasePhone" name="phone">
                    <input type="hidden" id="purchaseEmail" name="email">
                    <input type="hidden" id="purchasePrice" name="price">
                    <input type="hidden" id="purchaseFfmCharge" name="ffm_charge" value="0">
                    <input type="hidden" id="purchaseTicketPaymentMethod" name="ticket_payment_method" value="money">
                    <input type="hidden" id="purchaseFfmEarned" name="ffm_earned" value="0">

                    <!-- Payment method breakdown: ticket + extras + baggage. -->
                    <div class="bg-gray-700 border border-gray-600 rounded-lg p-5">
                        <h3 class="font-semibold text-white mb-4">Choose how you'd like to pay</h3>

                        <!-- Ticket -->
                        <div class="mb-4 pb-4 border-b border-gray-600">
                            <div class="text-sm text-gray-400 mb-2">Ticket</div>
                            <div class="space-y-3">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="ticketPaymentMethod" value="money" checked class="h-4 w-4">
                                    <span id="ticketMoneyLabel" class="text-gray-200">Pay with card - $0.00</span>
                                </label>
                                <?php $ffmDisabled = !$canUseFfm; ?>
                                <label class="flex items-center gap-3 <?= $ffmDisabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer' ?>" onmouseenter="showFfmTooltip()" onmouseleave="hideFfmTooltip()">
                                    <input type="radio" name="ticketPaymentMethod" value="ffm" class="h-4 w-4" <?= $ffmDisabled ? 'disabled' : '' ?>>
                                    <span id="ticketFfmLabel" class="text-gray-200">Pay with Frequent Flier Miles - 0 FFMs</span>
                                </label>
                                <?php if ($ffmDisabled): ?>
                                    <div id="ffmTooltip" class="hidden text-xs text-gray-400 pl-7">Sign in to pay with frequent flier miles.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Extras (populated by JS from whatever extras the user selected in step 4) -->
                        <div id="extrasPaymentSection" class="hidden mb-4 pb-4 border-b border-gray-600">
                            <div class="text-sm text-gray-400 mb-2">In-Flight Extras</div>
                            <div id="extrasPaymentRows" class="space-y-3"></div>
                        </div>

                        <!-- Running totals -->
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-300">Total due on card</span>
                                <span id="paymentMoneyDue" class="text-white font-semibold">$0.00</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-300">Total due in FFMs</span>
                                <span id="paymentFfmDue" class="text-white font-semibold">0 FFMs</span>
                            </div>
                            <?php if ($canUseFfm): ?>
                                <div class="flex justify-between items-center text-blue-300">
                                    <span>FFMs earned on this booking</span>
                                    <span id="paymentFfmEarned"><?= htmlspecialchars($flightFfmEarn) ?> FFMs</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Shown if the combined FFM total exceeds the user's balance -->
                        <div id="ffmBalanceWarning" class="hidden mt-3 text-sm text-red-400 bg-red-950/40 border border-red-800 rounded-lg p-3"></div>
                    </div>
                    <div id="cardDetailsSection">
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-300">Cardholder Name*</label>
                        <input type="text" id="cardHolderName" name="cardholder_name" class="required-payment w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-300">Card Number*</label>
                        <input type="text" id="cardNumber" name="card_number" maxlength="19" oninput="this.value=this.value.replace(/\D/g,'').replace(/(.{4})/g,'$1 ').trim()" class="required-payment w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-300">Expiration Date*</label>
                            <input type="text" id="expirationDate" name="expiration_date" maxlength="5" placeholder="MM/YY" oninput="this.value=this.value.replace(/\D/g,''); if(this.value.length > 2){ this.value=this.value.slice(0,2)+'/'+this.value.slice(2,4);}" class="required-payment w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-300">CVC*</label>
                            <input type="text" id="cvc" name="cvc" maxlength="4" oninput="this.value=this.value.replace(/\D/g,'')" class="required-payment w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-300">Billing Address*</label>
                        <input type="text" id="billingAddress" name="billing_address" class="required-payment w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block mb-2 text-sm font-medium text-gray-300">ZIP Code*</label>
                        <input type="text" id="zipCode" name="zip_code" maxlength="10" oninput="this.value=this.value.replace(/[^0-9\-]/g,'')" class="required-payment w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Saved payment methods -->
                    <?php if (!empty($_SESSION['user_id']) && !empty($savedCards)): ?>
                        <div class="mt-8 border-t border-gray-700 pt-6">
                            <h3 class="font-semibold mb-4 text-white">Saved Payment Methods</h3>
                            <select id="savedCard" class="w-full bg-gray-700 border border-gray-600 rounded-lg p-3 text-white">
                                <option value="">Select Payment Method</option>
                                <?php foreach ($savedCards as $card): ?>
                                    <option value="<?= $card['card_id'] ?>" data-name="<?= htmlspecialchars($card['cardholder_name']) ?>" data-number="<?= htmlspecialchars($card['card_number']) ?>" data-exp="<?= htmlspecialchars($card['expiration_date']) ?>" data-cvc="<?= htmlspecialchars($card['cvc']) ?>" data-address="<?= htmlspecialchars($card['billing_address']) ?>" data-zip="<?= htmlspecialchars($card['zip_code']) ?>">
                                        <?= htmlspecialchars($card['card_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- Save card option -->
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <input type="hidden" name="save_card" id="purchaseSaveCardFlag" value="0">
                        <div class="mt-6 relative">
                            <label class="flex items-center gap-3 cursor-pointer" onmouseenter="showSaveCardTooltip()" onmouseleave="hideSaveCardTooltip()">
                                <input type="checkbox" id="saveCardCheckbox" disabled class="h-4 w-4" onchange="document.getElementById('purchaseSaveCardFlag').value = this.checked ? 1 : 0">
                                <span class="text-gray-300">Save this as a new payment method for future purchases</span>
                            </label>

                            <div id="saveCardTooltip" class="hidden absolute left-0 top-full mt-2 w-72 bg-gray-800 border border-gray-700 rounded-lg p-3 text-sm text-gray-300 shadow-lg z-50">
                                New card/payment method can only be saved if all information is filled and doesn't already exist.
                            </div>
                            <div id="cardNameContainer" class="hidden mt-4">
                                <label class="block mb-2 text-sm font-medium text-gray-300">Card Name (Optional)</label>
                                <input type="text" id="cardName" name="card_name" class="w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white" placeholder="ex. Personal Card">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                </div>

                <!-- Footer actions -->
                <div class="px-8 py-6 border-t border-gray-700 bg-gray-800">
                    <div class="flex flex-col sm:flex-row justify-between gap-4">
                        <button type="button" onclick="backToReview()" class="px-6 h-12 bg-gray-700 hover:bg-gray-600 text-white rounded-lg border border-gray-600 transition">
                            Back to Review
                        </button>
                        <button type="submit" id="confirmPurchaseButton" disabled onmouseenter="showConfirmTooltip()" onmouseleave="hideConfirmTooltip()" class="relative px-8 h-12 bg-gray-600 text-gray-400 rounded-lg cursor-not-allowed transition">
                            Confirm &amp; Purchase 🎉
                            <div id="confirmTooltip" class="hidden absolute bottom-full right-0 mb-2 w-64 bg-gray-800 border border-gray-700 rounded-lg p-3 text-xs text-gray-300 shadow-lg text-left normal-case font-normal">
                                Fill out all required payment fields to complete your purchase.
                            </div>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('cardName')?.addEventListener('input', function () {
        this.dataset.touched = "1";
    });

    function showFfmTooltip() {
        document.getElementById("ffmTooltip")?.classList.remove("hidden");
    }

    function hideFfmTooltip() {
        document.getElementById("ffmTooltip")?.classList.add("hidden");
    }

    function showConfirmTooltip() {
        const button = document.getElementById("confirmPurchaseButton");
        if (button.disabled) {
            document.getElementById("confirmTooltip").classList.remove("hidden");
        }
    }

    function hideConfirmTooltip() {
        document.getElementById("confirmTooltip").classList.add("hidden");
    }

    window.updateCardDetailsVisibility = function (moneyDue) {
    const section = document.getElementById("cardDetailsSection");
    const savedSection = document.getElementById("savedCardSection");
    const show = moneyDue > 0;

    if (section) section.classList.toggle("hidden", !show);
    if (savedSection) savedSection.classList.toggle("hidden", !show);
};
</script>