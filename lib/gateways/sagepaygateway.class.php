<?php

include_once(dirname(__FILE__) . "/../gateway.class.php");

class SagepayGateway extends PaymentGateway {

	public function getConfigArray() {
		return array("connect-to", "description", "vendor-name", "currency", "transaction-type", "notification-url", "return-url","profile");	
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
	
	
	/**
		Process a transaction
		
	*/
	
	public function processTransaction($entryId, $transactionFieldData, $configuration) {
		
		/*
		
			Run some pre-flight checks
			
		*/
		$conf = $configuration;
		
		if($conf["vendor-name"] == null || strlen($conf["vendor-name"])==0){
			throw new Exception("SP00:Cannot Process Transaction, Vendor Name not set");
		}
		if($conf['transaction-type'] == null || strlen($conf['transaction-type'])==0){
			throw new Exception("SP00:Cannot Process Transaction, TxType not set");
		}
		if($conf['currency'] == null || strlen($conf['currency'])==0){
			throw new Exception("SP00:Cannot Process Transaction, Currency not set");
		}
		if($conf['notification-url'] == null || strlen($conf['notification-url'])==0){
			throw new Exception("SP00:Cannot Process Transaction, Notification URL not set");
		}
		if($conf['description'] == null || strlen($conf['description'])==0){
			throw new Exception("SP00:Cannot Process Transaction, Default Description not set");
		}
		if($conf['profile'] == null || strlen($conf['profile'])==0){
			throw new Exception("SP00:Cannot Process Transaction, Profile not set");
		}
		
		if($transactionFieldData['description'] == null || strlen($transactionFieldData['description'])==0){
			$transactionFieldData['description']=$conf['description'];
		}
		
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
		$uniqueTxId = $this->generateUniqueTxCode($entryId);
	
		$constantArray = array(
			"VPSProtocol" => "2.23",
			"TxType" => $configuration["transaction-type"],
			"Vendor" => $configuration["vendor-name"],
			"VendorTxCode" => $uniqueTxId,
			"Currency" => $configuration["currency"],
			"NotificationURL" => $configuration["notification-url"],
			"Profile" => $configuration["profile"] == "LOW" ? "LOW" : "NORMAL",
			);
			
			
		$postData = array_merge($constantArray, $transactionFieldData);
		
		
		$fieldData = array(
			'total-amount' => $postData[$this->getAmountFieldName()],
			'tx-type' => $postData["TxType"]
		);
		
		
		$url = "";
		// default the url simulator - much safer
		
		
		switch($configuration["connect-to"]) {
			case "LIVE":
				$url = "https://live.sagepay.com/gateway/service/vspserver-register.vsp";
				break;
			case "TEST":
				$url = "https://test.sagepay.com/gateway/service/vspserver-register.vsp";
				break;
			case "SHOWPOST":
				$url="https://test.sagepay.com/showpost/showpost.asp";
				break;
			default:
				$url = "https://test.sagepay.com/Simulator/VSPServerGateway.asp?Service=VendorRegisterTx";			
				break;
		}

		$response = $this->doPost($url, $postData);		
		
		$fieldData['accepted-ok'] = $response["Status"]== "OK" ? "on" : "off";
		$fieldData['returned-info'] = $response["Status"] ." (".$response["StatusDetail"].")";
		$fieldData['security-key'] = $response["SecurityKey"];
		$fieldData['auth-number'] = '';
		$fieldData['local-transaction-id'] = $uniqueTxId;
		$fieldData['remote-transaction-id'] = $response["VPSTxId"];
		$fieldData['tx-data'] = print_r($response,true);
		
		return array(
			'apiResponse' => $response,
			'fieldData' => $fieldData
		);	
	}

	
	
	/**
	
		IPN function to process IPN response
		
	*/
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
		
		
		$date = new DateTime();
		
		$fieldData = array(
			'tx-type' => $returnData['TxType'],
			'tx-data' => print_r($returnData,true),
			'auth-number' => $returnData['TxAuthNo'],
			'returned-info' => $returnData["Status"]." (".$returnData["StatusDetail"].")"
		);
		
		if(md5($checkStr) == strtolower($returnData["VPSSignature"])) {
			
			// we need to acknowledge a failure, but return a failed status
			$status = "failed";
			if($returnData["Status"] == "OK") {
				$status = "completed";
			}
			
			if($fieldData['tx-type']=='DEFERRED'){
				$fieldData['deferred-ok'] = 'on';
				$fieldData['processed-ok'] = 'off';
			}
			else{
				$fieldData['processed-ok'] = 'on';	
			}
			
			//build an array to be re-integrated into the response
			
			return array(
				"return-value" => "Status=OK\r\nRedirectURL=" . $storedData["return-url"] . "\r\nStatusDetail=Notification received successfully",
				"status" => $status,
				'fieldData' => $fieldData
				);
				
		}
		else {
			
			$fieldData['deferred-ok'] = 'off';
			$fieldData['processed-ok'] = 'off';
	
			return array(
				"return-value" => "Status=INVALID\r\nRedirectURL=" . $storedData["return-url"] . "{$eoln}StatusDetail=VPSSignature was incorrect " . md5($checkStr) . " computed " . strtolower($returnData["VPSSignature"]) . " expected",
				"status" => "failed",
				'fieldData' => $fieldData
				);	
	
		}
	
	}
	
	/**
	
	
		Function to process a deferred payment, either release or abort it
		
		@param Array $storedDate - the field data that is currently saved
		@param Boolean $paymentSuccessful - Release / Abort
		@param Array $configuration
		
		
		
	*/
	public function processDeferredPayment($storedData, $paymentSuccessful, $configuration) {
		
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
				break;
			case "TEST":
				break;
				$url = "https://test.sagepay.com/gateway/service/" . ( $paymentSuccessful ? "release" : "abort" ) . ".vsp";
			default:
				$url = "https://test.sagepay.com/Simulator/VSPServerGateway.asp?Service=" . ( $paymentSuccessful ? "VendorReleaseTx" : "VendorAbortTx" );
				break;		
		}
		$sageResponse = $this->doPost($url, $postArray);

		$fieldData = array(
			'tx-type' => $postArray['TxType'],
			'returned-info' => $sageResponse["Status"]." (".$sageResponse["StatusDetail"].")",
			'tx-data' => print_r($sageResponse,true)
		);
		
		if($paymentSuccessful && $sageResponse['Status']=="OK"){
			$fieldData['processed-ok']='on';
		}
		
		$response = array(
			'gateway' => $sageResponse,
			'fieldData' => $fieldData
		);

		return $response;		
		
	}
	
	
		
	/**

		Gets the transaction code from the IPN Data
		
	*/
	public function extractLocalTxId($returnData) {
		return $returnData["VendorTxCode"];
	}

	/**
	
		Filters unwanted characters out of an input string.
		Useful for tidying up FORM field inputs
		
	*/
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