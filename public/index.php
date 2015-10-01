<?php
use Phalcon\Mvc\Micro;
use Parse\ParseClient;
use Parse\ParseSessionStorage;

require '../vendor/autoload.php';

ParseClient::initialize( 
	"2NFoM9LpYh5Sj2WvoiVowTFJNKFgxdky2imQJryE", 
	"FasEn19DJuzSpSWMpbCJ8mnPk8Wv7qjZnV3vJUfv", 
	"HDz4O8vjjRi4Q3gH0OSytywOpelpXKV5RxkPQW1C" );

session_start();
ParseClient::setStorage( new ParseSessionStorage() );

//Loads managers and models
require "../app/config/loader.php";

$app = new Micro();

// Create the Dependency Injector Container and register dependencies
$di = new Phalcon\DI();
$di->setShared("mealsManager", 'MealsManager');
$di->setShared("responseManager", 'ResponseManager');

// Define the routes here
require "../app/controllers/mealsController.php";
require "../app/controllers/authenticationController.php";


$app->handle();

?>
