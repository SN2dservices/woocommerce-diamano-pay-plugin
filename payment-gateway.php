<?php
/*
 * Plugin Name: WooCommerce Diamano Pay Plugin
 * Plugin URI: https://www.diamanopay.com
 * Description: Accepte les paiements par Orange Money, Wave et carte bancaire au Sénégal.
 * Author: Diamanopay
 * Author URI: https://www.diamanopay.com
 * Version: 1.0.1
 */

add_action('plugins_loaded', 'woocommerce_diamano_pay_init', 0);
function woocommerce_diamano_pay_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    include_once dirname(__FILE__) . '/payment-gateway-class.php';

    add_filter('woocommerce_payment_gateways', 'diamano_pay_add_gateway_class');
    add_filter('woocommerce_currencies', 'add_fcfa_currency');
    add_filter('woocommerce_currency_symbol', 'add_fcfa_currency_symbol', 10, 2);
}

function add_fcfa_currency($currencies)
{
    $currencies['FCFA'] = __('West African CFA franc (XOF)', 'woocommerce');
    return $currencies;
}

function add_fcfa_currency_symbol($currency_symbol, $currency)
{
    if ($currency === 'FCFA') {
        $currency_symbol = 'FCFA';
    }
    return $currency_symbol;
}

function diamano_pay_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Diamano_Pay_Gateway';
    return $gateways;
}

add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');
function declare_cart_checkout_blocks_compatibility()
{
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}

add_action('woocommerce_blocks_loaded', 'diamano_register_order_approval_payment_method_type');
function diamano_register_order_approval_payment_method_type()
{
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }

    require_once plugin_dir_path(__FILE__) . 'class-block.php';

    add_action('woocommerce_blocks_payment_method_type_registration', function ($payment_method_registry) {
        $payment_method_registry->register(new Diamano_Pay_Gateway_Blocks());
    });
}
