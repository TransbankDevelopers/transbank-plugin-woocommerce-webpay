<?php

use \Transbank\Webpay\Webpay;
use \Transbank\Webpay\Configuration;

if (!class_exists("WC_Payment_Gateway")) {
    require_once ($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
}

class WoocommerceConfigProvider extends WC_Payment_Gateway {
    public function __construct() {
        $this->id = 'transbank';
    }

    public function getWoocommerceOption($option) {
        $value = $this->get_option($option);
        if (!empty($value))
            return $value;

        $config = Configuration::forTestingWebpayPlusNormal();

        switch ($option) {
            case 'webpay_test_mode':
                return Webpay::INTEGRACION;
            case 'webpay_commerce_code':
                return $config->getCommerceCode();
            case 'webpay_public_cert':
                return $config->getPublicCert();
            case 'webpay_private_key':
                return $config->getPrivateKey();
            case 'webpay_webpay_cert':
                return $config->getWebpayCert();
        }

        return -500;
    }
}
