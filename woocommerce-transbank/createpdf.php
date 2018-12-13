<?php

require_once ( __DIR__ . '/WoocommerceConfigProvider.php');
require_once( __DIR__ . "/libwebpay/tcpdf/reportPDFlog.php");
require_once( __DIR__ . "/libwebpay/healthcheck.php");

$wcConfig = new WoocommerceConfigProvider();

$document = $_GET["document"];
$config = array(
    'MODO' => $wcConfig->getWoocommerceOption('webpay_test_mode'),
    'COMMERCE_CODE' => $wcConfig->getWoocommerceOption('webpay_commerce_code'),
    'PUBLIC_CERT' => $wcConfig->getWoocommerceOption('webpay_public_cert'),
    'PRIVATE_KEY' => $wcConfig->getWoocommerceOption('webpay_private_key'),
    'WEBPAY_CERT' => $wcConfig->getWoocommerceOption('webpay_webpay_cert'),
    'ECOMMERCE' => 'woocommerce'
);

$healthcheck = new HealthCheck($config);

$json = $healthcheck->printFullResume();
$temp = json_decode($json);
if ($document == "report"){
    unset($temp->php_info);
} else {
    $temp = array('php_info' => $temp->php_info);
}
$rl = new reportPdfLog('woocommerce', $document);
$rl->getReport(json_encode($temp));
