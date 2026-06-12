<html>
    <head>
        <title> BDPA Airlines - Create Account </title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    </head>
    <body class="flex items-center justify-center min-h-screen m-0 font-sans bg-blue-900">
        <div class="bg-black shadow-xl rounded-xl p-8 w-full max-w-2xl">
            <h1 class="text-4xl font-bold text-emerald-400 text-center mb-2"> ✈️ BDPA Airlines </h1>
            <h3 class="text-center text-blue-600 mb-6"> Create Your Passenger Account </h3>
            <form method="POST">
                <table class="mx-auto">
                    <tr>
                        <td class="p-2 text-white"> First Name: </td>
                        <td class="p-2 text-white">
                            <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="first" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="p-2 text-white"> Middle Name: </td>
                        <td class="p-2 text-white">
                            <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="middle" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="p-2 text-white"> Last Name: </td>
                        <td class="p-2 text-white">
                            <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="last" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="p-2 text-white"> Email: </td>
                        <td class="p-2 text-white">
                            <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="email" name="email" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="p-2 text-white"> Password: </td>
                        <td class="p-2 text-white">
                            <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="password" name="password" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="p-2 text-white"> Phone Number: </td>
                        <td class="p-2 text-white">
                            <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="tel" name="phone">
                        </td>
                    </tr>
                    <tr>
                        <td class="p-2 text-white"> Favorite Food: </td>
                        <td class="p-2 text-white">
                            <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="favorite_food" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="p-2 text-white"> Favorite Color: </td>
                        <td class="p-2 text-white">
                            <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="favorite_color" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="p-2 text-white"> Favorite Sport: </td>
                        <td class="p-2 text-white">
                            <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="favorite_sport" required>
                        </td>
                    </tr>
                </table>
                <div class="text-center mt-6">
                    <p class="font-semibold text-emerald-400">
                        <?php echo "Security Check: What is $num1 + $num2 ?"; ?>
                    </p>
                    <input class="border-2 border-white rounded-md text-white bg-transparent" name="captcha"type="text" required>
                </div>
                <div class="text-center mt-6">
                    <input class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-2 rounded cursor-pointer" name="button" type="submit" value="Create Customer Account">
                </div>
            </form>
            <div class="text-center mt-6">
                <a class="text-emerald-400 hover:underline" href="https://www.bdpastudents.com/code/run/7924036/2026/test/admin_create.php"> Admin Portal </a>
            </div>
        </div>
    </body>
</html>
<?php
    $first = $_POST['first'] ?? '';
    $middle = $_POST['middle'] ?? '';
    $last = $_POST['last'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $food = $_POST['favorite_food'] ?? '';
    $color = $_POST['favorite_color'] ?? '';
    $sport = $_POST['favorite_sport'] ?? '';
    $captcha = $_POST['captcha'] ?? '';
    $num1 = rand(1,10);
    $num2 = rand(1,10);
    if (isset($_POST['button'])) {
        if (strlen($password) <= 10) {
            echo "<p class='text-red-500 font-semibold text-center mb-4'> Password is too short. Minimum 10 characters needed. </p>";
        }
        elseif ($captcha != ($num1 + $num2)) {
            echo "<p class='text-red-500 font-semibold text-center mb-4'> Wrong answer for captcha! </p>";
        }
        else {
            echo "<p class='text-green-600 font-semibold text-center mb-4'> Account Created Successfully! Welcome aboard. </p>";
        }
    }
?>
