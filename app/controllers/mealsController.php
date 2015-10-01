<?php
$app->get("/api/meals", function () use ($app, $di) {
	$mealsManager = $di->get("mealsManager");
	$responseManager = $di->get("responseManager");
    $authenticationManager = $di->get("authenticationManager");

	$name = $app->request->get("name");

	try {
        if ($authenticationManager->isLoggedIn($app->request->getHeader("TOKEN")))
            return $responseManager->getResponse(
                array('meals' => $mealsManager->getMeals($name))
            );
        else
            throw new YummyException("The user is not logged in", 401);

    }catch(YummyException $e){
		return $responseManager->getErrorResponse($e);
	} catch(Exception $e) {
		return $responseManager->getGenericErrorResponse($e);
	}
});

$app->post("/api/meals", function () use ($di, $app) {
	$mealsManager = $di->get("mealsManager");
	$responseManager = $di->get("responseManager");

    $meal = $app->request->getJsonRawBody();
	try {
		return $responseManager->getCreatedResponse($mealsManager->createMeal($meal));
	}catch(YummyException $e){
		return $responseManager->getErrorResponse($e);
	} catch(Exception $e) {
		return $responseManager->getGenericErrorResponse($e);
	}
});

$app->put("/api/meals", function () use ($di, $app) {
	$mealsManager = $di->get("mealsManager");
	$responseManager = $di->get("responseManager");
	$meal = $app->request->getJsonRawBody();
	try {
		return $responseManager->getResponse($mealsManager->updateMeal($meal));
	}catch(YummyException $e){
		return $responseManager->getErrorResponse($e);
	} catch(Exception $e) {
		return $responseManager->getGenericErrorResponse($e);
	}
});

$app->delete("/api/meals/{id:[0-9A-Za-z]+}", function ($mealId) use ($di, $app) {
	$mealsManager = $di->get("mealsManager");
	$responseManager = $di->get("responseManager");
	try {
		$mealsManager->deleteMeal($mealId);
		return $responseManager->getDeletedResponse();
	}catch(YummyException $e){
		return $responseManager->getErrorResponse($e);
	} catch(Exception $e) {
		return $responseManager->getGenericErrorResponse();
	}
});
?>
