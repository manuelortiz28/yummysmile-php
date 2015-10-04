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

		function getDeletedResponse(){
			$response = $this->getOKResponse();
			$response->setStatusCode(204, "No Content");

			return $response;
		}

		function getErrorResponse($exception){
			$response = $this->getOKResponse();

			$response->setJsonContent(
		            	array(
			                'errorCode' => $exception->getCode(),
			                'errorMessage' => $exception->getMessage(),
			                'errors' => array()
			            )
		      		);

			switch ($exception->getCode()) {
				case 401:
					$response->setStatusCode("Unauthorized", 401);
					break;
				case 404:
					$response->setStatusCode("Not Found", 404);
					break;
				case 500:
				default:
					$response->setStatusCode("Internal Server Error", 500);
			}
			

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
