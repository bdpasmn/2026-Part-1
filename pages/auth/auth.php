<?php // Test
    session_start();
    require_once "../../database/db.php";
    require_once "../../components/nav.php";
    if (isset($_POST["email"])) {
        $query="SELECT password, user_id, title, role  FROM Users WHERE first_name=? AND last_name=? AND email=?";
        $statement=$pdo->prepare($query);
        $statement->execute([$_POST["first"], $_POST["last"], $_POST["email"]]);
        if ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $data=$row["password"];
            if (password_verify($_POST["password"], $data)) {
                $_SESSION["email"]=$_POST["email"]; 
                $_SESSION["user"]=$data["title"];
                $_SESSION["name"]=$_POST["first"];
                $_SESSION["id"]=$data["user_id"];
                $_SESSION["role"]=$data["role"];
                if (!isset($_POST["remember"])) {
                    $_SESSION["remember"]="true";
                }
                if ($_SESSION["remember"]==="true") {
                    $_REQUEST[info]=true;
                }
                // header("location: ???");
                exit();
            }
        }
    }

?>
<html>
    <head>
        <title> Login </title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"> </script>
    </head>
    <body class="flex flex-col  min-h-screen bg-gray-900 text-white">
        <header class="flex items-center h-16 bg-gray-800 px-8 border-b border-gray-700">
            <h1 class="text-white font-bold text-xl"> BDPA Airports - TO BE REPLACED WITH NAV </h1>
        </header>
        <main class="flex flex-grow items-center justify-center bg-gradient-to-r from-slate-900 to-slate-800 p-6">
            <div class="w-full max-w-3xl space-y-6">
                <div class="text-center bg-gray-800 border border-gray-700 rounded-xl p-8 shadow-lg">
                    <h1 class="text-2xl font-bold"> BDPA Airlines </h1>
                    <h2 class="text-blue-300 mt-2"> Please Login </h2>
                    <br>
                    <h3 class="mb-3 text-red-100"> Attempts Remaining: 
                        <span id="incorrect">  </span> 
                    </h3>
                    <h3 class="mb-3 text-red-100" id="lockout" hidden> </h3>
                    <a class="mb-3 text-red-100" href=""> Forgot Your Password? </a> <!-- Finish -->
                </div>
                <div class="text-center bg-gray-800 border border-gray-700 rounded-xl p-8 shadow-lg">
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="email" class="text-xs text-gray-400"> Email </label>
                            <input class="w-full mt-1 h-10 bg-gray-700 border border-gray-600 rounded-lg px-3 text-sm" type="text" name="email" required>
                        </div> 
                        <div>
                            <label for="first" class="text-xs text-gray-400"> First Name: </label>
                            <input class="w-full mt-1 h-10 bg-gray-700 border border-gray-600 rounded-lg px-3 text-sm" type="text" name="first" required>
                        </div> 
                        <div>
                            <label for="last" class="text-xs text-gray-400"> Last Name: </label>
                            <input class="w-full mt-1 h-10 bg-gray-700 border border-gray-600 rounded-lg px-3 text-sm" type="text" name="last" required>
                        </div> 
                        <div>
                            <label for="password" class="text-xs text-gray-400"> Password: </label>
                            <input class="w-full mt-1 h-10 bg-gray-700 border border-gray-600 rounded-lg px-3 text-sm" type="text" name="password" required>
                        </div> 
                        <div class="md:col-span-2">
                                <label for="remember" class="flex justify-center text-xs text-gray-400"> Remember Me? </label> 
                                <input class="mt-1 h-10 bg-gray-700 border border-gray-600 rounded-lg px-3 scale-250" type="checkbox" name="remember" id="yes" value="yes">
                        </div> 
                        <br>
                        <div class="text-center md:col-span-2">
                            <input class="bg-blue-600 text-white px-6 py-2 rounded transition duration-200 hover:bg-blue-700 hover:shadow-md active:scale-95"  type="submit" name="button" id="submit" value="Login">
                        </div>
                    </form>
                </div>
            </div>
        </main>
        <script>
            var wrong=3;
            document.getElementById("incorrect").textContent=wrong;
            document.addEventListener('submit', function(event) {
                event.preventDefault();
                wrong--;
                document.getElementById("incorrect").textContent=wrong;
                if (wrong == 0 ) {
                    document.getElementById("submit").setAttribute("disabled", "");
                    setTimeout(enable, 900000);
                    document.getElementById("lockout").textContent="Your account has been locked, retry login in 15 minutes";
                    document.getElementById("lockout").removeAttribute("hidden");

                }
            });
            function enable() {
                document.getElementById("submit").removeAttribute("disabled");
                document.getElementById("lockout").setAttribute("hidden", "");
            }
            var url=""; // Add
            var info="boolean";
            function remember(url, info) {
                var xhttp = new XMLHttpRequest();
                xhttp.open("GET", `${url}?${info}`, true);
                xhttp.send();
            }
            remember(url, info);
            function read() {
                xhttp.onreadystatechange = function() {
                    if (info == true) {
                        const listener=["mousedown", "mousemove", "keydown", "scroll", "touchstart"];
                        document.addEventListener(listener, function() {
                            setTimeout(logout, 3600000);
                        });
                        function logout() {
                            session_destroy();
                            // header("location: ???");
                            exit();
                        }
                    }
                }
            }
            read();
        </script>
    </body>
</html>


