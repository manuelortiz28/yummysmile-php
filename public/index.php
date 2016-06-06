<?php
use Phalcon\Mvc\Micro;
use Parse\ParseClient;
use backendless\Backendless;

$folderApp = "../app/".$_GET["version"];

if (!file_exists($folderApp)) {
    http_response_code(404);
    return;
}

date_default_timezone_set('utc');

require '../parse/autoload.php';
require "../backendless/autoload.php";

ParseClient::initialize( 
	"QeaC0alUEePt3hDsAx9tXRcxFXjzfHiP5KWE5h8T",
	"cA6mLGVYPExKeIVvEF4bgq5n06II8UEXCW6gpcNn",
	"1XfBXA0trvI9GjRn9WcOPTU3bghsofy6H9cL8y1n" );

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
