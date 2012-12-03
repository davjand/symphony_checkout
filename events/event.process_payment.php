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
				
				// get the mappings for this specific field
				$transactionField = FieldManager::fetch($savedSettings["general"]["attached-field"], $savedSettings["general"]["attached-section"]);
				if(is_array($transactionField)) { $transactionField = $transactionField[32]; }
				$rawFieldMappings = $transactionField->get('mappings');
				
				$fieldMappings = array();
				foreach(explode("\r\n", $rawFieldMappings) as $f) {
					$mapParts = explode(":", $f);
					$fieldMappings[$mapParts[0]] = $mapParts[1];
				}
				
				// create the gateway
				include(dirname(__FILE__) . "/../lib/gatewayfactory.class.php");
				$gateway = PaymentGatewayFactory::createGateway($gwName);
				

				
				// for any missing required values, transfer data using the mappings
				foreach($gateway->getRequiredFieldsArray() as $f) {
					if(!isset($transactionData[$f]) && !$testing) {
						// we weren't passed the data... let's grab it from the mappings
						$mappedFieldName = $mapParts[$f];
						
						// we know we have the id...
						$query = new SymQLQuery();
						$query->from($savedSettings["general"]["attached-section"]);
						$query->where("system:id", $_POST["id"]);
						$query->select($mappedFieldName);
						
						$mappedValue = SymQL::run($query, RETURN_ARRAY);
							
						// assign the value back into the transactionData
						$transactionData[$f] = $mappedValue;
						
					}
				}			
				
				// REMOVED field presence check - code above will assure field integrity
						
				// post everything through the gateway
				$transactionReturn = $gateway->processTransaction($transactionData , $savedSettings[$gwName]);
						
				print_r($transactionField);
						
				// save posted information into the section - default event behavior
				// creates $result
				$_POST["fields"][$transactionField->_name]["gateway"] = $gwName;
				$_POST["fields"][$transactionField->_name]["total-amount"] = $_POST["fields"]["Amount"];
				$_POST["fields"][$transactionField->_name]["returned-info"] = print_r($transactionReturn, true);
				
				self::$targetSection = $savedSettings["general"]["attached-section"];
				if(!$testing) {
					include(TOOLKIT . '/events/event.section.php');
				}
				else {
					print_r($_POST);
				}
				
			}
					
		}
	}
