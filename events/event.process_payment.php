<?php

	require_once(TOOLKIT . '/class.event.php');

	Class eventprocess_payment extends Event{

		const ROOTELEMENT = 'process-payment';
		
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
			return '';
		}

		public static function allowEditorToParse(){
			return false;
		}

		public static function documentation(){
			return '';
		}

		public function load(){
		
			
		}
	}
