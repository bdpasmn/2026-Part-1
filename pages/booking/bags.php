<?php
    $bagsInfo = $flight['baggage'];
?>

<!-- Baggage selection section -->
<div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- LEFT -->
        <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
            <h2 class="text-xl font-bold mb-6 text-white">Baggage Selection 💼</h2>
            <div class="space-y-6">
                <!-- Carry-on -->
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-300">
                        Carry-on Bags (Max <?= $bagsInfo["carry"]["max"] ?>)
                    </label>
                    <select id="carryOnSelect" class="w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white">
                        <option value="0">0 Carry-on Bags ($0)</option>
                        <?php foreach ($bagsInfo["carry"]["prices"] as $i => $price): ?>
                            <option value="<?= $i + 1 ?>">
                                <?= $i + 1 ?> Carry-on Bag<?= ($i + 1) === 1 ? "" : "s" ?>
                                (<?= $price == 0 ? "FREE" : "$" . $price ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Checked -->
                <div>
                    <label class="block mb-2 text-sm font-medium text-gray-300">
                        Checked Bags (Max <?= $bagsInfo["checked"]["max"] ?>)
                    </label>

                    <select id="checkedSelect" class="w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white">
                        <option value="0">0 Checked Bags ($0)</option>
                        <?php foreach ($bagsInfo["checked"]["prices"] as $i => $price): ?>
                            <option value="<?= $i + 1 ?>">
                                <?= $i + 1 ?> Checked Bag<?= ($i + 1) === 1 ? "" : "s" ?>
                                (<?= $price == 0 ? "FREE" : "$" . $price ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button onclick="calculateBags()"
                    class="w-full h-12 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
                    Calculate Baggage Cost
                </button>

            </div>
        </div>

        <!-- Baggage summary -->
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
    const carryPrices = <?= json_encode($bagsInfo["carry"]["prices"]) ?>;
    const checkedPrices = <?= json_encode($bagsInfo["checked"]["prices"]) ?>;   
    
    // Calculate baggage fees
    function calculateBags() {
        const carryIndex = parseInt(document.getElementById("carryOnSelect").value);
        const checkedIndex = parseInt(document.getElementById("checkedSelect").value);

        const carryCost = carryIndex == 0 ? 0 : carryPrices[carryIndex - 1];
        const checkedCost = checkedIndex == 0 ? 0 : checkedPrices[checkedIndex - 1];

        const total = carryCost + checkedCost;

        document.getElementById("carrySummary").innerText = carryIndex;
        document.getElementById("checkedSummary").innerText = checkedIndex;
        document.getElementById("bagCost").innerText = "$" + total;
    }
</script>