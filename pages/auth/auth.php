<html>
    <head>
        <title> Login </title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"> </script>
    </head>
    <body class="flex items-center justify-center min-h-screen m-0 font-sans bg-blue-900">
        <div class="bg-black shadow-xl rounded-xl p-4 w-full max-w-2xl">
            <h1 class="text-4xl font-bold text-emerald-400 text-center mb-2"> ✈️ BDPA Airlines </h1>
            <h3 class="text-center text-blue-600 mb-6"> Please Create Account. </h3>
            <form method="POST">
                <table class="mx-auto">
                    <tr>
                        <td class="text-white"> First Name: </td>
                        <td class="text-white"> <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="first" required> </td>
                    </tr>
                    <tr>
                        <td class="text-white"> Last Name: </td>
                        <td class="text-white"> <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="last" required> </td>
                    </tr>
                    <tr>
                        <td class="text-white"> Date of Birth: </td>
                        <td class="text-white"> <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="date" name="birth" required> </td>
                    </tr>
                    <tr>
                        <td class="text-white"> Gender: </td>
                        <td class="text-white"> <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="gender" required> </td>
                    </tr>
                    <tr>
                        <td class="text-white"> Street Address: </td>
                        <td class="text-white"> <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="street" required> </td>
                    </tr>
                    <tr>
                        <td class="text-white"> City: </td>
                        <td class="text-white"> <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="city" required> </td>
                    </tr>
                    <tr>
                        <td class="text-white"> State: </td>
                        <td class="text-white"> <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="state" required> </td>
                    </tr>
                    <tr>
                        <td class="text-white"> Zip Code: </td>
                        <td class="text-white"> <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="zip" required> </td>
                    </tr>
                    <tr>
                        <td class="text-white"> Country: </td>
                        <td class="text-white"> <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="country" required> </td>
                    </tr>
                    <tr>
                        <td class="text-white"> Email: </td>
                        <td class="text-white"> <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="email" name="email" required> </td>
                    </tr>
                    <tr>
                        <td class="text-white"> Password: </td>
                        <td class="text-white"> <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="password" name="password" required> </td>
                    </tr>
                    <tr>
                        <td class="text-white"> Phone Number: </td>
                        <td class="text-white"> <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="tel" name="phone"> </td>
                    </tr>
                    <tr>
                        <td class="text-white"> Favorite Food: </td>
                        <td class="text-white"> <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="favorite_food" required> </td>
                    </tr>
                    <tr>
                        <td class="text-white"> Favorite Color: </td>
                        <td class="text-white"> <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="favorite_color" required> </td>
                    </tr>
                    <tr>
                        <td class="text-white"> Favorite Sport: </td>
                        <td class="text-white"> <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="favorite_sport" required> </td>
                    </tr>
                </table>
                <br>
                <div class="text-center">
                    <p class="font-semibold text-emerald-400"> <b> What is <?php echo "$num1 + $num2"; ?> ? </b> </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="captcha" required>
                </div>
                <br>
                <div class="text-center">
                    <input class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-2 rounded cursor-pointer" type="submit" name="button" value="Create Account">
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
    if(isset($_POST['button'])){
        if (strlen($password) <= 10){
            echo "<p class='text-red-500 font-semibold text-center mb-4'> Password is too short. Minimum 10 characters needed. </p>";
        }
        else if ($captcha != ($num1 + $num2)) {
            echo "<p class='text-red-500 font-semibold text-center mb-4'> Wrong answer for captcha! </p>";
        }
        else{
            echo "<p class='text-green-600 font-semibold text-center mb-4'> Account Created Successfully! Welcome aboard. </p>";
        }
    }
?>
