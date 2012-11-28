<?php

	Class extension_checkout extends Extension {

		public function install() {

			Symphony::Database()->query('CREATE TABLE IF NOT EXISTS `tbl_fields_sagepay_transaction` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`field_id` int(11) unsigned NOT NULL,
					PRIMARY KEY  (`id`),
					KEY `field_id` (`field_id`)
			);');		
		
		}

		public function uninstall() {
		
		}
/*
		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'appendPreferences'
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'Save',
					'callback' => '__SavePreferences'
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'CustomActions',
					'callback' => '__toggleMaintenanceMode'
				),
				array(
					'page' => '/backend/',
					'delegate' => 'AppendPageAlert',
					'callback' => '__appendAlert'
				),
				array(
					'page' => '/blueprints/pages/',
					'delegate' => 'AppendPageContent',
					'callback' => '__appendType'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendPrePageResolve',
					'callback' => '__checkForMaintenanceMode'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendParamsResolve',
					'callback' => '__addParam'
				)
			);
		}
*/
	}
