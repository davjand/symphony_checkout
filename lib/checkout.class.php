<?php
/*
	checkout.class.php
	SymphonyCheckout class
	Used to perform checkout functions
	
	David Anderson 2013
	dave@veodesign.co.uk
*/
require_once(TOOLKIT . '/class.entrymanager.php');
require_once(TOOLKIT . '/class.fieldmanager.php');
require_once(TOOLKIT . '/class.entry.php');
require_once(EXTENSIONS . '/symql/lib/class.symql.php');


require_once(dirname(__FILE__) . "/gatewayfactory.class.php");


class SymphonyCheckout{
	
	public $settings;
	
	function __construct(){
		
		$this->settings = self::getConfig();
		
		return;
	}
	
	/**
		Gets the configuration	
	*/
	public static function getConfig() {
		$path = self::getConfigPath();
			
		if(!file_exists($path)){
			self::saveConfig(array());
		}
		include($path);
		
		return $savedSettings;
	}
	
	
	/**
		Saves the configuration		
	*/
	public static function saveConfig($config) {
		file_put_contents(self::getConfigPath(), "<?php \$savedSettings = " . var_export($config, true) . "; ?>");			
	}
	
	/**
		Gets the config path
	*/
	private static function getConfigPath() {
		return (MANIFEST . "/sc_config.php");		
	}
	
	
	/**
		Retrieve the transaciton field from an entryId
		
		@param Integer $entryId
		
	*/
	private function getTransactionField($entryId){
	
		$sectionId = EntryManager::fetchEntrySectionID($entryId);
		$fieldList = FieldManager::fetch(null, $sectionId);
		
		$transactionField = null;
		
		foreach($fieldList as $f) {
			if($f->_handle == "transaction") {
				$transactionField = $f;
			}	
		}
		if($transactionField == null){
			throw new Exception('No Transaction Field Found');
		}
		return $transactionField;
	}
	
	
	/**
		Helper function to retrieve the name of the transaction field
	*/
	private function getTransactionFieldName($entryId){
		return $this->getTransactionField($entryid)->get('element_name');
	}
	
	/*
		
		Get an entry from an id
		
	*/
	public function getEntryFromId($entryId){
		$sectionId = EntryManager::fetchEntrySectionID($entryId);
		if($sectionId == null){
			throw(new Exception('Transaction Entry Not Found'));
		}
		$entries = EntryManager::fetch($entryId,$sectionId);
		
		if($entries == null || count($entries)<1){
			throw(new Exception('Transaction Entry Not Found'));
		}
		return $entries[0];
	}
	
	
	/**
	
		Get the transaction field Data from an entry Id
		
	*/
	public function getTransactionDataFromId($entryId){
		$entry = $this->getEntryFromId($entryId);
		$field = $this->getTransactionField($entryId);

		return $entry->getData($field->get('id'));	
	}
	
	/**
		
		Find out if a deferred payment was successful
		
	*/
	public function isDeferredPaymentSuccess($entryId){
		$data = $this->getTransactionDataFromId($entryId);
		
		if($data['accepted-ok'] == 'on' && $data['deferred-ok'] == 'on' && $data['processed-ok'] == 'off'){
			return true;
		}
		return false;
	}
	
	
	/**
		
		Function to intially process a payment
		
		@param Integer $entryId
		@param String [gatewayHandle]
		
		@returns Array gatewayResponse
	*/	
	public function processPayment($entryId, $gatewayHandle = null){
			
		/*
		
			Determine the gateway
			
		*/
		$gatewayHandle = $this->settings['general']['gateway'];
		$gateway = $this->getGateway();

		
		/*
		
			Get the entry
			
		*/
		$entry = $this->getEntryFromId($entryId);

		/*
		
			Get the transaction field
			
		*/
		$sectionId = EntryManager::fetchEntrySectionID($entryId);
		$transactionField = $this->getTransactionField($entryId);
		$transactionFieldName = $transactionField->get('element_name');
		
		
		/*
		
			Process the transaction data mappings
			
		*/
		
		$transactionData = array();
		$fieldMappings = $this->processRawMappings($transactionField->get('mappings'));
		
		$this->transferMappings($transactionData, $gateway->getRequiredFieldsArray(), $fieldMappings, $entryId);
		
		//Process the amount field (special case)
		$transactionAmount = null;
		$transactionAmount = $this->mappedFieldToValue($fieldMappings['Amount'], $entryId);
		
		//format the amount
		$transactionAmount = number_format(floatval($transactionAmount),2, '.', '');
					
		$transactionData[$gateway->getAmountFieldName()] = $transactionAmount;
				
		
		/*
		
			Send to the gateway
			
		*/	
		$gatewayResponse = $gateway->processTransaction($entryId, $transactionData, $this->settings[$gatewayHandle]);
		
		
		/*
		
			Process the gateway response and save into symphony
			
		*/
		$fieldData = array(
			'gateway' => $gatewayHandle,
			'return-url' => $this->settings[$gatewayHandle]['return-url']
		);		
		$this->mergePaymentResponseIntoField($fieldData,$gatewayResponse['fieldData']);
				
		$dataToSave[$transactionField->get('element_name')]=$fieldData;
		$errors = array();
		$entry->setDataFromPost($dataToSave,$errors, false, true);
		$entry->commit();
		
		return $gatewayResponse;
	}	
	
	
	/**
	
		Process an IPN postback
		
		@param Array $data Usually the $_REQUEST array	
		
	*/

	function processIPN($data){
		
		/*
		
			Determine the gateway . Entry
			
		*/
		$gateway = $this->getGateway();
		$txCode = $gateway->extractLocalTxId($data);
		$entryId = $gateway->getEntryIdFromTxCode($txCode);
		$sectionId = EntryManager::fetchEntrySectionID($entryId);
				
		$entry = $this->getEntryFromId($entryId);
		$field = $this->getTransactionField($entryId);

		$storedData = $entry->getData($field->get('id'));
		
		/**
		
			Process the response
			
		*/
		$gatewayResponse = $gateway->processPaymentNotification($data, $storedData, $this->getGatewaySettings());
		
		
		/**
		
			Save the response into the field
			
		*/
		//merge the response data
		$this->mergePaymentResponseIntoField($storedData,$gatewayResponse['fieldData']);
		$dataToSave = array(
			$field->get('element_name') => $storedData
		);
				
		//process the 'completed checkbox'
		$mappings = $this->getKeyValueCodedMappings($field->get('mappings'));
		
		if(array_key_exists('PaymentCompletedCheckbox',$mappings) && $mappings['PaymentCompletedCheckbox'] != null){
			$dataToSave[$mappings['PaymentCompletedCheckbox']] = $storedData["processed-ok"];
		}
		
		$entry->setDataFromPost($dataToSave, $errors, false, true);
		$entry->commit();
		
		//add the entryId to the response
		$gatewayResponse['entryId']=$entryId;
		
		return $gatewayResponse;
	}
	
	
	/**
	
		Either release or abort a deferred payment
		
		@param Integer $entryId The Entry ID
		@param Boolean $release true to release / false to abort
	
	*/	
	function processDeferredPayment($entryId,$release){
	
		$gateway = $this->getGateway();
		$sectionId = EntryManager::fetchEntrySectionID($entryId);
				
		$entry = $this->getEntryFromId($entryId);
		$field = $this->getTransactionField($entryId);

		$storedData = $entry->getData($field->get('id'));
		
		$gatewayResponse = $gateway->processDeferredPayment($storedData, $release, $this->getGatewaySettings());
		
		/**
		
			Save the response into the field
			
		*/
		$this->mergePaymentResponseIntoField($storedData,$gatewayResponse['fieldData']);
		$dataToSave = array(
			$field->get('element_name') => $storedData
		);
				
		//process the 'completed checkbox'
		$mappings = $this->getKeyValueCodedMappings($field->get('mappings'));
		
		if(array_key_exists('PaymentCompletedCheckbox',$mappings) && $mappings['PaymentCompletedCheckbox'] != null){
			$dataToSave[$mappings['PaymentCompletedCheckbox']] = $storedData["processed-ok"];
		}
		
		$entry->setDataFromPost($dataToSave, $errors, false, true);
		$entry->commit();
		
		//add the entryId to the response
		$gatewayResponse['entryId']=$entryId;
		
		return $gatewayResponse;
		
	}

	
	
	/*
	
	
	
	
	
		!HELPER FUNCTIONS
		
		
		
		
			
	*/
	
	
	/**
		Return the current active gateway
		throws an error if not set
	*/
	public function getGateway(){
	
		$gatewayHandle = $this->settings['general']['gateway'];
		
		if($gatewayHandle == 'test' || $gatewayHandle == null){
			throw new Exception("Invalid Gateway");
		}
		return PaymentGatewayFactory::createGateway($gatewayHandle);	
	}
	
	/**
	
		Get the currently active gateway specific settings
		
	*/
	public function getGatewaySettings(){
		$gatewayHandle = $this->settings['general']['gateway'];
		return $this->settings[$gatewayHandle];
	}
	
	/**
		Function to merge a new payment response into the current data array
		
		Uses the storedData field to maintain an audit trail
	*/
	protected function mergePaymentResponseIntoField(&$storedData,$fieldData){
		foreach($fieldData as $key => $val){
			if($key == 'tx-data'){
				//create an audit trail
				$date = new DateTime();
				
				if(strlen($storedData[$key]) < 1){
					//$storedData[$key]= "";
				}
				 $storedData[$key] = $storedData[$key] . $date->format('Y-m-d H:i:s')."\n".$fieldData[$key]."\n\n";
			}
			else{
				$storedData[$key] = $val;
			}
		}
	}
	

	
	/**
	
		A function to convert a mappings string into a key/value coded arrau
		
		@param String $mappings
		@returns Array
		
	*/
	public function getKeyValueCodedMappings($mappings){
		$mappings = explode("||",$mappings);
		
		if(count($mappings)<1){
			return array();
		}
		
		$arr = array();
		foreach($mappings as $m){
			$map = explode(':',$m);
			if(count($map)!=2){
				throw new Exception("Checkout: Invalid Mappings Data (".$m.")");
			}
			$arr[$map[0]] = $map[1];
		}
		return $arr;
	}

	
	/**
	
		Process raw field mappings
		
	*/
	public function processRawMappings($rawFieldMappings) {
		$fieldMappings = array();
		foreach(explode("||", $rawFieldMappings) as $f) {
			$mapParts = explode(":", $f);
			$fieldMappings[$mapParts[0]] = $mapParts[1];
		}	
		return $fieldMappings;
	}
	
	
	/**
	 * transferMappings
	 *
	 * @param &$transactionData {Array} The raw transaction data we have so far
	 * @param $requiredFields {Array} The fields required (by the gateway)
	 * @param $processedMappings {Array}
	 * @param $section_id {int} The section Id so we can do lookup
	 *
	 * This function maps the data to create a unified $transactionData array 
	 * Not returned as passed by reference
	 *
	*/
	public function transferMappings(&$transactionData, $requiredFields, $processedMappings, $entryId) {
		
		foreach($requiredFields as $f) {
			if(!isset($transactionData[$f])) {
				// we weren't passed the data... let's grab it from the mappings
				$mappedFieldName = $processedMappings[$f];
				
				$mappedValue = "";
				
				// the symql can fail massively if we don't have a mapping for a required field - since there
				// is no way of enforcing this we should return an empty string if something weird happens and
				// just let the rest of the code bumble along (I suspect that the failure will then happen at the
				// request level but we have better handling there).
				try {
					$mappedValue = $this->mappedFieldToValue($mappedFieldName, $entryId);
				}
				catch(Exception $e) {}
				
				// assign the value back into the complete array
				$transactionData[$f] = $mappedValue;
			}
		}
	}
	
	
	/**
	 * mappedFieldToValue
	 *
	 * @param $mappedFieldName The name of the field
	 * @param $itemId The entry Id
	 *
	 * @return The value for the field
	 *
	 * Looks up a value for a mapped field
	 *
	*/
	public function mappedFieldToValue($mappedFieldName, $itemId) {
		
		$sectionId = EntryManager::fetchEntrySectionID($itemId);
	
		$query = new SymQLQuery();
		$query->from($sectionId);
		$query->where("system:id", $itemId);
		$query->select($mappedFieldName);
		$entriesList = SymQL::run($query, SymQL::RETURN_ENTRY_OBJECTS);
		$mappedValue = null;
		foreach($entriesList["entries"] as $e) {
			foreach($e->getData() as $fVal) {
				$mappedValue = $fVal["value"];
			}
		}
		
		return $mappedValue;
	}
	
}


?>