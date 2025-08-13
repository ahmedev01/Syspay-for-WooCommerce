<?php 

/**
 * Syspay Payment Gateway Class
 *
 * Provides Syspay Payment Gateway for WooCommerce websites;
 *
 * @class 		ABA_Syspay_Gateway
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @author 		Ahmed Benali
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'ABA_Syspay_Gateway' ) ) {
class ABA_Syspay_Gateway extends WC_Payment_Gateway {
    
    /** @var bool Whether or not logging is enabled */
    public static $log_enabled = false;

    /** @var WC_Logger Logger instance */
    public static $log = false;
    
    /**
	 * Constructor for Syspay gateway settings.
     *
     * @since 1.0.0
     * @access public
	 */
		public function __construct() {
	  
			$this->id                 = 'syspay';
			$this->icon               = SYSP_WC_PLUGIN_URL.'/assets/images/logo_syspay.png';
			$this->has_fields         = true;
            $this->order_button_text  = __( 'Payer avec Syspay', 'aba-woo-syspay' );
			$this->method_title       = __( 'Syspay', 'aba-woo-syspay' );
			$this->method_description = __( 'Syspay Gateway redirects customers to Syspay to complete payment. You need to have a Syspay merchant account.', 'aba-woo-syspay' );
		    $this->title              = __( 'Syspay', 'aba-woo-syspay' );
			$this->base_url = 'https://app.syspay.com/';
           
			add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ), 999 );
            
            // Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->api_passphrase = $this->get_option( 'api_passphrase' );
			$this->js_key		= $this->get_option('js_key');
            $this->api_login    = $this->get_option( 'api_login' );
            $this->merchant_id  = $this->get_option( 'merchant_id' );
            $this->sandbox      = $this->get_option( 'sandbox', 'no' );
            $this->debug        = $this->get_option( 'debug', 'no' );
            $this->cancel_page  = $this->get_option( 'cancel_page' );
		  
            self::$log_enabled  = $this->debug;
			
			if ( $this->sandbox == 'yes' ) {
                $this->description .= __( ' SANDBOX ENABLED. You can use sandbox testing accounts only.','aba-woo-syspay');
				$this->base_url = 'https://app-sandbox.syspay.com/';
            }

            if ( ! $this->is_currency_valid()) {
                $this->enabled = 'no';
                self::log('Currency test: currency is not supported', 'error');
            } elseif (!$this->is_curl_enabled()){
                $this->enabled = 'no';
                self::log('cURL extension disabled', 'error');
            }else {
                add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
				add_action( 'woocommerce_after_checkout_form', array( $this, 'syspay_threatmetrics'));
				add_action( 'woocommerce_checkout_after_customer_details', array($this,'customise_checkout_field'),10, 1);
				add_action( 'woocommerce_checkout_update_order_meta', array($this,'customise_checkout_field_update_order_meta'),10, 1);
            }

			// Actions
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            } else {
            add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
            }
		}
    
    /**
     * Logging method.
     *
     * @since 1.0.0
     * @access public
     * @param string $message
     * @param string $level
     */
    public static function log( $message, $level = 'info' ) {
        if ( self::$log_enabled ) {
            if ( empty( self::$log ) ) {
                self::$log = wc_get_logger();
            }
            self::$log->log( $level, $message, array( 'source' => 'syspay' ) );
        }
    }
    
    /**
     * Check if this gateway is enabled and available in the user's country.
     *
     * @since 1.0.0
     * @access public
     * @return bool
     */
    public function is_currency_valid() {
        return in_array( get_woocommerce_currency(), array('ARS', 'AUD', 'BRL', 'GBP', 'BGN', 'CAD', 'CLP', 'COP', 'HRK', 'CZK', 'DKK', 'EUR', 'HUF', 'INR', 'LTL', 'USD', 'MXN', 'NOK', 'PLN', 'RON', 'SEK', 'CHF', 'TRY'));
    }
    
    /**
     * Check if php cURL extension is enabled.
     *
     * @since 1.0.0
     * @access public
     * @return bool
     */
    public function is_curl_enabled() {
        return function_exists('curl_version');
    }
    
    /**
     * Initialise Syspay WooCommerce settings form fields.
     *
     * @since 1.0.0
     * @access public
     */
    public function init_form_fields() {
        $this->form_fields = include( 'admin-wc-syspay-settings.php' );
    }

    /**
     * Admin Options.
     *
     * @since 1.0.0
     * @access public
     */
    public function admin_options() {
        if ( $this->is_currency_valid() && $this->is_curl_enabled()){
            if ($this->needs_setup()) {
                ?>
            <div class="inline error"><p><strong><?php _e( 'Syspay Gateway setup incomplete', 'aba-woo-syspay' ); ?></strong>: <?php _e( 'Please make sure that the fields API login, Merchant ID, API Passphrase and JS public key are filled correctly.', 'aba-woo-syspay' ); 
            ?></p></div>
            <?php
                $this->enabled = 'no';
            }
            
        } else {
            if ( !$this->is_currency_valid()) {
            ?>
            <div class="inline error"><p><strong><?php _e( 'Syspay Gateway disabled', 'aba-woo-syspay' ); ?></strong>: <?php _e( 'Syspay does not support your store currency.', 'aba-woo-syspay' ); ?></p></div>
            <?php
            } 
            if (!$this->is_curl_enabled()) {
            ?>
            <div class="inline error"><p><strong><?php _e( 'Syspay Gateway disabled', 'aba-woo-syspay' ); ?></strong>: <?php _e( 'Syspay requires cURL php extension to run properly. Please make sure to enable it in your server', 'aba-woo-syspay' ); ?></p></div>
            <?php
            }
        }
        parent::admin_options();
        }
    
    /**
	 * Return whether or not Syspay still requires setup.
	 *
	 * @since 1.0.0
     * @access public
	 * @return bool
	 */
	public function needs_setup() {
        $setup = false;
        if ($this->api_login == '' || $this->merchant_id == '' || $this->cancel_page == '00') 
            {
                $setup = true;
            }
		return $setup;
	}
    
	public function customise_checkout_field($checkout)	{
		echo '<div id="user_token_hidden_checkout_field">
				<input type="hidden" name="sys-auth" id="sys-authn" />
		</div>';
	}
	
	
	public function customise_checkout_field_update_order_meta($order_id){
	  if (!empty($_POST['sys-auth'])) {
		update_post_meta($order_id, 'sys-auth', $_POST['sys-auth']);
	  }
	}
    /**
	 * Changes footer text in Syspay settings page.
	 *
     * @since 1.0.0
     * @access public
	 * @param string $text Footer text.
	 *
	 * @return string
	 */
	public function admin_footer_text( $text ) {
		if ( isset( $_GET['section'] ) && 'syspay' === $_GET['section'] ) {
			$text = _e('If you like Syspay for WooCommerce, please consider <strong>assigning Syspay as your payment processor partner</strong>.','aba-woo-syspay' );
		}

		return $text;
	}
    
	public function syspay_threatmetrics (){
	  $systhreat = $this->merchant_id.'-'.$_COOKIE['woocommerce_cart_hash'];
	  echo '<span style="background:url(https://site.syspay.com/fp/clear.png?org_id=xxxxxxxx&amp;session_id='.$systhreat.'&amp;m=1)"></span>
<script src="https://site.syspay.com/fp/check.js?org_id=xxxxxxxx&amp;session_id='.$systhreat.'" type="text/javascript"></script>
<object type="application/x-shockwave-flash" data="https://site.syspay.com/fp/fp.swf?org_id=xxxxxxxx&amp;session_id='.$systhreat.'" width="1" height="1" style="position: absolute; left: -999px; top: -999px;"><param name="movie" value="https://site.syspay.com/fp/fp.swf?org_id=xxxxxxxx&amp;session_id='.$systhreat.'" /><div></div></object>
<iframe style="color:rgb(0,0,0); float: left; position: absolute; top: -999px; left: -999px; border: 0px;" src="https://site.syspay.com/tags?org_id=xxxxxxxx&amp;session_id='.$systhreat.'" height="100" widht="100"></iframe>
<img src="https://site.syspay.com/fp/clear.png?org_id=xxxxxxxx&amp;session_id='.$systhreat.'&amp;m=2" alt="" style="position: absolute; left: -999px; top: -999px;" />';
	}
	
	public function payment_fields() {
 
			// display the description with <p> tags etc.
			echo wpautop( wp_kses_post( $this->description ) );
	 
		// I will echo() the form, but you can close PHP tags and print it directly in HTML
		echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
	 
		// Add this action hook if you want your custom gateway to support it
		do_action( 'woocommerce_credit_card_form_start', $this->id );
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			wp_enqueue_script( 'syscard-script', SYSP_WC_PLUGIN_URL.'/assets/js/card-js.min.js',array(),false);
		}else{
			wp_enqueue_script( 'syscard-valid-script', SYSP_WC_PLUGIN_URL.'/assets/js/card-check-js.min.js',array(),false);
		}
		
		wp_enqueue_style( 'syscard-style', SYSP_WC_PLUGIN_URL.'/assets/css/card-js.min.css',array(),false );
		// I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
		echo '<div class="form-row form-row-first card-js">
		<input class="card-number">
		<input class="name">
		<input class="expiry-month">
		<input class="expiry-year">
		<input class="cvc">
		</div>';
	 
		do_action( 'woocommerce_credit_card_form_end', $this->id );
	 
	}
	
	public function payment_scripts() {
 
		// we need JavaScript to process a token only on cart/checkout pages, right?
		if ( ! is_checkout() && !is_wc_endpoint_url() ) {
			return;
		}
	 
		// if our payment gateway is disabled, we do not have to enqueue JS too
		if ( 'no' === $this->enabled ) {
			return;
		}
	 
		// no reason to enqueue JavaScript if API keys are not set
		if ( empty( $this->api_login ) || empty( $this->merchant_id ) ) {
			return;
		}
	 
		// do not work with card detailes without SSL unless your website is in a test mode
		if ( ! $this->sandbox && ! is_ssl() ) {
			return;
		}
	 
		// let's suppose it is our payment processor JavaScript that allows to obtain a token
		wp_enqueue_script( 'syspay_js', 'https://cdn.syspay.com/js/syspay.tokenizer-current.js');
	 
		//and this is our custom JS in your plugin directory that works with token.js
		 wp_register_script( 'woocommerce_syspay', SYSP_WC_PLUGIN_URL.'/assets/js/frontend.js', array('jquery','syspay_js'));
	 
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
	public function generateHeaders($merchantLogin, $passphrase) {
	  $nonce = md5(rand(), true);
	  $timestamp = time();

	  $digest = base64_encode(sha1($nonce . $timestamp . $passphrase, true));
	  $b64nonce = base64_encode($nonce);

	  $header = array(
	  'Accept: application/json',
	  'X-Wsse: AuthToken MerchantAPILogin="'.$merchantLogin.'", PasswordDigest="'.$digest.'", Nonce="'.$b64nonce.'", Created="'.$timestamp.'"'
	  );

	  return $header;
	}
	
	public function setOperationLink ($operation='preauth', $paymentId=''){
		$base_url = 'https://app.syspay.com/';
		if ( $this->sandbox == 'yes' ) {
				$base_url = 'https://app-sandbox.syspay.com/';
            }
		switch ($operation) {
			case 'preauth':
			  $request_url = $base_url.'api/v2/merchant/token';
			  break;
			case 'capture':
			  $request_url = $base_url.'api/v2/merchant/payment/'.$paymentId.'/capture';
			  break;
			case 'void':
			  $request_url = $base_url.'api/v2/merchant/payment/'.$paymentId.'/void';
			  break;
			case 'detoken':
			  $request_url = $base_url.'api/v2/merchant/token/'.$tokenId.'/detokenize';
			  break;
		}
		return $request_url;
	}
	
	public function syspay_authorize_payment($order,$operation='preauth'){
		if (isset($_POST['sys-tok'])||isset($_POST['submit_sys_capture'])){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		switch ($operation){
			case 'preauth':
		$descr= 'Paiement Commande N°'.$order->get_id().'sur Gestofi.com'.
		$amount = (int)$order->get_total();
		$amount = $amount*100;
		self::log($amount, 'error');
        $headers = $this->generateHeaders($this->api_login, $this->api_passphrase);
		$url = $this->setOperationLink();
        $params = json_encode(array (
            "flow" => "API",
            "interactive" => "1",
			"return_url"=>$this->get_return_url( $order ),
			"threatmetrix_session_id" => $this->merchant_id."-".$_COOKIE['woocommerce_cart_hash'],
			"payment_method" => array (
						"token_key" => $_POST['sys-tok']
						),
			"mandate"=> false,
			"description" => $descr,
			  "customer"=> array (
				  "firstname"=> $order->get_billing_first_name(),
				  "lastname"=> $order->get_billing_last_name(),
				  "email"=> $order->get_billing_email(),
				  "address_country"=> $order->get_billing_country(),
				  "ip"=>$order->get_customer_ip_address(),
				  "language"=>"fr"
			  ),
			"payment" => array(
						"reference" => uniqid($order->get_id().'-'),
						"amount" => $amount,
						"currency" => $order->get_currency(),
						"preauth" => true
						)
        ));
		array_push($headers, 'Content-Type: application/json');
		array_push($headers, 'Content-Length: ' . strlen($params));
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        break;
		
		case 'capture':
		$headers = $this->generateHeaders($this->api_login, $this->api_passphrase);
		$payId = get_post_meta($order->get_id(), 'sys-auth', true);
		$url = $this->setOperationLink('capture', $payId);
		array_push($headers, 'Content-Type: application/json');
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        break;

		case 'void':
		$headers = $this->generateHeaders($this->api_login, $this->api_passphrase);
		$payId = get_post_meta($order->get_id(), 'sys-auth', true);
		$url = $this->setOperationLink('void', $payId);
		array_push($headers, 'Content-Type: application/json');
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        break;
		
		case 'detoken':
		$headers = $this->generateHeaders($this->api_login, $this->api_passphrase);
		$url = $this->setOperationLink('detoken');
		array_push($headers, 'Content-Type: application/json');
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        break;
		}
        $resultat = curl_exec($ch);
		error_log("test\n" . $resultat);
		list($headers, $body) = explode("\r\n\r\n", $resultat, 2);
		$result = json_decode($body, true);
        curl_close($ch);
        return $result;
		}
		else 
		{
		if (( $pagenow == 'post.php' ) || (get_post_type() == 'shop_order')) {
			echo '<div class="notice notice-warning is-dismissible"><p>We are currently not able to process the payment. No valid response from Syspay. Sorry for the inconvenience.</p></div>';
			}else{
			wc_add_notice('We are currently not able to process the payment. No valid response from Syspay. Sorry for the 			  inconvenience.', 'error');
            self::log('Payment failure: token not found or invalid for order '.$order->get_id(), 'error');
            wp_redirect(get_permalink(get_option('woocommerce_checkout_page_id')));
            exit;
			}
		}
		
	}
	
	/**
     * Process the payment and return the result
     *
     * @since 1.0.0
     * @access public
     * @param int $order
     * @return array
     */
	public function process_payment($order_id) {
        global $woocommerce;
        $order = wc_get_order( $order_id );
		$result=$this->syspay_authorize_payment($order);
		self::log('response :'.print_r($result,true), 'info');
		if ($result['payment']['status']=='OPEN') {
			if( isset( $_COOKIE['syspay_order'] )) {
                unset( $_COOKIE['syspay_order'] );
                setcookie( 'syspay_order', '', time() - ( 15 * 60 ) );
                setcookie('syspay_order', $order_id, 0, '/');
			} else {
				setcookie('syspay_order', $order_id, 0, '/');
			}
			self::log('order '.$_SESSION['syspay_order'].'order id :'.$order_id.'session '.$_SESSION, 'info');
            $order->add_order_note(sprintf(__('Successful Payment. Transaction number # %s.', 'aba-woo-syspay'), $result['payment']['reference']));
            $order->payment_complete();
            //wc_reduce_stock_levels($current_order);
            WC()->cart->empty_cart();
			update_post_meta($order_id, 'sys-auth', $result['payment']['id']);
            wc_add_notice(sprintf(__('Successful Payment. Transaction number # %s.', 'aba-woo-syspay'), $result['payment']['reference']), $notice_type = 'info');
            self::log('Payment success: Transaction number # '.$result['payment']['reference'].'order '.$_COOKIE['syspay_order'], 'info');
            return array(
            'result' 	=> 'success',
            'redirect'	=> $result['payment']['action_url']
        );
        }else{
            wc_add_notice('Payment failed somewhere.', $notice_type = 'error');
            self::log('Payment failure: payment token not found or invalid. values '.$result['result'], 'error');
            // wp_redirect(get_permalink(get_option('woocommerce_checkout_page_id')));
            return array(
				'result' 	=> 'failed',
                'redirect'	=> get_permalink(get_option('woocommerce_checkout_page_id'))
			);
			}
			
		}	
  }
}