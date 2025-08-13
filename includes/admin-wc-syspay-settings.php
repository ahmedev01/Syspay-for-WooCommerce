<?php
/**
 * Syspay Gateway WooCommerce admin page
 *
 * @since 1.0.0
 * @author Ahmed Ben Ali, aqazi Studio
 */

    if ( ! defined( 'ABSPATH' ) ) {
        exit; // Exit if accessed directly
    }

    $pages = get_pages();
    $options['00'] = esc_attr( __( "Select page" ) );

      foreach ( $pages as $page ) {
        $options [get_page_link( $page->ID )] = $page->post_title;
      }

    return array(
        'enabled'               => array(
            'title'   => __( 'Enable/Disable', 'aba-woo-syspay' ),
            'type'    => 'checkbox',
            'label'   => __( 'Enable Syspay Gateway', 'aba-woo-syspay' ),
            'default' => 'no',
        ),
        'title'                 => array(
            'title'       => __( 'Title', 'aba-woo-syspay' ),
            'type'        => 'text',
            'description' => __( 'This controls the title which the user sees during checkout.', 'aba-woo-syspay' ),
            'default'     => __( 'Pay with Syspay', 'aba-woo-syspay' ),
            'desc_tip'    => true,
        ),
        'description'           => array(
            'title'       => __( 'Description', 'aba-woo-syspay' ),
            'type'        => 'text',
            'desc_tip'    => true,
            'description' => __( 'This controls the description which the user sees during checkout.', 'aba-woo-syspay' ),
            'default'     => __( "Pay via Syspay; you will be redirected to Syspay to login and complete payment.", 'aba-woo-syspay' ),
        ),
        'api_login'             => array(
            'title'       => __( 'API login', 'aba-woo-syspay' ),
            'type'        => 'text',
            'description' => __( 'Please enter your Syspay API login.', 'aba-woo-syspay' ),
            'desc_tip'    => true,
        ),
        'merchant_id'                 => array(
            'title'       => __( 'ID', 'aba-woo-syspay' ),
            'type'        => 'text',
            'description' => __( 'Please enter your Syspay Vendor ID; this is needed in order to receive payment.', 'aba-woo-syspay' ),
            'desc_tip'    => true,
        ),
		'api_passphrase'                 => array(
            'title'       => __( 'API passphrase', 'aba-woo-syspay' ),
            'type'        => 'text',
            'description' => __( 'Please enter your Syspay API passphrase; this is needed in order to process payment.', 'aba-woo-syspay' ),
            'desc_tip'    => true,
        ),
		'js_key'                 => array(
            'title'       => __( 'Javascript Library Public key', 'aba-woo-syspay' ),
            'type'        => 'text',
            'description' => __( 'Please enter your Syspay Javascript Library Public key; this is needed in order to process payment.', 'aba-woo-syspay' ),
            'desc_tip'    => true,
        ),
        'cancel_page'               => array(
            'title'       => __( 'Cancel Page', 'aba-woo-syspay' ),
            'type'        => 'select',
            'options'     => $options,
            'description' => __( 'Please enter the URL to the cancel payment page to show after a customer cancel payment;', 'aba-woo-syspay' ),
            'desc_tip'    => true,
        ),
        'advanced'              => array(
            'title'       => __( 'Advanced options', 'aba-woo-syspay' ),
            'type'        => 'title',
            'description' => '',
        ),
        'sandbox'              => array(
            'title'       => __( 'Syspay sandbox', 'aba-woo-syspay' ),
            'type'        => 'checkbox',
            'label'       => __( 'Enable Syspay sandbox for testing.', 'aba-woo-syspay' ),
            'default'     => 'no',
            'description' => __( 'Syspay sandbox can be used to test payments.', 'aba-woo-syspay' ),
        ),
        'debug'                 => array(
            'title'       => __( 'Debug log', 'aba-woo-syspay' ),
            'type'        => 'checkbox',
            'label'       => __( 'Enable logging', 'aba-woo-syspay' ),
            'default'     => 'no',
            'description' => sprintf( __( 'Log Syspay events, such as API requests, inside %s Note: We recommend using this for debugging purposes only and deleting the logs when finished.', 'aba-woo-syspay' ), '<code>' . WC_Log_Handler_File::get_log_file_path( 'syspay' ) . '</code>' )),
    );