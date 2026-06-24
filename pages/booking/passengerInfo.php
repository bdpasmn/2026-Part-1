            
            <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
                        <h2 class="text-xl font-bold mb-6 text-white">Passenger Information 👤</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-300">First Name*</label>
                                <input type="text" id="firstName" class="required-booking w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-300">Middle Name</label>
                                <input type="text" id="middleName" class="w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block mb-2 text-sm font-medium text-gray-300">Last Name*</label>
                                <input type="text" id="lastName" class="required-booking w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-300">Sex/Gender*</label>
                                <select id="sex" class="required-booking w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-whitetransition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="nonbinary">Non-binary</option>
                                    <option value="other">Other</option>
                                    <option value="prefer-not-to-say">Prefer not to say</option>
                                </select>
                            </div>

                            <div>
                                <label class="block mb-2 text-sm font-medium text-gray-300">Date of Birth*</label>
                                <input type="date" id="dob" class="required-booking w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div class="mt-8 border-t border-gray-700 pt-6 md:col-span-2">
                                <label class="block mb-2 text-sm font-medium text-gray-300">Phone Number*</label>
                                <input
                                    type="tel"
                                    id="phone"
                                    maxlength="20"
                                    oninput="this.value=this.value.replace(/[^0-9()\-\s]/g,'')"
                                    class="required-booking w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white"
                                >
                            </div>

                            <div class="md:col-span-2">
                                <label class="block mb-2 text-sm font-medium text-gray-300">Email Address*</label>
                                <input type="email" id="email" class="required-booking w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">                            </div>
                            </div>
                        </div>

                        <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
                            <h2 class="text-xl font-bold mb-6 text-white">Payment Information 💳</h2>
                            <div class="space-y-4">
                                <div>
                                    <label class="block mb-2 text-sm font-medium text-gray-300">Cardholder Name*</label>
                                    <input type="text" id="cardholderName" class="required-booking w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-medium text-gray-300">Card Number*</label>
                                    <input type="text" id="cardNumber" maxlength="19" oninput="this.value=this.value.replace(/\D/g,'').replace(/(.{4})/g,'$1 ').trim()" class="required-booking w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">                                
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-300">Expiration Date*</label>
                                        <input type="text" id="expirationDate" maxlength="5" placeholder="MM/YY" oninput="this.value=this.value.replace(/\D/g,''); if(this.value.length > 2){ this.value=this.value.slice(0,2)+'/'+this.value.slice(2,4);}" class="required-booking w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    </div>

                                    <div>
                                        <label class="block mb-2 text-sm font-medium text-gray-300">CVC*</label>
                                        <input type="text" id="cvc" maxlength="4" oninput="this.value=this.value.replace(/\D/g,'')" class="required-booking w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">                                    
                                    </div>
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-medium text-gray-300">Billing Address*</label>
                                    <input type="text" id="billingAddress" class="required-booking w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label class="block mb-2 text-sm font-medium text-gray-300">ZIP Code*</label>
                                    <input type="text" id="zipCode" maxlength="10" oninput="this.value=this.value.replace(/[^0-9\-]/g,'')" class="required-booking w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>

                            <?php if (!empty($_SESSION['user_id']) && !empty($savedPayments)): ?>
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

                            <?php if (!empty($_SESSION['user_id'])): ?>
                                <div class="mt-6 relative">
                                    <label class="flex items-center gap-3 cursor-pointer" onmouseenter="showSaveCardTooltip()" onmouseleave="hideSaveCardTooltip()">
                                        <input type="checkbox" id="saveCardCheckbox" disabled class="h-4 w-4">
                                        <span class="text-gray-300">Save this as a new payment method for future purchases</span>
                                    </label>

                                    <div id="saveCardTooltip" class="hidden absolute left-0 top-full mt-2 w-72 bg-gray-800 border border-gray-700 rounded-lg p-3 text-sm text-gray-300 shadow-lg z-50">
                                        New card/payment method can only be saved if all information is filled and doesn't already exist.
                                    </div>

                                    <div id="cardNameContainer" class="hidden mt-4">
                                        <label class="block mb-2 text-sm font-medium text-gray-300">Card Name (Optional)</label>
                                        <input type="text" id="cardName" class="w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white" placeholder="">
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                document.getElementById('cardholderName').addEventListener('input', function () {
                    const cardName = document.getElementById('cardName');

                    if (!cardName.dataset.touched) {
                        cardName.placeholder = this.value ?  `ex. ${this.value}'s card` : "ex. ____'s card";
                    }
                });
            </script>