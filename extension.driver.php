<?php

	Class extension_symphony_checkout extends Extension {

		public function install() {

			Symphony::Database()->query('CREATE TABLE IF NOT EXISTS `tbl_fields_transaction` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`field_id` int(11) unsigned NOT NULL,
					`mappings` MEDIUMTEXT NOT NULL,
					PRIMARY KEY  (`id`),
					KEY `field_id` (`field_id`)
			);');	
			
			
			// make an initial config file
			self::saveConfig(array());
		}
	
		public function fetchNavigation(){ 
			return array(
				array(
					'location'	=> __('System'),
					'name'		=> __('Checkout Configuration'),
					'link'		=> '/',
					'limit'		=> 'developer'
				)
			);
		}	
		
		public function update($previousVersion = false){
			
			/*
				
				v0.9.8
				
			*/
			if(version_compare($previousVersion, '0.9.8', '<')){
				try{
					$fields = Symphony::Database()->fetchCol('field_id',
						"SELECT `field_id` FROM `tbl_fields_transaction`"
					);
				}
				catch(Exception $e){
					// Discard
				}

				if(is_array($fields) && !empty($fields)){
					foreach($fields as $field_id){
						try{
							Symphony::Database()->query(
								"ALTER TABLE `tbl_entries_data_{$field_id}`
								ADD COLUMN `surcharge` VARCHAR(255) default NULL"
							);
						}
						catch(Exception $e){
							// Discard
						}
					}
				}
			}
			
			
			if(version_compare($previousVersion, '0.9.7', '<')){
				try{
					$fields = Symphony::Database()->fetchCol('field_id',
						"SELECT `field_id` FROM `tbl_fields_transaction`"
					);
				}
				catch(Exception $e){
					// Discard
				}

				if(is_array($fields) && !empty($fields)){
					foreach($fields as $field_id){
						try{
							Symphony::Database()->query(
								"ALTER TABLE `tbl_entries_data_{$field_id}`
								MODIFY `tx-data` MEDIUMTEXT default NULL"
							);
						}
						catch(Exception $e){
							// Discard
						}
					}
				}
			}
			
			if(version_compare($previousVersion, '0.9.6', '<')){
				try{
					$fields = Symphony::Database()->fetchCol('field_id',
						"SELECT `field_id` FROM `tbl_fields_transaction`"
					);
				}
				catch(Exception $e){
					// Discard
				}

				if(is_array($fields) && !empty($fields)){
					foreach($fields as $field_id){
						try{
							Symphony::Database()->query(
								"ALTER TABLE `tbl_entries_data_{$field_id}`
								ADD COLUMN `tx-data` VARCHAR(255) default NULL"
							);
						}
						catch(Exception $e){
							// Discard
						}
					}
				}
			}
			
			
			if(version_compare($previousVersion, '0.9.3', '<')){
				try{
					$fields = Symphony::Database()->fetchCol('field_id',
						"SELECT `field_id` FROM `tbl_fields_transaction`"
					);
				}
				catch(Exception $e){
					// Discard
				}

				if(is_array($fields) && !empty($fields)){
					foreach($fields as $field_id){
						try{
							Symphony::Database()->query(
								"ALTER TABLE `tbl_entries_data_{$field_id}`
								ADD COLUMN `tx-type` VARCHAR(255) default NULL,
								ADD COLUMN `deferred-ok` VARCHAR(255) default NULL
								"
							);
						}
						catch(Exception $e){
							// Discard
						}
					}
				}
			}

			return true;
		}
		
		public function uninstall() {
			if(parent::uninstall() == true){
				Symphony::Database()->query("DROP TABLE `tbl_fields_transaction`");
				return true;
			}
			return false;		
		}
		
		public function modifyHeaders($page) {
			// Our postback needs a text/plain content type. This is a pain to arrange in 
			// Symphony apart from in a delegate like this. We detect the respond_to_postback
			// event loaded in the current page and then set the type accordingly.
			$pageData = Frontend::Page()->pageData();
			$loadedEvents = explode(",",$pageData["events"]);
			if(in_array("respond_to_postback", $loadedEvents)) {
				Frontend::Page()->addHeaderToPage("Content-Type", "text/plain; charset=UTF-8");
			}
		}
		
		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendPreRenderHeaders',
					'callback' => 'modifyHeaders'
				)
			);
		}
		
		public static function getConfig() {
			
			$path = self::getConfigPath();
			
			if(!file_exists($path)){
				self::saveConfig(array());
			}
			include($path);	
			return $savedSettings;
		}
		
		public static function saveConfig($config) {
			file_put_contents(self::getConfigPath(), "<?php \$savedSettings = " . var_export($config, true) . "; ?>");			
		}
		
		private static function getConfigPath() {
			return (MANIFEST . "/sc_config.php");		
		}

	}
