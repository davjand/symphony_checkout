<?php

include_once(dirname(__FILE__) . "/../gateway.class.php");

class TestGateway extends PaymentGateway {

	public function getConfigArray() {}
	
	public function getDetailsArray() {
		return array("name" => "TestGateway");
	
	}
	
	public function processTransaction() {}
	
	public function processPaymentNotification() {}

}


?>