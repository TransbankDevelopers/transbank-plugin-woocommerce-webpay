<?php

namespace Transbank\WooCommerce\Webpay\Controllers;

use DateTime;
use Transbank\WooCommerce\Webpay\Exceptions\InvalidOrderException;
use Transbank\WooCommerce\Webpay\TransbankWebpayOrders;
use WC_Gateway_Transbank;
use WC_Order;

class ThanksPageController
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
    
    public function show($orderId)
    {
        $order_info = new WC_Order($orderId);
        $transbank_data = new WC_Gateway_transbank();
        if ($order_info->get_payment_method_title() != $transbank_data->title) {
            return;
        }
        $token = isset($_POST['token_ws']) ? $_POST['token_ws'] : (isset($_POST['TBK_TOKEN']) ? $_POST['TBK_TOKEN'] : null);
        $webpayTransaction = null;
        if ($token !== null) {
            $webpayTransaction = TransbankWebpayOrders::getByToken($token);
        } elseif (isset($_POST['TBK_ORDEN_COMPRA']) && isset($_POST['TBK_ID_SESION'])) {
            $webpayTransaction = TransbankWebpayOrders::getBySessionIdAndOrderId($_POST['TBK_ID_SESION'], $_POST['TBK_ORDEN_COMPRA']);
        } else {
            throw new \Exception('Token not provided');
        }
        
        
        if (!$webpayTransaction) {
            throw new InvalidOrderException('Token inválido');
        }
        if ((int)$webpayTransaction->order_id !== (int)$orderId) {
            throw new InvalidOrderException('La orden recibida no coincide con el token');
        }
        
        if ($webpayTransaction->status === TransbankWebpayOrders::STATUS_ABORTED_BY_USER) {
            wc_print_notice('Compra <strong>Anulada</strong> por usuario. Recuerda que puedes pagar volver a intentar el pago',
                'error');
            
            return;
        }
        
        if (isset($_POST['TBK_TOKEN'])) {
            $order_info = new WC_Order($orderId);
            // Transaction aborted by user
            $order_info->add_order_note(__('Pago abortado por el usuario', 'woocommerce'));
            $order_info->update_status('failed');
            wc_print_notice('Compra <strong>Anulada</strong> por usuario. Recuerda que puedes pagar volver a intentar el pago',
                'error');
            TransbankWebpayOrders::update($webpayTransaction->id,
                ['status' => TransbankWebpayOrders::STATUS_ABORTED_BY_USER]);
            
            return wp_redirect($order_info->get_checkout_payment_url());
        }
        
        if ($webpayTransaction->status == TransbankWebpayOrders::STATUS_FAILED) {
            wc_print_notice('Transacción <strong>fallida</strong>. Puedes pagar volver a intentar el pago', 'error');
        }
        
        $finalResponse = json_decode($webpayTransaction->transbank_response);
        
        if (isset($finalResponse->detailOutput)) {
            $detailOutput = $finalResponse->detailOutput;
            $paymentTypeCode = isset($detailOutput->paymentTypeCode) ? $detailOutput->paymentTypeCode : null;
            $responseCode = isset($detailOutput->responseCode) ? $detailOutput->responseCode : null;
        } else {
            $paymentTypeCode = null;
            $responseCode = null;
        }
        
        if (isset($transbank_data->config)) {
            $paymenCodeResult = "Sin cuotas";
            if (array_key_exists('VENTA_DESC', $transbank_data->config)) {
                if (array_key_exists($paymentTypeCode, $transbank_data->config['VENTA_DESC'])) {
                    $paymenCodeResult = $transbank_data->config['VENTA_DESC'][$paymentTypeCode];
                }
            }
        }
        
        if ($responseCode == 0) {
            $transactionResponse = "Transacci&oacute;n Aprobada";
        } else {
            $transactionResponse = "Transacci&oacute;n Rechazada";
        }
        
        $transactionDate = isset($finalResponse->transactionDate) ? $finalResponse->transactionDate : null;
        $date_accepted = new DateTime($transactionDate);
        
        if ($finalResponse != null) {
            
            if ($paymentTypeCode == "SI" || $paymentTypeCode == "S2" || $paymentTypeCode == "NC" || $paymentTypeCode == "VC") {
                $installmentType = $paymenCodeResult;
            } else {
                $installmentType = "Sin cuotas";
            }
            
            if ($paymentTypeCode == "VD") {
                $paymentType = "Débito";
            } else {
                $paymentType = "Crédito";
            }
            
            echo '</br><h2>Detalles del pago</h2>' . '<table class="shop_table order_details">' . '<tfoot>' . '<tr>' . '<th scope="row">Respuesta de la Transacci&oacute;n:</th>' . '<td><span class="RT">' . $transactionResponse . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row">C&oacute;digo de la Transacci&oacute;n:</th>' . '<td><span class="CT">' . $finalResponse->detailOutput->responseCode . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row">Orden de Compra:</th>' . '<td><span class="RT">' . $finalResponse->buyOrder . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row">Codigo de Autorizaci&oacute;n:</th>' . '<td><span class="CA">' . $finalResponse->detailOutput->authorizationCode . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row">Fecha Transacci&oacute;n:</th>' . '<td><span class="FC">' . $date_accepted->format('d-m-Y') . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row"> Hora Transacci&oacute;n:</th>' . '<td><span class="FT">' . $date_accepted->format('H:i:s') . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row">Tarjeta de Cr&eacute;dito:</th>' . '<td><span class="TC">************' . $finalResponse->cardDetail->cardNumber . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row">Tipo de Pago:</th>' . '<td><span class="TP">' . $paymentType . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row">Tipo de Cuota:</th>' . '<td><span class="TC">' . $installmentType . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row">Monto Compra:</th>' . '<td><span class="amount">' . $finalResponse->detailOutput->amount . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row">N&uacute;mero de Cuotas:</th>' . '<td><span class="NC">' . $finalResponse->detailOutput->sharesNumber . '</span></td>' . '</tr>' . '</tfoot>' . '</table><br/>';
        }
    }
}
