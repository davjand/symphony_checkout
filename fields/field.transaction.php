<?php

	require_once(TOOLKIT . '/class.field.php');
	
	Class fieldTransaction extends Field{

		private $subFields = array(
						"vendor", 
						"vendor-tx-code", 
						"amount", 
						"currency",
						"billing-firstnames",
						"billing-surname",
						"billing-address1",
						"billing-address2",
						"billing-city",
						"billing-postcode",
						"billing-country",
						"billing-state",
						"billing-phone",
						"delivery-firstnames",
						"delivery-surname",
						"delivery-address1",
						"delivery-address2",
						"delivery-city",
						"delivery-postcode",
						"delivery-state",
						"delivery-phone",
						"customer-email"
					);
	
		public function __construct() {
			parent::__construct();
			$this->_name = "Sagepay Transaction";
		}
	
		public function commit(){

			if(!parent::commit()) return false;
			
			$id = $this->get('id');

			if($id === false) return false;
			
			$fields = array();
			
			$fields['field_id'] = $id;
			
			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
				
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
					
		}	
		
		public function displaySettingsPanel(&$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);	
			
			$this->appendRequiredCheckbox($wrapper);
			$this->appendShowColumnCheckbox($wrapper);	
		}
	
		public function createTable(){
			
			$query = "CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (`id` int(11) unsigned NOT NULL auto_increment,`entry_id` int(11) unsigned NOT NULL,";
			
			foreach($this->subFields as $f) {
				$query .= "`{$f}` varchar(255) default NULL,";
			}
			
			$query .= "PRIMARY KEY  (`id`), KEY `entry_id` (`entry_id`)) TYPE=MyISAM;";
			
			return Symphony::Database()->query(
				$query
			);
		}	
		
		public function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
		
			$label = Widget::Label("<b>" . $this->get('label') . "</b>");
			
			foreach($this->subFields as $f) {
				$label->appendChild(Widget::Label($f, Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']['.$f.']'.$fieldnamePostfix, (strlen($data[$f]) != 0 ? $data[$f] : NULL))));
			}

			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);
			
		}		
	
		public function processRawFieldData($data, &$status, $simulate = false, $entry_id = null) {

			$status = self::__OK__;
			
			// no processing just now
			$result = $data;

			return $result;
		}	
	
	}

?>