<?php
$app->get("/meals", function () use ($app, $di) {
    $mealsManager = $di->get("mealsManager");
    $responseManager = $di->get("responseManager");

	try {
        $name = $app->request->get("name");

        $user = mustBeLogged($app->request, $di);

        return $responseManager->getResponse(
            array('meals' => $mealsManager->getMeals($name, $user))
        );
    } catch(YummyException $e){
		return $responseManager->getErrorResponse($e);
	} catch(Exception $e) {
		return $responseManager->getGenericErrorResponse($e);
	}
});

$app->post("/meals", function () use ($di, $app) {
	$mealsManager = $di->get("mealsManager");
	$responseManager = $di->get("responseManager");

	try {

        $user = mustBeLogged($app->request, $di);

        // Check if the user has uploaded files
        if (!$app->request->hasFiles()) {
            throw new YummyException("The request doesn't contain any photo", 400);
        }

        $mealString = $app->request->getPost()["meal"];

        $meal = json_decode($mealString);
        $meal->user = $user;

        $meal->filename = tempnam_sfx('images/', ".jpg");

        foreach ($app->request->getUploadedFiles() as $file) {
            // Move the file into the application
            $file->moveTo('images/' . $meal->filename . ".jpg");
            break;
        }

		return $responseManager->getCreatedResponse($mealsManager->createMeal($meal, $user));
	}catch(YummyException $e){
		return $responseManager->getErrorResponse($e);
	} catch(Exception $e) {
		return $responseManager->getGenericErrorResponse($e);
	}
});

$app->put("/meals", function () use ($di, $app) {
	$mealsManager = $di->get("mealsManager");
	$responseManager = $di->get("responseManager");
	$meal = $app->request->getJsonRawBody();
	try {
        $user = mustBeLogged($app->request, $di);
        $meal->user = $user;

		return $responseManager->getResponse($mealsManager->updateMeal($meal));
	}catch(YummyException $e){
		return $responseManager->getErrorResponse($e);
	} catch(Exception $e) {
		return $responseManager->getGenericErrorResponse($e);
	}
});

$app->delete("/meals/{id:[0-9A-Za-z]+}", function ($mealId) use ($di, $app) {
	$mealsManager = $di->get("mealsManager");
	$responseManager = $di->get("responseManager");
	try {
        $user = mustBeLogged($app->request, $di);

        //FIXME Only the owner should be able to detele its meal
		$mealsManager->deleteMeal($mealId);
		return $responseManager->getNotContentResponse();
	}catch(YummyException $e){
		return $responseManager->getErrorResponse($e);
	} catch(Exception $e) {
		return $responseManager->getGenericErrorResponse();
	}
});


//FIXME Are we using this method?
$app->post("/meals/upload", function () use ($di, $app) {
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

function tempnam_sfx($path, $suffix) {
    do {
        $fileName = strval(mt_rand());
        $file = $path."/".$fileName.$suffix;
        $fp = @fopen($file, 'x');
    } while(!$fp);

    fclose($fp);
    return $fileName;
}
?>
