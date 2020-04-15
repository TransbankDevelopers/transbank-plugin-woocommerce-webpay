<?php

namespace Transbank\WooCommerce\Webpay;

class TransbankWebpayPlugin
{
    const TRANSACTIONS_TABLE_NAME = 'webpay_transactions';
    /**
     * @return string
     */
    public static function getWebpayTransactionsTableName()
    {
        global $wpdb;
        return $wpdb->prefix . static::TRANSACTIONS_TABLE_NAME;
    }
    
}
