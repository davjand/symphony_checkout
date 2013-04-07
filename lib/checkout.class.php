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
		
		Function to intially process a payment
		
		@param Integer $entryId
		@param String [gatewayHandle]
		
		@returns Array gatewayResponse
	*/	
	public function processPayment($entryId, $gatewayHandle = null){
			
		/*
		
			Determine the gateway
			
		*/
		
		if($gatewayHandle == null){
			$gatewayHandle = $this->settings['general']['gateway'];
		}
		if($gatewayHandle == 'test' || $gatewayHandle == null){
			throw new Exception("Invalid Gateway");
		}
		$gateway = PaymentGatewayFactory::createGateway($gatewayHandle);
		
		
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
		$transactionData[$gateway->getAmountFieldName()] = $transactionAmount;
				
		
		/*
		
			Send to the gateway
			
		*/	
		$gatewayResponse = $gateway->processTransaction($transactionData, $this->settings[$gatewayHandle]);
		
		
		/*
		
			Process the gateway response and save into symphony
			
		*/
		$data = array(
			$transactionFieldName => array(
				'gateway' => $gatewayHandle,
				'total-amount' => $transactionAmount,
				'accepted-ok' => ($gatewayResponse["status"] == "OK" ? "on" : "off"),
				'security-key' => $gatewayResponse["security-key"],
				'auth-number' => '',
				'local-transaction-id' => $gatewayResponse["local-txid"],
				'remote-transaction-id' => $gatewayResponse["remote-txid"],
				'returned-info' => $gatewayResponse["detail"],
				'return-url' => $this->settings[$this->settings["general"]["gateway"]]["return-url"]
		));
		
		$errors = array();
		$entry->setDataFromPost($data,$errors, false, true);
		$entry->commit();
		
		return $gatewayResponse;
	}
	
	

	
	
	/*
	
	
	
	
	
		!HELPER FUNCTIONS
		
		
		
		
			
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