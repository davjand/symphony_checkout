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
	
	public function processPaymentNotification($returnData, $storedData, $configuration) {}
	
	public function extractLocalTxId($returnData) {}
	
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
			
			// interop code - test the algorithm code that sits in the event
			include_once(dirname(__FILE__) . "/../../events/event.process_payment.php");
			
			$output["Raw Mappings Processing"] = eventprocess_payment::processRawMappings("Splitting:ok\r\nSplittingAgain:ok\r\nAnyWackyBehaviour\r\nThis Should Be Ok: ok");
			
			$dummyTransactionData = array("Test1" => "1", "Test2" => "2", "Test5" => "5");
			$dummyRequiredFields = array("Test1", "Test2", "Test3", "Test4", "Test5");
			$dummyProcessedMappings = array("Test3" => "!!!", "Test4" => "????");
			
			eventprocess_payment::transferMappings(&$dummyTransactionData, $dummyRequiredFields, $dummyProcessedMappings, true);
			$output["Mappings Transfer"] = array(
				"Transferred Fields Ok" => (($dummyTransactionData["Test3"] == "ok" && $dummyTransactionData["Test4"] == "ok") ? "ok" : "failed"),
				"Count Preserved" => ((count($dummyTransactionData) == 5) ? "ok" : "failed")
			);
						
		}
		catch(Exception $e) { $output["ERROR"] = $e->getMessage(); }
		
		return $output;
		
	}

}


?>