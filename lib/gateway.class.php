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
	
	// posting http or https will dictate protocol used.
	protected function doPost($url, $postData) {
		
		$str = array();
		foreach($postData as $key => $value){
				$str[] = $key . '=' . urlencode($value);
		}
		$postFieldStr = implode('&', $str);		
		
		
		set_time_limit(60);
		$curlSession = curl_init();
		
		curl_setopt($curlSession, CURLOPT_URL, $url);
		curl_setopt($curlSession, CURLOPT_HEADER, 0);
		curl_setopt($curlSession, CURLOPT_POST, 1);
		curl_setopt($curlSession, CURLOPT_POSTFIELDS, $postFieldStr);
		
		curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curlSession, CURLOPT_TIMEOUT,30);
		
		curl_setopt($curlSession, CURLOPT_SSL_VERIFYPEER, FALSE);		
	
		$response = preg_split('/$\R?^/m',curl_exec($curlSession));
		
		curl_close($curlSession);
		
		$output = array();
		
		// Turn the response into an associative array
		for ($i=0; $i < count($response); $i++) {
				// Find position of first "=" character
				$splitAt = strpos($response[$i], '=');
				// Create an associative array
				$output[trim(substr($response[$i], 0, $splitAt))] = trim(substr($response[$i], ($splitAt+1)));
		}		
	
		return $output;
	
	}

	protected function generateUniqueTxCode($uniqueData) {
		$strOutput = mt_rand();
		foreach($uniqueData as $k => $v) {
			// beware!! because of postdata $v could be an object... so lets find out and deal with it.
			if(is_string($v)) {
				$strOutput .= md5($v);
			}
			else {
				$strOutput .= md5(print_r($v, true));
			}
		}
		$strOutput = "TX-" . date("d-m-Y") . "-" . substr(md5($strOutput), 0, 15);
		return $strOutput;
	}
	
	abstract public function getConfigArray();
	abstract public function getDetailsArray();
	abstract public function getRequiredFieldsArray();
	abstract public function getAmountFieldName();
	
	
	/*******
		processTransaction:
			params:
				$transactionFieldData - the posted data from the customer
				$configuration - the configuration sub-array specific to the gateway
			returns:
				$associativeArray = array("status", "local-txid", "remote-txid", "security-key", "redirect-url");
	*******/
	abstract public function processTransaction($transactionFieldData, $configuration);
	
	// as we won't have prior knowledge of the data structure, the gateway needs to do any preprocessing for us
	abstract public function extractLocalTxId($returnData);
	
	/*******
		processTransaction:
			params:
				$returnData - the data sent by the gateway
				$storedData - data that is already known about the transaction
				$configuration - the configuration sub-array specific to the gateway
			returns:
				$associativeArray = array("status", "return-value");
	*******/	
	abstract public function processPaymentNotification($returnData, $storedData, $configuration);

	abstract public function runTest($configuration);
	
}

?>