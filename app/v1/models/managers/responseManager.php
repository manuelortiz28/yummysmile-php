<?php
	use Phalcon\Http\Response;
	class ResponseManager {

		function getOKResponse(){
			$response = new Response();
			$response->setHeader("Content-Type", "application/json");
			return $response;
		}

		function getResponse($responseData){
			$response = $this->getOKResponse();
			$response->setJsonContent($responseData);
			return $response;
		}

	 	function getCreatedResponse($responseData){
			$response = $this->getResponse($responseData);
			$response->setStatusCode(201, "Created");

			return $response;
		}

		function getNotContentResponse(){
			$response = $this->getOKResponse();
			$response->setStatusCode(204, "No Content");

			return $response;
		}

		function getErrorResponse($exception, $errorList){
			$response = $this->getOKResponse();

			$newErrorList = $errorList;

			if (!isset($newErrorList)) {
				$newErrorList = array();
			}

			switch ($exception->getCode()) {
				case 400:
					$response->setStatusCode(400, "Bad Request");
					$errorMessage = "Bad Request";
					break;
				case 401:
					$response->setStatusCode(401, "Unauthorized");
					$errorMessage = "Unauthorized";
					break;
				case 404:
					$response->setStatusCode(404, "Not Found");
					$errorMessage = "Not Found";
					break;
				case 500:
				default:
					$response->setStatusCode(500, "Internal Server Error");
					$errorMessage = "Internal Server Error";
			}

			$response->setJsonContent(
				array(
					'code' => $exception->getCode(),
					'message' => $errorMessage,
					'errors' => $newErrorList
				)
			);

			return $response;
		}

		function getGenericErrorResponse($e){
			return $this->getErrorResponse(new YummyException("General error: ".$e->getMessage(), 500));
		}

		function getAttributes($fields, $entity) {
			$attributes=[];
			foreach($fields as $field) {
				$valueField = null;

				if($field == "objectId")
					$valueField = $entity->getObjectId();
				else if($field == "updatedAt")
					$valueField = $entity->getUpdatedAt();
				else if($field == "createdAt")
					$valueField = $entity->getCreatedAt();
				else
					$valueField = $entity->get($field);

				$attributes += array($field => $valueField);
			}

			return $attributes;
		}
	}


?>
