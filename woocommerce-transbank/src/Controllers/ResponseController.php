<?php

namespace Transbank\WooCommerce\Webpay\Controllers;

use DateTime;
use Transbank\WooCommerce\Webpay\Helpers\RedirectorHelper;
use Transbank\WooCommerce\Webpay\Helpers\SessionMessageHelper;
use Transbank\WooCommerce\Webpay\TransbankWebpayOrders;
use TransbankSdkWebpay;
use WC_Order;

class ResponseController
{
    /**
     * @var array
     */
    protected $pluginConfig;
    /**
     * ResponseController constructor.
     *
     * @param array $pluginConfig
     */
    public function __construct(array $pluginConfig)
    {
        $this->pluginConfig = $pluginConfig;
    }
    
    public function response($postData)
    {
        $token_ws = $this->getTokenWs($postData);
        $webpayTransaction = TransbankWebpayOrders::getByToken($token_ws);
        $wooCommerceOrder = $this->getWooCommerceOrderById($webpayTransaction->order_id);
        
        if ($webpayTransaction->status != TransbankWebpayOrders::STATUS_INITIALIZED) {
            wc_add_notice(__('Estimado cliente, le informamos que esta transacción ya ha sido pagada o rechazada.',
                'transbank_webpay'), 'error');
            return RedirectorHelper::redirect($wooCommerceOrder->get_checkout_order_received_url(), ['token_ws' => $token_ws]);
        }
        
        $transbankSdkWebpay = new TransbankSdkWebpay($this->pluginConfig);
        $result = $transbankSdkWebpay->commitTransaction($token_ws);
        
        if ($this->transactionIsApproved($result) && $this->validateTransactionDetails($result, $webpayTransaction)) {
            $this->completeWooCommerceOrder($wooCommerceOrder, $result, $webpayTransaction);
            return RedirectorHelper::redirect($result->urlRedirection, ["token_ws" => $token_ws]);
        }
        
        $this->setWooCommerceOrderAsFailed($wooCommerceOrder, $webpayTransaction, $result);
        return RedirectorHelper::redirect($result->urlRedirection, ["token_ws" => $token_ws]);
    }
    
    /**
     * @param $data
     * @return |null
     */
    protected function getTokenWs($data)
    {
        $token_ws = isset($data["token_ws"]) ? $data["token_ws"] : null;
        
        if (!isset($token_ws)) {
            $this->throwError('No se encontro el token');
        }
        
        return $token_ws;
    }
    /**
     * @param $orderId
     * @return WC_Order
     */
    protected function getWooCommerceOrderById($orderId)
    {
        $wooCommerceOrder = new WC_Order($orderId);
        
        return $wooCommerceOrder;
    }
    
    protected function throwError($msg)
    {
        $error_message = "Estimado cliente, le informamos que su orden termin&oacute; de forma inesperada: <br />" . $msg;
        wc_add_notice(__('ERROR: ', 'transbank_webpay') . $error_message, 'error');
        die();
    }
    
    /**
     * @param WC_Order $wooCommerceOrder
     * @param array $result
     * @param $webpayTransaction
     */
    protected function completeWooCommerceOrder(WC_Order $wooCommerceOrder, $result, $webpayTransaction)
    {
        $wooCommerceOrder->add_order_note(__('Pago exitoso con Webpay Plus', 'transbank_webpay'));
        $wooCommerceOrder->add_order_note(json_encode($result, JSON_PRETTY_PRINT));
        $wooCommerceOrder->payment_complete();
        $final_status = $this->pluginConfig['STATUS_AFTER_PAYMENT'];
        if ($final_status) {
            $wooCommerceOrder->update_status($final_status);
        }
        list($authorizationCode, $amount, $sharesNumber, $transactionResponse, $paymentCodeResult, $date_accepted) = $this->getTransactionDetails($result);
        
        update_post_meta($wooCommerceOrder->get_id(), 'transactionResponse', $transactionResponse);
        update_post_meta($wooCommerceOrder->get_id(), 'buyOrder', $result->buyOrder);
        update_post_meta($wooCommerceOrder->get_id(), 'authorizationCode', $authorizationCode);
        update_post_meta($wooCommerceOrder->get_id(), 'cardNumber', $result->cardDetail->cardNumber);
        update_post_meta($wooCommerceOrder->get_id(), 'paymentCodeResult', $paymentCodeResult);
        update_post_meta($wooCommerceOrder->get_id(), 'amount', $amount);
        update_post_meta($wooCommerceOrder->get_id(), 'cuotas', $sharesNumber);
        update_post_meta($wooCommerceOrder->get_id(), 'transactionDate', $date_accepted->format('d-m-Y / H:i:s'));
        
        wc_add_notice(__('Pago recibido satisfactoriamente', 'transbank_webpay'));
        TransbankWebpayOrders::update($webpayTransaction->id,
            ['status' => TransbankWebpayOrders::STATUS_APPROVED, 'transbank_response' => json_encode($result)]);
    }
    /**
     * @param WC_Order $wooCommerceOrder
     * @param array $result
     * @param $webpayTransaction
     */
    protected function setWooCommerceOrderAsFailed(WC_Order $wooCommerceOrder, $webpayTransaction, $result = null)
    {
        $_SESSION['woocommerce_order_failed'] = true;
        $wooCommerceOrder->add_order_note(__('Pago rechazado', 'transbank_webpay'));
        $wooCommerceOrder->update_status('failed');
        if ($result !== null) {
            $wooCommerceOrder->add_order_note(json_encode($result, JSON_PRETTY_PRINT));
        }
        
        TransbankWebpayOrders::update($webpayTransaction->id,
            ['status' => TransbankWebpayOrders::STATUS_FAILED, 'transbank_response' => json_encode($result)]);
    }
    
    /**
     * @param array $result
     * @return bool
     */
    protected function transactionIsApproved($result)
    {
        if (!isset($result->detailOutput->responseCode)) {
            return false;
        }
        
        return $result->detailOutput->responseCode == 0;
    }
    /**
     * @param array $result
     * @param $webpayTransaction
     * @return bool
     */
    protected function validateTransactionDetails($result, $webpayTransaction)
    {
        if (!isset($result->detailOutput->responseCode)) {
            return false;
        }
        
        return $result->detailOutput->buyOrder == $webpayTransaction->buy_order && $result->sessionId == $webpayTransaction->session_id && $result->detailOutput->amount == $webpayTransaction->amount;
    }
    /**
     * @param array $result
     * @return array
     * @throws \Exception
     */
    protected function getTransactionDetails($result)
    {
        $detailOutput = $result->detailOutput;
        $paymentTypeCode = isset($detailOutput->paymentTypeCode) ? $detailOutput->paymentTypeCode : null;
        $authorizationCode = isset($detailOutput->authorizationCode) ? $detailOutput->authorizationCode : null;
        $amount = isset($detailOutput->amount) ? $detailOutput->amount : null;
        $sharesNumber = isset($detailOutput->sharesNumber) ? $detailOutput->sharesNumber : null;
        $responseCode = isset($detailOutput->responseCode) ? $detailOutput->responseCode : null;
        if ($responseCode == 0) {
            $transactionResponse = "Transacción Aprobada";
        } else {
            $transactionResponse = "Transacción Rechazada";
        }
        $paymentCodeResult = "Sin cuotas";
        if ($this->pluginConfig) {
            if (array_key_exists('VENTA_DESC', $this->pluginConfig)) {
                if (array_key_exists($paymentTypeCode, $this->pluginConfig['VENTA_DESC'])) {
                    $paymentCodeResult = $this->pluginConfig['VENTA_DESC'][$paymentTypeCode];
                }
            }
        }
        
        $transactionDate = isset($result->transactionDate) ? $result->transactionDate : null;
        $date_accepted = new DateTime($transactionDate);
        
        return [$authorizationCode, $amount, $sharesNumber, $transactionResponse, $paymentCodeResult, $date_accepted];
    }
}
