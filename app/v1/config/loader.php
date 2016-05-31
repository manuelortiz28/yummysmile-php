<?php

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
        "../app/models/managers",
        "../app/models/exceptions"
    )
)->register();
*/
?>
