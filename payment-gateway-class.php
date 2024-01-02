<?php
class WC_Diamano_Pay_Gateway extends WC_Payment_Gateway
{

    /**
     * Class constructor, more about it in Step 3
     */
    public function __construct()
    {
        $this->id = 'diamano_pay'; // payment gateway plugin ID
        $this->icon = apply_filters('woocommerce_diamano_pay_icon', plugins_url('assets/icon.png', __FILE__));
        $this->has_fields = false; // in case you need a custom credit card form
        $this->method_title = 'Diamano Pay';
        $this->method_description = 'Accepte les paiments par Orange Money, Wave et carte bancaire au Sénégal'; // will be displayed on the options page

        // Method with all the options fields
        $this->init_form_fields();
        // Load the settings.
        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->apiBaseUrl = $this->testmode ? 'https://sandbox-api.diamanopay.com' : 'https://api.diamanopay.com';
        $this->client_id = $this->testmode ? $this->get_option('test_client_id') : $this->get_option('client_id');
        $this->client_secret = $this->testmode ? $this->get_option('test_client_secret') : $this->get_option('client_secret');
        $this->payment_services = $this->get_option('payment_services');
        // This action hook saves the settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // You can also register a webhook here
        add_action('woocommerce_api_diamano_pay', array($this, 'diamano_pay_webhook'));
    }

    /**
     * Plugin options, we deal with it in Step 3 too
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => 'Activer/Désactiver',
                'label'       => 'Activer Diamano Pay',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => 'Titre',
                'type'        => 'text',
                'description' => 'Entrer un titre à afficher durant le checkout.',
                'default'     => 'Diamano pay',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'Entrer une description à afficher durant le checkout.',
                'default'     => 'Payer avec Diamano pay',
            ),
            'testmode' => array(
                'title'       => 'Mode test',
                'label'       => 'Activer le mode test',
                'type'        => 'checkbox',
                'description' => 'Activer le mode test pour tester',
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'payment_services' => array(
                'title'       => 'Les moyens de paiement',
                'label'       => 'Sélectionner les moyens de paiement',
                'type'        => 'multiselect',
                'description' => 'Les moyens de paiement à autoriser',
                'options'     => array('WAVE' => 'Wave', 'ORANGE_MONEY' => 'Orange  money', 'CARD' => 'Carte bancaire'),
                'default'     => array('WAVE', 'ORANGE_MONEY'),
                'desc_tip'    => true,
            ),
            'test_client_id' => array(
                'title'       => "ClientId pour l'environnement sandbox",
                'type'        => 'password'
            ),
            'test_client_secret' => array(
                'title'       => "ClientSecret pour l'environnement sandbox",
                'type'        => 'password',
            ),
            'client_id' => array(
                'title'       => "ClientId pour l'environnement de production",
                'type'        => 'password'
            ),
            'client_secret' => array(
                'title'       => "ClientSecret pour l'environnement de production",
                'type'        => 'password'
            )
        );
    }
    /*
		 * We're processing the payments here, everything about it is in Step 5
		 */
    public function process_payment($order_id)
    {

        $order = wc_get_order($order_id);
        $data = $order->data;
        $webhook = str_replace('https:', 'http:', add_query_arg(array('wc-api' => 'diamano_pay', 'order_id' => $order_id), home_url('/')));
        $checkout_url = wc_get_page_permalink('checkout');
        $args = array(
            'method' => 'POST',
            'headers'  => array(
                'Content-type' => 'application/json'
            ),
            'body' => wp_json_encode(array(
                'amount' => (float)$data["total"],
                'webhook' => $webhook,
                'callbackSuccessUrl' => $this->get_return_url($order),
                'callbackCancelUrl' => $checkout_url,
                'paymentMethods' => $this->payment_services,
                'description' => $this->getDescription($order),
                'extraData' => array('order_id' => $order_id)
            ))
        );
        $url = add_query_arg(array(
            'clientId' => $this->client_id,
            'clientSecret' => $this->client_secret,
        ), $this->apiBaseUrl . '/api/payment/cms/paymentToken');
        $api_response = wp_remote_post($url, $args);
        $body = json_decode($api_response['body'], true);
        if (!is_wp_error($api_response)) {
            if (isset($body["statusCode"]) && $body["statusCode"] != "200") {
                $messages = $body["message"];
                wc_add_notice($messages[0], 'error');
                return;
            } else {
                return array(
                    'result' => 'success',
                    'redirect' => $body["paymentUrl"]
                );
            }
        } else {
            wc_add_notice('Une erreur est survenue. Merci de vérifer les informations de connexion à diamano pay', 'error');
            return;
        }
    }

    public function getDescription($order)
    {
        $description = "";
        foreach ($order->get_items() as $item_id => $item) {
            $product_name   = $item->get_name();
            $item_quantity  = $item->get_quantity();
            $item_total     = $item->get_total();
            $description .= 'Nom du produit: ' . $product_name . ' | Quantité: ' . $item_quantity . ' | total éléments: ' . number_format($item_total, 2) . '\n';
        }
        return $description;
    }


    public function diamano_pay_webhook()
    {
        global $woocommerce;
        $order = wc_get_order($_GET['order_id']);
        if ($this->isPaid($_GET['token'])) {
            $order->payment_complete();
            $order->reduce_order_stock();
            $order->add_order_note('Paiement reçu avec succès!', true);
        } else {
            $order->add_order_note('Erreur de paiement !', true);
        }
        $woocommerce->cart->empty_cart();
    }

    public function isPaid($token, $order_id)
    {
        $url = add_query_arg(array(
            'clientId' => $this->client_id,
            'clientSecret' => $this->client_secret,
            'token' => $token,
        ), $this->apiBaseUrl . '/api/payment/cms/paymentStatus');
        $api_response = wp_remote_get($url);
        $body = json_decode($api_response['body'], true);
        if (!is_wp_error($api_response)) {
            if (isset($body["statusCode"]) && $body["statusCode"] != "200") {
                return false;
            } else {
                $extraData = $body["extraData"];
                return $body['status'] === "SUCCESS" && $extraData != null && $extraData['order_id'] === $order_id;
            }
        }
        return false;
    }
}
