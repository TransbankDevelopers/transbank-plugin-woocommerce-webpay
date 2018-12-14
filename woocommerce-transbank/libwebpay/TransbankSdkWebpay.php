<?php

require_once(ABSPATH . "wp-content/plugins/woocommerce-transbank/vendor/autoload.php");
require_once('LogHandler.php');

use Transbank\Webpay\Configuration;
use Transbank\Webpay\Webpay;

class TransbankSdkWebpay {

    var $transaction;

    function __construct($config) {
        if (isset($config)) {
            $environment = isset($config["MODO"]) ? $config["MODO"] : 'INTEGRACION';
            $configuration = Configuration::forTestingWebpayPlusNormal();
            $this->log = new LogHandler();
            if ($environment != Webpay::INTEGRACION) {
                $configuration = new Configuration();
                $configuration->setEnvironment(Webpay::PRODUCCION);
                $configuration->setCommerceCode($config["COMMERCE_CODE"]);
                $configuration->setPrivateKey($config["PRIVATE_KEY"]);
                $configuration->setPublicCert($config["PUBLIC_CERT"]);
            }
            if (trim($config["WEBPAY_CERT"]) != '') {
                $this->log->logDebug($environment. ' - Usando certificado webpay definido por el usuario');
                $configuration->setWebpayCert($config["WEBPAY_CERT"]);
            } else {
                $this->log->logDebug($environment . ' - Usando certificado webpay predeterminado');
                $configuration->setWebpayCert(Webpay::defaultCert($environment));
            }
            $this->transaction = (new Webpay($configuration))->getNormalTransaction();
        }
    }

    public function getWebPayCertDefault() {
        return Webpay::defaultCert('INTEGRACION');
    }

	public function initTransaction($amount, $sessionId, $buyOrder, $returnUrl, $finalUrl) {
        $result = array();
		try{
            $initResult = $this->transaction->initTransaction($amount, $buyOrder, $sessionId, $returnUrl, $finalUrl);
            if (isset($initResult) && isset($initResult->url) && isset($initResult->token)) {
                $result = array(
					"url" => $initResult->url,
					"token_ws" => $initResult->token
				);
            } else {
                throw new Exception('No se ha creado la transacción para, amount: ' . $amount . ', sessionId: ' . $sessionId . ', buyOrder: ' . $buyOrder);
            }
		} catch(Exception $e) {
            $result = array(
                "error" => 'Error al crear la transacción',
                "detail" => $e->getMessage()
            );
            $this->log->logError(json_encode($result));
		}
		return $result;
    }

    public function commitTransaction($tokenWs) {
        $result = array();
        try{
            if ($tokenWs == null) {
                throw new Exception("El token webpay es requerido");
            }
            return $this->transaction->getTransactionResult($tokenWs);
        } catch(Exception $e) {
            $result = array(
                "error" => 'Error al confirmar la transacción',
                "detail" => $e->getMessage()
            );
            $this->log->logError(json_encode($result));
        }
        return $result;
    }
}
