<?php

// ***************************************
// gatewayfactory.class.php - a class which deals with enumerating available gateways and creates instances of them
// Tom Johnson 2012
// ***************************************


class PaymentGatewayFactory {

	public static function includeGateways() {		
		$includeDir = opendir( dirname(__FILE__) . "./gateways/" );
		while( false !== ( $includeFile = readdir( $includeDir ) ) ) {
			if( ( $includeFile != "." ) && ( $includeFile != ".." )) {
				$iPath =  dirname(__FILE__) . "/gateways/" . $includeFile;
				if(!is_dir($iPath)) {
					include_once($iPath);
				}
			}
		}
		closedir( $includeDir );
	}

	public static function getGatewayList() {
	
		$gatewayArr = array();
	
		$classArr = get_declared_classes();

		foreach($classArr as $c) {
			
			if(is_subclass_of($c, "PaymentGateway")) {
				$tmpC = new $c();
				$detailsArr = $tmpC->getDetailsArray();
				$gatewayArr[] = $detailsArr["name"];
			}
			
		}
		
		return $gatewayArr;
	
	}
	
	public static function createGateway($name) {
		
		if(class_exists($name)) {
			$gateway = new $name();
			return $gateway;
		}
		else {
			return null;
		}
		
	}


}

include_once(dirname(__FILE__) . "/gateway.class.php");
PaymentGatewayFactory::includeGateways();

?>