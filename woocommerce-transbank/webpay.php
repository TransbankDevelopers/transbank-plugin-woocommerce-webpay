<?php

use Transbank\WooCommerce\Webpay\Controllers\ResponseController;
use Transbank\WooCommerce\Webpay\Controllers\FinalProcessController;
use Transbank\WooCommerce\Webpay\Controllers\ThankYouPageController;
use Transbank\WooCommerce\Webpay\Exceptions\TokenNotFoundOnDatabaseException;
use Transbank\WooCommerce\Webpay\Helpers\RedirectorHelper;
use Transbank\WooCommerce\Webpay\Helpers\SessionMessageHelper;
use Transbank\WooCommerce\Webpay\Telemetry\PluginVersion;
use Transbank\WooCommerce\Webpay\TransbankWebpayOrders;
use Transbank\WooCommerce\Webpay\WordpressPluginVersion;

if (!defined('ABSPATH')) {
    exit();
} // Exit if accessed directly

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @wordpress-plugin
 * Plugin Name: Transbank Webpay Plus
 * Plugin URI: https://www.transbankdevelopers.cl/plugin/woocommerce/webpay
 * Description: Recibe pagos en l&iacute;nea con Tarjetas de Cr&eacute;dito y Redcompra en tu WooCommerce a trav&eacute;s de Webpay Plus.
 * Version: VERSION_REPLACE_HERE
 * Author: Transbank
 * Author URI: https://www.transbank.cl
 * WC requires at least: 3.4.0
 * WC tested up to: 4.0.1
 */

add_action('plugins_loaded', 'woocommerce_transbank_init', 0);

//todo: Eliminar todos estos require y usar PSR-4 de composer
require_once plugin_dir_path(__FILE__) . "vendor/autoload.php";
require_once plugin_dir_path(__FILE__) . "libwebpay/HealthCheck.php";
require_once plugin_dir_path(__FILE__) . "libwebpay/LogHandler.php";
require_once plugin_dir_path(__FILE__) . "libwebpay/ConnectionCheck.php";
require_once plugin_dir_path(__FILE__) . "libwebpay/ReportGenerator.php";
require_once plugin_dir_path(__FILE__) . "libwebpay/TransbankSdkWebpay.php";

register_activation_hook(__FILE__, 'on_webpay_plugin_activation');
add_action( 'admin_init', 'on_transbank_webpay_plugins_loaded' );
add_action('wp_ajax_check_connection', 'ConnectionCheck::check');
add_action('wp_ajax_download_report', 'Transbank\Woocommerce\ReportGenerator::download');
add_filter('woocommerce_payment_gateways', 'woocommerce_add_transbank_gateway');
add_action('woocommerce_before_cart', function() {
    SessionMessageHelper::printMessage();
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_action_links');

//Start sessions if not already done
add_action('init',function() {
    if( !headers_sent() && '' == session_id() ) {
        session_start([
            'read_and_close' => true
        ]);
    }
});

function woocommerce_transbank_init()
{
    if (!class_exists("WC_Payment_Gateway")) {
        return;
    }
    
    class WC_Gateway_Transbank extends WC_Payment_Gateway
    {
        private static $URL_RETURN;
        private static $URL_FINAL;
        
        var $notify_url;
        var $plugin_url;
        
        public function __construct()
        {
            
            self::$URL_RETURN = home_url('/') . '?wc-api=WC_Gateway_transbank';
            self::$URL_FINAL = home_url('/') . '?wc-api=TransbankWebpayThankYouPage';;
            
            $this->id = 'transbank';
            $this->icon = plugin_dir_url(__FILE__ ) . 'libwebpay/images/webpay.png';
            $this->method_title = __('Transbank Webpay Plus');
            $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_' . $this->id, home_url('/'));
            $this->title = 'Transbank Webpay';
            $this->description = 'Permite el pago de productos y/o servicios, con tarjetas de cr&eacute;dito y Redcompra a trav&eacute;s de Webpay Plus';
            $this->plugin_url = plugins_url('/', __FILE__);
            $this->log = new LogHandler();
            
            $certificates = include 'libwebpay/certificates.php';
            $webpay_commerce_code = $certificates['commerce_code'];
            $webpay_private_key = $certificates['private_key'];
            $webpay_public_cert = $certificates['public_cert'];
            $webpay_webpay_cert = (new TransbankSdkWebpay(null))->getWebPayCertDefault();
            
            $this->config = [
                "MODO" => trim($this->get_option('webpay_test_mode', 'INTEGRACION')),
                "COMMERCE_CODE" => trim($this->get_option('webpay_commerce_code', $webpay_commerce_code)),
                "PRIVATE_KEY" => trim(str_replace("<br/>", "\n",
                    $this->get_option('webpay_private_key', $webpay_private_key))),
                "PUBLIC_CERT" => trim(str_replace("<br/>", "\n",
                    $this->get_option('webpay_public_cert', $webpay_public_cert))),
                "WEBPAY_CERT" => trim(str_replace("<br/>", "\n",
                    $this->get_option('webpay_webpay_cert', $webpay_webpay_cert))),
                "URL_RETURN" => home_url('/') . '?wc-api=WC_Gateway_' . $this->id,
                "URL_FINAL" => "_URL_",
                "ECOMMERCE" => 'woocommerce',
                "VENTA_DESC" => [
                    "VD" => "Venta D&eacute;bito",
                    "VN" => "Venta Normal",
                    "VC" => "Venta en cuotas",
                    "SI" => "3 cuotas sin inter&eacute;s",
                    "S2" => "2 cuotas sin inter&eacute;s",
                    "NC" => "N cuotas sin inter&eacute;s"
                ],
                "STATUS_AFTER_PAYMENT" => $this->get_option('after_payment_order_status')
            ];
            
            
            /**
             * Carga configuración y variables de inicio
             **/
            
            $this->init_form_fields();
            $this->init_settings();
            
            add_action('woocommerce_thankyou', [new ThankYouPageController($this->config), 'show'], 1);
            add_action('woocommerce_api_transbankwebpaythankyoupage', [new FinalProcessController($this->config), 'show']);
            add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'registerPluginVersion']);
            add_action('woocommerce_api_wc_gateway_' . $this->id, [$this, 'check_ipn_response']);
            add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
            
            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }
        public function enqueueScripts()
        {
            wp_enqueue_script('ajax-script', plugins_url('/js/admin.js', __FILE__), ['jquery']);
            wp_localize_script('ajax-script', 'ajax_object', ['ajax_url' => admin_url('admin-ajax.php')]);
        }
        
        public function checkConnection()
        {
            require_once('ConfigProvider.php');
            require_once('HealthCheck.php');
            
            $configProvider = new ConfigProvider();
            $config = [
                'MODO' => $configProvider->getConfig('webpay_test_mode'),
                'COMMERCE_CODE' => $configProvider->getConfig('webpay_commerce_code'),
                'PUBLIC_CERT' => $configProvider->getConfig('webpay_public_cert'),
                'PRIVATE_KEY' => $configProvider->getConfig('webpay_private_key'),
                'WEBPAY_CERT' => $configProvider->getConfig('webpay_webpay_cert'),
                'ECOMMERCE' => 'woocommerce'
            ];
            $healthcheck = new HealthCheck($config);
            $resp = $healthcheck->setInitTransaction();
            // ob_clean();
            echo json_encode($resp);
            exit;
        }
        
        public function registerPluginVersion()
        {
            if (!$this->get_option('webpay_test_mode', 'INTEGRACION') === 'PRODUCCION') {
                return;
            }
            
            $commerceCode = $this->get_option('webpay_commerce_code');
            $certificates = include 'libwebpay/certificates.php';
            if ($commerceCode == $certificates['commerce_code']) {
                // If we are using the default commerce code, then abort as the user have not updated that value yet.
                return;
            };
            
            $pluginVersion = $this->getPluginVersion();
            
            (new PluginVersion)->registerVersion($commerceCode, $pluginVersion, wc()->version,
                PluginVersion::ECOMMERCE_WOOCOMMERCE);
        }
        
        /**
         * Comprueba configuración de moneda (Peso Chileno)
         **/
        function is_valid_for_use()
        {
            if (!in_array(get_woocommerce_currency(),
                apply_filters('woocommerce_' . $this->id . '_supported_currencies', ['CLP']))) {
                return false;
            }
            
            return true;
        }
        
        /**
         * Inicializar campos de formulario
         **/
        function init_form_fields()
        {
            $this->form_fields = [
                'enabled' => [
                    'title' => __('Activar/Desactivar', 'transbank_webpay'),
                    'type' => 'checkbox',
                    'default' => 'yes'
                ],
                'webpay_test_mode' => [
                    'title' => __('Ambiente', 'transbank_webpay'),
                    'type' => 'select',
                    'options' => [
                        'INTEGRACION' => __('Integraci&oacute;n', 'transbank_webpay'),
                        'PRODUCCION' => __('Producci&oacute;n', 'transbank_webpay')
                    ],
                    'default' => 'INTEGRACION'
                ],
                'webpay_commerce_code' => [
                    'title' => __('C&oacute;digo de Comercio', 'transbank_webpay'),
                    'type' => 'text',
                    'default' => $this->config['COMMERCE_CODE']
                ],
                'webpay_private_key' => [
                    'title' => __('Llave Privada', 'transbank_webpay'),
                    'description' => __('Contenido de archivo con extensión .key'),
                    'type' => 'textarea',
                    'default' => str_replace("<br/>", "\n", $this->config['PRIVATE_KEY']),
                    'css' => 'font-family: monospace'
                ],
                'webpay_public_cert' => [
                    'title' => __('Certificado', 'transbank_webpay'),
                    'description' => __('Contenido de archivo con extensión .crt'),
                    'type' => 'textarea',
                    'default' => str_replace("<br/>", "\n", $this->config['PUBLIC_CERT']),
                    'css' => 'font-family: monospace'
                ],
                'after_payment_order_status' => [
                    'title' => __('Estado de pedido después del pago.'),
                    'description' => __('<strong style="color:red;">DEPRECADO</strong>: Se eliminará en proximas versiones', 'transbank_webpay'),
                    'type' => 'select',
                    'options' => [
                        'wc-pending' => __('Pendiente', 'transbank_webpay'),
                        'wc-processing' => __('Procesando', 'transbank_webpay'),
                        'wc-on-hold' => __('Retenido', 'transbank_webpay'),
                        'wc-completed' => __('Completado', 'transbank_webpay'),
                        'wc-cancelled' => __('Cancelado', 'transbank_webpay'),
                        'wc-refunded' => __('Reembolsado', 'transbank_webpay'),
                        'wc-failed' => __('Fallido', 'transbank_webpay')
                    ],
                    'default' => 'wc-processing'
                ]
            ];
        }
        
        /**
         * Pagina Receptora
         **/
        function receipt_page($order_id)
        {
            $order = new WC_Order($order_id);
            $amount = (int) number_format($order->get_total(), 0, ',', '');
            $sessionId = uniqid();
            $buyOrder = $order_id;
            $returnUrl = self::$URL_RETURN;
            $finalUrl = str_replace("_URL_",
                add_query_arg('key', $order->get_order_key(), $order->get_checkout_order_received_url()),
                self::$URL_FINAL);
            
            $transbankSdkWebpay = new TransbankSdkWebpay($this->config);
            $result = $transbankSdkWebpay->initTransaction($amount, $sessionId, $buyOrder, $returnUrl, $finalUrl);
            
            if (!isset($result["token_ws"])) {
                wc_add_notice( 'Ocurrió un error al intentar conectar con WebPay Plus. Por favor intenta mas tarde.<br/>',
                    'error');
                return;
            }

            $url = $result["url"];
            $token_ws = $result["token_ws"];

            TransbankWebpayOrders::createTransaction([
                'order_id' => $order_id,
                'buy_order' => $buyOrder,
                'amount' => $amount,
                'token' => $token_ws,
                'session_id' => $sessionId,
                'status' => TransbankWebpayOrders::STATUS_INITIALIZED
            ]);

            RedirectorHelper::redirect($url, ["token_ws" => $token_ws]);
        }
        
        /**
         * Obtiene respuesta IPN (Instant Payment Notification)
         **/
        function check_ipn_response()
        {
            @ob_clean();
            if (isset($_POST)) {
                header('HTTP/1.1 200 OK');
                return (new ResponseController($this->config))->response($_POST);
            } else {
                echo "Ocurrio un error al procesar su compra";
            }
        }
        
        /**
         * Procesar pago y retornar resultado
         **/
        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);
            
            return [
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            ];
        }
        
        /**
         * Opciones panel de administración
         **/
        public function admin_options()
        {
            
            $this->healthcheck = new HealthCheck($this->config);
            include 'libwebpay/admin-options.php';
        }
       
        /**
         * @return mixed
         */
        public function getPluginVersion()
        {
            return (new WordpressPluginVersion())->get();
        }
        
        
    }
    
    /**
     * Añadir Transbank Plus a Woocommerce
     **/
    function woocommerce_add_transbank_gateway($methods)
    {
        $methods[] = 'WC_Gateway_transbank';
        
        return $methods;
    }
    
    /**
     * Muestra detalle de pago a Cliente a finalizar compra
     **/
    function pay_transbank_webpay_content($orderId)
    {
    
    }
    
    
}

function add_action_links($links)
{
    $newLinks = [
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank') . '">Settings</a>',
    ];
    
    return array_merge($links, $newLinks);
}

function on_webpay_plugin_activation()
{
    woocommerce_transbank_init();
    if (!class_exists(WC_Gateway_Transbank::class)) {
        die('Se necesita tener WooCommerce instalado y activo para poder activar este plugin');
        return;
    }
    $pluginObject = new WC_Gateway_Transbank();
    $pluginObject->registerPluginVersion();
}

function on_transbank_webpay_plugins_loaded() {
    TransbankWebpayOrders::createTableIfNeeded();
}

function transbank_remove_database() {
    TransbankWebpayOrders::deleteTable();
}

register_uninstall_hook( __FILE__, 'transbank_remove_database' );
