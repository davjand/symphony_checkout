<?php
	require_once(EXTENSIONS . '/symql/lib/class.symql.php');
	require_once(TOOLKIT . '/class.event.php');

	Class eventprocess_payment extends Event{

		const ROOTELEMENT = 'process-payment';
		
		public static $targetSection = 0;
		
		public $currentVersion = 0;
		public $eParamFILTERS = array();

		public static function about(){
			return array(
					 'name' => 'Process Payment',
					 'author' => array(
							'name' => 'Tom Johnson',
							'website' => 'http://www.devjet.co.uk',
							'email' => 'jetbackwards@gmail.com'),
					 'version' => '1.0',
					 'release-date' => '2010-01-19T23:37:24+00:00',
					 'trigger-condition' => 'action[process-payment]');
		}

		public static function getSource(){
			return self::$targetSection;
		}

		public static function allowEditorToParse(){
			return false;
		}

		public static function documentation(){
			return '
			&lt;h2&gt;Payment Processing Event&lt;/h2&gt;
			<p>The event allows payment processing through the checkout extension</p>
			
			';
		}

		public static function processRawMappings($rawFieldMappings) {
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
		public static function transferMappings(&$transactionData, $requiredFields, $processedMappings, $section_id) {
			
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
						$mappedValue = self::mappedFieldToValue($mappedFieldName, $section_id, $_POST["id"]);
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
		 * @param $sectionId The section Id
		 * @param $itemId The entry Id
		 *
		 * @return The value for the field
		 *
		 * Looks up a value for a mapped field
		 *
		*/
		public static function mappedFieldToValue($mappedFieldName, $sectionId, $itemId) {
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
		
		
		/**
		 * load
		 *
		 *
		 *
		*/
		public function load(){
			
			$TESTING = false;
			if($_POST["fields"]["test"]) {
				$TESTING = true;
			}
			
			if(isset($_POST["id"]) || $TESTING) {				
				
				// creates $savedSettings;
				include(dirname(__FILE__) . "/../config.php");
				
				// create the transactiondata array
				$transactionData = array();

				// resolve the transaction field from the entry id
				$section_id = EntryManager::fetchEntrySectionID($_POST["id"]);
				$field_list = FieldManager::fetch(null, $section_id);
				$transactionField = null;
				$transactionFieldName = null;
				foreach($field_list as $f) {
					if($f->_handle == "transaction") {
						$transactionField = $f;
						$transactionFieldName = $transactionField->get('element_name');
					}	
				}
				
				if($transactionField == null || $transactionFieldName == null){
					throw new Exception('No Transaction Field Found');
				}
				
				// put all the posted values in the transactiondata array
				$transactionData = array_merge($transactionData, $_POST["fields"][$transactionFieldName]);				
				
				$rawFieldMappings = $transactionField->get('mappings');
				$fieldMappings = self::processRawMappings($rawFieldMappings);
				
				include(dirname(__FILE__) . "/../lib/gatewayfactory.class.php");
				
				
				// create the gateway
				if($TESTING) {$gwName = "sagepaygateway";}
				//use the post data, ie: fields[field-name][gatweay]
				else if(isset($_POST['fields'][$transactionFieldName]["gateway"])){
					$gwName = $_POST['fields'][$transactionFieldName]["gateway"];
				}
				else{
					$gwName = $savedSettings['general']['gateway'];	
				}
				
				$gateway = PaymentGatewayFactory::createGateway($gwName);
				
				if($gateway == null){
					throw new Exception('Process Payments: Invalid Gateway');
				}

				// for any missing required values, transfer data using the mappings
				self::transferMappings($transactionData, $gateway->getRequiredFieldsArray(), $fieldMappings, $section_id);
						
				// post everything through the gateway
				$gatewayResponse = $gateway->processTransaction($transactionData, $savedSettings[$savedSettings["general"]["gateway"]]);
				
				// save posted information into the section - default event behavior
				// creates $result
				$_POST["fields"][$transactionField->get('element_name')]["gateway"] = $savedSettings["general"]["gateway"];
				$_POST["fields"][$transactionField->get('element_name')]["total-amount"] = $_POST["fields"]["Amount"];
				$_POST["fields"][$transactionField->get('element_name')]["accepted-ok"] = ($gatewayResponse["status"] == "OK" ? "on" : "off");
				$_POST["fields"][$transactionField->get('element_name')]["security-key"] = $gatewayResponse["security-key"];
				$_POST["fields"][$transactionField->get('element_name')]["local-transaction-id"] = $gatewayResponse["local-txid"];
				$_POST["fields"][$transactionField->get('element_name')]["remote-transaction-id"] = $gatewayResponse["remote-txid"];
				$_POST["fields"][$transactionField->get('element_name')]["returned-info"] = $gatewayResponse["detail"];
				
				
				self::$targetSection = $section_id;
				include(TOOLKIT . '/events/event.section.php');
				
				redirect($gatewayResponse["redirect-url"]);
				
			}
					
		}
	}
