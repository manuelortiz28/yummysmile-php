<?php
use backendless\Backendless;
use backendless\services\persistence\BackendlessDataQuery;
use Phalcon\DI\InjectionAwareInterface;

class MealsManager implements InjectionAwareInterface {

	private $fields = array("objectId", "name", "fileName");
	protected $_di;

    public function setDI (Phalcon\DiInterface $dependencyInjector){
    	$this->_di = $dependencyInjector;
    }

    public function getDI (){
    	return $this->_di;
    }

	public function getMeals($name, $user)
	{
		$mealQuery = new BackendlessDataQuery();
		$mealQuery->setDepth(1);

		$condition = "user.objectId = '" . $user->getObjectId() . "'";

		if ($name) {
			$condition = $condition." and name LIKE '".$name."%'";
		}

		$mealQuery->setWhereClause($condition);
		$results = Backendless::$Persistence->of('Meal')->find($mealQuery)->getAsClasses();

		$i=0;
		$rows=[];
		foreach($results as $pMeal) {
			$pMeal->setProperty("fileName", "/public/images/".$pMeal->getFileName().".jpg");
			$mealAttrs = $this->_di->get("responseManager")->getAttributes($this->fields, $pMeal);

			$rows[$i++] = $mealAttrs;
		}

		return $rows;
	}

	public function createMeal($meal) {
		$pMeal = new yummy\models\Meal();
		$pMeal->setProperty("name",$meal->name);
		$pMeal->setProperty("user", $meal->user);
		$pMeal->setProperty("fileName", $meal->fileName);

		try {
			$pMeal = Backendless::$Persistence->save($pMeal);
		} catch(Exception $e){
			throw new YummyException("Error Processing Request addMeal ".$e->getMessage(), 500);
		}
		
	    return $this->_di->get("responseManager")->getAttributes($this->fields, $pMeal);
	}

	public function updateMeal($meal) {
		try {
			$pMeal = $this->findById($meal->objectId);
			$pMeal->setProperty("name", $meal->name);
			Backendless::$Data->of("Meal")->save($pMeal);
		} catch(Exception $e) {
			$pMeal = null;
		}

		if (!$meal) {
			$errorList[] = new ErrorItem('ENTITY_NOT_FOUND', "Meal with id ".$meal->getObjectId()." not found");
			throw new YummyException("Meal with id ".$meal->getObjectId()." not found", 404, $errorList);
		}
		
	    return $this->_di->get("responseManager")->getAttributes($this->fields, $pMeal);
	}

	public function deleteMeal($idMeal) {
		try {
			$meal = $this->findById($idMeal);
			Backendless::$Data->of("YummySession")->remove($meal);
		} catch(Exception $e) {
		}

		if (!$meal) {
			$errorList[] = new ErrorItem('ENTITY_NOT_FOUND', "Meal with id ".$idMeal." not found");
			throw new YummyException("Meal with id ".$idMeal." not found", 404, $errorList);
		}
	}

	private function findById($mealId) {
		$mealQuery = new BackendlessDataQuery();
		$mealQuery->setWhereClause("objectId = '" . $mealId . "'");
		return Backendless::$Persistence->of('Meal')->findById($mealId);
	}
}
?>
