<?php

/*
INSERT INTO `users` (`id`, `email`, `password`, `createdAt`) VALUES (NULL, 'peldaEmail', 'peldaJelszo', '123');
*/
// útvonalválasztó
// https://kodbazis.hu/php-az-alapoktol/termek-listazo-website

$method = $_SERVER["REQUEST_METHOD"];
$parsed = parse_url($_SERVER['REQUEST_URI']);
$path = $parsed['path'];

$routes = [
    "GET" => [
        "/Bringatura_MKK/" => "homeHandler",
        "/Bringatura_MKK/varosok-megtekintese" => "cityListHandler",  //countryListHandler
        "/Bringatura_MKK/utvonal-valaszto" => "routeSelectHandler" //routeListHandler
    ],
    "POST" => [
        '/Bringatura_MKK/utvonal' => 'routeListHandler',
        '/Bringatura_MKK/utvonal-km' => 'routeListKmHandler',
        '/Bringatura_MKK/register' => 'registrationHandler',
        '/Bringatura_MKK/login' => 'loginHandler',
        '/Bringatura_MKK/logout' => 'logoutHandler'
    ],
];

$handlerFunction = $routes[$method][$path] ?? "notFoundHandler"; 

$handlerFunction();

function getPathWithId($url)
{ 
    $parsed = parse_url($url);
    if(!isset($parsed['query'])) {
        return $url;
    }

    /*echo "<pre>";
    var_dump($parsed);*/

    $queryParams = []; 
    parse_str($parsed['query'], $queryParams);
    //var_dump($queryParams);
    //var_dump($parsed['path'] . "?id=" . $queryParams['id']);
    return $parsed['path'] . "?id=" . $queryParams['id'];
}

//kijelentkezés
function logoutHandler()
{
    /*var_dump(getPathWithId($_SERVER['HTTP_REFERER']));
    exit;*/
    
    session_start();  //session-t indítunk
    $params = session_get_cookie_params();  //kiszedjük a cookie paramétereket
    //beállítjuk a böngészőből törölni kívánt cookie paramétereit: az első a neve, 2. tartalma (üres string), 
    //3. az ideje, a többi a kiszedett paraméterek:
    setcookie(session_name(),  '', 0, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
    //miután töröltük a böngészőből, szerver oldalon is töröljük a munkamenetet:
    session_destroy();// Ne ragadjonak be az adatok!!!!
    header('Location: '.getPathWithId($_SERVER['HTTP_REFERER']));

}

function loginHandler()
{
    $pdo = getConnection();
    $statement = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $statement->execute([$_POST["email"]]);

    $user = $statement->fetch(PDO::FETCH_ASSOC);

    if(!$user) {
        header('Location: ' . getPathWithId($_SERVER['HTTP_REFERER']) . '&info=invalidCredentials');
        return;
    }

    $isVerified = password_verify($_POST['password'], $user["password"]);
    
    if(!$isVerified) {
        header('Location: ' . getPathWithId($_SERVER['HTTP_REFERER']) . '&info=invalidCredentials');
        return;
    }
    
    session_start();
    $_SESSION['userId'] = $user['id']; 
    header('Location: '. getPathWithId($_SERVER['HTTP_REFERER'])); 
    
}

function emailCheck($email): bool
{
    $pdo = getConnection();
    $statement = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $statement->execute([$email]);

    $user = $statement->fetch(PDO::FETCH_ASSOC);

    if($user) {
        return false;
    }

    return true;
}

function registrationHandler()
{
    

    if(isset($_POST["email"]) && !empty($_POST["email"]) && 
        isset($_POST["password"]) && !empty($_POST["password"]))
    {
        
        $email = $_POST["email"];

        if(!emailCheck($email))
        {
            header('Location: ' . getPathWithId($_SERVER['HTTP_REFERER']) . '&info=existingEmail');
            return;
        }

        $pdo = getConnection();
        $statment = $pdo->prepare(
            "INSERT INTO `users` (`email`, `password`, `createdAt`)  
            VALUES (?, ?, ?);"
        );
        $statment->execute([
            $_POST["email"],
            password_hash($_POST["password"], PASSWORD_DEFAULT),
            time()
        ]);
        unset($_POST);
        header('Location: ' . getPathWithId($_SERVER['HTTP_REFERER']) . '&info=registrationSuccessful');
    }

    else header('Location: ' . getPathWithId($_SERVER['HTTP_REFERER']) . '&info=noData');
}

function isLoggedIn(): bool
{
    if (!isset($_COOKIE[session_name()])) { 
        return false; 
    }

    session_start();

    if (!isset($_SESSION['userId'])) { 
        return false; 
    }

    return true;
}

function singleCityHandler()
{
    if(!isLoggedIn()) {
        echo compileTemplate("wrapper.phtml", [
            'content' => compileTemplate('subscriptionForm.phtml', [
                'info' => $_GET['info'] ?? '',
                'isRegistration' => isset($_GET['isRegistration']),
                'url' => getPathWithId($_SERVER['REQUEST_URI']),
            ]),
            'isAuthorized' => false, //// nincs bejelentkezve -->ezt küldjük a wrapperbe
        ]);
        return;
    }

    $cityId = $_GET['id'] ?? '';
    $pdo = getConnection();
    $statement = $pdo->prepare("SELECT * FROM cities WHERE id = ?");
    $statement->execute([$cityId]);
    $city = $statement->fetch(PDO::FETCH_ASSOC);
    
    //var_dump($city["countryId"]);
    $countryId = $city["countryId"];
    $statement = $pdo->prepare("SELECT * FROM countries WHERE id = ?");
    $statement->execute([$countryId]);
    $country = $statement->fetch(PDO::FETCH_ASSOC);

    echo compileTemplate('wrapper.phtml', [
        'content' => compileTemplate('citySingle.phtml', [
            'city' => $city,
            'country' => $country
        ]),
        'isAuthorized' => true, //be van jelentkezve -->ezt küldjük a wrapperbe
    ]);
}


function singleCountryHandler()
{
    
    /*Ezt kiszervezzük az isLoggedIn függvénybe!!!! Early Return technika
    //Megvizsgáljuk, hogy be van-e jelentkezve, csak utána adjuk a védett tartalmat
    if(isset($_COOKIE[session_name()])) {
        session_start();

        if(isset($_SESSION['userId'])) {
            // védett tartalom...
        } 

    }*/
    if(!isLoggedIn()) {
        echo compileTemplate("wrapper.phtml", [
            'content' => compileTemplate('subscriptionForm.phtml', [
                'info' => $_GET['info'] ?? '',
                'isRegistration' => isset($_GET['isRegistration']),
                'url' => getPathWithId($_SERVER['REQUEST_URI']),
            ]),
            'isAuthorized' => false, //// nincs bejelentkezve -->ezt küldjük a wrapperbe
        ]);
        return;
    }
    
    $countryId = $_GET['id'] ?? '';
    $pdo = getConnection();
    $statement = $pdo->prepare("SELECT * FROM countries WHERE id = ?");
    $statement->execute([$countryId]);
    $country = $statement->fetch(PDO::FETCH_ASSOC);

    $statement = $pdo->prepare("SELECT * FROM cities WHERE countryId = ?");
    $statement ->execute([$countryId]);
    $cities = $statement ->fetchAll(PDO::FETCH_ASSOC);

    $statement = $pdo->prepare("SELECT * FROM `countryLanguages`
                            Join languages on languageId = languages.id
                            where countryId = ?");
    $statement ->execute([$countryId]);
    $languages = $statement ->fetchAll(PDO::FETCH_ASSOC);

    echo compileTemplate('wrapper.phtml', [
        'content' => compileTemplate('countrySingle.phtml', [
            'country' => $country,
            'cities' => $cities,
            'languages' => $languages,
        ]),
        'isAuthorized' => true, //be van jelentkezve -->ezt küldjük a wrapperbe
    ]);
}

function routeListKmHandler()
{
    $start = $_POST["startId"];
    $touch1 = $_POST["touchId1"];
    $touch2 = $_POST["touchId2"];
    $km = $_POST["km"];
    var_dump($start, $touch1, $touch2, $km);
    $pdo = getConnection();

    $statement = $pdo->prepare('SELECT * FROM fooldal');
    $statement->execute();
    $osszesTelepules = $statement->fetchAll(PDO::FETCH_ASSOC);
    //echo"<pre>";
    //var_dump($telepulesek);
    // 8 * 10 * 12
    if ($start != 1 && $start < $touch1 && $touch1 < $touch2)
    {
        $statement1 = $pdo->prepare('SELECT * FROM fooldal WHERE id BETWEEN ? AND 148');
        $statement1->execute([$start]);
        $telepulesek1 = $statement1->fetchAll(PDO::FETCH_ASSOC);

        $statement2 = $pdo->prepare('SELECT * FROM fooldal WHERE id BETWEEN 2 AND ? ');
        $statement2->execute([$start]);
        $telepulesek2 = $statement2->fetchAll(PDO::FETCH_ASSOC);

        $telepulesek = array_merge($telepulesek1, $telepulesek2);
        //Adatok csúsztatása
        $telepulesek[0]['tav'] = 0;
        $telepulesek[0]['utszam'] = '----';
        $telepulesek[0]['fel'] = 0;
        $telepulesek[0]['le'] = 0;
    }
    // 142 * 146 * 5 
    elseif ($start != 1 && $start < $touch1 && $touch1 > $touch2 && $touch2 < $touch1 && $start > $touch2)
    {
        $statement1 = $pdo->prepare('SELECT * FROM fooldal WHERE id BETWEEN ? AND 148');
        $statement1->execute([$start]);
        $telepulesek1 = $statement1->fetchAll(PDO::FETCH_ASSOC);

        $statement2 = $pdo->prepare('SELECT * FROM fooldal WHERE id BETWEEN 2 AND ? ');
        $statement2->execute([$start]);
        $telepulesek2 = $statement2->fetchAll(PDO::FETCH_ASSOC);

        $telepulesek = array_merge($telepulesek1, $telepulesek2);
        //Adatok csúsztatása
        $telepulesek[0]['tav'] = 0;
        $telepulesek[0]['utszam'] = '----';
        $telepulesek[0]['fel'] = 0;
        $telepulesek[0]['le'] = 0;
        //$telepulesek = dataSlider($telepulesek3);
    }
    // 112 * 80 * 70   140 * 120 * 80
    elseif($start > $touch1 && $touch1 > $touch2 || $start == 1 && $touch1 > $touch2 && $touch2 != $start)
    {
        $statement1 = $pdo->prepare('SELECT * FROM fooldal WHERE id BETWEEN 2 AND ? ORDER BY id DESC');
        $statement1->execute([$start]);
        $telepulesek1 = $statement1->fetchAll(PDO::FETCH_ASSOC);
        
        $statement2 = $pdo->prepare('SELECT * FROM fooldal WHERE id BETWEEN ? AND 148 ORDER BY id DESC');
        $statement2->execute([$start]);
        $telepulesek2 = $statement2->fetchAll(PDO::FETCH_ASSOC);

        $telepulesek3 = array_merge($telepulesek1, $telepulesek2);
        //Adatok csúsztatása
        $telepulesek = dataSlider($telepulesek3);
        $telepulesek = dataChange($telepulesek);  // a 'fel' - 'le' adatok cseréje az irányváltás miatt
    }
    // 12 * 5 * 140    5 * 140 * 82
    elseif($start > $touch1 && $touch1 < $touch2 || $start != 1 && $start < $touch1 && $touch2 < $touch1)
    {
        $statement1 = $pdo->prepare('SELECT * FROM fooldal WHERE id BETWEEN 2 AND ? ORDER BY id DESC');
        $statement1->execute([$start]);
        $telepulesek1 = $statement1->fetchAll(PDO::FETCH_ASSOC);

        $statement2 = $pdo->prepare('SELECT * FROM fooldal WHERE id BETWEEN ? AND 148 ORDER BY id DESC');
        $statement2->execute([$start]);
        $telepulesek2 = $statement2->fetchAll(PDO::FETCH_ASSOC);

        $telepulesek3 = array_merge($telepulesek1, $telepulesek2);

        $telepulesek = dataSlider($telepulesek3);
        $telepulesek = dataChange($telepulesek);  // a 'fel' - 'le' adatok cseréje az irányváltás miatt
    }
    // $start == 1 tehát szegedi indulás kelet felé
    else{
        $telepulesek = $osszesTelepules;
    }

    echo compileTemplate('wrapper.phtml', [
        'content' => compileTemplate('routeListKm.phtml',[
            'osszesTelepules' => $osszesTelepules,
            'telepulesek' => $telepulesek,
            'km' => $km,
            'start' => $start
        ])
        //'isAuthorized' => isLoggedIn() //megvizsgáljuk, hogy be van-e jelentkezve -->ezt küldjük a wrapperbe
    ]);
}

function dataSlider($telepulesek)
{
    $hossz = count($telepulesek);
        for ($i = $hossz-1; $i > -1 ; $i--)
        {
            if ($i == 0)
            {
                $telepulesek[$i]['tav'] = 0;
                $telepulesek[$i]['utszam'] = '----';
                $telepulesek[$i]['fel'] = 0;
                $telepulesek[$i]['le'] = 0;
            }
            else
            {
                $telepulesek[$i]['utszam'] = $telepulesek[$i-1]['utszam'];
                $telepulesek[$i]['tav'] = $telepulesek[$i-1]['tav'];
                $telepulesek[$i]['fel'] = $telepulesek[$i-1]['fel'];
                $telepulesek[$i]['le'] = $telepulesek[$i-1]['le'];
            }
        }
    return $telepulesek;
}

function dataChange($telepulesek) //ellentétes irányban a 'fel' - 'le' értékeket fel kell cserélni
{
    $hossz = count($telepulesek);
        for ($i = 1; $i < $hossz ; $i++)
        {
            $c = $telepulesek[$i]['fel'];
            $telepulesek[$i]['fel'] = $telepulesek[$i]['le'];
            $telepulesek[$i]['le'] = $c;
        }
    return $telepulesek;
}

function routeListHandler()
{
    var_dump($_POST["start"]);
    echo("   *****   ");
    var_dump($_POST["touching"]);
    echo("   *****   ");
    var_dump($_POST["end"]);
    $start = $_POST["start"];
    $touching = $_POST["touching"];
    $end = $_POST["end"];

    $pdo = getConnection();

    $statement = $pdo->prepare('SELECT * FROM fooldal');
    $statement->execute();
    $osszesTelepules = $statement->fetchAll(PDO::FETCH_ASSOC);
    // 8 * 10 * 12
    if ($start < $touching && $touching < $end)
    {
        $statement = $pdo->prepare('SELECT * FROM fooldal WHERE id BETWEEN ? AND ?');
        $statement->execute([$start, $end]);
        $telepulesek = $statement->fetchAll(PDO::FETCH_ASSOC);
        //Adatok csúsztatása
        $telepulesek[0]['tav'] = 0;
        $telepulesek[0]['utszam'] = '----';
        $telepulesek[0]['fel'] = 0;
        $telepulesek[0]['le'] = 0;
    }
    // 142 * 146 * 5 
    elseif ($start < $touching && $touching > $end && $start > $end)
    {
        $statement1 = $pdo->prepare('SELECT * FROM fooldal WHERE id BETWEEN 2 AND ? ');
        $statement1->execute([$end]);
        $telepulesek1 = $statement1->fetchAll(PDO::FETCH_ASSOC);

        $statement2 = $pdo->prepare('SELECT * FROM fooldal WHERE id BETWEEN ? AND 148');
        $statement2->execute([$start]);
        $telepulesek2 = $statement2->fetchAll(PDO::FETCH_ASSOC);

        $telepulesek = array_merge($telepulesek2, $telepulesek1);
        //Adatok csúsztatása
        $telepulesek[0]['tav'] = 0;
        $telepulesek[0]['utszam'] = '----';
        $telepulesek[0]['fel'] = 0;
        $telepulesek[0]['le'] = 0;
        //$telepulesek = dataSlider($telepulesek3);
    }
    // 112 * 80 * 70
    elseif($start > $touching && $touching > $end)
    {
        $statement = $pdo->prepare('SELECT * FROM fooldal WHERE id BETWEEN ? AND ? ORDER BY id DESC');
        $statement->execute([$end, $start]);
        $telepulesek4 = $statement->fetchAll(PDO::FETCH_ASSOC);
        
        $telepulesek = dataSlider($telepulesek4); //Adatok csúsztatása
        $telepulesek = dataChange($telepulesek);  // a 'fel' - 'le' adatok cseréje az irányváltás miatt 
    }
    // 12 * 5 * 140
    else
    {
        $statement1 = $pdo->prepare('SELECT * FROM fooldal WHERE id BETWEEN 2 AND ? ORDER BY id DESC');
        $statement1->execute([$start]);
        $telepulesek1 = $statement1->fetchAll(PDO::FETCH_ASSOC);

        $statement2 = $pdo->prepare('SELECT * FROM fooldal WHERE id BETWEEN ? AND 148 ORDER BY id DESC');
        $statement2->execute([$end]);
        $telepulesek2 = $statement2->fetchAll(PDO::FETCH_ASSOC);

        $telepulesek3 = array_merge($telepulesek1, $telepulesek2);  // a két tömb összeillesztése
        $telepulesek = dataSlider($telepulesek3);  //Adatok csúsztatása
        $telepulesek = dataChange($telepulesek);  // a 'fel' - 'le' adatok cseréje az irányváltás miatt
    }
    
    //echo"<pre>";
    //var_dump($telepulesek);
    echo compileTemplate('wrapper.phtml', [
        'content' => compileTemplate('routeList.phtml',[
            'telepulesek' => $telepulesek,
            'osszesTelepules' => $osszesTelepules,
        ])
        //'isAuthorized' => isLoggedIn() //megvizsgáljuk, hogy be van-e jelentkezve -->ezt küldjük a wrapperbe
    ]);
}

function routeSelectHandler()
{
    $pdo = getConnection();

    $statement = $pdo->prepare('SELECT * FROM fooldal');
    $statement->execute();
    $telepulesek = $statement->fetchAll(PDO::FETCH_ASSOC);
    
    /*echo"<pre>";
    var_dump($telepulesek);*/
    echo compileTemplate('wrapper.phtml', [
        'content' => compileTemplate('routeSelect.phtml',[
            'telepulesek' => $telepulesek,
        ])
        //'isAuthorized' => isLoggedIn() //megvizsgáljuk, hogy be van-e jelentkezve -->ezt küldjük a wrapperbe
    ]);
}

function cityListHandler()
{
    $pdo = getConnection();

    $statement = $pdo->prepare('SELECT * FROM fooldal');
    $statement->execute();
    $telepulesek = $statement->fetchAll(PDO::FETCH_ASSOC);
    
    /*echo"<pre>";
    var_dump($telepulesek);*/
    echo compileTemplate('wrapper.phtml', [
        'content' => compileTemplate('cityList.phtml',[
            'telepulesek' => $telepulesek,
        ])
        //'isAuthorized' => isLoggedIn() //megvizsgáljuk, hogy be van-e jelentkezve -->ezt küldjük a wrapperbe
    ]);
}

function homeHandler()
{
    echo compileTemplate('wrapper.phtml', [
        'content' => compileTemplate('home.phtml',[]),
        //'isAuthorized' => isLoggedIn() //megvizsgáljuk, hogy be van-e jelentkezve -->ezt küldjük a wrapperbe
    ]);
}

function getConnection()
{
    return new PDO(
        'mysql:host='.$_SERVER['DB_HOST'].';dbname='.$_SERVER['DB_NAME'],
        $_SERVER['DB_USER'],
        $_SERVER['DB_PASSWORD']
    );
}


function compileTemplate($filePath, $params =[]): string
{
    ob_start();
    require __DIR__."/views/".$filePath;
    return ob_get_clean();
}

function notFoundHandler()
{
    echo "Oldal nem található";
}

?>