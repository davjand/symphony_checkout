<?php

include_once(dirname(__FILE__) . "/../gateway.class.php");

class TestGateway extends PaymentGateway {

	public function getConfigArray() {
		return array("test-setting-1", "test-setting-2");	
	}
	
	public function getDetailsArray() {
		return array("name" => "TestGateway");
	
	}
	
	public function processTransaction() {}
	
	public function processPaymentNotification() {}

}


?>