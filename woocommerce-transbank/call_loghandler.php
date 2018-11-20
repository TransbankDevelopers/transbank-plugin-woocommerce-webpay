<?php

include '../../../wp-load.php';
require_once('libwebpay/loghandler.php');
session_start();
$log = new loghandler('woocommerce');

if ($_COOKIE["action_check"] == 'true') {
	$log->setLockStatus(true);
	$log->setnewconfig($_COOKIE['days'] , $_COOKIE['size']);
}
else
	$log->setLockStatus(false);

echo "<script>window.close();</script>";