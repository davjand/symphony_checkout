<?php

include_once(dirname(__FILE__) . "/../gateway.class.php");

class TestGateway extends PaymentGateway {

	public function getConfigArray() {
		return array("test-setting-1", "test-setting-2");	
	}
	
	public function getDetailsArray() {
		return array("name" => "TestGateway");
	
	}
	
	public function getRequiredFieldsArray() {
		return array();
	}
	
	public function processTransaction($transactionFieldData, $configuration) {
	
		print_r($transactionFieldData);
		print_r($configuration);
		
	}
	
	public function processPaymentNotification() {}
	
	public function runTest($configuration) {
	
		// a test inside a test gateway? allows us to potentially test all the other machinery!
	
		$output = array("Testing Routine" => "ok");
	
		try{
			// code tree integrity
			if(class_exists("PaymentGatewayFactory")) {
				$output["Gateway Factory Detectable"] = "ok";
				
				if(class_exists("PaymentGateway")) {
					$output["Gateway Baseclass Detectable"] = "ok";
				
					$output["Detectable Derived Classes"] = PaymentGatewayFactory::getGatewayList();
					
				}
				else {
					$output["Gateway Baseclass Detectable"] = "failed";
				}				
			}
			else {
				$output["Gateway Factory Detectable"] = "failed";
			}		
			
			// interop code - lets test some events!
			
		}
		catch(Exception $e) { $output["ERROR"] = $e->getMessage(); }
		
		return $output;
		
	}

}


?>