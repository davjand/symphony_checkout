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
			
			$configFilename = dirname(__FILE__) . "/config.php";
			file_put_contents($configFilename, "<?php \$savedSettings; ?>");	
		
		
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

	}
