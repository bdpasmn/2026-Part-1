<?php
session_start();
require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../../components/config.php';

if (isset($_SESSION['user_id'])) {
    $role = strtolower($_SESSION['role']) ?? ''; //sign in redirection
    
    if (in_array($role, ['customer', 'admin', 'root'])) {
        $roleLower = strtolower($role);

        header("Location: ../dashboard/{$roleLower}/{$roleLower}.php");
        exit;
    }
}
    function regenerateCaptcha() { //captcha regeneration
        $_SESSION['captcha_num1'] = rand(1, 10);
        $_SESSION['captcha_num2'] = rand(1, 10);
    } 
    if (!isset($_SESSION['captcha_num1']) || !isset($_SESSION['captcha_num2']) || $_SERVER['REQUEST_METHOD'] === 'GET') {
        regenerateCaptcha();
    }
    $first = trim($_POST['first'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $middle = trim($_POST['middle'] ?? '');
    $last = trim($_POST['last'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $birth = trim($_POST['birth'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $gender = strtolower(trim($_POST['gender'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $street = trim($_POST['street-address'] ?? '');
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
    $message = '';
    $redirect = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['button'])) { //error handling
        $captchaValid =
        ((int)$captcha ===
        ($_SESSION['captcha_num1'] + $_SESSION['captcha_num2']));
        if (strlen($password) <= 10) {
            regenerateCaptcha();
            $message = "
                <p class='text-red-500 font-semibold text-center mb-4'>
                    Weak password. Must be longer than 10 characters.
                </p>
            ";
        } else if (!$captchaValid) {
            regenerateCaptcha();
            $message = "
                <p class='text-red-500 font-semibold text-center mb-4'>
                    Wrong answer for captcha!
                </p>
            ";
        } else if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            regenerateCaptcha();
            $message = "
                <p class='text-red-500 font-semibold text-center mb-4'>
                    A valid email is required.
                </p>
            ";
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('
                    INSERT INTO public."Users"
                        (
                            first_name,
                            middle_name,
                            last_name,
                            suffix,
                            date_birth,
                            title,
                            sex,
                            street_address,
                            city,
                            country,
                            state,
                            zip_code,
                            phone,
                            email,
                            password,
                            role
                        )
                    VALUES
                        (
                            :first_name,
                            :middle_name,
                            :last_name,
                            :suffix,
                            :date_birth,
                            :title,
                            :sex,
                            :street_address,
                            :city,
                            :country,
                            :state,
                            :zip_code,
                            :phone,
                            :email,
                            :password,
                            :role
                        )
                    RETURNING user_id
                ');
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
                $userId = $stmt->fetchColumn();
                $stmt = $pdo->prepare('
                    INSERT INTO public."User Security Questions"
                    (
                        email,
                        question1,
                        question1_answer,
                        question2,
                        question2_answer,
                        question3,
                        question3_answer
                    )
                    VALUES
                    (
                        :email,
                        :question1,
                        :question1_answer,
                        :question2,
                        :question2_answer,
                        :question3,
                        :question3_answer
                    )
                ');
                $stmt->execute([
                    ':email' => $email,
                    ':question1' => $question1,
                    ':question1_answer' => password_hash($question1_answer, PASSWORD_DEFAULT), //NEW: question hashing(courtesy of sam)
                    ':question2' => $question2,
                    ':question2_answer' => password_hash($question2_answer, PASSWORD_DEFAULT),
                    ':question3' => $question3,
                    ':question3_answer' => password_hash($question3_answer, PASSWORD_DEFAULT),
                ]);
                $pdo->commit();
                $_SESSION["email"] = $email;
                $_SESSION["user"] = $title;
                $_SESSION["name"] = $first;
                $_SESSION["user_id"] = $userId;
                $_SESSION["role"] = "Customer";
                unset($_SESSION['captcha_num1']);
                unset($_SESSION['captcha_num2']);
                header("Location: " . BASE_URI . "/pages/dashboard/customer/customer.php");
                exit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                regenerateCaptcha();
                if ($e->getCode() === '23505') {
                    $message = "
                        <p class='text-red-500 font-semibold text-center mb-4'>
                            An account with that email already exists.
                        </p>
                    ";
                } else {
                    $message = "
                        <p class='text-red-500 font-semibold text-center mb-4'>
                            Sorry, we couldn't create your account right now.
                            DB ERROR: " . htmlspecialchars($e->getMessage()) . "
                            CODE: " . htmlspecialchars($e->getCode()) . "
                        </p>
                    ";
                }
            }
        }
    }
    $num1 = $_SESSION['captcha_num1'];
    $num2 = $_SESSION['captcha_num2'];
?>
<!DOCTYPE html>
<html>
    <head>
        <title> Create Account </title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"> </script>
        <link rel="icon" href="<?= BASE_URI ?>/favicon.ico" type="image/x-icon">
    </head>
    <body class="bg-gray-900 min-h-screen text-white flex flex-col">
        <div class="w-full min-h-screen bg-gray-900">
            <?php include __DIR__ . '/../../components/nav.php'; ?>
            <main class="flex-grow flex items-center justify-center p-6">
                <div class="w-full max-w-3xl space-y-6">
                    <div class="bg-gray-800 border border-gray-700 rounded-xl p-10 text-center relative overflow-hidden">
                        <div class="relative z-10 space-y-4">
                            <p class="tracking-[0.25em] text-xs text-blue-300">
                                BDPA AIRPORTS✈️
                            </p>
                            <h1 class="text-4xl md:text-5xl font-bold leading-tight">
                                Create Account 🔐
                            </h1>
                            <p class="text-gray-300 text-sm md:text-base max-w-2xl mx-auto">
                                Create an account to manage bookings and flights.
                            </p> 
                            <div class="mt-3 inline-flex items-center px-3 py-1 rounded-full bg-gray-900 border border-red-700 text-red-300 text-xs">
                                * Required fields
                            </div>   
                        </div>
                    </div>
                <div class="bg-gray-800 border border-gray-700 rounded-xl p-8 shadow-lg">
                    <?php if ($message): ?>
                    <div class="mb-6"><?= $message ?></div>
                    <?php endif; ?>
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div class="md:col-span-2">
                            <h2 class="text-lg font-semibold text-white text-center mt-2 mb-2">Personal Information</h2>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs text-gray-400">Account Title</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($title) ?>" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">* First Name</label>
                            <input required type="text" name="first" value="<?= htmlspecialchars($first) ?>" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">Middle Name</label>
                            <input type="text" name="middle" value="<?= htmlspecialchars($middle) ?>" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">* Last Name</label>
                            <input required type="text" name="last" value="<?= htmlspecialchars($last) ?>" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">Suffix</label>
                            <input type="text" name="suffix" value="<?= htmlspecialchars($suffix) ?>" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">* Date of Birth</label>
                            <input required type="date" name="birth" value="<?= htmlspecialchars($birth) ?>" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">* Sex/Gender</label>
                            <select required name="gender" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                                <option value="" disabled <?= $gender === '' ? 'selected' : '' ?>>Select</option>
                                <option value="male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Non binary" <?= $gender === 'Non binary' ? 'selected' : '' ?>>Non Binary</option>
                                <option value="Other" <?= $gender === 'Other' ? 'selected' : '' ?>>Other</option>
                                <option value="prefer-not-to-say" <?= $gender === 'prefer-not-to-say' ? 'selected' : '' ?>>Prefer Not To Say</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs text-gray-400">* Street Address</label>
                            <input required type="text" name="street-address" value="<?= htmlspecialchars($street) ?>" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">* City</label>
                            <input required type="text" name="city" value="<?= htmlspecialchars($city) ?>" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div> <!-- Country list -->
                            <label class="text-xs text-gray-400">* Country</label>
                            <select required name="country" id="country" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                                <option value=""> Select Country </option>
                                <option value="Afghanistan"> Afghanistan </option>
                                <option value="Albania"> Albania </option>
                                <option value="Algeria"> Algeria </option>
                                <option value="Andorra"> Andorra </option>
                                <option value="Angola"> Angola </option>
                                <option value="Antigua And Barbuda"> Antigua And Barbuda </option>
                                <option value="Argentina"> Argentina </option>
                                <option value="Armenia"> Armenia </option>
                                <option value="Australia"> Australia </option>
                                <option value="Austria"> Austria </option>
                                <option value="Azerbaijan"> Azerbaijan </option>
                                <option value="Bahamas"> Bahamas </option>
                                <option value="Bahrain"> Bahrain </option>
                                <option value="Bangladesh"> Bangladesh </option>
                                <option value="Barbados">Barbados</option>
                                <option value="Belarus"> Belarus </option>
                                <option value="Belgium"> Belgium </option>
                                <option value="Belize">Belize </option>
                                <option value="Benin"> Benin</option>
                                <option value="Bhutan"> Bhutan </option>
                                <option value="Bolivia"> Bolivia </option>
                                <option value="Bosnia And Herzegovina"> Bosnia And Herzegovina </option>
                                <option value="Botswana"> Botswana </option>
                                <option value="Brazil"> Brazil </option>
                                <option value="Brunei Darussalam"> Brunei Darussalam </option>
                                <option value="Bulgaria"> Bulgaria </option>
                                <option value="Burkina Faso"> Burkina Faso </option>
                                <option value="Burundi"> Burundi </option>
                                <option value="Cabo Verde"> Cabo Verde </option>
                                <option value="Cameroon"> Cameroon </option>
                                <option value="Canada"> Canada </option>
                                <option value="Central African Republic"> Central African Republic </option>
                                <option value="Chad"> Chad </option>
                                <option value="Chile"> Chile </option>
                                <option value="China"> China </option>
                                <option value="Colombia"> Colombia </option>
                                <option value="Comoros"> Comoros </option>
                                <option value="Congo"> Congo </option>
                                <option value="Costa Rica"> Costa Rica </option>
                                <option value="Cote D'Ivoire"> Côte D'Ivoire </option>
                                <option value="Croatia"> Croatia </option>
                                <option value="Cuba"> Cuba </option>
                                <option value="Czechia"> Czechia </option>
                                <option value="North Korea"> North Korea </option>
                                <option value="Democratic Republic Of The Congo"> Democratic Republic Of The Congo </option>
                                <option value="Denmark"> Denmark </option>
                                <option value="Djibouti"> Djibouti </option>
                                <option value="Dominica"> Dominica </option>
                                <option value="Dominican Republic"> Dominican Republic </option>
                                <option value="Ecuador"> Ecuador </option>
                                <option value="Egypt"> Egypt </option>
                                <option value="El Salvador"> El Salvador </option>
                                <option value="Equatorial Guinea"> Equatorial Guinea </option>
                                <option value="Eritrea"> Eritrea </option>
                                <option value="Estonia"> Estonia </option>
                                <option value="Eswatini"> Eswatini </option>
                                <option value="Ethiopia"> Ethiopia </option>
                                <option value="Fiji"> Fiji </option>
                                <option value="Finland"> Finland </option>
                                <option value="France"> France </option>
                                <option value="Gabon"> Gabon </option>
                                <option value="Gambia"> Gambia </option>
                                <option value="Georgia"> Georgia </option>
                                <option value="Germany"> Germany </option>
                                <option value="Ghana"> Ghana </option>
                                <option value="Greece"> Greece </option>
                                <option value="Grenada"> Grenada </option>
                                <option value="Guatemala"> Guatemala </option>
                                <option value="Guinea"> Guinea </option>
                                <option value="Guinea Bissau"> Guinea Bissau </option>
                                <option value="Guyana"> Guyana </option>
                                <option value="Haiti"> Haiti </option>
                                <option value="Honduras"> Honduras </option>
                                <option value="Hungary"> Hungary </option>
                                <option value="Iceland"> Iceland </option>
                                <option value="India"> India </option>
                                <option value="Indonesia"> Indonesia </option>
                                <option value="Iran"> Iran </option>
                                <option value="Iraq"> Iraq </option>
                                <option value="Ireland"> Ireland </option>
                                <option value="Israel"> Israel </option>
                                <option value="Italy"> Italy </option>
                                <option value="Jamaica"> Jamaica </option>
                                <option value="Japan"> Japan </option>
                                <option value="Jordan"> Jordan </option>
                                <option value="Kazakhstan"> Kazakhstan </option>
                                <option value="Kenya"> Kenya </option>
                                <option value="Kiribati"> Kiribati </option>
                                <option value="Kuwait"> Kuwait </option>
                                <option value="Kyrgyzstan"> Kyrgyzstan </option>
                                <option value="Laos"> Laos </option>
                                <option value="Latvia"> Latvia </option>
                                <option value="Lebanon"> Lebanon </option>
                                <option value="Lesotho"> Lesotho </option>
                                <option value="Liberia"> Liberia </option>
                                <option value="Libya"> Libya </option>
                                <option value="Liechtenstein"> Liechtenstein </option>
                                <option value="Lithuania"> Lithuania </option>
                                <option value="Luxembourg"> Luxembourg </option>
                                <option value="Madagascar"> Madagascar </option>
                                <option value="Malawi"> Malawi </option>
                                <option value="Malaysia"> Malaysia </option>
                                <option value="Maldives"> Maldives </option>
                                <option value="Mali"> Mali </option>
                                <option value="Malta"> Malta </option>
                                <option value="Marshall Islands"> Marshall Islands </option>
                                <option value="Mauritania"> Mauritania </option>
                                <option value="Mauritius"> Mauritius </option>
                                <option value="Mexico"> Mexico </option>
                                <option value="Micronesia"> Micronesia </option>
                                <option value="Monaco"> Monaco </option>
                                <option value="Mongolia"> Mongolia </option>
                                <option value="Montenegro"> Montenegro </option>
                                <option value="Morocco"> Morocco </option>
                                <option value="Mozambique"> Mozambique </option>
                                <option value="Myanmar"> Myanmar </option>
                                <option value="Namibia"> Namibia </option>
                                <option value="Nauru"> Nauru </option>
                                <option value="Nepal"> Nepal </option>
                                <option value="Netherlands"> Netherlands </option>
                                <option value="New Zealand"> New Zealand </option>
                                <option value="Nicaragua"> Nicaragua </option>
                                <option value="Niger"> Niger </option>
                                <option value="Nigeria"> Nigeria </option>
                                <option value="North Macedonia"> North Macedonia </option>
                                <option value="Norway"> Norway </option>
                                <option value="Oman"> Oman </option>
                                <option value="Pakistan"> Pakistan </option>
                                <option value="Palau"> Palau </option>
                                <option value="Panama"> Panama </option>
                                <option value="Papua New Guinea"> Papua New Guinea </option>
                                <option value="Paraguay"> Paraguay </option>
                                <option value="Peru"> Peru </option>
                                <option value="Philippines"> Philippines </option>
                                <option value="Poland"> Poland </option>
                                <option value="Portugal"> Portugal </option>
                                <option value="Qatar"> Qatar </option>
                                <option value="South Korea"> South Korea </option>
                                <option value="Moldova"> Moldova </option>
                                <option value="Romania"> Romania </option>
                                <option value="Russia"> Russia </option>
                                <option value="Rwanda"> Rwanda </option>
                                <option value="Saint Kitts And Nevis"> Saint Kitts And Nevis </option>
                                <option value="Saint Lucia"> Saint Lucia </option>
                                <option value="Saint Vincent And The Grenadines"> Saint Vincent And The Grenadines </option>
                                <option value="Samoa"> Samoa </option>
                                <option value="San Marino"> San Marino </option>
                                <option value="Sao Tome And Principe"> Sao Tome And Principe </option>
                                <option value="Saudi Arabia"> Saudi Arabia </option>
                                <option value="Senegal"> Senegal </option>
                                <option value="Serbia"> Serbia </option>
                                <option value="Seychelles"> Seychelles </option>
                                <option value="Sierra Leone"> Sierra Leone </option>
                                <option value="Singapore"> Singapore </option>
                                <option value="Slovakia"> Slovakia </option>
                                <option value="Slovenia"> Slovenia </option>
                                <option value="Solomon Islands"> Solomon Islands </option>
                                <option value="Somalia"> Somalia </option>
                                <option value="South Africa"> South Africa </option>
                                <option value="South Sudan"> South Sudan </option>
                                <option value="Spain"> Spain</option>
                                <option value="Sri Lanka"> Sri Lanka </option>
                                <option value="Sudan"> Sudan </option>
                                <option value="Suriname"> Suriname </option>
                                <option value="Sweden"> Sweden </option>
                                <option value="Switzerland"> Switzerland </option>
                                <option value="Syria"> Syria </option>
                                <option value="Tajikistan"> Tajikistan </option>
                                <option value="Tanzania"> Tanzania </option>
                                <option value="Thailand"> Thailand </option>
                                <option value="Timor Leste"> Timor Leste </option>
                                <option value="Togo"> Togo </option>
                                <option value="Tonga"> Tonga </option>
                                <option value="Trinidad And Tobago"> Trinidad And Tobago </option>
                                <option value="Tunisia"> Tunisia </option>
                                <option value="Turkey"> Turkey </option>
                                <option value="Turkmenistan"> Turkmenistan </option>
                                <option value="Tuvalu"> Tuvaluv </option>
                                <option value="Uganda"> Uganda </option>
                                <option value="Ukraine"> Ukraine </option>
                                <option value="United Arab Emirates"> United Arab Emirates </option>
                                <option value="United Kingdom"> United Kingdom </option>
                                <option value="United States"> United States </option>
                                <option value="Uruguay"> Uruguay </option>
                                <option value="Uzbekistan"> Uzbekistan </option>
                                <option value="Vanuatu"> Vanuatu </option>
                                <option value="Venezuela"> Venezuela </option>
                                <option value="Vietnam"> Vietnam </option>
                                <option value="Yemen"> Yemen </option>
                                <option value="Zambia"> Zambia </option>
                                <option value="Zimbabwe">Zimbabwe </option>
                            </select>
                        </div>
                        <div><!-- State list -->
                            <label class="text-xs text-gray-400">* State</label>
                            <select required name="state" id="state" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                                <option value=""> Select State </option>
                                <option value="AL"> Alabama </option>
                                <option value="AK"> Alaska </option>
                                <option value="AZ"> Arizona </option>
                                <option value="AR"> Arkansas </option>
                                <option value="CA"> California </option>
                                <option value="CO"> Colorado </option>
                                <option value="CT"> Connecticut </option>
                                <option value="DE"> Delaware </option>
                                <option value="DC"> District of Columbia </option>
                                <option value="FL"> Florida </option>
                                <option value="GA"> Georgia </option>
                                <option value="HI"> Hawaii </option>
                                <option value="ID"> Idaho </option>
                                <option value="IL"> Illinois </option>
                                <option value="IN"> Indiana </option>
                                <option value="IA"> Iowa </option>
                                <option value="KS"> Kansas </option>
                                <option value="KY"> Kentucky </option>
                                <option value="LA"> Louisiana </option>
                                <option value="ME"> Maine </option>
                                <option value="MD"> Maryland </option>
                                <option value="MA"> Massachusetts </option>
                                <option value="MI"> Michigan </option>
                                <option value="MN"> Minnesota </option>
                                <option value="MS"> Mississippi </option>
                                <option value="MO"> Missouri </option>
                                <option value="MT"> Montana </option>
                                <option value="NE"> Nebraska </option>
                                <option value="NV"> Nevada </option>
                                <option value="NH"> New Hampshire </option>
                                <option value="NJ"> New Jersey </option>
                                <option value="NM"> New Mexico </option>
                                <option value="NY"> New York </option>
                                <option value="NC"> North Carolina </option>
                                <option value="ND"> North Dakota </option>
                                <option value="OH"> Ohio </option>
                                <option value="OK"> Oklahoma </option>
                                <option value="OR"> Oregon </option>
                                <option value="PA"> Pennsylvania </option>
                                <option value="RI"> Rhode Island </option>
                                <option value="SC"> South Carolina </option>
                                <option value="SD"> South Dakota </option>
                                <option value="TN"> Tennessee </option>
                                <option value="TX"> Texas </option>
                                <option value="UT"> Utah </option>
                                <option value="VT"> Vermont </option>
                                <option value="VA"> Virginia </option>
                                <option value="WA"> Washington </option>
                                <option value="WV"> West Virginia </option>
                                <option value="WI"> Wisconsin </option>
                                <option value="WY"> Wyoming </option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">* ZIP</label>
                            <input required type="number" name="zip" id="zip" value="<?= htmlspecialchars($zip) ?>" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">Phone Number</label>
                            <input type="tel" name="phone" placeholder="" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition" inputmode="numeric" oninput="autoFormatPhone(this)">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">* Email</label>
                            <input required type="email" name="email" value="<?= htmlspecialchars($email) ?>" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs text-gray-400">* Password</label>
                            <input id="password" required type="password" name="password" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                            <p id="password-strength" class="text-xs text-gray-400 mt-2">
                                Password must be more than 10 characters.
                            </p>
                        </div>
                        <div class="md:col-span-2">
                            <h2 class="text-lg font-semibold text-white text-center mt-2 mb-2">
                                Security Questions Used for Account Recovery
                            </h2>
                            <p class="text-sm text-gray-400 text-center">
                                These questions are used to recover your account if you forget your password.
                            </p>
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">* Question 1</label>
                            <input required type="text" name="question1" value="<?= htmlspecialchars($question1) ?>" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">* Answer 1</label>
                            <input required type="text" name="answer1" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">* Question 2</label>
                            <input required type="text" name="question2" value="<?= htmlspecialchars($question2) ?>" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">* Answer 2</label>
                            <input required type="text" name="answer2" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">* Question 3</label>
                            <input required type="text" name="question3" value="<?= htmlspecialchars($question3) ?>" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div>
                            <label class="text-xs text-gray-400">* Answer 3</label>
                            <input required type="text" name="answer3" class="w-full mt-2 h-12 bg-gray-900 border border-gray-700 rounded-lg px-4 text-sm text-white placeholder-gray-500 shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition">
                        </div>
                        <div class="md:col-span-2 text-center bg-gray-900 border border-gray-700 rounded-lg p-4">
                            <label class="text-xs text-gray-400">* CAPTCHA</label>
                            <p class="font-medium text-white mt-1">
                                What is <?= htmlspecialchars($num1); ?> + <?= htmlspecialchars($num2); ?> ?
                            </p>
                            <input type="text" name="captcha" value="<?= htmlspecialchars($captcha); ?>" class="mt-2 w-32 h-10 bg-gray-800 border border-gray-600 rounded-lg text-center shadow-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition" required>
                            <input type="hidden" name="num1" value="<?= htmlspecialchars($num1); ?>">
                            <input type="hidden" name="num2" value="<?= htmlspecialchars($num2); ?>">
                        </div>
                        <div class="md:col-span-2 text-center">
                            <input class="bg-blue-600 w-full text-white px-6 py-3 rounded-lg transition hover:bg-blue-700 hover:shadow-md active:scale-95" type="submit" name="button" value="Create Account">
                        </div>
                    </form>
                    <div class="flex items-center my-4">
                        <div class="flex-1 h-px bg-gray-700"></div>
                    </div>
                    <div class="text-center text-xs text-gray-500">
                        Already have an account?
                        <a href="<?= BASE_URI ?>/pages/auth/login.php" class="text-blue-400 hover:text-blue-300 ml-1">
                            Sign in
                        </a>
                    </div>
                </div>
            </div>
        </main>
        <script>
            function autoFormatPhone(el) { //phone autoformater(taken from dashboard pages)
                let d = el.value.replace(/\D/g, '');
                if (d.length > 11) d = d.slice(0, 11);
                if (d.length === 0) { el.value = ''; return; }
                if (d.length <= 3)       { el.value = '(' + d; return; }
                if (d.length <= 6)       { el.value = '(' + d.slice(0,3) + ') ' + d.slice(3); return; }
                if (d.length <= 10)      { el.value = '(' + d.slice(0,3) + ') ' + d.slice(3,6) + '-' + d.slice(6); return; }
                el.value = '+' + d[0] + ' (' + d.slice(1,4) + ') ' + d.slice(4,7) + '-' + d.slice(7);
            }
            document.addEventListener('DOMContentLoaded', function () { //password strength checker
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
        </script>
    </body>
</html>

