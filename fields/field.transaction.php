<?php

	require_once(TOOLKIT . '/class.field.php');
	
	Class fieldTransaction extends Field{

		private $subFields = array(
						"gateway" => "text",
						"total-amount" => "text",
						"accepted-ok" => "checkbox",
						"processed-ok" => "checkbox",
						"local-transaction-id" => "text",						
						"remote-transaction-id" => "text",
						"security-key" => "text",
						"returned-info" => "text"
					);
	
		public function __construct() {
			parent::__construct();
			$this->_name = "Transaction";
			
			
			// these are storable details that may be stored elsewhere in the section
			// this are NOT a comprehensive list of what is required by the driver
			$this->set("mappings", "BillingFirstnames:||BillingSurname:||BillingAddress1:||BillingAddress2:||BillingCity:||BillingCountry:||BillingPostCode:||DeliveryFirstnames:||DeliverySurname:||DeliveryAddress1:||DeliveryAddress2:||DeliveryCity:||DeliveryCountry:||DeliveryPostCode:||DeliveryState:||DeliveryPhone:||CustomerEmail:");
			
		}
	
		// do we need to define any other functions? who knows?!
		public function canFilter() {
			return true;
		}
	
		public function commit(){

			if(!parent::commit()) return false;
			
			$id = $this->get('id');

			if($id === false) return false;
			
			$fields = array();
			
			$fields['field_id'] = $id;
		
			$useMappings = "";
			foreach($this->get('mappings') as $k => $v) {
				$useMappings .= $k . ":" . $v . "||";
			}
			$useMappings = substr($useMappings, 0, -2);
			
			$fields['mappings'] = $useMappings;
			print_r($this->get('mappings'));
			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
				
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());
					
		}	
		
		public function displaySettingsPanel(&$wrapper, $errors = null) {
			parent::displaySettingsPanel($wrapper, $errors);	
			
			$group = new XMLElement('div');
			$group->setAttribute("class", "two columns");
			
			$fieldset = new XMLElement('fieldset');
			
			$usingOptions = $this->__getFieldSelectOptions();
			
			foreach(explode("||", $this->get("mappings")) as $m) {
				$subM = explode(":", $m);
				$selectedOptions = $this->__getListWithSelectedOption($usingOptions, $subM[1]);
				$input = Widget::Select("fields[".$this->get('sortorder')."][mappings][".$subM[0]."]", $selectedOptions);
				$label = Widget::Label($subM[0], $input, "label", "", array("class" => "column"));			
				$fieldset->appendChild($label);
			}
		
			$group->appendChild($fieldset);
			$wrapper->appendChild($group);
			//$wrapper->appendChild($fieldset);
						
			$this->appendRequiredCheckbox($wrapper);
			$this->appendShowColumnCheckbox($wrapper);			
		}
		
		private function __getFieldSelectOptions() {
			$fields = FieldManager::fetch(NULL, Administration::instance()->Page->_context[1], 'ASC', 'sortorder', NULL, NULL);
			$options = array(
				array('', false, __('None'), ''),
			);
			$attributes = array(
				array()
			);
			if(is_array($fields) && !empty($fields)) {
				foreach($fields as $field) {
					$options[] = array($field->get('label'), 0);
				}
			};		
			return $options;		
		}
		
		private function __getListWithSelectedOption($options, $selectedValue) {
			// loop through the options array and select the specified value.
			// modularising the code like this allows __getFieldSelectOptions() to be
			// called only once but use many times with different selected options
		
			for($i=0;$i<count($options);$i++) {
				if($options[$i][0] == $selectedValue) {
					$options[$i][1] = 1;
				}
			}
					
			return $options;
				
		}
	
		public function createTable(){
			
			$query = "CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (`id` int(11) unsigned NOT NULL auto_increment,`entry_id` int(11) unsigned NOT NULL,";
			
			foreach($this->subFields as $f => $t) {
				$query .= "`{$f}` varchar(255) default NULL,";
			}
			
			$query .= "PRIMARY KEY  (`id`), KEY `entry_id` (`entry_id`)) TYPE=MyISAM;";
			
			return Symphony::Database()->query(
				$query
			);
		}	
		
		public function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){
		
			$title = Widget::Label($this->get('label'));
			$wrapper->appendChild($title);
		
			$container = new XMLElement("div");
			$container->setAttribute("class", "");
		
			$table = new XMLElement("table");	
			foreach($this->subFields as $f => $t) {
				$tr = new XMLElement("tr");
				$tr->appendChild(new XMLElement("td", $this->__prettifyValue($f)));
				$tr->appendChild(new XMLElement("td", $data[$f]));
				$table->appendChild($tr);
			}
			$container->appendChild($table);
			
			$wrapper->appendChild($container);
			
			
			/* ALLOWS EDITING FOR DEBUGGING! 
			$label = Widget::Label($this->get('label'));
			foreach($this->subFields as $f => $t) {
				$label->appendChild(Widget::Label($f, Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']['.$f.']'.$fieldnamePostfix, (strlen($data[$f]) != 0 ? $data[$f] : NULL), $t)));
			}

			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);
			*/
		}		

		private function __prettifyValue($value) {
			
			$value = str_replace("-", " ", $value);
			$value = ucwords($value);
			
			return $value;
		
		}
		
		public function appendFormattedElement(&$wrapper, $data, $encode=false) {
			
			$fieldRoot = new XMLElement($this->get('element_name'));
			
			foreach($this->subFields as $f => $t) {
				$newE = new XMLElement($f, $data[$f]);
				$fieldRoot->appendChild($newE);
			}
			
			$wrapper->appendChild($fieldRoot);
			
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