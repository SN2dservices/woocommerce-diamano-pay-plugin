<?php

class WC_Diamano_Pay_Gateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'diamano_pay';
        $this->icon = apply_filters('woocommerce_diamano_pay_icon', plugins_url('assets/icon.png', __FILE__));
        $this->has_fields = false;
        $this->method_title = 'Diamano Pay';
        $this->method_description = 'Accepte les paiements par Orange Money, Wave et carte bancaire au Sénégal';

        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = $this->get_option('testmode') === 'yes';
        $this->apiBaseUrl = 'https://api.diamanopay.com';
        $this->accessToken = $this->testmode ? $this->get_option('sandbox_access_token') : $this->get_option('access_token');
        $this->payment_services = $this->get_option('payment_services');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . $this->id, array($this, 'diamano_pay_webhook'));
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Activer/Désactiver',
                'label' => 'Activer Diamano Pay',
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'title' => array(
                'title' => 'Titre',
                'type' => 'text',
                'default' => 'Diamano pay',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'textarea',
                'default' => 'Payer avec Diamano pay',
            ),
            'testmode' => array(
                'title' => 'Mode test',
                'label' => 'Activer le mode test',
                'type' => 'checkbox',
                'default' => 'yes',
                'desc_tip' => true,
            ),
            'payment_services' => array(
                'title' => 'Les moyens de paiement',
                'type' => 'multiselect',
                'description' => 'Les moyens de paiement à autoriser',
                'options' => array('WAVE' => 'Wave', 'ORANGE_MONEY' => 'Orange money', 'CARD' => 'Carte bancaire'),
                'default' => array('WAVE', 'ORANGE_MONEY'),
                'desc_tip' => true,
            ),
            'sandbox_access_token' => array(
                'title' => "Access Token pour l'environnement sandbox",
                'type' => 'password',
            ),
            'access_token' => array(
                'title' => "Access Token pour l'environnement de production",
                'type' => 'password',
            ),
        );
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $webhook = add_query_arg(array('wc-api' => 'diamano_pay', 'order_id' => $order_id), home_url('/'));
        $checkout_url = wc_get_page_permalink('checkout');
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->accessToken,
            ),
            'body' => wp_json_encode(array(
                'amount' => (float) $order->get_total(),
                'webhook' => $webhook,
                'callbackSuccessUrl' => $this->get_return_url($order),
                'callbackCancelUrl' => $checkout_url,
                'paymentMethods' => $this->payment_services,
                'description' => $this->getDescription($order),
                'extraData' => array('order_id' => $order_id),
            )),
        );
        $url = $this->apiBaseUrl . '/api/payment/paymentToken';
        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            wc_add_notice('Une erreur est survenue. Merci de vérifier les informations de connexion à Diamano Pay.', 'error');
            return;
        }

        $body = json_decode($response['body'], true);

        if (isset($body['statusCode']) && $body['statusCode'] != '200') {
            wc_add_notice($body['message'][0], 'error');
            return;
        }

        return array(
            'result' => 'success',
            'redirect' => $body['paymentUrl'],
        );
    }

    public function getDescription($order)
    {
        $description = "";
        foreach ($order->get_items() as $item) {
            $description .= sprintf(
                'Nom du produit: %s | Quantité: %d | Total: %.2f\n',
                $item->get_name(),
                $item->get_quantity(),
                $item->get_total()
            );
        }
        return $description;
    }

    public function diamano_pay_webhook()
    {
        $order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $request_body = file_get_contents('php://input');
        $request_data = json_decode($request_body, true);

        if (isset($request_data['paymentRequestId'])) {
            if ($this->isPaid($order_id, $request_data['paymentRequestId'])) {
                $order->payment_complete();
                $order->reduce_order_stock();
                $order->add_order_note('Paiement reçu avec succès!', true);
            } else {
                $order->add_order_note('Erreur de paiement est survenue!', true);
            }
        } else {
            $order->add_order_note('Erreur, aucun statut sur le paiement', true);
        }

        WC()->cart->empty_cart();
    }

    public function isPaid($order_id, $payment_request_id)
    {
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ];
        $url = $this->apiBaseUrl . '/api/payment/paymentStatus?paymentReference=' . $payment_request_id;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = json_decode(curl_exec($ch), true);
        if ($response["statusCode"] != null && $response["statusCode"] != "200") {
            return false;
        }
        return true;
    }

    public function is_available()
    {
        if ('yes' !== $this->enabled) {
            return false;
        }

        if (empty($this->accessToken)) {
            return false;
        }

        return true;
    }
}
