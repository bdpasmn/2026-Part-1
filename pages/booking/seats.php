<?php
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
?>
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
                const seatPrice = <?= json_encode($flight['seatPrice'] ?? 0) ?>;
                let takenSeats = <?= json_encode($takenSeats) ?>;
            </script>

            <!-- Seat map layout -->
            <div class="grid gap-2"
                style="grid-template-columns: repeat(3, minmax(0, 1fr)) 0.3fr repeat(3, minmax(0, 1fr)) 0.3fr repeat(3, minmax(0, 1fr));">
                <script>
                    // Container for seat buttons
                    const seatContainer = document.currentScript.parentElement;

                    const rows = 10;   // number of rows
                    const cols = 9;    // seats per row
                    const letters = ["A","B","C","D","E","F","G","H","I"];

                    let selectedSeat = null;

                    // Maps seat column to seat map position
                    function getColIndex(c) {
                        if (c <= 2) return c + 1;
                        if (c <= 5) return c + 2;
                        return c + 3;
                    }

                    // Generate seat buttons dynamically
                    for (let r = 1; r <= rows; r++) {
                        for (let c = 0; c < cols; c++) {
                            const seatId = `${r}${letters[c]}`;
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
                                btn.className = "seat-btn h-10 w-full text-xs rounded bg-slate-600 border border-gray-500 text-white hover:bg-blue-600 hover:border-blue-400 transition";

                                btn.onclick = () => {
                                    selectedSeat = seatId;
                                    // Update UI summary panel
                                    document.getElementById("selectedSeat").innerText = selectedSeat;
                                    document.getElementById("selectedPrice").innerText = "$" + seatPrice;
                                    document.getElementById("seatInput").value = selectedSeat;

                                    validateBooking();

                                    // Reset previous selections visually
                                    document.querySelectorAll(".seat-btn").forEach(b => {
                                        b.classList.remove("bg-blue-500");
                                        b.classList.add("bg-gray-700");
                                    });

                                    // Highlight selected seat
                                    btn.classList.add("bg-blue-500");
                                    btn.classList.remove("border-blue-300");
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
                <div>
                    <div class="text-gray-300">Seat Price</div>
                    <div id="selectedPrice" class="text-white font-bold">$0</div>
                </div>
            </div>

            <!-- Legend -->
            <div class="mt-6 flex items-center gap-4 text-xs">
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-slate-600 border border-gray-300"></div>
                    <span class="text-gray-300">Available</span>
                </div>

                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-blue-500 border border-blue-300"></div>
                    <span class="text-gray-300">Selected</span>
                </div>

                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-slate-700 border border-slate-500"></div>
                    <span class="text-gray-300">Taken</span>
                </div>
            </div>

            <div class="mt-6 text-xs text-gray-400">Airplane layout: 10 rows × 9 seats (90 total seats)</div>
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

    // AJAX
    pollSeats();
    setInterval(pollSeats, 3000);
</script>