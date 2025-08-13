<?php
/**
 * Syspay Payment Gateway API Handler Class
 *
 * Handle API informations for Syspay Payment Gateway;
 *
 * @class 		ABA_Syspay_Transaction_Handler
 * @version		1.0.0
 * @author 		Ahmed Benali
 */

    if ( ! defined( 'ABSPATH' ) ) {
        exit; // Exit if accessed directly
    }

if ( ! class_exists( 'ABA_Syspay_Transaction_Handler' ) ) {
class ABA_Syspay_Transaction_Handler {
    
    protected $sandbox;
    protected $api_login;
    protected $merchant_id;
	protected $api_passphrase;
	protected $js_key;
	protected $base_url;
    protected $success_page;
    protected $cancel_page;
    protected $orderId;
	
    
    /**
     * Constructor for handler class.
     *
     * @since 1.0.0
     * @access public
     * @param bool $sandbox (yes or no)
     * @param string $api_key
     * @param string $merchant_id
     * @param string $success_page
     * @param string $cancel_page
	 */
	public function __construct($sandbox = '', $api_login = '', $api_passphrase='', $js_key='', $merchant_id='', $success_page='', $cancel_page='') {

        $this->sandbox = $sandbox;
        $this->api_login = $api_login;
		$this->api_passphrase = $api_passphrase;
		$this->js_key = $js_key;
        $this->merchant_id = $merchant_id;
        $this->cancel_page = $cancel_page;
        $this->success_page = add_query_arg('wc-api', 'aba_syspay_transaction_handler', home_url('/')).'&';
		$this->base_url= $this->getbaseurl();
            
        
		add_action ('init', array($this, 'check_payment_status'));
        add_action ('woocommerce_api_aba_syspay_transaction_handler', array( $this, 'check_payment_status'));
        add_action ('woocommerce_pay_order_after_submit', array( $this, 'transaction_launcher' ));
		
            
        }
		
		public function getbaseurl() {
			if ($this->sandbox == 'yes') {
                $base_url = 'https://app-sandbox.syspay.com/';
            }
        elseif ($this->sandbox == 'no') {
                $base_url = 'https://app.syspay.com/';
            }
			return $base_url;
		}
		
		public function payment_scripts() {
 
		// let's suppose it is our payment processor JavaScript that allows to obtain a token
		wp_enqueue_script( 'syspay_js', 'https://cdn.syspay.com/js/syspay.tokenizer-current.js' );
	 
		//and this is our custom JS in your plugin directory that works with token.js
		 wp_register_script( 'woocommerce_syspay', SYSP_WC_PLUGIN_URL.'/assets/js/frontend.js', array( 'jquery', 'syspay_js' ) );
	 
		//in most payment processors you have to use PUBLIC KEY to obtain a token
		 wp_localize_script( 'woocommerce_syspay', 'syspay_params', array(
			 'publicKey' => $this->js_key,
			 'baseUrl' => $this->base_url,
		 ) );
	 
		wp_enqueue_script( 'woocommerce_syspay' );
	 
	}
	
    /**
	 * Generate Requests Headers
	 *
	 *
	 * @since 1.0.0
	 */
	 function generateHeaders($merchantLogin, $passphrase) {
	  $nonce = md5(rand(), true);
	  $timestamp = time();

	  $digest = base64_encode(sha1($nonce . $timestamp . $passphrase, true));
	  $b64nonce = base64_encode($nonce);

	  $header = sprintf('X-Wsse: AuthToken MerchantAPILogin="%s", PasswordDigest="%s", Nonce="%s", Created="%d"',
						  $merchantLogin, $digest, $b64nonce, $timestamp);

	  return $header;
	}
	
    /**
     * Initialise payment request
     *
     * @since 1.0.0
     * @access public
     * @param int $order_id
     * @return array (with payment token)
     */
    public function init_payment_request($order_id){
        
        global $woocommerce;
        
        $order = new WC_Order($order_id);
        
        if ($this->sandbox == 'yes') {
                $request_url = 'http://sandbox.syspay.tn/api/OPRequest/';
            }
        elseif ($this->sandbox == 'no') {
                $request_url = 'https://app.syspay.tn/api/OPRequest/';
            }
        else {
            wc_add_notice(sprintf(__('We are currently experiencing problems trying to connect to Syspay. Sorry for the inconvenience.', 'aba-woo-syspay')), $notice_type = 'error');
            ABA_Syspay_Gateway::log('Payment failure: request URL not found or invalid.', 'error');
            wp_redirect(get_permalink(get_option('woocommerce_checkout_page_id')));
        }
        
        $json_headers = array('Content-type: application/json', 'Authorization: Token '.$this->api_key);
        
        $json_data = json_encode(array (
            'vendor' => $this->merchant_id,
            'amount' => $order->get_total(),
            'note'   => $order->get_customer_note()
        ));
        
        $init_request = curl_init($request_url);
        
        //cURL options
        curl_setopt($init_request, CURLOPT_POST, 1);
        curl_setopt($init_request, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($init_request, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($init_request, CURLOPT_HTTPHEADER, $json_headers);
        curl_setopt($init_request, CURLOPT_HEADER, false);
        curl_setopt($init_request, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($init_request, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($init_request, CURLOPT_SSL_VERIFYPEER, false);

        $result = json_decode(curl_exec($init_request), true);
        curl_close($init_request);
        
        return $result;
    }
    
    /*
     * Syspay redirection
     *
     * @since 1.0.0
     * @access public
     * @param int $order_id
     * @return string (payment form html)
     */
    public function redirect_and_pay($order_id){
        global $woocommerce;
        $order = new WC_Order($order_id);
		
	 
		//and this is our custom JS in your plugin directory that works with token.js
		 wp_register_script( 'woocommerce_syspay', SYSP_WC_PLUGIN_URL.'/assets/js/frontend.js', array( 'jquery', 'syspay_js' ) );
	 
		//in most payment processors you have to use PUBLIC KEY to obtain a token
		 wp_localize_script( 'woocommerce_syspay', 'syspay_params', array(
			 'publicKey' => $this->js_key,
			 'baseUrl' => $this->base_url,
		 ) );
	 
		wp_enqueue_script( 'woocommerce_syspay' );
        //$init_result = $this->init_payment_request($order_id);
        
        if ($_POST['syspay-token']){
        if ($this->sandbox == 'yes') {
                $redirect_url = 'http://sandbox.syspay.tn/gateway/';
            }
        elseif ($this->sandbox == 'no') {
                $redirect_url = 'https://app.syspay.tn/gateway/';
            }
        else {
            wc_add_notice(sprintf(__('We are currently experiencing problems trying to connect to Syspay. Sorry for the inconvenience.', 'aba-woo-syspay')), $notice_type = 'error');
            ABA_Syspay_Gateway::log('Payment failure: redirect URL not found or invalid for order '.$order_id, 'error');
            wp_redirect(get_permalink(get_option('woocommerce_checkout_page_id')));
            exit;
        }
        }else{
            wc_add_notice(sprintf(__('We are currently not able to process the payment. No valid response from Syspay. Sorry for the inconvenience.', 'aba-woo-syspay')), $notice_type = 'error');
            ABA_Syspay_Gateway::log('Payment failure: token not found or invalid for order '.$order_id, 'error');
            wp_redirect(get_permalink(get_option('woocommerce_checkout_page_id')));
            exit;
        }
       
       $formfields = array (
           'payment_token' => $init_result['token'],
           'url_ok' => $this->success_page,
           'url_ko' => $this->cancel_page
       );
            wc_enqueue_js('
            jQuery("#submit_syspay_form").click();
            ');
            $formresult = '<form action="'.esc_url($redirect_url).'" method="post" id="syspay_form">'; 
            foreach ($formfields as $key => $value) {
                $formresult .= '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'" />';
            }
            $formresult .= '<input type="submit" class="button" id="submit_syspay_form" value="'.__('Pay Now', 'aba-woo-syspay').'"/>
            </form>';
            echo $formresult;
        
    }
    
    /**
     * Payment transaction launcher
     *
     * @since 1.0.0
     * @access public
     * @param int $order_id
     */
    public function transaction_launcher($order_id) {
		wp_enqueue_script( 'syspay_js', 'https://cdn.syspay.com/js/syspay.tokenizer-current.js' );
		wc_enqueue_js("
            var apiUrl = '".$this->base_url."api/v2/public/';
		syspay.tokenizer.setBaseUrl(apiUrl);

        // The public key can be found from your merchant backend
        syspay.tokenizer.setPublicKey(".$this->js_key.");

        // The following function will be called back once the card data will have been submitted to the SysPay API
        var callback = function(response) {
            if (null === response.error) {
                // The request was successfully processed. Add the returned token to the form before submitting it for real.
                $('<input type=\"hidden\" name=\"syspay-token\" />')
                    .val(response.token)
                    .appendTo('form.checkout');

                // Submit the form
				alert(response.token);
                $('form.checkout').get(0).submit();
            } else {
                alert('An error occured: ' + response.message + '(Code: ' + response.error + ')');
                return false;
            }
        };

        $(function() {
            // Catch form submissions and send the card data to syspay
            $('form.checkout').submit(function() {
                // Submit the card data to Syspay
                syspay.tokenizer.tokenizeCard({
                    number:     $('#thecardnum').val(),
                    cardholder: $('#thecardname').val(),
                    exp_month:  $('#thecardmonth').val(),
                    exp_year:   $('#thecardyear').val(),
                    cvc:        $('#thecardcvc').val()
                }, callback);

                // Prevent form submission
                return false;
            });
        });
            ");
        $this->redirect_and_pay($order_id);
    }
    
    /*
     * Check for payment status
     *
     * @since 1.0.0
     * @access public
     */
    public function check_payment_status(){
        global $woocommerce;
        if ($this->sandbox == 'yes') {
                $check_url = 'http://sandbox.syspay.tn/api/OPCheck/';
            }
        elseif ($this->sandbox == 'no') {
                $check_url = 'https://app.syspay.tn/api/OPCheck/';
            }
        
        $json_headers = array('Content-Type:application/json', 'Authorization: Token '.$this->api_key);
        
        $json_data = json_encode(array (
            'token' => $_GET['?payment_token'],
        ));
        
        $check_status = curl_init($check_url);
        
        //set cURL options
        curl_setopt($check_status, CURLOPT_POST, 1);
        curl_setopt($check_status, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($check_status, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($check_status, CURLOPT_HTTPHEADER, $json_headers);
        curl_setopt($check_status, CURLOPT_HEADER, false);
        curl_setopt($check_status, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($check_status, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($check_status, CURLOPT_SSL_VERIFYPEER, false);
        
        $result = json_decode(curl_exec($check_status), true);
        curl_close($check_status);
        
        if ($result['result']==1) {
                $order = new WC_Order($_COOKIE['syspay_order']);
                $order->add_order_note(sprintf(__('Successful Payment. Transaction number # %s.', 'aba-woo-syspay'), $_GET['transaction']));
                $order->payment_complete();
                wc_reduce_stock_levels($current_order);
                WC()->cart->empty_cart();
                wc_add_notice(sprintf(__('Successful Payment. Transaction number # %s.', 'aba-woo-syspay'), $_GET['transaction']), $notice_type = 'info');
                ABA_Syspay_Gateway::log('Payment success: payment token:'.$_GET['?payment_token'].'Transaction number # '.$_GET['transaction'].'order '.$_COOKIE['syspay_order'], 'info');
                wp_redirect(ABA_Syspay_Gateway::get_return_url($order));
                exit;
            }
            wc_add_notice(sprintf(__('Payment failed somewhere.', 'aba-woo-syspay')), $notice_type = 'error');
            ABA_Syspay_Gateway::log('Payment failure: payment token not found or invalid. Used token:'.$_GET['?payment_token'].'values '.$result['result'], 'error');
            wp_redirect(get_permalink(get_option('woocommerce_checkout_page_id')));
            exit;
    }
}
}