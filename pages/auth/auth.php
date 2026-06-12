<html>
    <head>
        <title> Login </title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"> </script>
    </head>
    <body class="flex flex-col items-center justify-center min-h-screen m-0 bg-gray-900">
        <h1 class="text-white font-bold text-xl"> BDPA Airports - TO BE REPLACED WITH NAV </h1>
        <div class="bg-gray-800 shadow-xl rounded-xl p-4 w-xs md:w-full max-w-2xl">
            <div class="bg-gradient-to-r from-slate-800 to-slate-900 border border-gray-700 rounded-xl p-10 shadow-lg">
                <h1 class="text-center mb-2 text-white font-bold text-xl"> BDPA Airlines </h1>
                <h3 class="text-center mb-6 text-blue-300 text-lg"> Please Create An Account </h3>
            </div>
            <form method="POST" class="flex items-center justify-center flex-col">
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <p class="font-medium text-white"> First Name: </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="first" required>
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <p class="font-medium text-white"> Last Name: </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="last" required>
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <p class="font-medium text-white"> Date of Birth: </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="date" name="birth" required>
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <p class="font-medium text-white"> Gender: </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="gender" required>
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <p class="font-medium text-white"> Street Address: </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="street" required>
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <p class="font-medium text-white"> City: </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="city" required> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <p class="font-medium text-white"> State: </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95t" type="text" name="state" required>
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <p class="font-medium text-white"> Zip Code: </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="zip" required> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <p class="font-medium text-white"> Country: </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="country" required>
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <p class="font-medium text-white"> Email: </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="email" name="email" required> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <p class="font-medium text-white"> Password: </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="password" name="password" required> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <p class=" font-medium text-white"> Phone #: </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="tel" name="phone"> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <p class="font-medium text-white"> Favorite Food: </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="favorite_food" required> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <p class="font-medium text-white"> Favorite Color: </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="favorite_color" required> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <p class="font-medium text-white"> Favorite Sport: </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="favorite_sport" required>
                </div> 
                <br>
                <div class="text-center bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md"> <!--Look into if this is needed -->
                    <p class="font-medium text-white"> 
                        <b> What is <?php echo "$num1 + $num2"; ?> ? </b> 
                    </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="captcha" required>
                </div>
                <br>
                <div class="text-center">
                    <input class="bg-blue-600 text-white px-6 py-2 rounded transition duration-200 hover:bg-blue-700 hover:shadow-md active:scale-95" type="submit" name="button" value="Create Account">
                </div>
            </form>
        </div>
    </body>
</html>
<?php
    $first = $_POST['first'] ?? '';
    $middle = $_POST['middle'] ?? '';
    $last = $_POST['last'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $street = $_POST['street'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $zip = $_POST['zip'] ?? '';
    $country = $_POST['country'] ?? '';
    $password = $_POST['password'] ?? '';
    $food = $_POST['favorite_food'] ?? '';
    $color = $_POST['favorite_color'] ?? '';
    $sport = $_POST['favorite_sport'] ?? '';
    $captcha = $_POST['captcha'] ?? '';
    $num1 = rand(1,10);
    $num2 = rand(1,10);
    if (isset($_POST['button'])) {
        if (strlen($password) <= 10) {
            echo "<p class='text-red-500 font-semibold text-center mb-4'> Password is too short. Minimum 10 characters needed. </p>"; // Sam--Just limit the min. char. in the input tag itself
        }
        else if ($captcha != ($num1 + $num2)) {
            echo "<p class='text-red-500 font-semibold text-center mb-4'> Wrong answer for captcha! </p>";
        } else {
            echo "<p class='text-green-600 font-semibold text-center mb-4'> Account Created Successfully! Welcome aboard. </p>";
        }
    }
?>


