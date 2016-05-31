<?php

/**
 * Created by PhpStorm.
 * User: manuel
 * Date: 30/05/16
 * Time: 9:51 AM
 */
class ErrorItem
{
    private $reason;
    private $message;

    public function __construct($reason, $message) {
        $this->reason = $reason;
        $this->message = $message;
    }
    
    public function __get($property) {
        if (property_exists($this, $property)) {
            return $this->$property;
        }
    }

    public function __set($property, $value) {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }

        return $this;
    }

    public function toArray() {
        return array(
            "reason" => $this->reason,
            "message" => $this->message
        );
    }

    public static function toArrayList($errorList) {
        $parsedList = array();
        foreach ($errorList as $currentError) {
            $parsedList[] = $currentError->toArray();
        }
        return $parsedList;
    }
}