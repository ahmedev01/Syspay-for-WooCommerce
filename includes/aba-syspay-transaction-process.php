<?php
/**
 * Syspay Payment Gateway API Handler Class
 *
 * Handle API informations for Syspay Payment Gateway;
 *
 * @class 		ABA_Syspay_Transaction_Handler
 * @version		1.0.0
 * @author 		Ahmed Ben Ali, aqazi Studio
 */

    if ( ! defined( 'ABSPATH' ) ) {
        exit; // Exit if accessed directly
    }
	include_once( dirname( __FILE__ ) . '/class-aba-syspay-gateway.php' );
	
	function object_to_array($data)
{
    if (is_array($data) || is_object($data))
    {
        $result = array();
        foreach ($data as $key => $value)
        {
            $result[$key] = object_to_array($value);
        }
        return $result;
    }
    return $data;
}

	add_filter( 'woocommerce_thankyou_order_received_text', 'syspay_decode_3ds' );
	function syspay_decode_3ds($post_id){
	/*$keys = array(
    get_option( 'api_login' ) => get_option( 'api_passphrase' )
    );*/
    $order = wc_get_order( $post_id );
    $merchant = $_GET['merchant'];
    $result   = $_GET['result'];
    $checksum = $_GET['checksum'];
    
    /*if (!isset($keys[$merchant])) {
        die("Unknown merchant login");
    }
    
    $shouldBe = sha1($result . $keys[$merchant]);
    
    if ($checksum !== $shouldBe) {
        // URL is not genuine
    }*/
    
    // $result is a base64-encoded json string
    $resultat = json_decode(base64_decode($result));
    $result=object_to_array($resultat);
    if ($result['payment']['status']=='AUTHORIZED'){
    //$order->add_order_note('Pre-authorisaton successful.', $notice_type = 'info');
    update_post_meta($_COOKIE['syspay_order'], 'sys-auth', $result['payment']['id']);
    return 'Payment Successful. Your order received';
    }else{
    //$order->add_order_note('Pre-authorisaton failed.', $notice_type = 'info'); 
    return 'Payment failed. Your order received but in awaiting payment status. Please go to your dashboard and try to pay again';
    }
	}
	
	add_action( 'add_meta_boxes', 'syspay_capture_order_meta_boxes',5,1 );
	function syspay_capture_order_meta_boxes() {

		add_meta_box(
			'syswoo-capturer',
			'Paiement Syspay',
			'syspay_order_capture_payment',
			'shop_order',
			'side',
			'high'
		);
	}

	// Custom metabox content
	function syspay_order_capture_payment(){
		//$sysAuth = get_post_meta( $post->ID, 'sys-auth', true);
		
		// if($sysAuth !='Paid'){
		?><form method="post">
			<input type="submit" name="submit_sys_capture" value="Capturer le paiement"/>
			<input type="hidden" name="sys_capture_nonce" value="<?php echo wp_create_nonce();?>">
		</form>
		<?php
		// }
	}
	add_action( 'save_post_shop_order', 'syspay_capture_order_save_meta_box',15,1 );
	function syspay_capture_order_save_meta_box( $post_id ){

		// Only for shop order
		if ( 'shop_order' != $_POST[ 'post_type' ] )
			return $post_id;

		// Check if our nonce is set (and our cutom field)
		if ( ! isset( $_POST[ 'sys_capture_nonce' ] ) && isset( $_POST['submit_sys_capture'] ) )
			return $post_id;

		$nonce = $_POST[ 'sys_capture_nonce' ];

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $nonce ) )
			return $post_id;

		// Checking that is not an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return $post_id;

		// Check the user’s permissions (for 'shop_manager' and 'administrator' user roles)
		if ( ! current_user_can( 'edit_shop_order', $post_id ) && ! current_user_can( 'edit_shop_orders', $post_id ) )
			return $post_id;

		// Action to make or (saving data)
		if( isset( $_POST['submit_sys_capture'] ) ) {
			$order = wc_get_order( $post_id );
			$authorization= new ABA_Syspay_Gateway();
			$result=$authorization->syspay_authorize_payment($order, 'capture');
			ABA_Syspay_Gateway::log('response :'.print_r($result,true), 'info');
			// $customeremail = $order->get_billing_email();
			if ($result['payment']['status']=='SUCCESS') {
				$order->add_order_note(sprintf(__('Successful Payment. Transaction number # %s.', 'aba-woo-syspay'), $result['processor_reference']));
				update_post_meta($_COOKIE['syspay_order'], 'sys-auth', 'Paid');
			}else{
            $order->add_order_note('Payment failed somewhere.', $notice_type = 'error');
			}
		}
	}