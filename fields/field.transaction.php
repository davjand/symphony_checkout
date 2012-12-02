<?php

	require_once(TOOLKIT . '/class.field.php');
	
	Class fieldTransaction extends Field{

		private $subFields = array(
						"payment-reference",
						"gateway",
						"total-amount"
					);
	
		public function __construct() {
			parent::__construct();
			$this->_name = "Transaction";
			
			$this->set("mappings", 
			"

			");
			
		}
	
		public function commit(){

			if(!parent::commit()) return false;
			
			$id = $this->get('id');

			if($id === false) return false;
			
			$fields = array();
			
			$fields['field_id'] = $id;
			$fields['mappings'] = $this->get('mappings');
			
			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
				
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
					
		}	
		
		public function displaySettingsPanel(&$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);	
			
			$label = Widget::Label(__('Data Mappings'));
			$label->appendChild(
				Widget::Textarea("fields[".$this->get('sortorder')."][mappings]", 15, 30, $this->get('mappings'))
			);
			
			$wrapper->appendChild($label);
			
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
	
		public function set($field, $value){
			if($field == 'related_field_id' && !is_array($value)){
				$value = explode(',', $value);
			}
			$this->_fields[$field] = $value;
		}	
	
	}

?>