<?php
/*
 * Plugin Name: WooCommerce Diamano pay Plugin
 * Plugin URI: https://www.2dservices.sn
 * Description: Accepte les paiments par Orange Money, Wave et carte bancaire au Sénégal.
 * Author: 2dServices
 * Author URI: http://www.2dservices.sn
 * Version: 1.0.0
 */

/*
 * trigger plugins_loaded action hook to check woocommerce plugin activated or not and extend WooCommerce WC_Payment_Gateway class.
 */
add_action('plugins_loaded', 'woocommerce_myplugin');
function woocommerce_myplugin()
{
    if (!class_exists('WC_Payment_Gateway'))
        return; // if the WC payment gateway class 

    include(plugin_dir_path(__FILE__) . 'payment-gateway-class.php');
}

// Define your plugin class name here
add_filter('woocommerce_payment_gateways', 'diamono_pay_add_gateway_class');
add_filter('woocommerce_currencies', 'add_fcfa_currency');
add_filter('woocommerce_currency_symbol', 'add_fcfa_currency_symbol', 10, 2);

function add_fcfa_currency($currencies)
{
    $currencies['FCFA'] = __('XOF', 'woocommerce');
    return $currencies;
}

function add_fcfa_currency_symbol($currency_symbol, $currency)
{
    switch ($currency) {
        case 'FCFA':
            $currency_symbol = 'FCFA';
            break;
    }
    return $currency_symbol;
}
function diamono_pay_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Diamano_Pay_Gateway'; // Custom Payment Gateway class name
    return $gateways;
}



function declare_cart_checkout_blocks_compatibility()
{
    // Check if the required class exists
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        // Declare compatibility for 'cart_checkout_blocks'
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}
// Hook the custom function to the 'before_woocommerce_init' action
add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');

add_action('woocommerce_blocks_loaded', 'oawoo_register_order_approval_payment_method_type');

/**
 * Custom function to register a payment method type

 */
function oawoo_register_order_approval_payment_method_type()
{
    // Check if the required class exists
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }

    // Include the custom Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . 'class-block.php';

    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            // Register an instance of Diamano_Pay_Gateway_Blocks
            $payment_method_registry->register(new Diamano_Pay_Gateway_Blocks);
        }
    );
}
