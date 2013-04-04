<?php
	require_once(EXTENSIONS . '/symql/lib/class.symql.php');
	require_once(TOOLKIT . '/class.event.php');
	
	require_once(dirname(__FILE__) . "/../lib/checkout.class.php");

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
			<h3>Payment Processing Event</h3>
			<p>The event allows payment processing through the checkout extension</p>
			<h3>Sample Form</h3>
			<pre class="xml"><code>
	&lt;form method=&quot;post&quot; action=&quot;&quot;&gt;
  			&lt;input type=&quot;hidden&quot; name=&quot;id&quot; value=&quot;{entry-id}&quot;/&gt;
 			 &lt;input type=&quot;hidden&quot; name=&quot;fields[transaction_field_name][Description]&quot; value=&quot;A Description for the transaction&quot;/&gt;
 			 &lt;input type=&quot;hidden&quot; name=&quot;fields[transaction_field_name][gateway] value = &quot;sagepaygateway&quot; /&gt; (OPTIONAL)
 			 &lt;input type=&quot;hidden&quot; name=&quot;return-url&quot; value = &quot;http://mysite.com/mycustomurl&quot; /&gt; (OPTIONAL)
 			 &lt;input type=&quot;submit&quot; name=&quot;action[process-payment]&quot; value=&quot;Submit&quot;/&gt;
 &lt;/form&gt;
			</pre></code>
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
		*/
		public function load(){
			if(isset($_POST['action']['process-payment'])){
				return $this->__trigger();
			}
		}
		
		/**
		 * __trigger
		 *
		 *
		 *
		*/
		public function __trigger(){
			
			$TESTING = false;
			if($_POST["fields"]["test"]) {
				$TESTING = true;
			}
			
			if(isset($_POST["id"]) || $TESTING) {				
				
				// creates $savedSettings;
				$savedSettings = extension_symphony_checkout::getConfig();
				
				// create the transactiondata array
				$transactionData = array();
				$transactionEntryId = $_POST['id'];	

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
				
				/*
				 * ! Build the transaction data into the correct format
				 *
				*/
				
				// for any missing required values, transfer data using the mappings
				$transactionData = array_merge($transactionData, $_POST["fields"][$transactionFieldName]);				
				
				//get the field mappings from the transaction field
				$fieldMappings = self::processRawMappings($transactionField->get('mappings'));
				
				//use all the mappings to build the array
				self::transferMappings($transactionData, $gateway->getRequiredFieldsArray(), $fieldMappings, $section_id);
				
				//Process the amount field (special case)
				$transactionAmount = null;
				$transactionAmount = self::mappedFieldToValue($fieldMappings['Amount'], $section_id, $transactionEntryId);				
				$transactionData[$gateway->getAmountFieldName()] = $transactionAmount;
						
				// post everything through the gateway
				$gatewayResponse = $gateway->processTransaction($transactionData, $savedSettings[$savedSettings["general"]["gateway"]]);
				
				// save posted information into the section - default event behavior
				// creates $result
				$_POST["fields"][$transactionFieldName] = array();
				$_POST["fields"][$transactionFieldName]["gateway"] = $gwName;
				$_POST["fields"][$transactionFieldName]["total-amount"] = $transactionAmount;
				$_POST["fields"][$transactionFieldName]["accepted-ok"] = ($gatewayResponse["status"] == "OK" ? "on" : "off");
				$_POST["fields"][$transactionFieldName]["security-key"] = $gatewayResponse["security-key"];
				$_POST["fields"][$transactionFieldName]["auth-number"] = "";
				$_POST["fields"][$transactionFieldName]["local-transaction-id"] = $gatewayResponse["local-txid"];
				$_POST["fields"][$transactionFieldName]["remote-transaction-id"] = $gatewayResponse["remote-txid"];
				$_POST["fields"][$transactionFieldName]["returned-info"] = $gatewayResponse["detail"];
				// allows setting of return url on a transaction-by-transaction basis
				$_POST["fields"][$transactionFieldName]["return-url"] = ( isset($_POST["return-url"]) ? $_POST["return-url"] : $savedSettings[$savedSettings["general"]["gateway"]]["return-url"] );
				
				self::$targetSection = $section_id;
				include(TOOLKIT . '/events/event.section.php');
				
				redirect($gatewayResponse["redirect-url"]);
				
			}
					
		}
	}
