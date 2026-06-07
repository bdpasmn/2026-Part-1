<html> <!-- Fix responsivity -->
    <head>
        <title> Login </title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"> </script>
    </head>
    <body class="flex flex-col items-center justify-center min-h-screen m-0 bg-gray-900">
        <h1 class="text-white font-bold text-xl"> BDPA Airports - TO BE REPLACED WITH NAV </h1>
        <div class="bg-gray-800 shadow-xl rounded-xl p-4 w-xs md:w-full max-w-2xl">
            <div class="bg-gradient-to-r from-slate-800 to-slate-900 border border-gray-700 rounded-xl p-10 shadow-lg">
                <h1 class="text-center mb-2 text-white font-bold text-xl"> BDPA Airlines </h1>
                <h2 class="text-center mb-6 text-blue-300 text-lg"> Please Create An Account </h2>
            </div>
            <br>
            <h3 class="text-center mb-3 text-red-100"> An Asterisk Denotes Required Fields </h3>
            <form method="POST" class="flex items-center justify-center flex-col">
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="first" class="font-medium text-white"> Account Title: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="title">
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="first" class="font-medium text-white"> *First Name: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="first" required>
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="middle" class="font-medium text-white"> Middle Name: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="middle">
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="last" class="font-medium text-white"> *Last Name: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="last" required>
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="suffix" class="font-medium text-white"> Suffix: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="suffix">
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="birth" class="font-medium text-white"> *Date of Birth: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="date" name="birth" required>
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="sex" class="font-medium text-white"> *Sex: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="radio" name="sex" id="male" value="male" required>
                    <label for="male" class="font-medium text-white"> Male </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="radio" name="sex" id="female" value="female" required>
                    <label for="female" class="font-medium text-white"> Female </label>
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="street" class="font-medium text-white"> *Street Address: </label> <!-- Have this open a seperate menu -->
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="street" required>
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="city" class="font-medium text-white"> *City: </label> 
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="city" required> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
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
                        <option value="côte d'ivoire"> Côte D'Ivoire </option>
                        <option value="croatia"> Croatia </option>
                        <option value="cuba"> Cuba </option>
                        <option value="czechia"> Czechia </option>
                        <option value="dcemocratic people's republic of korea"> Democratic People's Republic of Korea </option>
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
                        <option value="slovakia"> Slovakia </option>
                        <option value="slovenia"> Slovenia </option>
                        <option value="solomon islands"> Solomon Islands </option>
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
                        <option value="united states of america"> United States of America </option>
                        <option value="uruguay"> Uruguay </option>
                        <option value="uzbekistan"> Uzbekistan </option>
                        <option value="vanuatu"> Vanuatu </option>
                        <option value="venezuela"> Venezuela </option>
                        <option value="viet nam"> Viet Nam </option>
                        <option value="yemen"> Yemen </option>
                        <option value="zambia"> Zambia </option>
                        <option value="zimbabwe"> Zimbabwe </option>
                    </select>
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="state" class="font-medium text-white"> State: </label> <!-- Make it only appear if US has been selected -->
                    <select class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95t" type="text" name="state" id="state">
                        <option value="alabama"> Alabama </option>
                        <option value="alaska"> Alaska </option>
                        <option value="arizona"> Arizona </option>
                        <option value="arkansas"> Arkansas </option>
                        <option value="california"> California </option>
                        <option vakue="colorado"> Colorado </option>
                        <option value="connecticut"> Connecticut </option>
                        <option value="delaware"> Delaware </option>
                        <option value="district of columbia"> District of Columbia </option>
                        <option value="florida"> Florida </option>
                        <option value="georgia"> Georgia </option>
                        <option value="gawaii"> Hawaii </option>
                        <option value="idaho"> Idaho </option>
                        <option value="illinois"> Illinois </option>
                        <option value="indiana"> Indiana </option>
                        <option value="iowa"> Iowa </option>
                        <option value="kansas"> Kansas </option>
                        <option value="kentucky"> Kentucky </option>
                        <option value="louisiana"> Louisiana </option>
                        <option value="maine"> Maine </option>
                        <option value="maryland"> Maryland </option>
                        <option value="massachusetts"> Massachusetts </option>
                        <option value="michigan"> Michigan </option>
                        <option value="minnesota"> Minnesota </option>
                        <option value="mississippi"> Mississippi </option>
                        <option value="missouri"> Missouri </option>
                        <option value="montana"> Montana </option>
                        <option value="mebraska"> Nebraska </option>
                        <option value="mevada"> Nevada </option>
                        <option value="new hampshire"> New Hampshire </option>
                        <option value="new jersey"> New Jersey </option>
                        <option value="new mexico"> New Mexico </option>
                        <option value="new york"> New York </option>
                        <option value="north carolina"> North Carolina </option>
                        <option value="north dakota"> North Dakota </option>
                        <option value="ohio"> Ohio </option>
                        <option value="oklahoma"> Oklahoma </option>
                        <option value="oregon"> Oregon </option>
                        <option value="pennsylvania"> Pennsylvania </option>
                        <option value="rhode island"> Rhode Island </option>
                        <option value="south carolina"> South Carolina </option>
                        <option value="south dakota"> South Dakota </option>
                        <option value="tennessee"> Tennessee </option>
                        <option value="texas"> Texas </option>
                        <option value="utah"> Utah </option>
                        <option value="vermont"> Vermont </option>
                        <option value="virginia"> Virginia </option>
                        <option value="washington"> Washington </option>
                        <option value="west virginia"> West Virginia </option>
                        <option value="wisconsin"> Wisconsin </option>
                        <option value="wyoming"> Wyoming </option>
                    </select>
                </div> 
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="zip" class="font-medium text-white"> Zip Code: </label> <!--Should only appear if US has been selected-->
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="number" name="zip" maxlength="10"> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label class=" font-medium text-white"> Phone: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="tel" name="phone" maxlength="15"> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="email" class="font-medium text-white"> *Email: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="email" name="email" required> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="password" class="font-medium text-white"> *Password: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="password" name="password" minlength="10" required> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="question1" class="font-medium text-white"> *Custom Security Question: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="question1" required> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="answer1" class="font-medium text-white"> *Answer: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="answer1" required> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="question2" class="font-medium text-white"> *Custom Security Question: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="question2" required> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="answer2" class="font-medium text-white"> *Answer: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="answer2" required> 
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="question3" class="font-medium text-white"> *Custom Security Question: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="question3" required>
                </div>
                <div class="md:flex md:flex-row md:gap-5 md:m-5 bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="answer3" class="font-medium text-white"> *Answer: </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent hover:shadow-md active:scale-95" type="text" name="answer3" required> 
                </div>
                <br>
                <div class="text-center bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                    <label for="captcha" class="font-medium text-white"> 
                        <b> What is <?php echo "$num1 + $num2"; ?> ? </b> 
                    </label>
                    <input class="border-2 border-white p-1 rounded-md text-white bg-transparent" type="number" name="captcha" required>
                </div>
                <br>
                <input class="bg-blue-600 text-white px-6 py-2 rounded transition duration-200 hover:bg-blue-700 hover:shadow-md active:scale-95" type="submit" name="button" value="Create Account">
            </form>
        </div>
    </body>
</html>
<?php
    $num1=rand(1,10);
    $num2=rand(1,10);
    //Fix captcha
    if (strlen($password) <= 10) {
        //finish
    } else if (strlen($password) >= 17) {
        //finish
    }
    if ($captcha != ($num1 + $num2)) { //Rework alert thing
        //finish
    } else {
        $title=$_POST["title"];
        $first=$_POST["first"];
        $middle=$_POST["middle"];
        $last=$_POST["last"];
        $suffix=$_POST["suffix"];
        $birth=$_POST["birth"];
        $sex=$_POST["sex"];
        $street=$_POST["street"];
        $city=$_POST["city"];
        $country=$_POST["country"];
        $state=$_POST["state"];
        $zip=$_POST["zip"];
        $phone=$_POST["phone"];
        $email=$_POST["email"];
        $password=$_POST["password"];
        $question1=$_POST["question1"];
        $answer1=$_POST["answer1"];
        $question2=$_POST["question2"];
        $answer2=$_POST["answer2"];
        $question3=$_POST["question3"];
        $answer3=$_POST["answer3"];
        $captcha=$_POST["captcha"];
        //Do API and database stuff here, don't froget to give message, redirect, and start a session.
    }


