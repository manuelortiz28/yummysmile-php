<?php
use Parse\ParseQuery;
use Parse\ParseObject;
use Phalcon\DI\InjectionAwareInterface;

class MealsManager implements InjectionAwareInterface {

	private $fields = array("objectId", "name", "price");
	protected $_di;

    public function setDI (Phalcon\DiInterface $dependencyInjector){
    	$this->_di = $dependencyInjector;
    }

    public function getDI (){
    	return $this->_di;
    }

	public function getMeals($name) {

		$mealQuery = new ParseQuery("Meal");

		if($name)
			$mealQuery->startsWith("name", $name);

		$results = $mealQuery->find();

		$i=0;
		$rows=[];
		foreach($results as $meal) {
			$rows[$i++] = $this->_di->get("responseManager")->getAttributes($this->fields, $meal);
		}

		return $rows;
	}

	public function createMeal($meal) {
		$pMeal = new ParseObject("Meal");
		$pMeal->set("name", $meal->name);
		$pMeal->set("price", $meal->price);

		try {
			$pMeal->save();
		} catch(Exception $e){
			throw new YummyException("Error Processing Request addMeal", 1);
			
		}
		
	    return $this->_di->get("responseManager")->getAttributes($this->fields, $pMeal);
	}

	public function updateMeal($meal) {
		$query = new ParseQuery("Meal");
		try {
			$pMeal = $query->get($meal->objectId);
		} catch(Exception $e) {
			throw new YummyException("Meal with id ".$meal->objectId." not found", 404);
		}

		$pMeal->set("name", $meal->name);
		$pMeal->set("price", $meal->price);

		try {
			$pMeal->save();
		} catch(Exception $e){
			throw new YummyException("Error Processing Request addMeal", 1);
			
		}
		
	    return $this->_di->get("responseManager")->getAttributes($this->fields, $pMeal);
	}

	public function deleteMeal($idMeal) {
		$query = new ParseQuery("Meal");
		try {
			$pMeal = $query->get($idMeal);
			$pMeal->destroy();
		} catch(Exception $e) {
			throw new YummyException("Meal with id ".$idMeal." not found", 404);
		}

		$pMeal->delete();
	}
}
?>
