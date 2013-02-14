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
		);
	}
	
	/**
	 * getAmountFieldName
	 *
	 * Returns the field name that should be used for the amount field
	 * Needed so that the extension can tie up the amount field.
	*/
	public function getAmountFieldName(){
		return "Amount";
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

	// Filters unwanted characters out of an input string.  Useful for tidying up FORM field inputs
	private function __cleanInput($strRawText,$strType)
	{

		if ($strType=="Number") {
			$strClean="0123456789.";
			$bolHighOrder=false;
		}
		else if ($strType=="VendorTxCode") {
			$strClean="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_.";
			$bolHighOrder=false;
		}
		else {
			$strClean=" ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789.,'/{}@():?-_&£$=%~<>*+\"";
			$bolHighOrder=true;
		}
		
		$strCleanedText="";
		$iCharPos = 0;
			
		do
		{
			// Only include valid characters
			$chrThisChar=substr($strRawText,$iCharPos,1);
				
			if (strspn($chrThisChar,$strClean,0,strlen($strClean))>0) { 
				$strCleanedText=$strCleanedText . $chrThisChar;
			}
			else if ($bolHighOrder==true) {
					// Fix to allow accented characters and most high order bit chars which are harmless 
					if (bin2hex($chrThisChar)>=191) {
						$strCleanedText=$strCleanedText . $chrThisChar;
					}
				}
				
			$iCharPos=$iCharPos+1;
			}
		while ($iCharPos<strlen($strRawText));
			
		$cleanInput = ltrim($strCleanedText);
		return $cleanInput;

	}
	
	public function processPaymentNotification($returnData, $storedData, $configuration) {
		
		
		// clean everything - can affect the md5
		foreach($returnData as $k => $v) {
			$returnData[$k] = $this->__cleanInput($v, "Text");
		}	
		foreach($storedData as $k => $v) {
			$storedData[$k] = $this->__cleanInput($v, "Text");
		}	
		foreach($configuration as $k => $v) {
			$configuration[$k] = $this->__cleanInput($v, "Text");
		}	
	
		//check signature first!
		$checkStr = "";
		$checkStr .= $returnData["VPSTxId"];
		$checkStr .= $returnData["VendorTxCode"];
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

		if(md5($checkStr) == strtolower($returnData["VPSSignature"])) {
			
			// we need to acknowledge a failure, but return a failed status
			$status = "failed";
			if($returnData["Status"] == "OK") {
				$status = "completed";
			}
			
			
			return array(
				"return-value" => "Status=OK\r\nRedirectURL=" . $storedData["return-url"] . "\r\nStatusDetail=Notification received successfully",
				"status" => $status,
				"tx-auth-no" => $returnData["TxAuthNo"]
				);
				
		}
		else {
	
			return array(
				"return-value" => "Status=INVALID\r\nRedirectURL=" . $storedData["return-url"] . "{$eoln}StatusDetail=VPSSignature was incorrect " . md5($checkStr) . " computed " . strtolower($returnData["VPSSignature"]) . " expected",
				"status" => "failed"
				);	
	
		}
	
	}
	
	public function processReleasePayment($storedData, $paymentSuccessful, $configuration) {
		
		$postArray = array(
			"VPSProtocol" => "2.23",
			"TxType" => ($paymentSuccessful ? "RELEASE" : "ABORT"),
			"Vendor" => $configuration["vendor-name"],
			"VendorTxCode" => $storedData["local-transaction-id"],
			"VPSTxId" => $storedData["remote-transaction-id"],
			"SecurityKey" => $storedData["security-key"],
			"TxAuthNo" => $storedData["auth-number"]
			);	

		if($paymentSuccessful) {
			$postArray["ReleaseAmount"] = $storedData["total-amount"];	
		}
		
		$url = "";
		// default the url simulator - much safer
		switch($configuration["connect-to"]) {
			case "LIVE":
				$url = "https://live.sagepay.com/gateway/service/" . ( $paymentSuccessful ? "release" : "abort" ) . ".vsp";
			case "TEST":
				$url = "https://test.sagepay.com/gateway/service/" . ( $paymentSuccessful ? "release" : "abort" ) . ".vsp";
			default:
				$url = "https://test.sagepay.com/Simulator/VSPServerGateway.asp?Service=" . ( $paymentSuccessful ? "release" : "abort" );			
		}

		$response = $this->doPost($url, $postData);

		return strtolower($response["Status"]);		
		
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