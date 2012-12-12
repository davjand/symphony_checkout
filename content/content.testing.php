<?php

require_once(TOOLKIT . '/class.xsltpage.php');
require_once(TOOLKIT . '/class.administrationpage.php');

require_once(TOOLKIT . '/class.sectionmanager.php');
require_once(TOOLKIT . '/class.fieldmanager.php');
require_once(TOOLKIT . '/class.entrymanager.php');
require_once(TOOLKIT . '/class.entry.php');

require_once(TOOLKIT . '/class.datasource.php');
require_once(TOOLKIT . '/class.datasourcemanager.php');

require_once(CORE . '/class.cacheable.php');
require_once(CORE . '/class.administration.php');

require_once(EXTENSIONS . '/symphony_checkout/data-sources/data.available_gateways.php');


class contentExtensionSymphony_checkoutTesting extends AdministrationPage	
{	

/*    public function __construct(&$parent)
    {
        parent::__construct($parent);

    }
*/
    public function build()
    {
        parent::build();
		$this->setPageType('form');
        $this->setTitle('Symphony - Checkout Testing');
		
    }

	public function about() {

	}

    public function view()
    {
		$this->__indexPage();
    }
	
	public function action() {
	
	}
	
	private function __indexPage() {
		
		$link = new XMLElement('link');
		$this->addElementToHead($link, 500);	
		
		$this->setPageType('form');
		$this->appendSubheading(__('Checkout Testing'));

		$linkSet = new XMLElement('fieldset');
		$linkSet->setAttribute('class', 'settings type-file');
		$linkSet->appendChild(new XMLElement('legend', __("Select Gateway")));
		
		include(dirname(__FILE__) . "/../lib/gatewayfactory.class.php");
		$gatewayList = PaymentGatewayFactory::getGatewayList();
		foreach($gatewayList as $g) {
			$linkSet->appendChild(Widget::Input("action[requested]", $g, "submit"));
		}		
		$this->Form->appendChild($linkSet);
		
		if(isset($_POST["action"]["requested"])) {
		
			$outputSet = new XMLElement("fieldset");
			$outputSet->setAttribute("class", "settings type-file");
			$outputSet->appendChild(new XMLElement("legend", __($_POST["action"]["requested"] . " Output")));
			
			$testGateway = PaymentGatewayFactory::createGateway($_POST["action"]["requested"]);
			
			// give it some config - if this might alter it from test behaviour then each gateway test routine should allow for this
			// creates $savedSettings
			include(dirname(__FILE__) . "/../config.php");
			
			$testOutput = print_r($testGateway->runTest($savedSettings[$_POST["action"]["requested"]]), true);

			// format the output
			$testOutput = nl2br($testOutput);
			
			$outputSet->appendChild(Widget::Label($testOutput));
			
			$this->Form->appendChild($outputSet);		
		}		
	
	}

}

