<?php
use Phalcon\Http\Response;

$app->post("/loginsn", function () use ($app, $di) {
	$authenticationManager = $di->get("authenticationManager");
	$responseManager = $di->get("responseManager");

	$authenticationData = $app->request->getJsonRawBody();//email, snUid
	$authenticationData["userId"] = $app->request->getHeader("UID");
	$authenticationData["socialNetworkType"] = $app->request->getHeader("SN");
	$authenticationData["socialNetworkToken"] = $app->request->getHeader("SNTOKEN");

	try {
		$authenticationManager->signinSocialNetwork($authenticationData);
		return new Response();
	}catch(YummyException $e){
		return $responseManager->getErrorResponse($e);
	} catch(Exception $e) {
		return $responseManager->getGenericErrorResponse($e);
	}
});

$app->post("/login", function () use ($di, $app) {
	$authenticationManager = $di->get("authenticationManager");
	$responseManager = $di->get("responseManager");

	$authenticationData = $app->request->getJsonRawBody();//email, password

	try {
		return $responseManager->getResponse($authenticationManager->signin($authenticationData));
	}catch(YummyException $e){
		$e->errorList[] = new ErrorItem('INVALID_CREDENTIALS', 'User name or password invalid');

		return $responseManager->getErrorResponse($e);
	} catch(Exception $e) {
		return $responseManager->getGenericErrorResponse($e);
	}
});

$app->post("/signup", function () use ($di, $app) {
	$authenticationManager = $di->get("authenticationManager");
	$responseManager = $di->get("responseManager");

	$authenticationData = $app->request->getJsonRawBody();//email, password, name, lastname

	try {
        return $responseManager->getCreatedResponse($authenticationManager->signup($authenticationData));
	}catch(YummyException $e){
		return $responseManager->getErrorResponse($e);
	} catch(Exception $e) {
		return $responseManager->getGenericErrorResponse($e);
	}
});

$app->get("/logout", function () use ($di, $app) {
	$authenticationManager = $di->get("authenticationManager");
	$responseManager = $di->get("responseManager");

	$token = $app->request->getHeader("TOKEN");
	$userId = $app->request->getHeader("USERID");

	try {
		$authenticationManager->signout($token, $userId);
		return $responseManager->getNotContentResponse();
	}catch(YummyException $e){
		return $responseManager->getErrorResponse($e);
	} catch(Exception $e) {
		return $responseManager->getGenericErrorResponse($e);
	}
});
?>
