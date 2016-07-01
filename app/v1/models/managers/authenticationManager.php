<?php
use backendless\Backendless;
use backendless\model\BackendlessUser;
use backendless\services\persistence\BackendlessDataQuery;
use Phalcon\DI\InjectionAwareInterface;

class AuthenticationManager implements InjectionAwareInterface {

	private $userFields = array("objectId", "name", "lastName", "email", "token");
	private $app_id = "1750759625204363";
	private $app_secret = "9593f7ed05b59502e31fe8550107713e";
	protected $_di;

    public function setDI (Phalcon\DiInterface $dependencyInjector){
    	$this->_di = $dependencyInjector;
    }

    public function getDI (){
    	return $this->_di;
    }

    public function signup($authenticationData) {
		//TODO Closes any session

		//Search for the user
		$query = new BackendlessDataQuery();
		$query->setWhereClause("email = '".$authenticationData->email."'");
		$result_collection = Backendless::$Persistence->of('Users')->find($query)->getAsObjects();

		//If the user doesn't exist, then create a new one
		if (count($result_collection) > 0) {
			throw new YummyException("This user already exists", 409);
		}

		$errorList = array();

		if (empty($authenticationData->name)
			|| empty($authenticationData->lastName)
			|| empty($authenticationData->email)
			|| empty($authenticationData->password)) {
			$errorList[] = new ErrorItem('FIELDS_REQUIRED', 'Name, LastName, email and password are mandatory');
		}

		if (!$this->isEmailValid($authenticationData->email)) {
			$errorList[] = new ErrorItem('NEW_PASSWORD_INVALID', 'The email is not a email format valid');
		}

		//TODO Validate other password business rules
		if (!empty($authenticationData->password)) {
			if (strlen($authenticationData->password) < 5) {
				$errorList[] = new ErrorItem('NEW_PASSWORD_INVALID', 'Password length should be at least 5 characters');
			}
		}

		if (count($errorList) > 0) {
			throw new YummyException("Create user validation error", 422, $errorList);
		}

		//Validation was successful
		$user = new BackendlessUser();
		$user->setProperty("name", $authenticationData->name);
		$user->setProperty("lastName", $authenticationData->lastName);
		$user->setProperty("username", $authenticationData->email);
		$user->setEmail($authenticationData->email);
		$user->setPassword($authenticationData->password);
		$user->setProperty("socialNetworkType", "none");

		try {
			$user = Backendless::$UserService->register($user);
		} catch(Exception $e) {
			throw new YummyException("This user already exists", 409);
		}

        $userArray = $this->_di->get("responseManager")->getAttributes($this->userFields, $user);

        $this->changeExpirationDate($user->getSessionToken());
		
		return $userArray;
	}

    public function login($authenticationData) {
		//TODO Closes any session
		//TODO Validate that this user didn't create its account using a social network

		try {
		  $user = Backendless::$UserService->login($authenticationData->email, $authenticationData->password);
		} catch (Exception $error) {
			$e = new YummyException("User couldn't logged in", 422);

			if ($error->getCode() == 3087) {//Backendless error code, User email is not confirmed
				$e->errorList[] =
                    new ErrorItem(
                        'EMAIL_NOT_CONFIRMED',
                        "The email has not been confirmed. Please review your email inbox.");
			} else if ($error->getCode() == 3003) {//Backendless error code, invalid credentials
				$e->errorList[] =
                    new ErrorItem(
                        'INVALID_CREDENTIALS',
                        "Username or password invalid");
			} else {
				$e->errorList[] =
                    new ErrorItem(
                        'UNKNOWN',
                        $error->getMessage());
			}

			throw $e;
		}

		$user->setProperty("token", $user->getProperty("user-token"));
        $userArray = $this->_di->get("responseManager")->getAttributes($this->userFields, $user);

        $this->changeExpirationDate($user->getSessionToken());

		//Stores user into a session
		$session = new yummy\models\YummySession();
		$session->setProperty("user", $user);
		$session->setToken("token", $user->getProperty("user-token"));
		Backendless::$Persistence->save($session);

		return $userArray;
	}

	function signOut($token, $userId) {

		if ($this->isLoggedIn($token, $userId)) {
			//Closes any session
			try {
				Backendless::$UserService->logout($token);
			} catch (Exception $exception) {
			}

			//Delete session
			$session = $this->findSession($token, $userId);
			if ($session) {
				Backendless::$Data->of("YummySession")->remove($session);
			}
		}

		return true;
	}

	function socialNetworkLogin($authenticationData, $token) {
		$errorList = array();

		if (empty($authenticationData->name)
			|| empty($authenticationData->lastName)
			|| empty($authenticationData->email)
			|| empty($authenticationData->socialNetworkUserId)) {
			$errorList[] = new ErrorItem('FIELDS_REQUIRED', 'Name, LastName, email and socialNetworkUserId are mandatory');
		}

		if (empty($token)) {
			$errorList[] = new ErrorItem('TOKEN_INVALID', 'The token is not valid for the given social network.');
		}

		if (!$this->isEmailValid($authenticationData->email)) {
			$errorList[] = new ErrorItem('NEW_PASSWORD_INVALID', 'The email is not a email format valid');
		}

		if (empty($authenticationData->socialNetworkType) || $authenticationData->socialNetworkType != "fb") {
			$errorList[] = new ErrorItem('INVALID_SOCIAL_NETWORK_TYPE', 'The given social network type is not allowed.');
		}

		if (count($errorList) > 0) {
			throw new YummyException("Create user validation error", 422, $errorList);
		}

		//Search for the user
		$query = new BackendlessDataQuery();
		$query->setWhereClause("email = '".$authenticationData->email."'");
		$result_collection = Backendless::$Persistence->of('Users')->find($query)->getAsClasses();


		$user = null;

		//If the user already exists, validate that was created using the same social network
		if (count($result_collection) > 0) {
			$user = $result_collection[0];
			if ($authenticationData->socialNetworkType != $user->socialNetworkType) {

				$errorList[] =
					new ErrorItem(
						'ACCOUNT_EXISTS_WITH_DIFFERENT_TYPE',
						'You could not sign in with this social network. You created your account in another way.');

				throw new YummyException("This user already exists", 409, $errorList);
			}
		}

		//The session is not active in the social network
		if (!$this->validateSocialNetworkSession(
			$authenticationData->socialNetworkUserId,
			$token,
			$authenticationData->socialNetworkType)) {

			$errorList[] = new ErrorItem('TOKEN_INVALID', 'The token is not valid for the given social network k.');
			throw new YummyException("Create user validation error", 422, $errorList);
		}

		//If the user doesn't exist, create it in the database
		if ($user == null) {
			//Validation was successful, Creates the new user.
			$user = new BackendlessUser();
			$user->setEmail($authenticationData->email);
			$user->setPassword($authenticationData->socialNetworkType);
			$user->setProperty("name", $authenticationData->name);
			$user->setProperty("lastName", $authenticationData->lastName);
			$user->setProperty("username", $authenticationData->email);
			$user->setProperty("socialNetworkType", $authenticationData->socialNetworkType);
			$user->setProperty("socialNetworkUserId", $authenticationData->socialNetworkUserId);

			try {
				$user = Backendless::$UserService->register($user);
			} catch(Exception $e) {
				$errorList[] =
					new ErrorItem(
						'UNKNOWN',
						$e->getMessage());
				throw new YummyException("This user already exists", 409, $errorList);
			}
		}

		$user->setProperty("token", $token);
		$userArray = $this->_di->get("responseManager")->getAttributes($this->userFields, $user);

		$this->changeExpirationDate($user->getSessionToken());

		return $userArray;
	}

    function isLoggedIn($token, $userId) {
		//TODO Delete all expired sessions

		//1. Retrieve user for knowing account type
		//Search for the user
		$query = new BackendlessDataQuery();
		$query->setWhereClause("objectId = '".$userId."'");
		$result_collection = Backendless::$Persistence->of('Users')->find($query)->getAsClasses();

		//If the user doesn't exist
		if (count($result_collection) == 0) {
			return false;
		}

		$user = $result_collection[0];

		//Retrieves the session
		if ($user->getSocialNetworkType() == "none") {
			$session = $this->findSession($token, $userId);
		} else if ($this->validateSocialNetworkSession(
						$user->getSocialNetworkUserId(),
						$token,
						$user->getSocialNetworkType())) {

			$session = new yummy\models\YummySession();
			$session->setProperty("user", $user);
		}

		if ($session) {
			Backendless::$UserService->setCurrentUser($session->getUser());
		}

		//If the user is still logged in
		$currentUser = Backendless::$UserService->getCurrentUser();
		if ($currentUser && $currentUser->getUserId() == $userId) {
			return $currentUser;
		}

        return false;
    }

	function recoverPassword($email) {

		if ($this->isEmailValid($email)) {
			try {
				Backendless::$UserService->restorePassword($email);
			} catch (Exception $e) {
				throw new YummyException(
					'Could not recovery password for "$username"',
					422,
					array(new ErrorItem ("USERNAME_NON_EXISTENT", "The username provided doesn't exist")));
			}
		} else {
			throw new YummyException(
				'Could not recovery password for "$username"',
				422,
				array(new ErrorItem ("INVALID_USERNAME", "The format for the username is incorrect. Should be an email address.")));
		}
	}

	private function findSession($token, $userId) {
		//Search for the User session
		$sessionQuery = new BackendlessDataQuery();
		$sessionQuery->setDepth(1);
		$sessionQuery->setWhereClause("user.objectId = '" . $userId . "' and token = '".$token."'");
		$sessionResults = Backendless::$Persistence->of('YummySession')->find($sessionQuery)->getAsClasses();

		if (count($sessionResults) > 0) {
			return $sessionResults[0];
		}

		return false;
	}

	private function isEmailValid($email) {
		if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) ) {
			return false;
		}

		return true;
	}

	//FIXME Currently it is not doing anything
	private function changeExpirationDate($token) {
		//Add 2 days to current date and apply the ISO8601 format
		$expirationDate = gmdate(DateTime::ISO8601, time() + (2 * 24 * 60 * 60));
	}

	private function process_input($data) {
		$data = trim($data);
		$data = stripslashes($data);
		$data = htmlspecialchars($data);
		return $data;
	}

	private function validateSocialNetworkSession($idProfile, $token, $socialNetworkType) {
		if ($socialNetworkType == "fb") {
			return $this->validateFbSession($idProfile, $token);
		}

		return false;
	}

	private function validateFbSession($idProfile, $token) {
		$lines = file("https://graph.facebook.com/debug_token?input_token=".$token."&access_token=".$this->app_id."|".$this->app_secret);
		$session = json_decode($lines[0]);

		if(isset($session->error)) {
			return false;
		}

		return $session->data->user_id == $idProfile;
	}
}
?>
