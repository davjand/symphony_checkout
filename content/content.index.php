<?php

require_once(TOOLKIT . '/class.xsltpage.php');
require_once(TOOLKIT . '/class.administrationpage.php');

require_once(TOOLKIT . '/class.sectionmanager.php');
require_once(TOOLKIT . '/class.fieldmanager.php');
require_once(TOOLKIT . '/class.entrymanager.php');
require_once(TOOLKIT . '/class.entry.php');
require_once(EXTENSIONS . '/extension_installer/lib/extension-data.class.php');

require_once(TOOLKIT . '/class.datasource.php');
require_once(TOOLKIT . '/class.datasourcemanager.php');

require_once(CORE . '/class.cacheable.php');
require_once(CORE . '/class.administration.php');

require_once(EXTENSIONS . '/checkout/data-sources/data.available_gateways.php');


class contentExtensionCheckoutIndex extends AdministrationPage	
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
        $this->setTitle('Symphony - Configure Checkout');
		
    }

	public function about() {
	
	}

    public function view()
    {
		$this->__indexPage();
    }
	
	public function action() {
		print_r($_POST);
		if(isset($_POST["action"]["save"])) {		
			$this->pageAlert(__('Configuration Settings updated successfully.'), Alert::SUCCESS);
		}
	}
	
	private function __indexPage() {
		
		$link = new XMLElement('link');
		$this->addElementToHead($link, 500);	
		
		$this->setPageType('form');
		$this->appendSubheading(__('Checkout Configuration'));		
		
		$gatewayList = new datasourceavailable_gateways(Administration::instance(), array());
		$gatewayListXml = $gatewayList->grab();


		## Get Configuration Settings and display as a table list
		$config_settings = Symphony::Configuration()->get();

		$count = 0;
		
		$acronymsarray = array( 'db', 'gc', 'tbl', 'xml' );
		$smallwordsarray = array( 'in' );

		$fieldset = new XMLElement('fieldset');
		$fieldset->setAttribute('class', 'settings type-file');
		$fieldset->appendChild(new XMLElement('legend', __("General")));
		
		$label = Widget::Label("Active Gateways");
		
		$activeOptions = array();

		//include(dirname(__FILE__) . "/../lib/gatewayfactory.class.php");
		$gatewayList = PaymentGatewayFactory::getGatewayList();	
	
		foreach($gatewayList as $g) {
			$activeOptions[] = array(strtolower($g), false, $g);
		}
	
	
		$label->appendChild(Widget::Select("settings[general][gateways][]", $activeOptions, array("multiple" => "multiple")));	
		
		//$label->appendChild(Widget::Input("settings[test]", "hello", "text"));
		$fieldset->appendChild($label);
		
		$this->Form->appendChild($fieldset);

		## Save Button
		$div = new XMLElement('div');
		$div->setAttribute('class', 'actions');
		$div->appendChild(Widget::Input('action[save]', __('Save Settings'), 'submit', array('accesskey' => 's')));
		$this->Form->appendChild($div);		
		
	}

}

