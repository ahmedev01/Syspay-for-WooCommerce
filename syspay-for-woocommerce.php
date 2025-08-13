<?php
/**
* Plugin Name:  Syspay for WooCommerce
* Plugin URI:   https://ahmedev.com
* Description:  Syspay WooCommerce extension adds the ability to use syspay payment system as a payment gateway in your  WooCommerce powered website.
* Version:      v 1.0.0
* Author: Ahmed Benali
* Author URI: https://ahmedev.com
* Text Domain: aba-woo-syspay
* Domain Path: /languages
*
* WC requires at least: 2.6
* WC tested up to: 3.4
*
* Copyright: © 2018 aqazi Studio / Ahmed Ben Ali.
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

    if ( ! defined( 'ABSPATH' ) ) {
        exit; // Exit if accessed directly
    }

    session_start();
    /**
     * Required minimums and constants
     **/

    define( 'SYSP_WC_VERSION', '1.6.1' );
    define( 'SYSP_WC_MIN_PHP_VER', '5.3.0' );
    define( 'SYSP_WC_MIN_WC_VER', '2.6.0' );
    define( 'SYSP_WC_MAIN_FILE', __FILE__ );
    define( 'SYSP_WC_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
    define( 'SYSP_WC_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

    /**
     * Add Syspay Payment to WC Available Gateways
     * 
     * @since 1.0.0
     * @param array $gateways (all available WC gateways)
     * @return array $gateways (all WC gateways + new added Syspay Gateway)
     */
    function aba_syspay_wc_add_to_gateways( $gateways ) {
        $gateways[] = 'ABA_Syspay_Gateway';
        return $gateways;
    }

    /**
     * Adds woo-syspay extension page links
     * 
     * @since 1.0.0
     * @param array $links (all plugin links)
     * @return array $links (all plugin links + Syspay links)
     */
    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'aba_syspay_wc_plugin_links' );
    function aba_syspay_wc_plugin_links( $links ) {
        $plugin_links = array(
            '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=syspay' ) . '">' . __( 'Settings', 'aba-woo-syspay' ) . '</a>',
            '<a href="https://ahmedev.com/">' . __( 'Support', 'aba-woo-syspay' ) . '</a>',
        );
        return array_merge( $plugin_links, $links );
    }

    /**
         * Adds Syspay extension templating engine
         * 
         * @since 1.0.0
         * @param string $template
         * @param string $template_name
         * @param string $template_path
         */
    add_filter( 'woocommerce_locate_template', 'syspay_wc_locate_template' , 10, 3 );
    function syspay_wc_locate_template( $template, $template_name, $template_path ) {
      global $woocommerce;

      $_template = $template;

      if ( ! $template_path ) $template_path = $woocommerce->template_url;

      $plugin_path  = SYSP_WC_PLUGIN_PATH . '/woocommerce/';

      $template = locate_template(

        array(
          $template_path . $template_name,
          $template_name
        )
      );

      if ( ! $template && file_exists( $plugin_path . $template_name ) )
        $template = $plugin_path . $template_name;

      if ( ! $template )
        $template = $_template;

      return $template;
    }

    /**
     * Load plugin textdomain.
     *
     * @since 1.0.0
     * @return bool
     */
	add_action( 'init', 'aba_syspay_load_textdomain');
    function aba_syspay_load_textdomain() {
      load_plugin_textdomain( 'aba-woo-syspay', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
    }

    /**
     * Include Syspay Gateway Class and Register Syspay System with WooCommerce
     * 
     * @since 1.0.0
     */

    add_action( 'plugins_loaded', 'aba_syspay_wc_init', 0 );
    function aba_syspay_wc_init() {

        // check if woocommerce is installed and active
        // do nothing if not active
        if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

        // Include Syspay Class ABA_Paymee_Gateway
        include_once( 'includes/class-aba-syspay-gateway.php' );
		include_once( 'includes/aba-syspay-transaction-process.php' );
        // Register Syspay Gateway
        add_filter( 'woocommerce_payment_gateways', 'aba_syspay_wc_add_to_gateways' );
    }