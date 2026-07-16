<?php
    $isGuest = !isset($_SESSION['user_id']); 

    $flightExtras = $flight['extras'];
?>

<script>
    const flightExtras = <?= json_encode($flightExtras) ?>;
    const isGuest = <?= json_encode($isGuest) ?>;
</script>

<div class="bg-gray-800 border border-gray-700 rounded-lg p-6 mt-6">
    <h2 class="text-xl font-bold mb-6 text-white">In-Flight Extras ✨</h2>
    <p class="text-sm text-gray-400 mb-4">Customize your flight by selecting optional in-flight extras.</p>
    <div id="extrasContainer" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
</div>

<script>
    const extrasContainer = document.getElementById("extrasContainer");

    Object.entries(flightExtras).forEach(([name, extra]) => {
        const card = document.createElement("label");
        card.className = "cursor-pointer bg-gray-700 border border-gray-600 rounded-lg p-4 transition hover:border-blue-500 hover:bg-gray-650";

        card.innerHTML = `
        <div class="flex items-center justify-between">
                <div class="flex gap-4">
                    <input type="checkbox" class="mt-1 extra-checkbox" data-name="${name}" data-price="${extra.priceDollars}" data-ffms="${extra.priceFfms}">

                    <div>
                        <div class="font-semibold text-white flex items-center gap-2">
                        ${name.replace(/\b\w/g, c => c.toUpperCase())}
                        </div>
                    </div>
                </div>

                <div class="text-right">
                    <div class="text-white font-bold">
                        $${extra.priceDollars.toFixed(2)}
                    </div>

                    <div>
                        ${!isGuest ? `
                            <div class="text-xs text-gray-400">
                                ${extra.priceFfms.toLocaleString()} FFMs
                            </div>
                        ` : ""}
                    </div>    
                </div>
            </div>
        `;

        extrasContainer.appendChild(card);
    });
</script>