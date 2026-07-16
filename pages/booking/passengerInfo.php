<!-- Passenger information and payment section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Left: Personal details -->
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
                <select id="sex" class="required-booking w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
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
        </div>
    </div>

    <!-- Right: Contact information -->
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-6">
        <h2 class="text-xl font-bold mb-6 text-white">Contact Information 📞</h2>
        <div class="grid grid-cols-1 gap-4">
            <div>
                <label class="block mb-2 text-sm font-medium text-gray-300">Phone Number*</label>
                <input type="tel" id="phone" maxlength="20" class="required-booking w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white">
            </div>

            <div>
                <label class="block mb-2 text-sm font-medium text-gray-300">Email Address*</label>
                <input type="email" id="email" class="required-booking w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>
    </div>
</div>

<script>
    // Format the phone number as the user types
    const phoneInput = document.getElementById('phone');
    phoneInput.addEventListener('input', function () {
        let d = this.value.replace(/\D/g, '');

        if (d.length > 11) {
            d = d.slice(0, 11);
        }

        if (d.length == 0) {
            this.value = '';
        } else if (d.length <= 3) {
            this.value = '(' + d;
        } else if (d.length <= 6) {
            this.value = '(' + d.slice(0, 3) + ') ' + d.slice(3);
        } else if (d.length <= 10) {
            this.value = '(' + d.slice(0, 3) + ') ' + d.slice(3, 6) + '-' + d.slice(6);
        } else {
            this.value = '+1 (' + d.slice(1, 4) + ') ' + d.slice(4, 7) + '-' + d.slice(7);
        }
    });
</script>