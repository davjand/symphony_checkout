<?php
include_once(dirname(__FILE__) . "/../gateway.class.php");

class SagepayGateway extends PaymentGateway {

	public function getConfigArray() {
		return array("connect-to", "description", "vendor-name", "currency", "transaction-type", "partner-id");	
	}
	
	public function getDetailsArray() {
		return array("name" => "SagepayGateway");
	
	}
	
	public function getRequiredFieldsArray() {
	
		return array(
			"CardHolder",
			"CardNumber",
			"StartDateMonth",
			"StartDataYear",
			"ExpiryDateMonth",
			"ExpiryDateYear",
			"CardType",
			"IssueNumber",
			"CV2",
			"BillingFirstnames",
			"BillingSurname",
			"BillingAddress1",
			"BillingAddress2",
			"BillingCity",
			"BillingCountry",
			"BillingPostCode",
			"DeliveryFirstnames",
			"DeliverySurname",
			"DeliveryAddress1",
			"DeliveryAddress2",
			"DeliveryCity",
			"DeliveryCountry",
			"DeliveryPostCode",
			"DeliveryState",
			"DeliveryPhone",
			"CustomerEmail",
			"Amount"
		);
	
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
			DeliveryFirstnames
			DeliverySurname
			DeliveryAddress1
			DeliveryAddress2
			DeliveryCity
			DeliveryCountry
			DeliveryPostCode
			DeliveryState
			DeliveryPhone
			CustomerEmail
			Amount
		*/
	
		require(dirname(__FILE__) . "/sagepay/sagepay.class.php");

		$data = SagePay::formatRawData($transactionFieldData);
				
		$sgpy = new SagePay($data, $configuration);
		
		$sgpy->execute();
		
		return($sgpy->response);		
	
	}
	
	public function processPaymentNotification() {}

	public function runTest($configuration) {
		
		// ensure this is only a testing request
		$configuration["connect-to"] = "SIMULATOR";
	
		require(dirname(__FILE__) . "/sagepay/sagepay.class.php");
		$data = SagePay::formatRawData(array("Amount" => "123.45", "test" => true));
		
		$sgpy = new SagePay($data, $configuration);
		
		$sgpy->execute();
		
		// return the whole object so that it can be outputted
		return($sgpy);
	
	}
	
}


?>