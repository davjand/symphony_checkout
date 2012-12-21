Symphony Checkout Extension
================

* Version 0.91
* Author: David Anderson and Tom Johnson
* Date: 21/12/2012
* Requirements: Symphony 2.3 or later

Installation
---------------

1. Upload the extension to extensions/symphony_checkout within a symphony install
2. Install/Enable it in the System/Extensions page
3. Attach the 'Process Payment' Event to the page you wish to serve as your 'checkout'
4. Attach the 'Respond to Postback' Event to a blank page
5. You need to now configure payment gateway (currently sagepay is supported) in System/Checkout Configuration
6. Add a 'transaction field' to your section and setup the mappings (see Mappings in the readme)
7. Aside from setting up your 'Process payment event' you're done!


Supported Gateways
---------------

1. Currently only sagepay server integration method is supported

@TODO


Transaction Field and Mappings
------------------------------

The transaction field serves as an interface between the payment gateway and symphony. It allows storing of payment amounts to be submitted to the gateway and 

@TODO


Process Payment Event
-----------------------

@TODO

Developer API
---------------

@TODO