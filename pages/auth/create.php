<?php

require_once __DIR__ . '/../../database/db.php';

$first = trim($_POST['first'] ?? '');
$title = trim($_POST['title'] ?? '');
$middle = trim($_POST['middle'] ?? '');
$last = trim($_POST['last'] ?? '');
$suffix = trim($_POST['suffix'] ?? '');
$birth = trim($_POST['birth'] ?? '');
$email = trim($_POST['email'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$street = trim($_POST['street'] ?? '');
$city = trim($_POST['city'] ?? '');
$state = trim($_POST['state'] ?? '');
$zip = trim($_POST['zip'] ?? '');
$country = trim($_POST['country'] ?? '');
$password = $_POST['password'] ?? '';
$question1 = trim($_POST['question1'] ?? '');
$question2 = trim($_POST['question2'] ?? '');
$question3 = trim($_POST['question3'] ?? '');
$question1_answer = trim($_POST['answer1'] ?? '');
$question2_answer = trim($_POST['answer2'] ?? '');
$question3_answer = trim($_POST['answer3'] ?? '');
$captcha = trim($_POST['captcha'] ?? '');
$num1 = isset($_POST['num1']) ? (int) $_POST['num1'] : rand(1, 10);
$num2 = isset($_POST['num2']) ? (int) $_POST['num2'] : rand(1, 10);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['button'])) {
    if (strlen($password) <= 10) {
        $message = "<p class='text-red-500 font-semibold text-center mb-4'> Weak password. Password must be longer than 10 characters. </p>";
    } elseif ($captcha === '' || (int) $captcha !== $num1 + $num2) {
        $message = "<p class='text-red-500 font-semibold text-center mb-4'> Wrong answer for captcha! </p>";
    } elseif ($email === '') {
        $message = "<p class='text-red-500 font-semibold text-center mb-4'> Email is required. </p>";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO public."Users" (first_name, middle_name, last_name, suffix, date_birth, title, sex, street_address, city, country, state, zip_code, phone, email, password, role) VALUES (:first_name, :middle_name, :last_name, :suffix, :date_birth, :title, :sex, :street_address, :city, :country, :state, :zip_code, :phone, :email, :password, :role)');
            $stmt->execute([
                ':first_name' => $first,
                ':middle_name' => $middle,
                ':last_name' => $last,
                ':suffix' => $suffix,
                ':date_birth' => $birth,
                ':title' => $title,
                ':sex' => $gender,
                ':street_address' => $street,
                ':city' => $city,
                ':country' => $country,
                ':state' => $state,
                ':zip_code' => $zip,
                ':phone' => $phone,
                ':email' => $email,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':role' => 'Customer',
            ]);

            $stmt = $pdo->prepare('INSERT INTO public."User Security Questions" (email, question1, question1_answer, question2, question2_answer, question3, question3_answer) VALUES (:email, :question1, :question1_answer, :question2, :question2_answer, :question3, :question3_answer)');
            $stmt->execute([
                ':email' => $email,
                ':question1' => $question1,
                ':question1_answer' => $question1_answer,
                ':question2' => $question2,
                ':question2_answer' => $question2_answer,
                ':question3' => $question3,
                ':question3_answer' => $question3_answer,
            ]);

            $pdo->commit();
            $message = "<p class='text-green-600 font-semibold text-center mb-4'> Account Created Successfully! Welcome aboard. </p>";
        } catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() === '23505') {
        $message = "<p class='text-red-500 font-semibold text-center mb-4'>An account with that email already exists.</p>";
    } else {
    $message = "<p class='text-red-500 font-semibold text-center mb-4'>
        Sorry, we couldn't create your account right now. Please try again later.
    </p>";
    }

        }
    }
}
?>
<html>
<head>
    <title>Create Account</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body class="bg-gray-900 min-h-screen text-white flex flex-col">
    <header class="h-16 bg-gray-800 flex items-center px-8 border-b border-gray-700">
        <h1 class="font-bold text-xl">
            BDPA Airports - TO BE REPLACED WITH NAV
        </h1>
    </header>
    <main class="bg-gradient-to-r from-slate-800 to-slate-900 flex-grow flex items-center justify-center">
        <div class="bg-gray-800 shadow-xl rounded-xl p-4 w-xs md:w-full max-w-2xl">
            <div class="bg-gradient-to-r from-slate-800 to-slate-900 border border-gray-700 rounded-xl p-10 shadow-lg">
                <h1 class="text-center mb-2 text-white font-bold text-xl"> BDPA Airlines </h1>
                <h2 class="text-center mb-6 text-blue-300 text-lg"> Please Create An Account </h2>
            </div>
            <br>
            <h3 class="text-center mb-3 text-red-300"> * denotes Required Fields </h3>
            <form method="POST" class="flex items-center justify-center flex-col">
                <?php echo $message; ?>
                <div class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="title" class="font-medium text-white"> Account Title: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="title" value="<?php echo htmlspecialchars($title); ?>">
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="first" class="font-medium text-white"> *First Name: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="first" value="<?php echo htmlspecialchars($first); ?>" required>
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="middle" class="font-medium text-white"> Middle Name: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="middle" value="<?php echo htmlspecialchars($middle); ?>">
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="last" class="font-medium text-white"> *Last Name: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="last" value="<?php echo htmlspecialchars($last); ?>" required>
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="suffix" class="font-medium text-white"> Suffix: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="suffix" value="<?php echo htmlspecialchars($suffix); ?>">
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="birth" class="font-medium text-white"> *Date of Birth: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="date" name="birth" value="<?php echo htmlspecialchars($birth); ?>" required>
                </div>

                 <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <p class="font-medium text-white"> *Gender: </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="gender" value="<?php echo htmlspecialchars($gender); ?>" required>
                </div> 

                <div class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="street" class="font-medium text-white"> *Street Address: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="street" value="<?php echo htmlspecialchars($street); ?>" required>
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="city" class="font-medium text-white"> *City: </label> 
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="city" value="<?php echo htmlspecialchars($city); ?>" required> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="country" class="font-medium text-white"> *Country: </label>
                    <select class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="country" id="country" required>
                        <option value="afghanistan"> Afghanistan </option>
                        <option value="albania"> Albania </option>
                        <option value="algeria"> Algeria </option>
                        <option value="andorra"> Andorra </option>
                        <option value="angola"> Angola </option>
                        <option value="antigu and barbuda"> Antigua and Barbuda </option>
                        <option value="argentina"> Argentina </option>
                        <option value="armenia"> Armenia </option>
                        <option value="australia"> Australia </option>
                        <option value="australia"> Austria </option>
                        <option value="azerbaijan"> Azerbaijan </option>
                        <option value="bahamas"> Bahamas </option>
                        <option value="bahrain"> Bahrain </option>
                        <option value="bangladesh"> Bangladesh </option>
                        <option value="barbados"> Barbados </option>
                        <option value="belarus"> Belarus </option>
                        <option value="belgium"> Belgium </option>
                        <option value="belize"> Belize </option>
                        <option value="benin"> Benin </option>
                        <option value="bhutan"> Bhutan </option>
                        <option value="bolivia"> Bolivia </option>
                        <option value="bosnia and herzegovina"> Bosnia and Herzegovina </option>
                        <option value="botswana"> Botswana  </option>
                        <option value="brazil"> Brazil  </option>
                        <option value="brunei darussalam"> Brunei Darussalam </option>
                        <option value="bulgaria"> Bulgaria </option>
                        <option value="burkina faso"> Burkina Faso </option>
                        <option value="burundi"> Burundi </option>
                        <option value="cabo verde"> Cabo Verde </option>
                        <option value="cameroon"> Cameroon </option>
                        <option value="canada"> Canada </option>
                        <option value="central african republic"> Central African Republic </option>
                        <option value="chad"> Chad </option>
                        <option value="chile"> Chile </option>
                        <option value="china"> China </option>
                        <option value="colombia"> Colombia </option>
                        <option value="comoros"> Comoros </option>
                        <option value="congo"> Congo </option>
                        <option value="costa rica"> Costa Rica </option>
                        <option value="cote-d'ivoire"> Côte D'Ivoire </option>
                        <option value="croatia"> Croatia </option>
                        <option value="cuba"> Cuba </option>
                        <option value="czechia"> Czechia </option>
                        <option value="democratic people's republic of korea"> Democratic People's Republic of Korea </option>
                        <option value="democratic republic of the congo"> Democratic Republic of the Congo </option>
                        <option value="denmark"> Denmark </option>
                        <option value="djibouti"> Djibouti </option>
                        <option value="dominica"> Dominica </option>
                        <option value="dominican republic"> Dominican Republic </option>
                        <option value="ecuador"> Ecuador </option>
                        <option value="egypt"> Egypt </option>
                        <option value="el salvador"> El Salvador </option>
                        <option value="equatorial guinea"> Equatorial Guinea </option>
                        <option value="eritrea"> Eritrea </option>
                        <option value="estonia"> Estonia </option>
                        <option value="eswatini"> Eswatini </option>
                        <option value="ethiopia"> Ethiopia </option>
                        <option value="fiji"> Fiji </option>
                        <option value="finland"> Finland </option>
                        <option value="france"> France  </option>
                        <option value="gabon"> Gabon  </option>
                        <option value="gambia"> Gambia </option>
                        <option value="georgia"> Georgia </option>
                        <option value="germany"> Germany </option>
                        <option value="ghana"> Ghana </option>
                        <option value="greece"> Greece </option>
                        <option value="grenada"> Grenada </option>
                        <option value="guatemala"> Guatemala </option>
                        <option value="guinea"> Guinea </option>
                        <option value="guinea bissau"> Guinea Bissau </option>
                        <option value="guyana"> Guyana </option>
                        <option value="haiti"> Haiti </option>
                        <option value="honduras"> Honduras </option>
                        <option value="hungary"> Hungary </option>
                        <option value="iceland"> Iceland </option>
                        <option value="india"> India </option>
                        <option value="indonesia"> Indonesia </option>
                        <option value="iran"> Iran </option>
                        <option value="iraq"> Iraq </option>
                        <option value="ireland"> Ireland </option>
                        <option value="israel"> Israel </option>
                        <option value="italy"> Italy </option>
                        <option value="jamaica"> Jamaica </option>
                        <option value="japan"> Japan </option>
                        <option value="jordan"> Jordan </option>
                        <option value="kazakhstan"> Kazakhstan </option>
                        <option value="kenya"> Kenya </option>
                        <option value="kiribati"> Kiribati </option>
                        <option value="kuwait"> Kuwait </option>
                        <option value="kyrgyzstan"> Kyrgyzstan </option>
                        <option value="lao people's democratic republic"> Lao People's Democratic Republic </option>
                        <option value="latvia"> Latvia </option>
                        <option value="lebanon"> Lebanon </option>
                        <option value="lesotho"> Lesotho </option>
                        <option value="liberia"> Liberia </option>
                        <option value="libya"> Libya </option>
                        <option value="liechtenstein"> Liechtenstein </option>
                        <option value="lithuania"> Lithuania </option>
                        <option value="luxembourg"> Luxembourg </option>
                        <option value="madagascar"> Madagascar </option>
                        <option value="malawi"> Malawi </option>
                        <option value="malaysia"> Malaysia </option>
                        <option value="maldives"> Maldives </option>
                        <option value="mali"> Mali  </option>
                        <option value="malta"> Malta </option>
                        <option value="marshall islands"> Marshall Islands </option>
                        <option value="mauritania"> Mauritania </option>
                        <option value="mauritius"> Mauritius </option>
                        <option value="mexico"> Mexico </option>
                        <option value="micronesia"> Micronesia </option>
                        <option value="monaco"> Monaco </option>
                        <option value="mongolia"> Mongolia </option>
                        <option value="montenegro"> Montenegro </option>
                        <option value="morocco"> Morocco </option>
                        <option value="mozambique"> Mozambique </option>
                        <option value="myanmar"> Myanmar </option>
                        <option value="namibia"> Namibia </option>
                        <option value="nauru"> Nauru </option>
                        <option value="nepal"> Nepal </option>
                        <option value="netherlands"> Netherlands </option>
                        <option value="new zealand"> New Zealand </option>
                        <option value="nicaragua"> Nicaragua </option>
                        <option value="niger"> Niger </option>
                        <option value="nigeria"> Nigeria </option>
                        <option value="north macedonia"> North Macedonia </option>
                        <option value="norway"> Norway </option>
                        <option value="oman"> Oman </option>
                        <option value="pakistan"> Pakistan </option>
                        <option value="palau"> Palau </option>
                        <option value="panama"> Panama </option>
                        <option value="papua new guinea"> Papua New Guinea </option>
                        <option value="paraguay"> Paraguay </option>
                        <option value="peru"> Peru </option>
                        <option value="philippines"> Philippines </option>
                        <option value="poland"> Poland </option>
                        <option value="portugal"> Portugal </option>
                        <option value="qatar"> Qatar </option>
                        <option value="republic of korea"> Republic of Korea </option>
                        <option value="republic of moldova"> Republic of Moldova </option>
                        <option value="romania"> Romania </option>
                        <option value="russian federation"> Russian Federation </option>
                        <option value="rwanda"> Rwanda </option>
                        <option value="saint kitts and nevis"> Saint Kitts and Nevis </option>
                        <option value="saint lucia"> Saint Lucia </option>
                        <option value="saint vincent and the grenadines"> Saint Vincent and the Grenadines </option>
                        <option value="samoa"> Samoa </option>
                        <option value="san marino"> San Marino </option>
                        <option value="sao tome and principe"> Sao Tome and Principe </option>
                        <option value="saudi arabia"> Saudi Arabia </option>
                        <option value="senegal"> Senegal </option>
                        <option value="serbia"> Serbia </option>
                        <option value="seychelles"> Seychelles </option>
                        <option value="sierra leone"> Sierra Leone </option>
                        <option value="singapore"> Singapore </option>
                        <option value="Slovakia "> Slovakia </option>
                        <option value="Slovenia"> Slovenia </option>
                        <option value="Solomon Islands"> Solomon Islands </option>
                        <option value="somalia"> Somalia </option>
                        <option value="south africa"> South Africa </option>
                        <option value="south sudan"> South Sudan </option>
                        <option value="spain"> Spain </option>
                        <option value="sri lanka"> Sri Lanka </option>
                        <option value="sudan"> Sudan </option>
                        <option value="suriname"> Suriname </option>
                        <option value="sweden"> Sweden </option>
                        <option value="switzerland"> Switzerland </option>
                        <option value="syrian arab republic"> Syrian Arab Republic </option>
                        <option value="tajikistan"> Tajikistan </option>
                        <option value="thailand"> Thailand </option>
                        <option value="timor-leste"> Timor-Leste </option>
                        <option value="togo"> Togo </option>
                        <option value="tonga"> Tonga </option>
                        <option value="trinidad and tobago"> Trinidad and Tobago </option>
                        <option value="tunisia"> Tunisia </option>
                        <option value="türkiye"> Türkiye </option>
                        <option value="turkmenistan"> Turkmenistan </option>
                        <option value="tuvalu"> Tuvalu </option>
                        <option value="uganda"> Uganda </option>
                        <option value="ukraine"> Ukraine </option>
                        <option value="united arab emirates"> United Arab Emirates </option>
                        <option value="united kingdom of great britain and northern ireland"> United Kingdom of Great Britain and Northern Ireland </option>
                        <option value="united republic of tanzania"> United Republic of Tanzania </option>
                        <option value="US"> United States of America </option>
                        <option value="uruguay"> Uruguay </option>
                        <option value="uzbekistan"> Uzbekistan </option>
                        <option value="vanuatu"> Vanuatu </option>
                        <option value="venezuela"> Venezuela </option>
                        <option value="vietnam"> Vietnam </option>
                        <option value="yemen"> Yemen </option>
                        <option value="zambia"> Zambia </option>
                        <option value="zimbabwe"> Zimbabwe </option>
                    </select>
                </div> 
                <div id="state-container" class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="state" class="font-medium text-white">*State: </label> 
                    <select class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="state" id="state" required>
<option value="AL">Alabama</option>
<option value="AK">Alaska</option>
<option value="AZ">Arizona</option>
<option value="AR">Arkansas</option>
<option value="CA">California</option>
<option value="CO">Colorado</option>
<option value="CT">Connecticut</option>
<option value="DE">Delaware</option>
<option value="DC">District of Columbia</option>
<option value="FL">Florida</option>
<option value="GA">Georgia</option>
<option value="HI">Hawaii</option>
<option value="ID">Idaho</option>
<option value="IL">Illinois</option>
<option value="IN">Indiana</option>
<option value="IA">Iowa</option>
<option value="KS">Kansas</option>
<option value="KY">Kentucky</option>
<option value="LA">Louisiana</option>
<option value="ME">Maine</option>
<option value="MD">Maryland</option>
<option value="MA">Massachusetts</option>
<option value="MI">Michigan</option>
<option value="MN">Minnesota</option>
<option value="MS">Mississippi</option>
<option value="MO">Missouri</option>
<option value="MT">Montana</option>
<option value="NE">Nebraska</option>
<option value="NV">Nevada</option>
<option value="NH">New Hampshire</option>
<option value="NJ">New Jersey</option>
<option value="NM">New Mexico</option>
<option value="NY">New York</option>
<option value="NC">North Carolina</option>
<option value="ND">North Dakota</option>
<option value="OH">Ohio</option>
<option value="OK">Oklahoma</option>
<option value="OR">Oregon</option>
<option value="PA">Pennsylvania</option>
<option value="RI">Rhode Island</option>
<option value="SC">South Carolina</option>
<option value="SD">South Dakota</option>
<option value="TN">Tennessee</option>
<option value="TX">Texas</option>
<option value="UT">Utah</option>
<option value="VT">Vermont</option>
<option value="VA">Virginia</option>
<option value="WA">Washington</option>
<option value="WV">West Virginia</option>
<option value="WI">Wisconsin</option>
<option value="WY">Wyoming</option>
                    </select>
                </div> 
                
                <div id="zip-container" class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label class="font-medium text-white"> *Zip Code: </label>
                    <input id="zip" class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="zip" value="<?php echo htmlspecialchars($zip); ?>" required>
                </div> 

                <div class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label class="font-medium text-white"> Phone: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="tel" name="phone" maxlength="15" value="<?php echo htmlspecialchars($phone); ?>">
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="email" class="font-medium text-white"> *Email: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required> 
                </div>

                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <p class="font-medium text-white"> Password: </p>
                    <div class="flex flex-col w-full">
                        <input id="password" class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="password" name="password" required>
                        <p id="password-strength" class="mt-2 text-sm text-white">Password must be more than 10 characters.</p>
                    </div>
                </div>

                <div class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="question1" class="font-medium text-white"> *Custom Security Question 1</label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="question1" value="<?php echo htmlspecialchars($question1); ?>" required> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="answer1" class="font-medium text-white"> *Answer 1</label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="answer1" value="<?php echo htmlspecialchars($answer1); ?>" required> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="question2" class="font-medium text-white"> *Custom Security Question 2</label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="question2" value="<?php echo htmlspecialchars($question2); ?>" required> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="answer2" class="font-medium text-white"> *Answer 2</label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="answer2" value="<?php echo htmlspecialchars($answer2); ?>" required> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="question3" class="font-medium text-white"> *Custom Security Question 3</label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="question3" value="<?php echo htmlspecialchars($question3); ?>" required>
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-2 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="answer3" class="font-medium text-white"> *Answer 3</label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="answer3" value="<?php echo htmlspecialchars($answer3); ?>" required> 
                </div>
                <br>
                <div class="text-center bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <p class="font-medium text-white"> 
                        <b> What is <?php echo htmlspecialchars($num1); ?> + <?php echo htmlspecialchars($num2); ?> ? </b> 
                    </p>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="text" name="captcha" value="<?php echo htmlspecialchars($captcha); ?>" required>
                    <input type="hidden" name="num1" value="<?php echo htmlspecialchars($num1); ?>">
                    <input type="hidden" name="num2" value="<?php echo htmlspecialchars($num2); ?>">
                </div>
                <br>
                <div class="text-center">
                    <input class="bg-blue-600 text-white px-6 py-2 rounded transition duration-200 hover:bg-blue-700 hover:shadow-md active:scale-95" type="submit" name="button" value="Create Account">
                </div>
            </form>
            </main>
        </div>
        <script>
            //password strength checker
            document.addEventListener('DOMContentLoaded', function () {
                var passwordInput = document.getElementById('password');
                var strengthText = document.getElementById('password-strength');

                function updateStrength() {
                    var length = passwordInput.value.length;
                    if (length === 0) {
                        strengthText.textContent = 'Password must be more than 10 characters.';
                        strengthText.className = 'mt-2 text-sm text-white';
                    } else if (length <= 10) {
                        strengthText.textContent = 'Weak password';
                        strengthText.className = 'mt-2 text-sm text-red-400';
                    } else if (length <= 17) {
                        strengthText.textContent = 'Medium strength password';
                        strengthText.className = 'mt-2 text-sm text-yellow-300';
                    } else {
                        strengthText.textContent = 'Strong password';
                        strengthText.className = 'mt-2 text-sm text-green-400';
                    }
                }

                passwordInput.addEventListener('input', updateStrength);
                updateStrength();
            });

document.addEventListener("DOMContentLoaded", function () {
    const countrySelect = document.getElementById("country");
    const stateSelect = document.getElementById("state");
    const zipInput = document.getElementById("zip");

    const stateContainer = document.getElementById("state-container");
    const zipContainer = document.getElementById("zip-container");

    const selectedCountry = <?php echo json_encode($country); ?>;
    const selectedState = <?php echo json_encode($state); ?>;

    if (selectedCountry) {
        countrySelect.value = selectedCountry;
    }

    if (selectedState) {
        stateSelect.value = selectedState;
    }

    function updateUSFields() {
        const isUS =
            countrySelect.value === "US";

        stateContainer.style.display = isUS ? "flex" : "none";
        zipContainer.style.display = isUS ? "flex" : "none";

        stateSelect.required = isUS;
        zipInput.required = isUS;

        if (!isUS) {
            stateSelect.value = "";
            zipInput.value = "";
        }
    }

    updateUSFields();

    countrySelect.addEventListener("change", updateUSFields);
});
        </script>
    </body>
</html>


