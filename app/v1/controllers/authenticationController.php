<?php
use Phalcon\Http\Response;

$app->post("/login", function () use ($di, $app) {
	$authenticationManager = $di->get("authenticationManager");
	$responseManager = $di->get("responseManager");

	$authenticationData = $app->request->getJsonRawBody();//email, password

	try {
		return $responseManager->getResponse($authenticationManager->login($authenticationData));
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

	$authenticationData = $app->request->getJsonRawBody();//email, password, name, lastName

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
		$authenticationManager->signOut($token, $userId);
		return $responseManager->getNotContentResponse();
	}catch(YummyException $e){
		return $responseManager->getErrorResponse($e);
	} catch(Exception $e) {
		return $responseManager->getGenericErrorResponse($e);
	}
});

$app->post("/recoverpassword", function () use ($di, $app) {
	$authenticationManager = $di->get("authenticationManager");
	$responseManager = $di->get("responseManager");

	$authenticationData = $app->request->get("email");//email

	try {
		$authenticationManager->recoverPassword($authenticationData);
		return $responseManager->getNotContentResponse();
	}catch(YummyException $e) {
		return $responseManager->getErrorResponse($e);
	} catch(Exception $e) {
		return $responseManager->getGenericErrorResponse($e);
	}
});
?>
