<?php
use backendless\Backendless;
use backendless\model\BackendlessUser;
use backendless\services\persistence\BackendlessDataQuery;
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

	//FIXME Currently it is not working since Parse could not change session expiration date
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
}
?>
