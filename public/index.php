<?php
use Phalcon\Mvc\Micro;
use backendless\Backendless;

$folderApp = "../app/".$_GET["version"];

if (!file_exists($folderApp)) {
    http_response_code(404);
    return;
}

date_default_timezone_set('utc');

require "../backendless/autoload.php";

Backendless::initApp(
    "B4F73CC4-EAB1-1665-FF98-858940A5A700",
    "D807B131-3B9D-AC06-FFE6-21CB56387800",
    "v1");

$app = new Micro();

// Create the Dependency Injector Container and register dependencies
$di = new Phalcon\DI();

//Loads managers, models and controllers
require $folderApp."/config/loader.php";

$app->handle();

function mustBeLogged($request, $di) {
    $authenticationManager = $di->get("authenticationManager");

    $token = $request->getHeader("TOKEN");
    $userId = $request->getHeader("USERID");

    $user = $authenticationManager->isLoggedIn($token, $userId);

    if (!$user) {
        throw new YummyException("The user is not logged in", 401);
    }
    return $user;
}
?>
