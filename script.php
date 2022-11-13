<?php

require __DIR__ . '/vendor/autoload.php'; // Autoload files using Composer autoload

// Create a new instance of the class and use .env file to set the variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Pokud není skript spuštěn v CLI prostředí, vypíše se chybová hláška
if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */

// Goolge API client
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('Google Calendar API PHP Quickstart');
    $client->setScopes(Google_Service_Calendar::CALENDAR);
    $client->setAuthConfig('credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load previously authorized token from a file, if it exists.
    // The file token.json stores the user's access and refresh tokens, and is
    // created automatically when the authorization flow completes for the first
    // time.
    $tokenPath = 'token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
};

$url = file_get_contents($_ENV["ICANTEEN_URL"]);

$dom = new DOMDocument('1.0', 'UTF-8');
@$dom->loadHTML($url);
$xpath = new DomXPath($dom);

$nodeList = $xpath->query("//div[@class='jidelnicekDen']"); // Obsahuje všechny dny - počet, které jsou ukázány v jídelníku (např. 7)

$node = $nodeList->item(1); // Obsahuje celý content classy jidelnicekDen a vypíše se jako string pomocí $node->nodeValue

$delka = $nodeList->length; // Počet dní v jídelníčku


$client = getClient();
$service = new Google_Service_Calendar($client);
$calendarId = $_ENV['CALENDAR_ID']; // ID kalendáře -> PHP iCanteen

// Pro všechny dny v jídelníčku platí:
foreach ($nodeList as $n) {
    $den = $n->nodeValue; // Celý content jednoho dne (celý obdélníček)

    $datum1 = explode('Jídelníček na', $den); // explode the contents
    $datum2 = explode('-', $datum1[1]); // explode the contents
    $datum3 = $datum2[0]; // get the contents
    $datum_zacatek = date("c", strtotime("$datum3 13:30:00")); // Start date
    $datum_konec = date("c", strtotime("$datum3 14:00:00")); // End date

    $obed1 = explode('Oběd 1 --', $den);
    $obed2 = explode('(', $obed1[1]);
    $obed3 = trim(str_replace('Hlavní -- ', "", $obed2[0])); // Řádek oběd 1
    
    $obed4 = explode('Oběd 2 --', $den);
    $obed5 = explode('(', $obed4[1]);
    $obed6 = trim(str_replace('Hlavní -- ', "", $obed5[0])); // Řádek oběd 2

    // Roztrhne oba řádky obědů podle znaku čarka
    $zdroj1 = explode(',', $obed3);
    $zdroj2 = explode(',', $obed6);

    $polevka = ucfirst(trim($zdroj1[0])) ?: 'Polévka'; // Polévka
    $jednicka = ucfirst(trim($zdroj1[1])) . ", " . trim($zdroj1[2]) ?: 'Jednička'; // Hlavní jídlo 1 + příloha
    $dvojka = ucfirst(trim($zdroj2[1])) . ", " . trim($zdroj2[2]) ?: 'Dvojka'; // Hlavní jídlo 2 + příloha

    $vyber_jidla = (int)readline("Vyber jídlo, které sis navolil: na $datum3 \n1. $jednicka \n2. $dvojka \nVýběr: "); // Vybere jídlo, které sis navolil a přidá do kalendáře

    pclose(popen('cls','w')); // Vymaže obrazovku terminálu

    $popisek = "Dobrou chuť!"; // Popisek, který se zobrazí v kalendáři

    if ($vyber_jidla == 0) {
        $jidlo = "Oběd odhlášen";
        $polevka = '';
        $popisek = '';
    } elseif ($vyber_jidla == 1) {
        $jidlo = $jednicka;
    } elseif ($vyber_jidla == 2) {
        $jidlo = $dvojka;
    } else {
        echo "Nepodařilo se vybrat jídlo.\n";
        exit;
    }

    $event = new Google_Service_Calendar_Event(array(
        'summary' => $jidlo,
        'location' => $polevka,
        'description' => $popisek,
        'start' => array(
            'dateTime' => $datum_zacatek,
            'timeZone' => 'Europe/Prague',
        ),
        'end' => array(
            'dateTime' => $datum_konec,
            'timeZone' => 'Europe/Prague',
        ),
    ));

    $event = $service->events->insert($calendarId, $event);
};

printf("$delka events successfully added to calendar."); // Pokud se vše povede
