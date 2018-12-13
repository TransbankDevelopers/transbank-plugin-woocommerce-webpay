<?php
if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

use Transbank\Webpay\Webpay;
use Transbank\Plugin\Woocommerce\Util;

require_once(ABSPATH . "wp-content/plugins/woocommerce-transbank/vendor/autoload.php");
require_once(ABSPATH . "wp-content/plugins/woocommerce-transbank/Util.php");
require_once(ABSPATH . "wp-content/plugins/woocommerce-transbank/libwebpay/loghandler.php");

class HealthCheck
{
    var $phpinfo;
    var $publicCert;
    var $privateKey;
    var $webpayCert;
    var $commerceCode;
    var $environment;
    var $extensions;
    var $versioninfo;
    var $resume;
    var $fullResume;
    var $certficados;
    var $ecommerce;
    var $webpay;
    var $webpayconfig;
    var $testurl;
    var $log;

    function __construct($args)
    {
        $this->log = new LogHandler();
        $this->environment = $args['MODO'];
        $this->commerceCode = $args['COMMERCE_CODE'];
        $this->publicCert = $args['PUBLIC_CERT'];
        $this->privateKey = $args['PRIVATE_KEY'];
        $this->webpayCert = $args['WEBPAY_CERT'];
        if (empty($args['ECOMMERCE'] or $args['ECOMMERCE'] === null or !isset($args['ECOMMERCE']))) {
            $args['ECOMMERCE'] = 'sdk';
        }
        $this->ecommerce = strtolower($args['ECOMMERCE']);
        $this->testurl = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];

        $args['URL_RETURN'] = $this->testurl . "?action=return";
        $args['URL_FINAL'] = $this->testurl . "?action=final";
        // $this->webpayconfig = new WebPayConfig($args);

        $config = Util::createWebpayPlusConfig($args);
        $this->webpay = new Webpay($config);
        $this->extensions = null;
        $this->resume = null;
        $this->fullResume = null;
        $this->versioninfo = null;
        $this->certificates = null;

        // extensiones necesarias
        $this->extensions = array(
            'openssl',
            'SimpleXML',
            'soap',
            'dom',
        );
    }

    // validaciones


    // validacion certificado publico versus la llave
    private function getValidateCertificates()
    {
        if ($var = openssl_x509_parse($this->publicCert)) {
            $today = date('Y-m-d H:i:s');
            $from = date('Y-m-d H:i:s', $var['validFrom_time_t']);
            $to = date('Y-m-d H:i:s', $var['validTo_time_t']);
            if ($today >= $from and $today <= $to) {
                $val = "OK";
            } else {
                $val = "Error!: Certificado Invalido por Fecha";
            }
            $this->certinfo = array(
                'subject_commerce_code' => $var['subject']['CN'],
                'version' => $var['version'],
                'is_valid' => $val,
                'valid_from' => date('Y-m-d H:i:s', $var['validFrom_time_t']),
                'valid_to' => date('Y-m-d H:i:s', $var['validTo_time_t']),
            );
        } else {
            $this->certinfo = array(
                'subject_commerce_code' => "",
                'version' => "",
                'is_valid' => "",
                'valid_from' => "",
                'valid_to' => "",
            );
        }
        if (openssl_x509_check_private_key($this->publicCert, $this->privateKey)) {
            if ($this->commerceCode == $this->certinfo['subject_commerce_code']) {
                $this->certificates = array(
                    'cert_vs_private_key' => 'OK',
                    'commerce_code_validate' => 'OK'
                );
            }
        } else {
            $this->certificates = array(
                'cert_vs_private_key' => 'Error!: Certificados inconsistentes'
            );
        }
        return array('consistency' => $this->certificates, 'cert_info' => $this->certinfo);
    }

    // valida version de php
    private function getValidatephp()
    {
        if (version_compare(phpversion(), '7.0.0', '<') and version_compare(phpversion(), '5.3.0', '>=')) {
            $this->versioninfo = array(
                'status' => 'OK',
                'version' => phpversion()
            );
        } else {
            $this->versioninfo = array(
                'status' => 'Error!: Version no soportada',
                'version' => phpversion()
            );
        }

        return $this->versioninfo;

    }


    // verifica si existe la extension y cual es la version de esta
    private function getCheckExtension($extension)
    {
        if (extension_loaded($extension)) {
            if ($extension == 'openssl') {
                $version = OPENSSL_VERSION_TEXT;
            } else {
                $version = phpversion($extension);
                if (empty($version) or $version == null or $version === false or $version == " " or $version == "") {
                    $version = "PHP Extension Compiled. ver:" . phpversion();
                }
            }
            $status = 'OK';
            $result = array(
                'status' => $status,
                'version' => $version
            );
        } else {
            $result = array(
                'status' => 'Error!',
                'version' => 'No Disponible'
            );
        }

        return $result;
    }

    private function getLastGitHubReleaseVersion($string)
    {
        $baseurl = 'https://api.github.com/repos/' . $string . '/releases/latest';
        $agent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);

        $content = curl_exec($ch);

        curl_close($ch);

        $con = json_decode($content, true);

        $version = $con['tag_name'];

        return $version;

    }


    // funcion para obtener info de cada ecommerce, si el ecommerce es incorrecto o no esta seteado se escapa como respuesta "NO APLICA"
    private function getEcommerceInfo($ecommerce)
    {
        if (!class_exists('WooCommerce')) {
            exit;
        } else {
            global $woocommerce;
            if (!$woocommerce->version) {
                exit;
            } else {
                $actualversion = $woocommerce->version;
                $lastversion = $this->getLastGitHubReleaseVersion('woocommerce/woocommerce');
                $file = ABSPATH . "/wp-content/plugins/woocommerce-transbank/class-wc-transbank.php";
                $search = " * Version:";
                $lines = file($file);
                foreach ($lines as $line) {
                    if (strpos($line, $search) !== false) {
                        $currentplugin = str_replace(" * Version:", "", $line);
                    }
                }
            }
        }

        $result = array(
            'current_ecommerce_version' => $actualversion,
            'last_ecommerce_version' => $lastversion,
            'current_plugin_version' => $currentplugin
        );

        return $result;
    }

    private function getPluginInfo($ecommerce)
    {
        $data = $this->getEcommerceInfo($ecommerce);
        $result = array(
            'ecommerce' => $ecommerce,
            'ecommerce_version' => $data['current_ecommerce_version'],
            'current_plugin_version' => $data['current_plugin_version'],
            'last_plugin_version' => $this->getPluginLastVersion()
        );
        return $result;
    }


    // arma array con informacion del ultimo plugin compatible con el ecommerce
    private function getPluginLastVersion()
    {
        $githubApiUrl = 'https://api.github.com/repos/TransbankDevelopers/transbank-plugin-woocommerce-webpay/releases/latest';

        $agent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $githubApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);

        $body = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($body, true);
        if (isset($json['message']))
            return 'UNDEFINED';

        $tag_name = $json['tag_name'];

        return $tag_name;
    }


    // lista y valida extensiones/ modulos de php en servidor ademas mostrar version
    private function getExtensionsValidate()
    {
        foreach ($this->extensions as $value) {
            $this->resExtensions[$value] = $this->getCheckExtension($value);
        }
        return $this->resExtensions;
    }

    // crea resumen de informacion del servidor. NO incluye a PHP info
    private function getServerResume()
    {
        // arma array de despliegue
        $this->resume = array(
            'php_version' => $this->getValidatephp(),
            'server_version' => array('server_software' => $_SERVER['SERVER_SOFTWARE']),
            'plugin_info' => $this->getPluginInfo($this->ecommerce)
        );
        return $this->resume;
    }

    // crea array con la informacion de comercio para posteriormente exportarla via json

    private function getCommerceInfo()
    {
        $result = array(
            'environment' => $this->environment,
            'commerce_code' => $this->commerceCode,
            'public_cert' => $this->publicCert,
            'private_key' => $this->privateKey,
            'webpay_cert' => $this->webpayCert
        );

        return array('data' => $result);
    }

    // guarda en array informacion de funcion phpinfo

    private function getPhpInfo()
    {
        ob_start();
        phpinfo();
        $info = ob_get_contents();
        ob_end_clean();

        $newinfo = strstr($info, '<table>');
        $newinfo = strstr($newinfo, '<h1>PHP Credits</h1>', true);
        $return = array('string' => array('content' => str_replace('</div></body></html>', '', $newinfo)));

        return $return;
    }

    private function setInitTransaction()
    {

        $rs = array();

        $amount = 9990;
        $buyOrder = "_Healthcheck_";
        $sessionId = uniqid();
        $url = "https://webpay3gint.transbank.cl/filtroUnificado/initTransaction";
        $result = $this->webpay->getNormalTransaction()->initTransaction(
            $amount, $sessionId, $buyOrder, $this->testurl . "?action=return", $url
        );

        if (is_array($result) && isset($result["error"]) && !empty($result["error"]))
            $status = 'Error';
        else if (is_object($result) && !property_exists($result, 'url'))
            $status = 'Error';
        else {
            $status = 'OK';

            $rs = array (
                "url" => $result->url,
                "token_ws" => $result->token
            );
        }


        $response = array(
            'status' => array('string' => $status),
            'response' => preg_replace('/<!--(.*)-->/Uis', '', $rs)
        );

        return $response;
    }

    //compila en solo un metodo toda la informacion obtenida, lista para imprimir
    private function getFullResume()
    {
        $this->fullResume = array(
            'validate_certificates' => $this->getValidateCertificates(),
            'validate_init_transaction' => $this->setInitTransaction(),
            'server_resume' => $this->getServerResume(),
            'php_extensions_status' => $this->getExtensionsValidate(),
            'commerce_info' => $this->getCommerceInfo(),
            'php_info' => $this->getPhpInfo()
        );

        return $this->fullResume;
    }


//funciones de impresion
    // imprime informacion de comercio y llaves
    public function printCommerceInfo()
    {
        return json_encode($this->getCommerceInfo());
    }

    public function printPhpInfo()
    {
        return json_encode($this->getPhpInfo());
    }

    // imprime resultado la consistencia de certificados y llabves
    public function printCertificatesStatus()
    {
        return json_encode($this->getValidateCertificates());
    }

    // imprime en formato json la validacion de extensiones / modulos de php
    public function printExtensionStatus()
    {
        return json_encode($this->getExtensionsValidate());
    }

    // imprime en formato json informacion del servidor
    public function printServerResume()
    {
        return json_encode($this->getServerResume());
    }

    // imprime en formato json el resumen completo
    public function printFullResume()
    {
        return json_encode($this->getFullResume(), JSON_PRETTY_PRINT); // NOTE: quitar el pretty print antes de pasar a produccion
    }

    public function getInitTransaction()
    {
        return json_encode($this->setInitTransaction());
    }
}


?>
