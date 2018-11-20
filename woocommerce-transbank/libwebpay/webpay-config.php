<?php
# @Author: Nicolas Martinez <nicolas>
# @Date:   2017-08-08T11:53:02-04:00
# @Email:  pmartinez@allware.cl
# @Filename: webpay-config.php
# @Last modified by:   nicolas
# @Last modified time: 2017-08-08T15:56:35-04:00


class WebPayConfig{
        private $params = array();

        function __construct($params){
                $this->params = $params;
        }

        public function getParams(){
        return $this->params;
    }

        public function getParam($name){
        return $this->params[$name];
    }

        public function getModo(){
                $modo = $this->params["MODO"];
        if (!isset($modo) || $modo == ""){
            $modo = "INTEGRACION";
        }
                return $modo;
        }
}


?>
