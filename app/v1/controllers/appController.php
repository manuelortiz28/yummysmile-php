<?php
use Phalcon\Http\Response;

$app->notFound(function () use ($app, $di) {
    $responseManager = $di->get("responseManager");
    return $responseManager->getErrorResponse(
        new YummyException("This is crazy, but this page was not found!", 404)
    );
});
?>
