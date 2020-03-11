<?php

require_once dirname(__FILE__, 2) . "/LogHandler.php";


class GenerateSSL
{
    // shell comando php -m | grep ssl
    function __construct($commonName) {
        $this->log = new LogHandler();

        $this->bits = 2048;
        $this->extraArgs = array('digest_alg' => 'sha256');
        $this->days=1460;
        $this->password = "";

        $this->dn = array(
            "commonName" => $commonName,
        );
    }

    private function __generate_ssl(){

        // Generate a new private (and public) key pair
        $privkey = openssl_pkey_new(array(
            "private_key_bits" => $this->bits,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ));
        
        // Generate a certificate signing request
        $csr = openssl_csr_new(
            $this->dn,
            $privkey,
            $this->extraArgs,
        );
        
        // Generate a self-signed cert, valid for 365 days
        $x509 = openssl_csr_sign(
            $csr,
            null,
            $privkey,
            $days=$this->days,
            $this->extraArgs,
        );
        
        // Save your private key, CSR and self-signed cert for later use
        openssl_csr_export($csr, $csrout);
        openssl_x509_export($x509, $certout);
        openssl_pkey_export($privkey, $pkeyout, $this->password);

        // output keys
        return array(
            'csrout' => $csrout,
            'certout' => $certout,
            'pkeyout' => $pkeyout,
            'commerce_code' => $this->dn['commonName'],
        );

    }

    public function generate(){
        try {
            $result = $this->__generate_ssl();
        } catch(Exception $e) {
            $result = array(
                "error" => 'Error al generar los certificados',
                "detail" => $e->getMessage()
            );
            $this->log->logError(json_encode($result));
        }
        return $result;
    }

    public function getFullResume() {
        return json_encode($this->generate());
    }

    // Ajax call method
    public static function getSSL() {
        $self = new GenerateSSL($_POST["commerce_code"]);
        $result = $self->generate();
        if ($result) {
            if (!empty($result["error"]) && isset($result["error"])) {
                $status = 'Error';
            } else {
                $status = 'OK';
            }
        } else {
            if (array_key_exists('error', $result)) {
                $status =  "Error";
            }
        }
        $response = array(
            'status' => $status,
            'message' => preg_replace('/<!--(.*)-->/Uis', '', $result)
        );
        ob_clean();
        echo json_encode($response);
        wp_die();
    }
}
?>