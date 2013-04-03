Symphony Checkout Extension
================

* Version 0.91
* Author: David Anderson and Tom Johnson
* Date: 21/12/2012
* Requirements: Symphony 2.3 or later


Symphony Checkout is an extension that aims to bring more robust payment functionality to symphony. Inspired by paypal payments and PGI loader, Symphony Checkout provides a Transaction field that can be used to store the relevent data to process a transaction.

A 'Transaction' field goes into a section whoes entries represent a payment. The field can then map in data from the other fields in the entry (such as names, addresses, emails etc) in order to pass the relevent data to a payment processor. The field can then post the data to the payment processor via an event and then reconcile the transaction into the field.

Currently only sagepay server integration is supported but a test payment gateway class is provided which can be extended for any other payment gateway.

@TODO Finish documentation

NB: Currently the price field is grabbed from the section which means that it has to be pre populated. For use on a 'basket' based ecommerce website, your basket extension needs to save the data into the section. There are obviously security issues here that need to be resolved but this extension currently doesn't cater for that. I have a cart extension (davjand/symphony_cart) that could potentially be integrated in the future.


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

Please see event code


Developer API
---------------

@TODO