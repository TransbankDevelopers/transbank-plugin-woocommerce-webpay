<?php

require_once dirname(__FILE__, 2) . "/LogHandler.php";


class GenerateSSL
{
    // shell comando php -m | grep ssl
    function __construct() {
        $this->log = new LogHandler();

        $this->dn = array(
            "countryName" => "CL",
            "stateOrProvinceName" => "Santiago",
            "localityName" => "Santiago",
            "organizationName" => "Transbank",
            "organizationalUnitName" => "",
            "commonName" => "",
            "emailAddress" => "example@example.com"
        );
        $this->bits = 2048;
        $this->extraArgs = array('digest_alg' => 'sha256');
        $this->days=1460;
        $this->password = 'mypassword';

        // output keys
        $this->csrout = null;
        $this->certout = null;
        $this->pkeyout = null;
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
        openssl_csr_export($csr, $this->csrout);
        openssl_x509_export($x509, $this->certout);
        openssl_pkey_export($privkey, $this->pkeyout, $this->password);

    }

    public function generate(){
        try {
            $this->__generate_ssl();
        } catch(Exception $e) {
            $result = array(
                "error" => 'Error al generar los certificados',
                "detail" => $e->getMessage()
            );
            $this->log->logError(json_encode($result));
        }
    }

    public function getFullResume() {
        $this->generate();

        $this->fullResume = array(
            'csrout' => $this->csrout,
            'certout' => $this->certout,
            'pkeyout' => $this->pkeyout,
        );
        return json_encode($this->fullResume);
    }
}

?>