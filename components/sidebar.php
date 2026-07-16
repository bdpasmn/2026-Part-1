<?php

require_once __DIR__ . '/../api/api.php';
require_once __DIR__ . '/../api/key.php';
require_once __DIR__ . '/../database/db.php';

if (empty($_SESSION['user_id'])) {
    return;
}

$api = new AirportsAPI(AIRPORTS_API_KEY);
?>

<aside class="w-72 bg-gradient-to-b from-gray-900 via-gray-800 to-gray-900 text-white border-r border-gray-700 shadow-xl flex flex-col">

    <!-- Header -->
    <div class="p-6 border-b border-gray-700">
        <h2 class="text-2xl font-bold">Welcome 👋</h2>
        <p class="text-sm text-gray-400 mt-1">
            Glad to see you back.
        </p>
    </div>

    <!-- Profile Card -->
    <div class="p-6">
        <div class="bg-gray-800 border border-gray-700 rounded-2xl p-5 shadow-lg">

            <!-- Avatar -->
            <div class="flex justify-center mb-5">
                <div class="w-20 h-20 rounded-full bg-blue-600 flex items-center justify-center text-3xl font-bold shadow-lg">
                    <?= strtoupper(substr($_SESSION['name'], 0, 1)) ?>
                </div>
            </div>

            <!-- Name -->
            <h3 class="text-xl font-semibold text-center">
                <?= htmlspecialchars($_SESSION['name']) ?>
            </h3>

            <div class="mt-6 space-y-4">

                <!-- Username -->
                <div class="bg-gray-900 rounded-xl p-3 border border-gray-700">
                    <p class="text-xs uppercase tracking-wider text-gray-500 mb-1">
                        Username
                    </p>
                    <p class="text-gray-100 break-all">
                        <?= htmlspecialchars($_SESSION['name']) ?>
                    </p>
                </div>

                <!-- Email -->
                <div class="bg-gray-900 rounded-xl p-3 border border-gray-700">
                    <p class="text-xs uppercase tracking-wider text-gray-500 mb-1">
                        Email
                    </p>
                    <p class="text-gray-100 break-all">
                        <?= htmlspecialchars($_SESSION['email']) ?>
                    </p>
                </div>

            </div>
        </div>
    </div>

</aside>