<?php
/**
 * SagePay class
 * Handles the formatting of requests to SagePay,
 * the actual request and response of the request
 *
 * @package Payment
 * @author Pete Robinson <work@pete-robinson.co.uk>
 * @version 1.0
 * @license http://www.gnu.org/copyleft/gpl.html GPL License 2
 **/

class SagePay
{
        
        public
                $status = '',           // status returned from the cURL request
                $error = '',            // stores any errors
                $vendorTxCode = '',     // vendor transaction code. must be unqiue
                $acsurl = '',           // used to store data for 3D Secure
                $pareq = '',            // used to store data for 3D Secure
                $md = '';                       // used to store data for 3D Secure
        private
                $env = '',                      // environment, set according to 'ENV' site constant
                $url = '',                      // the URL to post the cURL request to (set further down)
                $data = array(),        // the data to post
                $price = 0,                     // transaction amount
                $standardFields = array(), // holds standard SagePay info (currency etc)
                $response = array(),    // response from SagePay cURL request
                $description = 'New order from your online store',      // Description of the order sent to SagePay
                $curl_str = '';         // the url encoded string derrived from the $this->data array
        
                $type = 'PAYMENT',       // Transaction type
                $protocol_version = '2.22',      // SagePay protocol vers no
                $vendor_name = 'my_vendor_name',      // Your SagePay vendor name
                $currency = 'gbp';       // Currency transaction is to be in. For multi-currency sites, you need to change this from a constant to a property
                
        
        /**
         * Constructor method
         * Sets the $this->env property, assigns the necessary urls,
         * sets the price, sets and formats the data to pass to SagePay
         * @return void
         * @param arr $data - the data provided by the user (billing, price and card)
         **/
        public function __construct($data, $config)
        {
				// mod - set some config options
				$this->type = $config["type"];
				$this->vendor_name = $config["vendor-name"];
				$this->description = $config["description"];
				$this->currency = $config["currency"];
				$this->env = $config["connect-to"];
		
                // sets the url to post to based on ENV
                $this->setUrls();
                $this->setPrice($data['Amount']);
                // adds all of the config fields to the data array
                $this->setData($data);
                // converts $this->data from an array into a query string
                $this->formatData();
        }
        
        
        /**
         * setData method
         * Assigns the user data and SagePay config data to $this->data
         * @return void
         * @param arr $data - billing and card info from user
         **/
        private function setData($data)
        {
                // Set the billing, card and purchase details as provided by the user
                $this->data = $data;
                
                // Format the StartDate field
                if($data['StartDateMonth']){
                        // If so, add start date to data array to be appended to POST
                        $this->data['StartDate'] = $data['StartDateMonth'] . $data['StartDateYear'];
                }

                // Format the ExpiryDate field
                $this->data['ExpiryDate'] = $data['ExpiryDateMonth'] . $data['ExpiryDateYear'];
                
                // set the vendorTxCode
                $this->vendorTxCode = $data['VendorTxCode'];
                
                // set the required fields to pass to SagePay
                $this->standardFields = array(
                        'VPSProtocol' => $this->protocol_version,
                        'TxType' => $this->type,
                        'Vendor' => $this->vendor_name,
                        'VendorTxCode' => $this->vendorTxCode,
                        'Amount' => $this->price,
                        'Currency' => $this->currency,
                        'Description' => $this->description
                );
                
                // Add Payment Type
                $this->data['PaymentType'] = $this->type;
                
                // Add currency details
                $this->data['Currency'] = $this->currency;
                
                // Add vendor and transaction details
                $this->data['VendorTxCode'] = $this->vendorTxCode;
                $this->data['Description'] = $this->description;
                $this->data['Vendor'] = $this->vendor_name;
        }
        
        
        /**
         * setUrls method
         * Selects which SagePay url to use (live or test)
         * based on the $this->env property
         * @return void
         **/
        private function setUrls()
        {
                $this->url = ($this->env == 'DEVELOPMENT') ? 'https://test.sagepay.com/gateway/service/vspdirect-register.vsp' : 'https://live.sagepay.com/gateway/service/vspdirect-register.vsp';
        }
        
        
        /**
         * setPrice method
         *
         * @return void
         **/
        private function setPrice($price)
        {
                $this->price = $price;
        }
        
        
        /**
         * setVendorTxCode method
         *
         * @return void
         **/
        private function setVendorTxCode($code)
        {
                $this->vendorTxCode = $code;
        }
        
        
        /**
         * formatData method
         * Takes $this->data and converts it to
         * a url encoded query string
         * @return void
         **/
        private function formatData()
        {
                $arr = array();

                // loop through $this->data
                foreach($this->data as $key => $value){
                        // assign as an item of $arr (field=value)
                        $arr[] = $key . '='. urlencode($value);
                }

                // Implode the array using & as the glue and store the data
                $this->curl_str = implode('&', $arr);
        }



        /**
         * execute method
         * Executes the cURL request to SagePay and formats the result
         *
         * @return void
         **/
        public function execute()
        {
                // Max exec time of 1 minute.
                set_time_limit(60);
                
                // Open cURL request
                $curlSession = curl_init();

                // Set the url to post request to
                curl_setopt ($curlSession, CURLOPT_URL, $this->url);
                // cURL params
                curl_setopt ($curlSession, CURLOPT_HEADER, 0);
                curl_setopt ($curlSession, CURLOPT_POST, 1);
                // Pass it the query string we created from $this->data earlier
                curl_setopt ($curlSession, CURLOPT_POSTFIELDS, $this->curl_str);
                // Return the result instead of print
                curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, 1);
                // Set a cURL timeout of 30 seconds
                curl_setopt($curlSession, CURLOPT_TIMEOUT,30);
                curl_setopt($curlSession, CURLOPT_SSL_VERIFYPEER, FALSE);


                // Send the request and convert the return value to an array
                $response = preg_split('/$\R?^/m',curl_exec($curlSession));
                
                // Check that it actually reached the SagePay server
                // If it didn't, set the status as FAIL and the error as the cURL error
                if (curl_error($curlSession)){
                        $this->status = 'FAIL';
                        $this->error = curl_error($curlSession);
                }

                // Close the cURL session
                curl_close ($curlSession);
                
                // Turn the reponse into an associative array
                for ($i=0; $i < count($response); $i++){
                        // Find position of first "=" character
                        $splitAt = strpos($response[$i], "=");
                        // Create an associative array
                        $this->response[trim(substr($response[$i], 0, $splitAt))] = trim(substr($response[$i], ($splitAt+1)));
                }
                
                // Return values. Assign stuff based on the return 'Status' value from SagePay
                switch($this->response['Status']) {
                        case 'OK':
                                // Transactino made succssfully
                                $this->status = 'success';
                                $_SESSION['transaction']['VPSTxId'] = $this->response['VPSTxId']; // assign the VPSTxId to a session variable for storing if need be
                                $_SESSION['transaction']['TxAuthNo'] = $this->response['TxAuthNo']; // assign the TxAuthNo to a session variable for storing if need be
                                break;
                        case '3DAUTH':
                                // Transaction required 3D Secure authentication
                                // The request will return two parameters that need to be passed with the 3D Secure
                                $this->acsurl = $this->response['ACSURL']; // the url to request for 3D Secure
                                $this->pareq = $this->response['PAReq']; // param to pass to 3D Secure
                                $this->md = $this->response['MD']; // param to pass to 3D Secure
                                $this->status = '3dAuth'; // set $this->status to '3dAuth' so your controller knows how to handle it
                                break;
                        case 'REJECTED':
                                // errors for if the card is declined
                                $this->status = 'declined';
                                $this->error = 'Your payment was not authorised by your bank or your card details where incorrect.';
                                break;
                        case 'NOTAUTHED':
                                // errors for if their card doesn't authenticate
                                $this->status = 'notauthed';
                                $this->error = 'Your payment was not authorised by your bank or your card details where incorrect.';
                                break;
                        case 'INVALID':
                                // errors for if the user provides incorrect card data
                                $this->status = 'invalid';
                                $this->error = 'One or more of your card details where invalid. Please try again.';
                                break;
                        case 'FAIL':
                                // errors for if the transaction fails for any reason
                                $this->status = 'fail';
                                $this->error = 'An unexpected error has occurred. Please try again.';
                                break;
                        default:
                                // default error if none of the above conditions are met
                                $this->status = 'error';
                                $this->error = 'An error has occurred. Please try again.';
                                break;
                }
        }
        
        
        /**
         * formatRawData static method
         * Takes the array from the form the user fills out
         * and returns an array with the correct array keys assigned to each item
         * 
         * @param array $arr - the array of user data to process
         * @return array
         **/
        public static function formatRawData($arr)
        {
                $data = array();
                // this is test card details for SagePay, if we are testing, we don't want to use live card details
                if($arr["Testing"] == true) {
                        // this is where the VendorTxCode is set. Once it's set here, don't set it anywhere else, use this one
                        $data['VendorTxCode'] = 'prefix_' . time() . rand(0, 9999);
                        $data['CardHolder'] = 'DELTA';
                        $data['CardNumber'] = '4462000000000003';
                        $data['StartDateMonth'] = '01';
                        $data['StartDateYear'] = '05';
                        $data['ExpiryDateMonth'] = '01';
                        $data['ExpiryDateYear'] = '12';
                        $data['CardType'] = 'VISA';
                        $data['IssueNumber'] = '';
                        $data['CV2'] = '123';
                        $data['BillingFirstnames'] = 'Tester';
                        $data['BillingSurname'] = 'Testing';
                        $data['BillingAddress1'] = '88';
                        $data['BillingAddress2'] = '432 Testing Road';
                        $data['BillingCity'] = 'Test Town';
                        $data['BillingCountry'] = 'GB';
                        $data['BillingPostCode'] = '412';
                        $data['Amount'] = $arr['total'];
                } else {
                        // this is where the VendorTxCode is set. Once it's set here, don't set it anywhere else, use this one
                        $data['VendorTxCode'] = 'prefix_' . time() . rand(0, 9999);
                        
                        // If you're using different names for your input fields for this data (card and billing address), use this section
                        // to map the data to the array keys that SagePay expects. I've used the same keys so this piece of code is pretty much redundant
                        $data['CardHolder'] = $arr['CardHolder'];
                        $data['CardNumber'] = $arr['CardNumber'];
                        $data['StartDateMonth'] = $arr['StartDateMonth'];
                        $data['StartDateYear'] = $arr['StartDateYear'];
                        $data['ExpiryDateMonth'] = $arr['ExpiryDateMonth'];
                        $data['ExpiryDateYear'] = $arr['ExpiryDateYear'];
                        $data['CardType'] = $arr['CardType'];
                        $data['IssueNumber'] = $arr['IssueNumber'];
                        $data['CV2'] = $arr['CV2'];
                        $data['BillingFirstnames'] = $arr['BillingFirstnames'];
                        $data['BillingSurname'] = $arr['BillingSurname'];
                        $data['BillingAddress1'] = $arr['BillingAddress1'];
                        $data['BillingAddress2'] = $arr['BillingAddress2'];
                        $data['BillingCity'] = $arr['BillingCity'];
                        $data['BillingCountry'] = $arr['BillingCountry'];
                        $data['BillingPostCode'] = $arr['BillingPostCode'];
                        $data['Amount'] = $arr['Amount'];
                }
                
                return $data;
        }
        
}

/**
 * SecureAuth class
 * Handles the integration with 3dSecure
 *
 * @package Payment
 * @author Pete Robinson <work@pete-robinson.co.uk>
 * @version 1.0
 * @license http://www.gnu.org/copyleft/gpl.html GPL License 2
 **/
class SecureAuth
{
        
        public
                $vendorTxCode = '',     // vendor transaction code. must be unqiue
                $status = '',           // status returned from the cURL request
                $error = '';            // stores any errors
        private
                $md = '',                       // param received from SagePay to pass with the 3D Secure request
                $pareq = '',            // param received from SagePay to pass with the 3D Secure request
                $data = array(),        // the data to post to the 3D Secure server
                $response = '',         // the response from the server
                $url = '',                      // the url to pos the cURL request to
                $env = '',                      // the environment, set according to 'ENV' site constant
                $curl_str = '';         // the url encoded string derrived from the $this->data array
        
        
        /**
         * Constructor method
         * Sets the $this->env property, assigns the necessary urls,
         * sets and formats the data to pass to 3D Secure
         * @return void
         * @param arr $data - the data provided by the user (billing, price and card)
         **/
        public function __construct($data)
        {
                $this->data = $data;
                
                $this->env = ENV;
                $this->setUrls();
                $this->formatData();
                $this->execute();
        }
        
        /**
         * setUrls method
         * Selects which SagePay url to use (live or test)
         * based on the $this->env property
         * @return void
         **/
        private function setUrls()
        {
                $this->url = ($this->env == 'DEVELOPMENT') ? 'https://test.sagepay.com/gateway/service/direct3dcallback.vsp' : 'https://live.sagepay.com/gateway/service/direct3dcallback.vsp';
        }
        
        
        /**
         * formatData method
         * Takes $this->data and converts it to
         * a url encoded query string
         * @return void
         **/
        private function formatData()
        {
                // Initialise arr variable
                $str = array();

                // Step through the fields
                foreach($this->data as $key => $value){
                        // Stick them together as key=value pairs (url encoded)
                        $str[] = $key . '=' . urlencode($value);
                }

                // Implode the arry using & as the glue and store the data
                $this->curl_str = implode('&', $str);
        }
        
        
        /**
         * execute method
         * Executes the cURL request to SagePay and formats the result
         *
         * @return void
         **/
        private function execute()
        {
                // Max exec time of 1 minute.
                set_time_limit(60);
                // Open cURL request
                $curlSession = curl_init();

                // Set the url to post request to
                curl_setopt ($curlSession, CURLOPT_URL, $this->url);
                // cURL params
                curl_setopt ($curlSession, CURLOPT_HEADER, 0);
                curl_setopt ($curlSession, CURLOPT_POST, 1);
                // Pass it the query string we created from $this->data earlier
                curl_setopt ($curlSession, CURLOPT_POSTFIELDS, $this->curl_str);
                // Return the result instead of print
                curl_setopt($curlSession, CURLOPT_RETURNTRANSFER,1); 
                // Set a cURL timeout of 30 seconds
                curl_setopt($curlSession, CURLOPT_TIMEOUT,30); 
                curl_setopt($curlSession, CURLOPT_SSL_VERIFYPEER, FALSE);
                
                // Send the request and convert the return value to an array
                $response = preg_split('/$\R?^/m',curl_exec($curlSession));
                
                // Check that it actually reached the SagePay server
                // If it didn't, set the status as FAIL and the error as the cURL error
                if (curl_error($curlSession)){
                        $this->status = 'FAIL';
                        $this->error = curl_error($curlSession);
                }

                // Close the cURL session
                curl_close ($curlSession);
                
                // Turn the response into an associative array
                for ($i=0; $i < count($response); $i++) {
                        // Find position of first "=" character
                        $splitAt = strpos($response[$i], '=');
                        // Create an associative array
                        $this->response[trim(substr($response[$i], 0, $splitAt))] = trim(substr($response[$i], ($splitAt+1)));
                }
                
                // Return values. Assign stuff based on the return 'Status' value from SagePay
                switch($this->response['Status']) {
                        case 'OK':
                                // Transactino made succssfully
                                $this->status = 'success';
                                $_SESSION['transaction']['VPSTxId'] = $this->response['VPSTxId']; // assign the VPSTxId to a session variable for storing if need be
                                $_SESSION['transaction']['TxAuthNo'] = $this->response['TxAuthNo']; // assign the TxAuthNo to a session variable for storing if need be
                                break;
                        case '3DAUTH':
                                // Transaction required 3D Secure authentication
                                // The request will return two parameters that need to be passed with the 3D Secure
                                $this->acsurl = $this->response['ACSURL']; // the url to request for 3D Secure
                                $this->pareq = $this->response['PAReq']; // param to pass to 3D Secure
                                $this->md = $this->response['MD']; // param to pass to 3D Secure
                                $this->status = '3dAuth'; // set $this->status to '3dAuth' so your controller knows how to handle it
                                break;
                        case 'REJECTED':
                                // errors for if the card is declined
                                $this->status = 'declined';
                                $this->error = 'Your payment was not authorised by your bank or your card details where incorrect.';
                                break;
                        case 'NOTAUTHED':
                                // errors for if their card doesn't authenticate
                                $this->status = 'notauthed';
                                $this->error = 'Your payment was not authorised by your bank or your card details where incorrect.';
                                break;
                        case 'INVALID':
                                // errors for if the user provides incorrect card data
                                $this->status = 'invalid';
                                $this->error = 'One or more of your card details where invalid. Please try again.';
                                break;
                        case 'FAIL':
                                // errors for if the transaction fails for any reason
                                $this->status = 'fail';
                                $this->error = 'An unexpected error has occurred. Please try again.';
                                break;
                        default:
                                // default error if none of the above conditions are met
                                $this->status = 'error';
                                $this->error = 'An error has occurred. Please try again.';
                                break;
                }
                
                // set error sessions if the request failed or was declined to be handled by controller
                if($this->status != 'success') {
                        $_SESSION['error']['status'] = $this->status;
                        $_SESSION['error']['description'] = $this->error;
                }
        }
}
?>