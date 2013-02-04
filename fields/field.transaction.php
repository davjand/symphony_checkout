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
						"returned-info" => "text",
						"return-url" => "text"
					);
	
		public function __construct() {
			parent::__construct();
			$this->_name = "Transaction";
			
			
			// these are storable details that may be stored elsewhere in the section
			// this are NOT a comprehensive list of what is required by the driver
			$this->set("mappings", "BillingFirstnames:||BillingSurname:||BillingAddress1:||BillingAddress2:||BillingCity:||BillingState::||BillingCountry:||BillingPostCode:||DeliveryFirstnames:||DeliverySurname:||DeliveryAddress1:||DeliveryAddress2:||DeliveryCity:||DeliveryState:||DeliveryCountry:||DeliveryPostCode:||DeliveryPhone:||CustomerEmail:||Description:||Amount:||PaymentCompletedCheckbox:");
			
		}

		public function canFilter() {
			return false;
		}

		public function prepareTableValue($data, XMLElement $link = null, $entry_id = null) {
			$max_length = Symphony::Configuration()->get('cell_truncation_length', 'symphony');
			$max_length = ($max_length ? $max_length : 75);	

			// only this line is modified from the default
			$value = strip_tags($data['local-transaction-id']);

			if(function_exists('mb_substr') && function_exists('mb_strlen')) {
				$value = (mb_strlen($value, 'utf-8') <= $max_length ? $value : mb_substr($value, 0, $max_length, 'utf-8') . '…');
			}
			else {
				$value = (strlen($value) <= $max_length ? $value : substr($value, 0, $max_length) . '…');
			}

			if (strlen($value) == 0) $value = __('None');

			if ($link) {
				$link->setValue($value);

				return $link->generate();
			}

			return $value;
			
		}
	
		public function commit(){

			if(!parent::commit() || $this->get('id') === false || $this->handle() === false) {
				return false;
			}

			$fields = array();
			
			$fields['field_id'] = $id;
		
			$useMappings = "";
			foreach($this->get('mappings') as $k => $v) {
				$useMappings .= $k . ":" . $v . "||";
			}
			$useMappings = substr($useMappings, 0, -2);
			
			$fields['mappings'] = $useMappings;
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
					$options[] = array($field->get('element_name'), 0, $field->get('label'));
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
	
		function displayDatasourceFilterPanel(&$wrapper, $data=null, $errors=null, $fieldnamePrefix=null, $fieldnamePostfix=null) {
			parent::displayDatasourceFilterPanel($wrapper, $data, $errors, $fieldnamePrefix, $fieldnamePostfix);

			$text = new XMLElement('p', __("Use different subfield prefixes for different filter options (e.g. 'security-key|hello' to filter where the security key = 'hello'."), array('class' => 'help') );
			$wrapper->appendChild($text);
		}	
		
		public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation=false) {

			// Current field id
			$field_id = $this->get('id');

			// ONLY SIMPLE AND FILTERING FOR NOW
			
			// Filters connected with AND
			if($andOperation) {
				$op = '=';
				if(preg_match('/^not:\s*/i', $data[0], $m)) {
					$data[0] = str_replace($m[0], '', $data[0]);
					$op = '!=';
				}

				foreach($data as $value) {
					$this->_key++;
					$joins .= " LEFT JOIN `tbl_entries_data_{$field_id}` AS `t{$field_id}_{$this->_key}` ON (`e`.`id` = `t{$field_id}_{$this->_key}`.entry_id) ";
					// the where clause is where our filtering actually happens
					$filterParts = explode("|", $value);
					$where .= " AND `t{$field_id}_{$this->_key}`.{$filterParts[0]} {$op} '{$filterParts[1]}' ";
				}
			}
			
			return true;
			
		}		
		
	
		public function set($field, $value){
			if($field == 'related_field_id' && !is_array($value)){
				$value = explode(',', $value);
			}
			$this->_fields[$field] = $value;
		}	
	
	}

?>