<?php

require_once(TOOLKIT . '/class.xsltpage.php');
require_once(TOOLKIT . '/class.administrationpage.php');

require_once(TOOLKIT . '/class.sectionmanager.php');
require_once(TOOLKIT . '/class.fieldmanager.php');
require_once(TOOLKIT . '/class.entrymanager.php');
require_once(TOOLKIT . '/class.entry.php');
//require_once(EXTENSIONS . '/extension_installer/lib/extension-data.class.php');

require_once(TOOLKIT . '/class.datasource.php');
require_once(TOOLKIT . '/class.datasourcemanager.php');

require_once(CORE . '/class.cacheable.php');
require_once(CORE . '/class.administration.php');

require_once(EXTENSIONS . '/symphony_checkout/data-sources/data.available_gateways.php');


class contentExtensionSymphony_checkoutIndex extends AdministrationPage	
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
		if(isset($_POST["action"]["save"])) {
			extension_symphony_checkout::saveConfig($_POST["settings"]);
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
	
		
		// Get the saved settings from the file - this will populate $savedSettings
		$savedSettings = extension_symphony_checkout::getConfig();
	
		// Link to testing
		$linkSet = new XMLElement('fieldset');
		$linkSet->setAttribute('class', 'settings type-file');
		$linkSet->appendChild(new XMLElement('legend', __("Links")));
		$linkSet->appendChild(Widget::Anchor("Checkout Testing", "./testing"));
		$this->Form->appendChild($linkSet);
	
		// General settings section
		$fieldset = new XMLElement('fieldset');
		$fieldset->setAttribute('class', 'settings type-file');
		$fieldset->appendChild(new XMLElement('legend', __("General")));
		$label = Widget::Label("Active Gateway");
		$activeOptions = array();
		$gatewayList = PaymentGatewayFactory::getGatewayList();	
		foreach($gatewayList as $g) {
			$isItemSelected = false;
			if($savedSettings["general"]["gateway"]) {
				if(strtolower($g) == $savedSettings["general"]["gateway"]) {
					$isItemSelected = true;
				}
			}
			$activeOptions[] = array(strtolower($g), $isItemSelected, $g);
		}
		$label->appendChild(Widget::Select("settings[general][gateway]", $activeOptions));	
		$fieldset->appendChild($label);

		$this->Form->appendChild($fieldset);

		
		// Retrieve all the gateway setting names
		$gatewaySettings = array();
		$classArr = get_declared_classes();
		foreach($classArr as $c) {
			if(is_subclass_of($c, "PaymentGateway")) {
				$tmpC = new $c();
				$detailsArr = $tmpC->getDetailsArray();
				$gatewaySettings[$detailsArr["name"]] = $tmpC->getConfigArray();
			}
		}		

		
		// Add settings for each of the included gateways
		foreach($gatewaySettings as $g => $c) {
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings type-file');
			$fieldset->appendChild(new XMLElement('legend', __($g)));
			foreach($c as $item) {
				$label = Widget::Label($item);
				$lowerg = strtolower($g);
				$label->appendChild(Widget::Input("settings[{$lowerg}][{$item}]", $savedSettings[$lowerg][$item]));
				$fieldset->appendChild($label);
			}			
			$this->Form->appendChild($fieldset);
		}
		
		
		
		$div = new XMLElement('div');
		$div->setAttribute('class', 'actions');
		$div->appendChild(Widget::Input('action[save]', __('Save Settings'), 'submit', array('accesskey' => 's')));
		$this->Form->appendChild($div);		
		
	}

}

