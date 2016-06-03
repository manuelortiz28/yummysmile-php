<?php
use backendless\BackendlessAutoloader;
use backendless\Backendless;

//Mapping backendless
BackendlessAutoloader::addNamespace( 'yummy', __DIR__. DIRECTORY_SEPARATOR . "..");
Backendless::mapTableToClass( "Users", "backendless\model\BackendlessUser" );
Backendless::mapTableToClass( "YummySession", "yummy\models\YummySession" );
Backendless::mapTableToClass( "Meal", "yummy\models\Meal" );

//Loading other classes
require $folderApp."/models/ErrorItem.php";

require $folderApp."/models/managers/mealsManager.php";
require $folderApp."/models/managers/responseManager.php";
require $folderApp."/models/managers/authenticationManager.php";
require $folderApp."/models/exceptions/yummyException.php";

$di->setShared("mealsManager", 'MealsManager');
$di->setShared("responseManager", 'ResponseManager');

// Define the routes here
require $folderApp."/controllers/appController.php";
require $folderApp."/controllers/mealsController.php";
require $folderApp."/controllers/authenticationController.php";

/*
$loader = new \Phalcon\Loader();

// We're a registering a set of directories taken from the configuration file
$loader->registerDirs(
    array(
        "../models/managers",
        "../models/exceptions"
    )
)->register();
*/

?>
