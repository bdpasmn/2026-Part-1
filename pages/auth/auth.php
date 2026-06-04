<html>
    <head>
        <title>Signin</title>
         <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
         <style>
            input {
                border: 2px solid black;
                padding: 4px;
                border-radius: 6px;
            }
        </style>
    </head>
    <body class="place-items-center min-h-screen m-0">
        <b><h1>Welcome to BDPA Airlines!</h1></b>
        <h3>Please Create Account.</h3>
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

            if(isset($_POST['button'])){
                if(strlen($password) <= 10){
                    echo"Password is too short. Minimum 10 characters needed.";
                }
                if ($captcha != ($num1 + $num2)) {
                    echo "Wrong answer for captcha!";
                }
                else{
                    echo "complete";
                }
            }
        ?>
        <form method="POST">
            <table>
            <tr>
                <td><span>First Name:</span></td>
                <td><input type="text" name="first" id="first" required/></td>
            </tr>
            <tr>
                <td><span>Middle Name:</span></td>
                <td><input type="text" name="middle" id="middle" required/></td>
            </tr>
            <tr>
                <td><span>Last Name:</span></td>
                <td><input type="text" name="last" id="last" required/></td>
            </tr>
            <tr>
                <td><span>Email:</span></td>
                <td><input type="email" name="email" id="email" required/></td>
            </tr>
            <tr>
                <td><span>Password:</span></td>
                <td><input type="password" name="password" id="password" required/></td>
            </tr>
            <tr>
                <td><span>Phone number:</span></td>
                <td><input type="tel" name="phone" id="phone"></td>
            </tr>
            <tr>
                <td><span>Favorite food:</span></td>
                <td><input type="text" name="favorite_food" id="favorite_food" required></td>
            </tr>
            <tr>
                <td><span>Favorite Color:</span></td>
                <td><input type="text" name="favorite_color" id="favorite_color" required></td>
            </tr>
            <tr>
                <td><span>Favorite Sport:</span></td>
                <td><input type="text" name="favorite_sport" id="favorite_sport" required></td>
            </tr>
            <tr>
                <td><input name="button" type="submit"></td>
            </tr>
            </table>
            <br/>
            <?php
                echo "What is $num1 + $num2?";
            ?>
            <tr>
                <td><input name="captcha" type="text" required></td>
            </tr>
        </form>
    </body>
</html>
