<?php

include_once(dirname(__FILE__) . "/../gateway.class.php");

class SagepayGateway extends PaymentGateway {

	public function getConfigArray() {
		return array("connect-to", "description", "site-fqdn", "vendor-name", "encryption-password", "currency", "transaction-type", "partner-id");	
	}
	
	public function getDetailsArray() {
		return array("name" => "SagepayGateway");
	
	}
	
	public function processTransaction($transactionFieldData, $configuration) {
	
		/* transaction field data should contain...
			CardHolder
			CardNumber
			StartDateMonth
			StartDataYear
			ExpiryDateMonth
			ExpiryDateYear
			CardType
			IssueNumber
			CV2
			BillingFirstnames
			BillingSurname
			BillingAddress1
			BillingAddress2
			BillingCity
			BillingCountry
			BillingPostCode
			Amount
		*/
	
	
		require("./sagepay/sagepay.class.php");

		$data = SagePay::formatRawData($transactionFieldData);
		
		$sgpy = new SagePay($data, $configuration);
		
		$sgpy->execute();
	
	}
	
	public function processPaymentNotification() {}

}


?>