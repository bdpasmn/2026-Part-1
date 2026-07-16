<?php
    // BUGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGG
    // when try again, seat color not reset
    // add ffm seat price too

    // Initialize empty array for seats that are already taken
    $takenSeats = [];

    // Use database for taken seats for this flight
    $stmt = $pdo->prepare("SELECT taken_seats FROM \"Flights\" WHERE flight_id = ?");

    $stmt->execute([$flightId]);

    // Fetch flight row
    $flightRow = $stmt->fetch(PDO::FETCH_ASSOC);

    // If flight exists, decode JSON list of taken seats
    if ($flightRow) {
        $takenSeats = json_decode($flightRow['taken_seats'] ?? '[]', true);
        if (!is_array($takenSeats)) {
            $takenSeats = [];
        }
    }

    $seatInfo = array_reverse($flight['seats']);
?>
<script>
    const seatInfo = <?= json_encode($seatInfo) ?>;
</script>
<style>
    /* Prevent interaction with already-taken seats */
    .taken-seat {
        pointer-events: none !important;
    }

    /* Override hover styles so taken seats stay inactive */
    .taken-seat:hover {
        background-color: rgb(51 65 85) !important;
        border-color: rgb(71 85 105) !important;
        transform: none !important;
    }
</style>
<div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
    <h2 class="text-xl font-bold mb-6 text-white">Select Your Seat ✏️</h2>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- SEAT GRID -->
        <div class="lg:col-span-2">
            <div class="mb-4 text-sm text-gray-400">
                Click a seat to select it. All seats are the same price.
            </div>

            <script>
                // Pass backend data into JavaScript
                const flightId = <?= json_encode($flightId) ?>;
                let takenSeats = <?= json_encode($takenSeats) ?>;
            </script>

            <!-- Seat map layout -->
            <div class="grid gap-2"
                style="grid-template-columns: repeat(3, minmax(0, 1fr)) 0.3fr repeat(3, minmax(0, 1fr)) 0.3fr repeat(3, minmax(0, 1fr));">
                <script>
                    // Container for seat buttons
                    const seatContainer = document.currentScript.parentElement;

                    const cols = 9;
                    const letters = ["A","B","C","D","E","F","G","H","I"];

                    // Calculate total seats 
                    const totalSeats = Object.values(seatInfo).reduce(
                        (sum, info) => sum + info.total,
                        0
                    );

                    // Calculate required rows
                    const rows = Math.ceil(totalSeats / cols);
                    const seatTypes = {};
                    let seatIndex = 0;

                    for (const [type, info] of Object.entries(seatInfo)) {
                        for (let i = 0; i < info.total; i++) {
                            const row = Math.floor(seatIndex / cols) + 1;
                            const col = seatIndex % cols;
                            const seatId = `${row}${letters[col]}`;

                            seatTypes[seatId] = {
                                type: type,
                                priceDollars: info.priceDollars,
                                priceFfms: info.priceFfms
                            };

                            seatIndex++;
                        }
                    }

                    let selectedSeat = null;

                    // Maps seat column to seat map position
                    function getColIndex(c) {
                        if (c <= 2) return c + 1;
                        if (c <= 5) return c + 2;
                        return c + 3;
                    }

                    // Generate seat buttons 
                    for (let r = 1; r <= rows; r++) {
                        for (let c = 0; c < cols; c++) {
                            const seatNumber = (r - 1) * cols + c;

                            // Skip cells beyond the total seat count
                            if (seatNumber >= totalSeats) {
                                continue;
                            }

                            const seatId = `${r}${letters[c]}`;
                            const seat = seatTypes[seatId];
                            const wrapper = document.createElement("div");
                            wrapper.style.gridColumnStart = getColIndex(c);

                            const btn = document.createElement("button");
                            btn.innerText = seatId;

                            // Check if seat is already taken
                            const isTaken = Array.isArray(takenSeats) && takenSeats.includes(seatId);

                            if (isTaken) {
                                // Disabled styling for taken seats
                                btn.className = "h-10 w-full text-xs rounded bg-slate-700 border border-slate-600 text-gray-300 cursor-not-allowed";
                                btn.disabled = true;
                            } else {
                                // Available seat styling + click handler
                                let classes = "";

                                switch (seat.type) {
                                    case "first class":
                                        classes = "bg-pink-600 border border-pink-400 hover:bg-pink-500 hover:border-pink-300";
                                        break;

                                    case "economy plus":
                                        classes = "bg-orange-400 border border-orange-200 hover:bg-orange-300 hover:border-orange-100";
                                        break;

                                    case "exit row":
                                        classes = "bg-pink-400 border border-pink-200 hover:bg-pink-300 hover:border-pink-100";
                                        break;

                                    default:
                                        classes = "bg-slate-600 border border-gray-500 hover:bg-slate-500 hover:border-gray-400";
                                }

                                btn.className = `seat-btn h-10 w-full text-xs rounded text-white transition ${classes}`;

                                btn.onclick = () => {
                                    selectedSeat = seatId;
                                    // Update UI summary panel
                                    document.getElementById("selectedSeat").innerText = selectedSeat;
                                    document.getElementById("selectedType").innerText = seat.type.replace(/\b\w/g, c => c.toUpperCase());
                                    document.getElementById("selectedPrice").innerText = "$" + seat.priceDollars.toFixed(2);
                                    document.getElementById("selectedPriceFfms").innerText = seat.priceFfms + " FFMs";
                                    document.getElementById("seatInput").value = selectedSeat;

                                    validateBooking();

                                    // Reset previous selections visually
                                    document.querySelectorAll(".seat-btn").forEach(b => {
                                        const seat = seatTypes[b.innerText];

                                        switch (seat.type) {
                                            case "first class":
                                                b.className = "seat-btn h-10 w-full text-xs rounded text-white transition bg-pink-600 border border-pink-400 hover:bg-pink-500 hover:border-pink-300";
                                                break;

                                            case "economy plus":
                                                b.className = "seat-btn h-10 w-full text-xs rounded text-white transition bg-orange-400 border border-orange-200 hover:bg-orange-300 hover:border-orange-100";
                                                break;

                                            case "exit row":
                                                b.className = "seat-btn h-10 w-full text-xs rounded text-white transition bg-pink-400 border border-pink-200 hover:bg-pink-300 hover:border-pink-100";
                                                break;

                                            default:
                                                b.className = "seat-btn h-10 w-full text-xs rounded text-white transition bg-slate-600 border border-gray-500 hover:bg-slate-500 hover:border-gray-400";
                                        }
                                    });

                                    btn.className = "seat-btn h-10 w-full text-xs rounded text-white transition bg-blue-500 border border-blue-300";
                                };
                            }

                            wrapper.appendChild(btn);
                            seatContainer.appendChild(wrapper);
                        }
                    }
                </script>
            </div>
        </div>

        <!-- SELECTION SUMMARY PANEL -->
        <div class="bg-gray-700 border border-gray-600 rounded-lg p-4 transition duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-gray-500">
            <h3 class="font-semibold text-white mb-4">Your Selection</h3>
            <div class="space-y-3 text-sm text-gray-300">
                <div>
                    <div class="text-gray-300">Seat Number</div>
                    <div id="selectedSeat" class="text-white font-bold">None selected</div>
                </div>
                <div class="mt-3">
                    <div class="text-gray-300">Seat Class</div>
                    <div id="selectedType" class="text-white font-bold">None</div>
                </div>
                <div>
                    <div class="text-gray-300">Seat Price</div>
                    <div id="selectedPrice" class="text-white font-bold">$0</div>
                    <div id="selectedPriceFfms" class="text-white font-bold">0 FFMs</div>
                </div>
            </div>

            <!-- Legend -->
            <div class="mt-6 space-y-2 text-xs">
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-pink-600 border border-pink-400"></div>
                    <span class="text-gray-300">First Class</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-orange-400 border border-orange-200"></div>
                    <span class="text-gray-300">Economy Plus</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-pink-400 border border-pink-200"></div>
                    <span class="text-gray-300">Exit Row</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-slate-600 border border-gray-500"></div>
                    <span class="text-gray-300">Economy</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-slate-700 border border-slate-600"></div>
                    <span class="text-gray-300">Taken</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-blue-500 border border-blue-300"></div>
                    <span class="text-gray-300">Selected</span>
                </div>
            </div>

            <div id="layoutInfo" class="mt-6 text-xs text-gray-400"></div>
        </div>
    </div>
</div>
<script>
    // Track latest seat state locally
    let latestTakenSeats = new Set(<?= json_encode($takenSeats) ?>);

    // Update UI when new seats become taken
    function updateSeatsUI(newTakenSeats) {
        const seatButtons = document.querySelectorAll(".seat-btn");

        seatButtons.forEach(btn => {
            const seatId = btn.innerText;
            const isNowTaken = newTakenSeats.includes(seatId);

            if (isNowTaken) {
                btn.disabled = true;

                // Apply locked styling
                btn.className = "h-10 w-full text-xs rounded bg-slate-700 border border-slate-600 text-gray-300 cursor-not-allowed taken-seat";
            }
        });

        latestTakenSeats = new Set(newTakenSeats);
    }

    // Poll backend for real-time seat updates
    async function pollSeats() {
        try {
            const res = await fetch(`checkSeats.php?flight_id=${flightId}`);
            const data = await res.json();
            if (!data.takenSeats) return;

            const newSeats = data.takenSeats;

            // Only update UI if seats changed
            if (
                JSON.stringify([...latestTakenSeats].sort()) !==
                JSON.stringify([...newSeats].sort())
            ) {
                updateSeatsUI(newSeats);
            }

        } catch (err) {
            console.error("Seat polling failed:", err);
        }
    }

    document.getElementById("layoutInfo").innerText =
    `Airplane layout: ${rows} rows × ${cols} seats (${totalSeats} total seats)`;

    // AJAX
    pollSeats();
    setInterval(pollSeats, 3000);
</script>