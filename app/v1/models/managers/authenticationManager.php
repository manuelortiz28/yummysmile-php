<?php
use Parse\ParseUser;
use Parse\ParseException;
use Phalcon\DI\InjectionAwareInterface;

class AuthenticationManager implements InjectionAwareInterface {

	private $userFields = array("objectId", "name", "lastname", "email");
	protected $_di;
	private $snPassword = "and='¿nwqo78$!97,.273¨`^¨";
	private $appToken = "ADASDSAD";

    public function setDI (Phalcon\DiInterface $dependencyInjector){
    	$this->_di = $dependencyInjector;
    }

    public function getDI (){
    	return $this->_di;
    }

    public function signup($authenticationData) {

		ParseUser::logOut();//Closes any session

		//Search for the user
		$query = ParseUser::query();
		$query->equalTo("email", $authenticationData->email);
        $user = $query->first();
		
		//If the user doesn´t exist, then create a new one
		if($user) {
			throw new YummyException("This user already exists", 401);
		}

		$user = new ParseUser();
		$user->set("name", $authenticationData->name);
		$user->set("lastname", $authenticationData->lastname);
		$user->set("username", $authenticationData->email);
		$user->set("email", $authenticationData->email);
		$user->set("password", $authenticationData->password);
		$user->set("socialNetworkType", "none");

		try {
			$user->signUp();
		} catch(ParseException $e) {
			throw new YummyException("This user already exists", 401);
		}

        try {
            $user = ParseUser::logIn($authenticationData->email, $authenticationData->password);
        } catch (ParseException $error) {
            // The login failed. Check error to see why.
        }

        $userArray = $this->_di->get("responseManager")->getAttributes($this->userFields, $user);
        $userArray["token"]=$user->getSessionToken();
		
		return $userArray;
	}

    public function signin($authenticationData) {
		ParseUser::logOut();//Closes any session

		try {
		  $user = ParseUser::logIn($authenticationData->email, $authenticationData->password);
		} catch (ParseException $error) {
		  // The login failed. throw an exception
			throw new YummyException("Email or password Incorrect", 401);
		}

        $userArray = $this->_di->get("responseManager")->getAttributes($this->userFields, $user);
        $userArray["token"]=$user->getSessionToken();
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
			} catch (ParseException $error) {
				throw new YummyException("Unable to login", 500);
			}
		} else {
			throw new YummyException("The session has expired", 401);
		}

		return $user;
	}

	function signout($token, $userId) {

		if ($this->isLoggedIn($token, $userId)) {
			ParseUser::logOut();//Closes any session
		}

		return true;
	}

    function isLoggedIn($token, $userId) {
		try {
			ParseUser::become($token);

			if (ParseUser::getCurrentUser() && ParseUser::getCurrentUser()->getObjectId() == $userId) {
				return ParseUser::getCurrentUser();
			}
        } catch (ParseException $ex) {
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
}
?>
