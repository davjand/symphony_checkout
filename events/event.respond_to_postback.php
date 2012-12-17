<?php
	require_once(EXTENSIONS . '/symql/lib/class.symql.php');
	require_once(TOOLKIT . '/class.event.php');

	Class eventrespond_to_postback extends Event{

		const ROOTELEMENT = 'respond-to-postback';
		
		public static $targetSection = 0;
		
		public $currentVersion = 0;
		public $eParamFILTERS = array();

		public static function about(){
			return array(
					 'name' => 'Respond To Postback',
					 'author' => array(
							'name' => 'Tom Johnson',
							'website' => 'http://www.devjet.co.uk',
							'email' => 'jetbackwards@gmail.com'),
					 'version' => '1.0',
					 'release-date' => '2010-01-19T23:37:24+00:00',
					 'trigger-condition' => 'action[respond-to-postback]');
		}

		public static function getSource(){
			return self::$targetSection;
		}

		public static function allowEditorToParse(){
			return false;
		}

		public static function documentation(){
			return '
			Reponds to the post from the gateway using the active gateway format.<br/><br/>
			This event should be attached to a page and the page URL should be listed in the Checkout Configuration as the notification-url.<br/><br/>
			The page that this event is attached to should not return any additional text or data. It should also not output any headers (i.e. it should use the JSON template).
			';
		}
		
		public function load(){
			
			// creates $savedSettings;
			include(dirname(__FILE__) . "/../config.php");
			
			// create the gateway
			include(dirname(__FILE__) . "/../lib/gatewayfactory.class.php");
			$gateway = PaymentGatewayFactory::createGateway($savedSettings["general"]["gateway"]);
			
			$transactionId = $gateway->extractLocalTxId($_POST);
			
			// get the information related to this transaction id - a pretty involved process!...
			$storedData = null;
			$fieldName = "";
			// first get all the fields
			$field_list = FieldManager::fetch();
			foreach($field_list as $f) {
				// now just the fields that are transactions
				if($f->_handle == "transaction") {
					
					// now the section that this relates to
					$section = SectionManager::fetch($f->get('parent_section'));
					
					// now we have enough for SYMQL!
					require_once(EXTENSIONS . '/symql/lib/class.symql.php');
					$query = new SymQLQuery();
					// run the query to get the transaction field data for all entries from this section
					$query->select($f->get('element_name'))->from($section->get('handle'));
					$entriesList = SymQL::run($query, SymQL::RETURN_ENTRY_OBJECTS);
					
					// loop down into each entry object's data
					foreach($entriesList["entries"] as $e) {
						foreach($e->getData() as $fVal) {
							// in the above step we automatically will skip past any entries that don't have
							// transaction data so we don't need to do any additional checks
							
							// have we found the local tx id?
							if($fVal["local-transaction-id"] == $transactionId) {
								// grab it!!
								$storedData = $fVal;
								// save the entry id
								$_POST["id"] = $e->get('id');
								// save the field name
								$fieldName = $f->get('element_name');
								// set the target section for when we save everything
								self::$targetSection = $f->get('parent_section');
							}
						}
					}
					
				}	
			}			
			
			// to recap - we now have the stored data related to the transaction in $storedData
			$gatewayResponse = $gateway->processPaymentNotification($_POST, $storedData, $savedSettings[$savedSettings["general"]["gateway"]]);
			
			// save the result in the field
			$_POST["fields"][$fieldName]["processed-ok"] = ($gatewayResponse["status"] == "completed" ? "on" : "off");
			//include(TOOLKIT . '/events/event.section.php');			
			
			// ? should we echo it or use it as XML, echo for now.
			echo($gatewayResponse["return-value"]);
			die();
			//return (new XMLElement(self::ROOTELEMENT,));
			//return (new XMLElement(self::ROOTELEMENT, print_r($_POST, true)));
			
		}
	}
