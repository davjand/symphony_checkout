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
			Process payment will run the payment through the gateway specified by \$_POST[gateway] provided that it is active. <br/><br/>
			
			Each payment gateway has specified required number of fields. If they are not present in $_POST[fields], they will be looked for in the defined data mappings. <br/><br/>
			
			Posted data and response data will be stored inside the transaction field.<br/>
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
					
					// assign the value back into the transactionData
					$transactionData[$f] = $mappedValue;
					
				}
			}	
		}
		
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
		
		public function load(){
			
			$testing = false;
			if($_POST["fields"]["test"]) {
				$testing = true;
			}
			
			if(isset($_POST["id"]) || $testing) {				
				
				if($testing) {
					$gwName = "SagepayGateway";
				}
				else {
					$gwName = $_POST["gateway"];
				}
			
				// creates $savedSettings;
				include(dirname(__FILE__) . "/../config.php");
				
				// create the transactiondata array
				$transactionData = array();
				
				// put all the posted values in the transactiondata array
				$transactionData = array_merge($transactionData, $_POST["fields"]);

				// resolve the transaction field from the entry id
				$section_id = EntryManager::fetchEntrySectionID($_POST["id"]);
				$field_list = FieldManager::fetch(null, $section_id);
				$transactionField = null;
				foreach($field_list as $f) {
					if($f->_handle == "transaction") {
						$transactionField = $f;
					}	
				}				
				
				$rawFieldMappings = $transactionField->get('mappings');
				$fieldMappings = self::processRawMappings($rawFieldMappings);
				
				// create the gateway
				include(dirname(__FILE__) . "/../lib/gatewayfactory.class.php");
				$gateway = PaymentGatewayFactory::createGateway($gwName);
				
				// for any missing required values, transfer data using the mappings
				self::transferMappings(&$transactionData, $gateway->getRequiredFieldsArray(), $fieldMappings, $section_id);
										
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
