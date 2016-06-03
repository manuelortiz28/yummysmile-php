<?php
use backendless\Backendless;
use Parse\ParseUser;
use backendless\model\BackendlessUser;
use backendless\services\persistence\BackendlessDataQuery;
use Parse\ParseException;
use Phalcon\DI\InjectionAwareInterface;

class AuthenticationManager implements InjectionAwareInterface {

	private $userFields = array("objectId", "name", "lastName", "email");
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

		//TODO Validate email format
		if (!empty($authenticationData->email)) {
			//if (strlen($authenticationData->email) < 5) {
			//	$errorList[] = new ErrorItem('NEW_PASSWORD_INVALID', 'The email is not a email format valid');
			//}
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

	function process_input($data) {
		$data = trim($data);
		$data = stripslashes($data);
		$data = htmlspecialchars($data);
		return $data;
	}

    public function login($authenticationData) {
		//TODO Closes any session

		try {
		  $user = Backendless::$UserService->login($authenticationData->email, $authenticationData->password);
		} catch (Exception $error) {
			throw new YummyException("Email or password Incorrect", 422);
		}

        $userArray = $this->_di->get("responseManager")->getAttributes($this->userFields, $user);
        $userArray["token"]=$user->getProperty("user-token");

        $this->changeExpirationDate($user->getSessionToken());

		//Stores user into a session
		$session = new yummy\models\YummySession();
		$session->setProperty("user", $user);
		$session->setToken("token", $user->getProperty("user-token"));
		Backendless::$Persistence->save($session);

		return $userArray;
	}

	function signinSocialNetwork($authenticationData) {
		ParseUser::logOut();//Closes any session

		//Search for the user
		$query = ParseUser::query();
		$user = $query->equalTo("email", $authenticationData->email);
		
		//If the user doesn´t exist, then create a new one
		if(!$user) {
			$user = new ParseUser();
			$user->set("username", $authenticationData->email);
			$user->set("email", $authenticationData->email);
			$user->set("password", $this->snPassword);
			$user->set("socialNetworkUserId", $authenticationData->snUid);
			$user->set("socialNetworkType", $authenticationData->socialNetworkType);

			try {
				$user->signUp();
			} catch(ParseException $e) {
				throw new YummyException("Other user exists with the same email address", 401);
			}
		}

		ParseUser::logOut();//Closes any session

		if ($user->get("socialNetworkType") != $this->socialNetworkType) {
			throw new YummyException("You could not sign in with this social network. You created your account in another way", 401);
		}

		//Validates with the social network API if it is currently logged
		if ($user->get("socialNetworkType") == "fb" 
				&& $this->validateFbSession($user->get("socialNetworkUserId"), $user->get("socialNetworkToken"))) {
			$authenticated = true;
		} else if ($user->get("socialNetworkType") == "g+"
				&& $this->validateGooglePlusSession($user->get("socialNetworkUserId"), $user->get("socialNetworkToken"))) {
			$authenticated = true;
		}

		if($authenticated) {
			try {
                $user = ParseUser::logIn($authenticationData->email, $this->snPassword);
                $this->changeExpirationDate($user->getSessionToken());
			} catch (ParseException $error) {
				throw new YummyException("Unable to login", 500);
			}
		} else {
			throw new YummyException("The session has expired", 401);
		}

		return $user;
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

    function isLoggedIn($token, $userId) {
		//TODO Delete all expired sessions

		$session = $this->findSession($token, $userId);

		if($session) {
			Backendless::$UserService->setCurrentUser($session->getUser());
		}

		//If the user is still logged in
		$currentUser = Backendless::$UserService->getCurrentUser();
		if ($currentUser && $currentUser->getUserId() == $userId) {
			return $currentUser;
		}

        return false;
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

	function validateFbSession($idProfile, $token) {
		$session = $this->getFbSession($token);
		if($session->error) return false;

		return $session->data->user_id == $idProfile;
	}

	function getFbSession($token) {
		$lines = file("https://graph.facebook.com/debug_token?input_token=".$token."&access_token=".$this->appToken);
		return json_decode($lines);
	}

	function validateGooglePlusSession($idProfile, $token) {
		return false;
	}

    //FIXME Currently it is not working since Parse could not change session expiration date
	function changeExpirationDate($token) {
		//Add 2 days to current date and apply the ISO8601 format
		$expirationDate = gmdate(DateTime::ISO8601, time() + (2 * 24 * 60 * 60));
	}
}
?>
