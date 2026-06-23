<div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
            <h2 class="text-xl font-bold mb-6 text-white">Baggage Selection 💼</h2>
            <div class="space-y-6">
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-300">Carry-on Bags (Max 2)</label>

                    <select id="carryOnSelect" class="w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="0">0 Carry-on Bags</option>
                        <option value="1">1 Carry-on Bag (FREE)</option>
                        <option value="2">2 Carry-on Bags ($30 extra)</option>
                    </select>

                    <p class="text-xs text-gray-400 mt-2">First carry-on is free. Second carry-on costs $30.</p>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-300">Checked Bags (Max 5)</label>

                    <select id="checkedSelect"class="w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="0">0 Checked Bags</option>
                        <option value="1">1 Checked Bag (FREE)</option>
                        <option value="2">2 Checked Bags ($50 extra)</option>
                        <option value="3">3 Checked Bags ($150 extra)</option>
                        <option value="4">4 Checked Bags ($250 extra)</option>
                        <option value="5">5 Checked Bags ($350 extra)</option>
                    </select>

                    <p class="text-xs text-gray-400 mt-2">1st checked bag is free. 2nd is $50. Each additional is $100.</p>
                </div>

                <button onclick="calculateBags()" class="w-full h-12 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">Calculate Baggage Cost</button>
            </div>
        </div>

        <div class="bg-gray-700 border border-gray-600 rounded-lg p-6 transition duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-gray-500">
            <h3 class="text-lg font-bold text-white mb-4">Baggage Summary</h3>
            <div class="space-y-4 text-sm text-gray-300">
                <div>
                    <div class="text-gray-300">Carry-on Bags</div>
                    <div id="carrySummary" class="text-white font-bold">0</div>
                </div>
                <div>
                    <div class="text-gray-300">Checked Bags</div>
                    <div id="checkedSummary" class="text-white font-bold">0</div>
                </div>
                <div class="border-t border-gray-700 pt-4">
                    <div class="text-gray-300">Total Bag Fees</div>
                    <div id="bagCost" class="text-whtie font-bold text-xl">$0</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function calculateBags() {
        const carryOn = parseInt(document.getElementById("carryOnSelect").value);
        const checked = parseInt(document.getElementById("checkedSelect").value);

        let carryCost = 0;
        if (carryOn == 2) carryCost = 30;

        let checkedCost = 0;
        if (checked >= 2) checkedCost = checkedCost + 50;
        if (checked > 2) checkedCost = checkedCost + (checked - 2) * 100; 

        const total = carryCost + checkedCost;

        document.getElementById("carrySummary").innerText = carryOn;
        document.getElementById("checkedSummary").innerText = checked;
        document.getElementById("bagCost").innerText = "$" + total;
    }
</script>