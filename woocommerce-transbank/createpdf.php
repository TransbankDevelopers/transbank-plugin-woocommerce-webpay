<?php
if (!isset($_COOKIE["ambient"])) {
	exit;
}
include '../../../wp-load.php';
require_once( __DIR__ . "/libwebpay/tcpdf/reportPDFlog.php");
require_once( __DIR__ . "/libwebpay/healthcheck.php");
	$ecommerce = 'woocommerce';
	$arg =  array('MODO' => $_COOKIE["ambient"],
				'COMMERCE_CODE' => $_COOKIE["storeID"],
				'PUBLIC_CERT' => $_COOKIE["certificate"],
				'PRIVATE_KEY' => $_COOKIE["secretCode"],
				'WEBPAY_CERT' => $_COOKIE["certificateTransbank"],
				'ECOMMERCE' => $ecommerce);
	$document = $_POST["document"];

	$healthcheck = new HealthCheck($arg);
	$json =$healthcheck->printFullResume();
	$rl = new reportPDFlog($ecommerce, $document);
	$temp = json_decode($json);
	if ($document == "report"){
		unset($temp->php_info);
	}
	else
	{
		$temp = array('php_info' => $temp->php_info);
	}
	$json = json_encode($temp);
	$rl->getReport($json);
