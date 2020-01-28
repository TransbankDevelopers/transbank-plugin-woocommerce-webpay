<?php

class ConnectionCheck {
    public static function check()
    {
        require_once(__DIR__  . '/ConfigProvider.php');
        require_once(__DIR__  . '/HealthCheck.php');
    
        $configProvider = new ConfigProvider();
        $config = array(
            'MODO' => $configProvider->getConfig('webpay_test_mode'),
            'COMMERCE_CODE' => $configProvider->getConfig('webpay_commerce_code'),
            'PUBLIC_CERT' => $configProvider->getConfig('webpay_public_cert'),
            'PRIVATE_KEY' => $configProvider->getConfig('webpay_private_key'),
            'WEBPAY_CERT' => $configProvider->getConfig('webpay_webpay_cert'),
            'ECOMMERCE' => 'woocommerce'
        );
        $healthcheck = new HealthCheck($config);
        $resp = $healthcheck->setInitTransaction();
        ob_clean();
        echo json_encode($resp);
        wp_die();
    }
}
