<?php

// ***************************************
// gateway.class.php - an abstract class that contains common functionality and interfacing for all gateway subclasses.
// Tom Johnson 2012
// ***************************************

abstract class PaymentGateway {

	protected function buildTransactionArray($data, $mappings) {
		$output = array();
		foreach($data as $k => $v) {
			$output[$mappings[$k]] = $v;
		}	
		return $output;
	}
	
	protected function isEmail($email) {
		$result = TRUE;
		if(!eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$", $email)) {
		$result = FALSE;
		}
		return $result;	
	}	
	
	abstract public function getConfigArray();
	abstract public function getDetailsArray();
	abstract public function getRequiredFieldsArray();
	
	abstract public function processTransaction($transactionFieldData, $configuration);
	abstract public function processPaymentNotification();

}

?>