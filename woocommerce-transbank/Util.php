<?php

namespace Transbank\Plugin\Woocommerce;

use Transbank\Webpay\Configuration;
use Transbank\Webpay\Webpay;


class Util
{
    public static function createWebpayPlusConfig($options) {
        $config = new Configuration();
        $config->setEnvironment($options['MODO']);
        $config->setCommerceCode($options['COMMERCE_CODE']);
        $config->setPrivateKey($options['PRIVATE_KEY']);
        $config->setPublicCert($options['PUBLIC_CERT']);
        $config->setWebpayCert($options['WEBPAY_CERT']);

        return $config;
    }

    public static function get($url, $options = array('headers' => 0, 'transport' => 'https', 'port' => 443, 'proxy' => null)) {
        $transport = '';
        $port = 80;
        if (!empty($options['transport'])) $transport = $options['transport'];
        if (!empty($options['port'])) $port = $options['port'];

        $http_options = array(
            'method' => 'GET'
        );

        if(isset($options['proxy']) && $options['proxy'] != null) {
            $http_options['proxy'] = $options['proxy'];
        }

        $ssl_options = array(
            'verify_host' => false
        );

        $context = stream_context_create(array(
            'http' => $http_options,
            'ssl' => $ssl_options
        ));

        $fp = fopen($url, 'r', false, $context);

        $response_metadata = stream_get_meta_data($fp);
        if (1 != preg_match("/^HTTP\/[0-9\.]* ([0-9]{3}) ([^\r\n]*)/", $response_metadata['wrapper_data'][0], $matches)) {
            trigger_error('httpGet: invalid HTTP reply.');
            fclose($fp);
            return null;
        }

        if ($matches[1] != '200') {
            trigger_error('httpGet: HTTP error: ' . $matches[1] . ' ' . $matches[2]);
            fclose($fp);
            return null;
        }

        switch (intval($matches[1])) {
            case 200: // OK
            case 304: // Not modified
                break;
            case 301: // Moved permanently
            case 302: // Moved temporarily
            case 307: // Moved temporarily
                break;
            default:
                trigger_error('httpGet: HTTP error: ' . $matches[1] . ' ' . $matches[2]);
                return null;
        }

        $response_body = stream_get_contents($fp);

        fclose($fp);

        return $response_body;
    }
}
