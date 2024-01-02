<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Diamano_Pay_Gateway_Blocks extends AbstractPaymentMethodType
{

    private $gateway;
    protected $name = 'diamano_pay'; // your payment gateway name

    public function initialize()
    {
        $this->settings = get_option('woocommerce_diamano_pay_settings', []);
        $this->gateway = new WC_Diamano_Pay_Gateway();
    }

    public function is_active()
    {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles()
    {

        wp_register_script(
            'diamano_pay-blocks-integration',
            plugin_dir_url(__FILE__) . 'checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('diamano_pay-blocks-integration');
        }
        return ['diamano_pay-blocks-integration'];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => $this->gateway->title,
            'asset_url' => plugin_dir_url(__FILE__) . '/assets',
            //'description' => $this->gateway->description,
        ];
    }
}
