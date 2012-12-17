<?php

include_once(dirname(__FILE__) . "/../gateway.class.php");

class SagepayGateway extends PaymentGateway {

	public function getConfigArray() {
		return array("connect-to", "description", "vendor-name", "currency", "transaction-type", "notification-url", "return-url");	
	}
	
	public function getDetailsArray() {
		return array("name" => "SagepayGateway");
	
	}
	
	public function getRequiredFieldsArray() {
	
		return array(
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
			"Description",
			"Amount"
		);
	
	}
	
	public function processTransaction($transactionFieldData, $configuration) {
	
		/* transaction field data should contain...
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
	
		$uniqueTxId = $this->generateUniqueTxCode($transactionFieldData);
	
		$constantArray = array(
			"VPSProtocol" => "2.23",
			"TxType" => $configuration["transaction-type"],
			"Vendor" => $configuration["vendor-name"],
			"VendorTxCode" => $uniqueTxId,
			"Currency" => $configuration["currency"],
			"NotificationURL" => $configuration["notification-url"]
			);
			
		$postData = array_merge($constantArray, $transactionFieldData);
		
		$url = "";
		// default the url simulator - much safer
		switch($configuration["connect-to"]) {
			case "LIVE":
				$url = "https://live.sagepay.com/gateway/service/vspserver-register.vsp";
			case "TEST":
				$url = "https://test.sagepay.com/gateway/service/vspserver-register.vsp";
			default:
				$url = "https://test.sagepay.com/Simulator/VSPServerGateway.asp?Service=VendorRegisterTx";			
		}
	
		$response = $this->doPost($url, $postData);
		
		// TODO: strip out the fields that we need to
		return array(
				"status" => $response["Status"], 
				"detail" => $response["StatusDetail"],
				"local-txid" => $uniqueTxId, 
				"remote-txid" => $response["VPSTxId"], 
				"security-key" => $response["SecurityKey"], 
				"redirect-url" => $response["NextURL"]
			);
		
	
	}
	
	public function extractLocalTxId($returnData) {
		return $returnData["VendorTxCode"];
	}
	
	public function processPaymentNotification($returnData, $storedData, $configuration) {

		//check signature first!
		$checkStr = "";
		$checkStr .= $returnData["VPSTxId"];
		$checkStr .= $storedData["VendorTxCode"];
		$checkStr .= $returnData["Status"];
		$checkStr .= $returnData["TxAuthNo"];
		$checkStr .= $configuration["vendor-name"];
		$checkStr .= $returnData["AVSCV2"];
		$checkStr .= $storedData["security-key"];
		$checkStr .= $returnData["AddressResult"];
		$checkStr .= $returnData["PostCodeResult"];
		$checkStr .= $returnData["CV2Result"];
		$checkStr .= $returnData["GiftAid"];
		$checkStr .= $returnData["3DSecureStatus"];
		$checkStr .= $returnData["CAVV"];
		$checkStr .= $returnData["AddressStatus"];
		$checkStr .= $returnData["PayerStatus"];
		$checkStr .= $returnData["CardType"];
		$checkStr .= $returnData["Last4Digits"];

		$eoln = chr(13) . chr(10);
		
		if(md5($checkStr) == strtolower($returnData["VPSSignature"])) {
			
			return array(
				"return-value" => "Status=OK{$eoln}RedirectURL=" . $configuration["return-url"] . "{$eoln}StatusDetail=Notification received successfully",
				"status" => "completed"
				);
				
		}
		else {
	
			return array(
				"return-value" => "Status=INVALID\r\nRedirectURL=" . $configuration["return-url"] . "{$eoln}StatusDetail=VPSSignature was incorrect " . md5($checkStr) . " computed " . strtolower($returnData["VPSSignature"]) . " expected",
				"status" => "completed"
				);	
	
		}
	
	}
	
	public function runTest($configuration) {
		
		// ensure this is only a testing request
		$configuration["connect-to"] = "SIMULATOR";
		
		$data = array();
		$data['BillingFirstnames'] = 'Tester';
		$data['BillingSurname'] = 'Testing';
		$data['BillingAddress1'] = '88';
		$data['BillingAddress2'] = '432 Testing Road';
		$data['BillingCity'] = 'Test Town';
		$data['BillingCountry'] = 'GB';
		$data['BillingPostCode'] = '412';
		$data['DeliveryFirstnames'] = 'Tester';
		$data['DeliverySurname'] = 'Testing';
		$data['DeliveryAddress1'] = '88';
		$data['DeliveryAddress2'] = '432 Testing Road';
		$data['DeliveryCity'] = 'Test Town';
		$data['DeliveryCountry'] = 'GB';
		$data['DeliveryPostCode'] = '412';
		$data["Description"] = "TestProduct";
		$data['Amount'] = "123.50";
	
		$storedData = array(
				"status" => "OK",
				"detail" => "Server transaction registered successfully.",
				"local-txid" => "TX-16-12-2012-80b6a984a56c1e8",
				"remote-txid" => "{EA0C23A5-E5F6-4B36-B5BF-644EC5FD3DDB}",
				"security-key" => "DL3LVMO3RY",
				"redirect-url" => "https://test.sagepay.com/Simulator/VSPServerPaymentPage.asp?TransactionID={EA0C23A5-E5F6-4B36-B5BF-644EC5FD3DDB}"				
			);
	
		$returnData = array(
			"VPSProtocol" => "2.23",
			"TxType" => "PAYMENT",
			"VendorTxCode" => "TX-16-12-2012-80b6a984a56c1e8",
			"VPSTxId" => "{EA0C23A5-E5F6-4B36-B5BF-644EC5FD3DDB}",
			"Status" => "OK",
			"StatusDetail" => "Transaction Successful",
			"TxAuthNo" => "123",
			"AVSCV2" => "ALL MATCH",
			"AddressResult" => "MATCHED",
			"PostCodeResult" => "MATCHED",
			"CV2Result" => "MATCHED",
			"GiftAid" => "0",
			"3DSecureStatus" => "OK",
			"CAVV" => "123",
			"AddressStatus" => "",
			"PayerStatus" => "",
			"CardType" => "VISA",
			"Last4Digits" => "1234",
			"VPSSignature" => "2831b4ce7c3995a2535a847a3a874d1a"
			);
	
		return array(
			"processTransaction" => $this->processTransaction($data, $configuration),
			"processPaymentNotification" => $this->processPaymentNotification($returnData, $storedData, $configuration)
			);
	
	}
	
}


?>