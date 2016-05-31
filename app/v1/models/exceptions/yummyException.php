<?php
class YummyException extends Exception
{
    public $errorList;

    // Redefine the exception so message isn't optional
    public function __construct($message, $code = 0, $errorList = null, Exception $previous = null)
    {
        if (!isset($errorList) || is_null($errorList)) {
            $this->errorList = array();
        } else {
            $this->errorList = $errorList;
        }
    
        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }

    // custom string representation of object
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
?>
