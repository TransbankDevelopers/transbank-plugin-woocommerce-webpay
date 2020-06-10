<?php

namespace Transbank\WooCommerce\Webpay\Controllers;

use DateTime;
use Transbank\WooCommerce\Webpay\TransbankWebpayOrders;
use WC_Gateway_Transbank;
use WC_Order;

class ThankYouPageController
{
    public function show($orderId)
    {
        $order_info = new WC_Order($orderId);
        $transbank_data = new WC_Gateway_transbank();
        if ($order_info->get_payment_method_title() != $transbank_data->title) {
            return;
        }
    
        $webpayTransaction = TransbankWebpayOrders::getApprovedByOrderId($orderId);
        if ($webpayTransaction === null) {
            wc_print_notice('Transacción <strong>fallida</strong>. Puedes pagar volver a intentar el pago', 'error');
            return;
        }
        
        // Transacción aprobada
        wc_print_notice('Transacción aprobada', 'success');
        
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
            $paymentCodeResult = "Sin cuotas";
            if (array_key_exists('VENTA_DESC', $transbank_data->config)) {
                if (array_key_exists($paymentTypeCode, $transbank_data->config['VENTA_DESC'])) {
                    $paymentCodeResult = $transbank_data->config['VENTA_DESC'][$paymentTypeCode];
                }
            }
        }
    
        if ($responseCode == 0) {
            $transactionResponse = __("Transacci&oacute;n Aprobada", 'transbank_webpay');
        } else {
            $transactionResponse = __("Transacci&oacute;n Rechazada", 'transbank_webpay');
        }
    
        $transactionDate = isset($finalResponse->transactionDate) ? $finalResponse->transactionDate : null;
        $date_accepted = new DateTime($transactionDate);
        if ($webpayTransaction->status == TransbankWebpayOrders::STATUS_APPROVED) {
        
            if ($paymentTypeCode == "SI" || $paymentTypeCode == "S2" || $paymentTypeCode == "NC" || $paymentTypeCode == "VC") {
                $installmentType = $paymentCodeResult;
            } else {
                $installmentType = __("Sin cuotas", 'transbank_webpay');
            }
        
            if ($paymentTypeCode == "VD") {
                $paymentType = __("Débito", 'transbank_webpay');
            } else {
                $paymentType = __("Crédito", 'transbank_webpay');
            }
        
            echo '</br><h2>Detalles del pago</h2>' . '<table class="shop_table order_details">' . '<tfoot>' . '<tr>' . '<th scope="row">Respuesta de la Transacci&oacute;n:</th>' . '<td><span class="RT">' . $transactionResponse . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row">C&oacute;digo de la Transacci&oacute;n:</th>' . '<td><span class="CT">' . $finalResponse->detailOutput->responseCode . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row">Orden de Compra:</th>' . '<td><span class="RT">' . $finalResponse->buyOrder . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row">Codigo de Autorizaci&oacute;n:</th>' . '<td><span class="CA">' . $finalResponse->detailOutput->authorizationCode . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row">Fecha Transacci&oacute;n:</th>' . '<td><span class="FC">' . $date_accepted->format('d-m-Y') . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row"> Hora Transacci&oacute;n:</th>' . '<td><span class="FT">' . $date_accepted->format('H:i:s') . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row">Tarjeta de Cr&eacute;dito:</th>' . '<td><span class="TC">************' . $finalResponse->cardDetail->cardNumber . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row">Tipo de Pago:</th>' . '<td><span class="TP">' . $paymentType . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row">Tipo de Cuota:</th>' . '<td><span class="TC">' . $installmentType . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row">Monto Compra:</th>' . '<td><span class="amount">' . $finalResponse->detailOutput->amount . '</span></td>' . '</tr>' . '<tr>' . '<th scope="row">N&uacute;mero de Cuotas:</th>' . '<td><span class="NC">' . $finalResponse->detailOutput->sharesNumber . '</span></td>' . '</tr>' . '</tfoot>' . '</table><br/>';
        }
    }
}
