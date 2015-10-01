<?php

$loader = new \Phalcon\Loader();

// We're a registering a set of directories taken from the configuration file
$loader->registerDirs(
    array(
        "../app/models/managers",
        "../app/models/exceptions"
    )
)->register();
?>
