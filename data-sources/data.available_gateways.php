<?php

	require_once(TOOLKIT . '/class.datasource.php');

	Class datasourceavailable_gateways extends Datasource{

		public $dsParamROOTELEMENT = 'available-gateways';
		public $dsParamURL = 'http://symphonyextensions.com/api/extensions';
		public $dsParamXPATH = '/';
		public $dsParamCACHE = '0';
		public $dsParamTIMEOUT = '6';

		
		public function __construct(&$parent, $env=NULL, $process_params=true){
			parent::__construct($parent, $env, $process_params);
			$this->_dependencies = array();
			
			$urlParams = array();

			
			if(isset($_REQUEST['s'])) {
				$urlParams["keywords"] = $_REQUEST['s'];
			}
			
			if(isset($_REQUEST['p'])) {
				$urlParams["page"] = $_REQUEST['p'];
			}

			
			$paramStr = "";
			foreach($urlParams as $k => $v) {
				$paramStr .= $k . "=" . $v . "&";
			}
			$paramStr = substr($paramStr, 0, strlen($paramStr) - 1);
			
			$this->dsParamURL .= "?" . $paramStr;
		}

		
		
		
		public function about(){
			return array(
				'name' => 'Available Payment Gateways',
				'author' => array(
					'name' => 'Thomas Johnson',
					'website' => 'www.devjet.co.uk',
					'email' => 'jetbackwards@gmail.com'),
				'release-date' => '2012-05-25T20:41:00+00:00'
			);
		}

		public function getSource(){
			return 'static_xml';
		}

		public function allowEditorToParse(){
			return false;
		}

		public function grab(&$param_pool=NULL){
			$result = new XMLElement($this->dsParamROOTELEMENT);

			try{
			
				
				include(TOOLKIT . '/data-sources/datasource.static.php');
			}
			catch(FrontendPageNotFoundException $e){
				// Work around. This ensures the 404 page is displayed and
				// is not picked up by the default catch() statement below
				FrontendPageNotFoundExceptionHandler::render($e);
			}
			catch(Exception $e){
				$result->appendChild(new XMLElement('error', $e->getMessage()));
				return $result;
			}

			if($this->_force_empty_result) $result = $this->emptyXMLSet();

			

			return $result;
		}

	}
