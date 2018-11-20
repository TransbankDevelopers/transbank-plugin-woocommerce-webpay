<?php
if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/**
 * Plugin Name: WooCommerce Webpay Plus
 * Plugin URI: https://www.transbank.cl
 * Description: Recibe pagos en l&iacute;nea con Tarjetas de Cr&eacute;dito y Redcompra en tu WooCommerce a trav&eacute;s de Webpay Plus.
 * Version: 2.0.0
 * Author: Transbank
 * Author URI: https://www.transbank.cl
 */

add_action('plugins_loaded', 'woocommerce_transbank_init', 0);

require_once( ABSPATH . "wp-includes/pluggable.php" );
require_once( ABSPATH . "wp-content/plugins/woocommerce-transbank/libwebpay/healthcheck.php" );
require_once( ABSPATH . "wp-content/plugins/woocommerce-transbank/libwebpay/tcpdf/reportPDFlog.php");
require_once( ABSPATH . "wp-content/plugins/woocommerce-transbank/libwebpay/loghandler.php");
require_once( ABSPATH . "wp-content/plugins/woocommerce-transbank/libwebpay/webpay-config.php");
require_once( ABSPATH . "wp-content/plugins/woocommerce-transbank/libwebpay/webpay-normal.php");

function woocommerce_transbank_init()
{
    if (!class_exists("WC_Payment_Gateway")){
        return;
    }

    class WC_Gateway_Transbank extends WC_Payment_Gateway
    {

        var $notify_url;
        /**
         * Constructor de gateway
         **/

        public function __construct()
        {


            $this->id = 'transbank';
            $this->icon = "https://www.transbank.cl/public/img/Logo_Webpay3-01-50x50.png";
            $this->method_title = __('Transbank – Pago a trav&eacute;s de Webpay Plus');
            $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_' . $this->id, home_url('/'));
            $this->integration = include( 'integration/integration.php' );

            /**
             * Carga configuración y variables de inicio
             **/


            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');

            $this->config = array(
                "MODO"            => $this->get_option('webpay_test_mode'),
                "PRIVATE_KEY"     => $this->get_option('webpay_private_key'),
                "PUBLIC_CERT"     => $this->get_option('webpay_public_cert'),
                "WEBPAY_CERT"     => $this->get_option('webpay_webpay_cert'),
                "COMMERCE_CODE" => $this->get_option('webpay_commerce_code'),
                "URL_RETURN"      => home_url('/') . '?wc-api=WC_Gateway_' . $this->id,
                "URL_FINAL"       => "_URL_",
				"ECOMMERCE"     => 'woocommerce',
                "VENTA_DESC"      => array(
						"VD" => "Venta Deb&iacute;to",
						"VN" => "Venta Normal",
						"VC" => "Venta en cuotas",
						"SI" => "3 cuotas sin inter&eacute;s",
						"S2" => "2 cuotas sin inter&eacute;s",
						"NC" => "N cuotas sin inter&eacute;s",
                ),
            );
			$this->args = array (
				"MODO"          => $this->get_option('webpay_test_mode'),
				"COMMERCE_CODE" => $this->get_option('webpay_commerce_code'),
				"PUBLIC_CERT"   => $this->get_option('webpay_public_cert'),
				"PRIVATE_KEY"   => $this->get_option('webpay_private_key'),
				"WEBPAY_CERT"   => $this->get_option('webpay_webpay_cert'),
				"ECOMMERCE"     => 'woocommerce',
				);
				

			$this->healthcheck = new HealthCheck($this->args);
			$this->datos_hc = json_decode($this->healthcheck->printFullResume());
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'check_ipn_response'));
			$this->log = new LogHandler($this->args['ECOMMERCE']);

			if ($this->config['MODO'] == 'PRODUCCION'){
				$this->healthcheck->getpostinstallinfo();
			}

			if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }

        /**
         * Comprueba configuración de moneda (Peso Chileno)
         **/
        function is_valid_for_use()
        {
            if (!in_array(get_woocommerce_currency(), apply_filters('woocommerce_' . $this->id . '_supported_currencies', array('CLP')))) {
                return false;
            }
            return true;
        }

        /**
         * Inicializar campos de formulario
         **/
        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Activar/Desactivar', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Activar Transbank', 'woocommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('T&iacute;tulo', 'woocommerce'),
                    'type' => 'text',
                    'default' => __('Pago con Tarjetas de Cr&eacute;dito o Redcompra', 'woocommerce'),
                    // 'disabled' => true
                ),
                'description' => array(
                    'title' => __('Descripci&oacute;n', 'woocommerce'),
                    'type' => 'textarea',
                    // 'disabled' => true,
                    'default' => __('Permite el pago de productos y/o servicios, con Tarjetas de Cr&eacute;dito y Redcompra a trav&eacute;s de Webpay Plus', 'woocommerce')
                ),
                'webpay_test_mode' => array(
                    'title' => __('Ambiente', 'woocommerce'),
                    'type' => 'select',
                    'options' => array('INTEGRACION' => 'Integraci&oacute;n', 'PRODUCCION' => 'Producci&oacute;n'),
                    'default'     => __( 'INTEGRACION', 'woocommerce' ),
                    'custom_attributes' => array(
                        'onchange' => "webpay_mode('".$this->integration['commerce_code']."', '".$this->integration['private_key']."', '".$this->integration['public_cert']."', '".$this->integration['webpay_cert']."')",
                    )
                ),
                'webpay_commerce_code' => array(
                    'title' => __('C&oacute;digo de Comercio', 'woocommerce'),
                    'type' => 'text',
                    'default' => __($this->integration['commerce_code'], 'woocommerce'),
                ),
                'webpay_private_key' => array(
                    'title' => __('Llave Privada', 'woocommerce'),
                    'type' => 'textarea',
                    'default' => __(str_replace("<br/>", "\n", $this->integration['private_key']), 'woocommerce'),
                    'css' => 'font-family: monospace',
                ),
                'webpay_public_cert' => array(
                    'title' => __('Certificado', 'woocommerce'),
                    'type' => 'textarea',
                    'default' => __(str_replace("<br/>", "\n", $this->integration['public_cert']), 'woocommerce'),
                    'css' => 'font-family: monospace',
                ),
                'webpay_webpay_cert' => array(
                    'title' => __('Certificado Transbank', 'woocommerce'),
                    'type' => 'textarea',
                    'default' => __(str_replace("<br/>", "\n", $this->integration['webpay_cert']), 'woocommerce'),
                    'css' => 'font-family: monospace',
                ),

            );
        }

        /**
         * Pagina Receptora
         **/
        function receipt_page($order)
        {
            echo $this->generate_transbank_payment($order);
        }

        /**
         * Obtiene respuesta IPN (Instant Payment Notification)
         **/
        function check_ipn_response()
        {
            @ob_clean();

            if (isset($_POST)) {
                header('HTTP/1.1 200 OK');
                $this->check_ipn_request_is_valid($_POST);
            } else {
                echo "Ocurrio un error al procesar su Compra";
            }
        }

        /**
         * Valida respuesta IPN (Instant Payment Notification)
         **/
        public function check_ipn_request_is_valid($data)
        {

            $voucher = false;

            try {

                if (isset($data["token_ws"])) {
                    $token_ws = $data["token_ws"];
                } else {
                    $token_ws = 0;
                }
				$wp_config = new WebPayConfig($this->config);
                $webpay = new WebPayNormal($wp_config);
                $result = $webpay->getTransactionResult($token_ws);

            } catch (Exception $e) {

                $result["error"] = "Error conectando a Webpay";
                $result["detail"] = $e->getMessage();

            }

            $order_info = new WC_Order($result->buyOrder);

            WC()->session->set($order_info->order_key, $result);

            if ($result->buyOrder && $order_info) {

                if (($result->VCI == "TSY" || $result->VCI == "") && $result->detailOutput->responseCode == 0) {

                    $voucher = true;
                    WC()->session->set($order_info->order_key . "_transaction_paid", 1);

                    self::redirect($result->urlRedirection, array("token_ws" => $token_ws));

                    $order_info->add_order_note(__('Pago con WEBPAY PLUS', 'woocommerce'));
                    $order_info->update_status('completed');
                    $order_info->reduce_order_stock();

                } else {

                    $responseDescription = htmlentities($result->detailOutput->responseDescription);
                }
            }

            if (!$voucher) {

                $date = new DateTime($result->transactionDate);

                WC()->session->set($order_info->order_key, "");

                $error_message = "Estimado cliente, le informamos que su orden número ". $result->buyOrder . ", realizada el " . $date->format('d-m-Y H:i:s') . " termin&oacute; de forma inesperada ( " . $responseDescription . " ) ";
                wc_add_notice(__('ERROR: ', 'woothemes') . $error_message, 'error');

                $redirectOrderReceived = $order_info->get_checkout_payment_url();
                self::redirect($redirectOrderReceived, array("token_ws" => $token_ws));
            }

            die;
        }

        /**
         * Generar pago en Transbank
         **/
		 
		 
		public function redirect($url, $data){
			echo  "<form action='" . $url . "' method='POST' name='webpayForm'>";
			foreach ($data as $name => $value) {
				echo "<input type='hidden' name='".htmlentities($name)."' value='".htmlentities($value)."'>";
			}
			echo  "</form>"
				 ."<script language='JavaScript'>"
				 ."document.webpayForm.submit();"
				 ."</script>";
		}
		
        function generate_transbank_payment($order_id)
        {

            $order = new WC_Order($order_id);
            $amount = (int) number_format($order->get_total(), 0, ',', '');

            $urlFinal = str_replace("_URL_", add_query_arg('key', $order->order_key, $order->get_checkout_order_received_url()), $this->config["URL_FINAL"]);

            try {
				$wp_config = new WebPayConfig($this->config);
                $webpay = new WebPayNormal($wp_config);
                $result = $webpay->initTransaction($amount, $sessionId = "", $order_id, $urlFinal);

            } catch (Exception $e) {

                $result["error"] = "Error conectando a Webpay";
                $result["detail"] = $e->getMessage();
            }

            if (isset($result["token_ws"])) {

                $url = $result["url"];
                $token = $result["token_ws"];

                echo "<br/>Gracias por su pedido, por favor haga clic en el bot&oacute;n de abajo para pagar con WebPay.<br/><br/>";

                return '<form action="' . $url . '" method="post">' .
                        '<input type="hidden" name="token_ws" value="' . $token . '"></input>' .
                        '<input type="submit" value="WebPay"></input>' .
                        '</form>';
            } else {

                wc_add_notice(__('ERROR: ', 'woothemes') . 'Ocurri&oacute; un error al intentar conectar con WebPay Plus. Por favor intenta mas tarde.<br/>', 'error');
            }
        }

        /**
         * Procesar pago y retornar resultado
         **/
        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);
            return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true));
        }

        /**
         * Opciones panel de administración
         **/
        public function admin_options()
        {
            ?>
			<link rel="stylesheet" href="https://bootswatch.com/spacelab/bootstrap.min.css">

			<link href="../wp-content/plugins/woocommerce-transbank/css/bootstrap-switch.css" rel="stylesheet">
			<link href="../wp-content/plugins/woocommerce-transbank/css/tbk.css" rel="stylesheet">


			<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
			<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
			<script src="https://unpkg.com/bootstrap-switch"></script>


            <h3><?php _e('Transbank', 'woocommerce'); ?></h3>
            <p><?php _e('Transbank es la empresa l&iacute;der en negocios de medio de pago seguros en Chile.'); ?></p>

			<a  class ="tbk_btn tbk_danger_btn" data-toggle="modal" href="#tb_modal">Informacion</a>
			<hr>
			<?php if ($this->is_valid_for_use()) : ?>
				<?php if (empty($this->config["COMMERCE_CODE"])) : ?>
					<div class="inline error">
						<p><strong><?php _e('C&oacute;digo de Comercio', 'woocommerce'); ?></strong>: <?php _e('Para el correcto funcionamiento de Webpay Plus debes ingresar tu C&oacute;digo de Comercio', 'woocommerce'); ?></p>
					</div>
				<?php endif; ?>

				<?php if (empty($this->config["PRIVATE_KEY"])) : ?>
					<div class="inline error">
						<p><strong><?php _e('Llave Privada', 'woocommerce'); ?></strong>: <?php _e('Para el correcto funcionamiento de Webpay Plus debes ingresar tu Llave Privada', 'woocommerce'); ?></p>
					</div>
				<?php endif; ?>

				<?php if (empty($this->config["PUBLIC_CERT"])) : ?>
					<div class="inline error">
						<p><strong><?php _e('Certificado', 'woocommerce'); ?></strong>: <?php _e('Para el correcto funcionamiento de Webpay Plus debes ingresar tu Certificado', 'woocommerce'); ?></p>
					</div>
				<?php endif; ?>

				<?php if (empty($this->config["WEBPAY_CERT"])) : ?>
					<div class="inline error">
						<p><strong><?php _e('Certificado Transbank', 'woocommerce'); ?></strong>: <?php _e('Para el correcto funcionamiento de Webpay Plus debes ingresar tu Certificado Transbank', 'woocommerce'); ?></p>
					</div>
				<?php endif; ?>

				<table class="form-table">
					<?php
						$this->generate_settings_html();
					?>
				</table>

			<?php else : ?>
				<div class="inline error">
					<p>
					<strong><?php _e('Webpay Plus ', 'woocommerce');
					?></strong>: <?php _e('Este Plugin est&aacute; dise&ntilde;ado para operar con Webpay Plus solo en Pesos Chilenos.', 'woocommerce');
			?>
					</p>
				</div>
			<?php
			endif;
			setcookie("ambient", $this->get_option('webpay_test_mode'), strtotime('+30 days') ,"/");
			setcookie("storeID", $this->get_option('webpay_commerce_code'), strtotime('+30 days') , "/");
			setcookie("certificate", $this->get_option('webpay_public_cert'), strtotime('+30 days'), "/");
			setcookie("secretCode", $this->get_option('webpay_private_key'), strtotime('+30 days'), "/");
			setcookie("certificateTransbank", $this->get_option('webpay_webpay_cert'), strtotime('+30 days'), "/");
			?>


			<div class="modal" id="tb_modal">
				<div class="modal-dialog">
					<div class="modal-content">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
							<ul class="nav nav-tabs">
								<li class="active" > <a data-toggle="tab" href="#info" class="tbk_tabs">Información</a></li>
								<li> <a data-toggle="tab" href="#php_info" class="tbk_tabs">PHP info</a></li>
								<li> <a data-toggle="tab" href="#logs" class="tbk_tabs">Registros</a></li>
							</ul>
						</div>
						<div class="modal-body">
							<div class="tab-content">
								<div id="info" class="tab-pane in active">
									<fieldset class="tbk_info">
										<h3 class="tbk_title_h3">Informe pdf</h3>
										<div class="button-primary" id="tbk_pdf_button">
											Crear PDF
										</div>
									</fieldset>
								  
									<h3 class="tbk_title_h3">Información de Plugin / Ambiente</h3>
									<table class="tbk_table_info">
										<tr>
											<td><div title="Nombre del E-commerce instalado en el servidor" class="label label-info">?</div> <strong>Software E-commerce: </strong></td>
											<td class="tbk_table_td"><?php echo $this->datos_hc->server_resume->plugin_info->ecommerce ?> </td>
										</tr>
										<tr>
											<td><div title="Versión de <?php echo $this->datos_hc->server_resume->plugin_info->ecommerce ?> instalada en el servidor" class="label label-info">?</div> <strong>Version E-commerce: </strong></td>
											<td class="tbk_table_td"><?php echo $this->datos_hc->server_resume->plugin_info->ecommerce_version ?> </td>
										</tr>
										<tr>
											<td><div title="Versión del plugin Webpay para <?php echo $this->datos_hc->server_resume->plugin_info->ecommerce ?> instalada actualmente" class="label label-info">?</div> <strong>Versión actual del plugin: </strong></td>
											<td class="tbk_table_td"><?php echo $this->datos_hc->server_resume->plugin_info->current_plugin_version ?></td>
										</tr>
										<tr>
											<td><div title="Última versión del plugin Webpay para <?php echo $this->datos_hc->server_resume->plugin_info->ecommerce ?> disponible" class="label label-info">?</div> <strong>Última versión del plugin: </strong></td>
											<td class="tbk_table_td"><?php echo $this->datos_hc->server_resume->plugin_info->last_plugin_version ?></td>
										</tr>
									</table>
									<br>
									<h3 class="tbk_title_h3">Validación certificados</h3>
									<h4 class="tbk_table_title">Consistencias</h4>
									<table class="tbk_table_info">
										<tr>
											<td><div title="Informa si las llaves ingresadas por el usuario corresponden al certificado entregado por Transbank" class="label label-info">?</div> <strong>Consistencias con llaves: </strong></td>
											<td class="tbk_table_td"><span class="label <?php if ($this->datos_hc->validate_certificates->consistency->cert_vs_private_key == 'OK') echo 'label-success'; else echo 'label-danger';?>">
												<?php echo $this->datos_hc->validate_certificates->consistency->cert_vs_private_key ?>
											</span></td>
										</tr>
										<tr>
											<td><div title="Informa si el código de comercio ingresado por el usuario corresponde al certificado entregado por Transbank" class="label label-info">?</div> <strong>Validación Código de comercio: </strong></td>
											<td class="tbk_table_td"><span class="label <?php if ($this->datos_hc->validate_certificates->consistency->commerce_code_validate == 'OK') echo 'label-success'; else echo 'label-danger';?>">
												<?php echo $this->datos_hc->validate_certificates->consistency->commerce_code_validate ?>
											</span></td>
										</tr>
									</table>
									<hr>
									<h4 class="tbk_table_title">Información del certificado</h4>
									<table class="tbk_table_info">
										<tr>
											<td><div title="CN (common name) dentro del certificado, en este caso corresponde al código de comercio emitido por Transbank" class="label label-info">?</div> <strong>Código de Comercio Válido: </strong></td>
										<td class="tbk_table_td"><?php echo $this->datos_hc->validate_certificates->cert_info->subject_commerce_code ?></td>
										</tr>
										<tr>
											<td><div title="Versión del certificado emitido por Transbank" class="label label-info">?</div> <strong>Versión certificado: </strong></td>
											<td class="tbk_table_td"><?php echo $this->datos_hc->validate_certificates->cert_info->version ?></td>
										</tr>
										<tr>
											<td><div title="Informa si el certificado está vigente actualmente" class="label label-info">?</div> <strong>Vigencia: </strong></td>
											<td class="tbk_table_td"><span class="label <?php if ($this->datos_hc->validate_certificates->cert_info->is_valid == 'OK') echo 'label-success'; else echo 'label-danger';?>">
												<?php echo $this->datos_hc->validate_certificates->cert_info->is_valid ?>
											</span></td>
										</tr>
										<tr>
											<td><div title="Fecha desde la cual el certificado es válido" class="label label-info">?</div> <strong>Válido desde: </strong></td>
											<td class="tbk_table_td"><?php echo $this->datos_hc->validate_certificates->cert_info->valid_from ?></td>
										</tr>
										<tr>
											<td><div title="Fecha hasta la cual el certificado es válido" class="label label-info">?</div> <strong>Válido hasta: </strong></td>
											<td class="tbk_table_td"><?php echo $this->datos_hc->validate_certificates->cert_info->valid_to ?></td>
										</tr>
									</table>
									<br>
									<h3 class="tbk_title_h3">Estado de la Extensiones de PHP</h3>
									<h4 class="tbk_table_title">Información Principal</h4>
									<table class="tbk_table_info">
										<tr>
											<td><div title="Descripción del Servidor Web instalado" class="label label-info">?</div> <strong>Software Servidor: </strong></td>
											<td class="tbk_table_td"><?php echo $this->datos_hc->server_resume->server_version->server_software ?></td>
										</tr>
									</table>
									<hr>
									<h4 class="tbk_table_title">PHP</h4>
									<table class="tbk_table_info">
										<tr>
											<td><div title="Informa si la versión de PHP instalada en el servidor es compatible con el plugin de Webpay" class="label label-info">?</div> <strong>Estado de PHP</strong></td>
											<td class="tbk_table_td"><span class="label <?php if ($this->datos_hc->server_resume->php_version->status == 'OK') echo 'label-success'; else echo 'label-danger';?>">
												<?php echo $this->datos_hc->server_resume->php_version->status ?>
											</span></td>
										</tr>
										<tr>
											<td><div title="Versión de PHP instalada en el servidor" class="label label-info">?</div> <strong>Versión: </strong></td>
											<td class="tbk_table_td"><?php echo $this->datos_hc->server_resume->php_version->version ?></td>
										</tr>
									</table>
									<hr>
									<h4 class="tbk_table_title">Extensiones PHP requeridas</h4>
									<table class="table table-responsive table-striped">
										<tr>
											<th>Extensión</th>
											<th>Estado</th>
											<th class="tbk_table_td">Versión</th>
										</tr>
										<tr>
											<td style="font-weight:bold">openssl</td>
											<td> <span class="label <?php if ($this->datos_hc->php_extensions_status->openssl->status == 'OK') echo 'label-success'; else echo 'label-danger';?>">
												<?php echo $this->datos_hc->php_extensions_status->openssl->status ?>
											</span></td>
											<td class="tbk_table_td"><?php echo $this->datos_hc->php_extensions_status->openssl->version ?></td>
										</tr>
										<tr>
											<td style="font-weight:bold">SimpleXml</td>
											<td> <span class="label <?php if ($this->datos_hc->php_extensions_status->SimpleXML->status == 'OK') echo 'label-success'; else echo 'label-danger';?>">
												<?php echo $this->datos_hc->php_extensions_status->SimpleXML->status ?>
											</span></td>
											<td class="tbk_table_td"><?php echo $this->datos_hc->php_extensions_status->SimpleXML->version ?></td>
										</tr>
										<tr>
											<td style="font-weight:bold">soap</td>
											<td><span class="label <?php if ($this->datos_hc->php_extensions_status->soap->status == 'OK') echo 'label-success'; else echo 'label-danger';?>">
												<?php echo $this->datos_hc->php_extensions_status->soap->status ?>
											</span></td>
											<td class="tbk_table_td"><?php echo $this->datos_hc->php_extensions_status->soap->version ?></td>
										</tr>
										<tr>
											<td style="font-weight:bold">mcrypt</td>
											<td><span class="label <?php if ($this->datos_hc->php_extensions_status->mcrypt>status == 'OK') echo 'label-success'; else echo 'label-danger';?>">
												<?php echo $this->datos_hc->php_extensions_status->mcrypt->status ?>
											</span></td>
											<td class="tbk_table_td"><?php echo $this->datos_hc->php_extensions_status->mcrypt->version ?></td>
										</tr>
										<tr>
											<td style="font-weight:bold">dom</td>
											<td><span class="label <?php if ($this->datos_hc->php_extensions_status->dom->status == 'OK') echo 'label-success'; else echo 'label-danger';?>">
												<?php echo $this->datos_hc->php_extensions_status->dom->status ?>
											</span></td>
											<td class="tbk_table_td"><?php echo $this->datos_hc->php_extensions_status->dom->version ?></td>
										</tr>
									</table>
									<br>
									<h3 class="tbk_title_h3">Validación Transacción</h3>
									<h4 class="tbk_table_title">General</h4>
									<table class="tbk_table_info">
										<tr>
											<td><div title="Informa el estado de la comunicación con Transbank mediante método init_transaction" class="label label-info">?</div> <strong>Estado: </strong></td>
											<td class="tbk_table_td"><span class="label <?php if ($this->datos_hc->validate_init_transaction->status->string == 'OK') echo 'label-success'; else echo 'label-danger';?>">
												<?php echo $this->datos_hc->validate_init_transaction->status->string; ?>
											</span></td>
										</tr>
									</table>
									<hr>
									<h4 class="tbk_table_title">Respuesta</h4>
									<table class="tbk_table_info">
										<tr>
											<?php
												if ($this->datos_hc->validate_init_transaction->status->string == 'OK') {
													echo'
														<td><div title="URL entregada por Transbank para realizar la transacción" class="label label-info">?</div> <strong>URL: </strong></td>
														<td class="tbk_table_trans">'.$this->datos_hc->validate_init_transaction->response->url.'</td>
													</tr>
													<tr>
														<td><div title="Token entregada por Transbank para realizar la transacción" class="label label-info">?</div> <strong>Token: </strong></td>
														<td class="tbk_table_trans"><code>'.$this->datos_hc->validate_init_transaction->response->token_ws.'</code></td>';
												}
												else{
													echo'
														<td><div title="Mensaje de error devuelto por Transbank al fallar init_transaction" class="label label-info">?</div> <strong>Error: </strong></td>
														<td class="tbk_table_trans">'.$this->datos_hc->validate_init_transaction->response->error.'</td>
													</tr>
													<tr>
														<td><div title="Detalle del error devuelto por Transbank al fallar init_transaction" class="label label-info">?</div> <strong>Detalle: </strong></td>
														<td class="tbk_table_trans"><pre><code>'.$this->datos_hc->validate_init_transaction->response->detail.'</code></pre></td>';
												}
											?>
										</tr>
									</table>
								</div>

								<div id="php_info" class="tab-pane">
								  <fieldset class="tbk_info">
									<h3 class="tbk_title_h3">Informe PHP info</h3>
									<a class="button-primary" onclick="document.cookie = 'document=php_info; path=/'" href="../wp-content/plugins/woocommerce-transbank/createpdf.php" target="_blank">
									  Crear PHP info
									</a>
									<br>
								  </fieldset>
								  
									<fieldset>
										<h3 class="tbk_title_h3">PHP info</h3>
										<span style="font-size: 10px; font-family:monospace; display: block; background: white;overflow: hidden;" >
											<?php echo $this->datos_hc->php_info->string->content ?>
										</span><br>
									</fieldset>
								</div>

								<div id="logs" class="tab-pane">
									<fieldset>
										<h3 class="tbk_title_h3">Configuración</h3>
										<?php
											$log_days = isset($this->log->getValidateLockFile()['max_logs_days']) ? $this->log->getValidateLockFile()['max_logs_days'] : NULL;
											$log_size = isset($this->log->getValidateLockFile()['max_log_weight']) ? $this->log->getValidateLockFile()['max_log_weight']: NULL;
											$lockfile = json_decode($this->log->getLockFile(),true)['status'];
										?>
										<table class="tbk_table_info">
											<tr>

												<td><div title="Al activar esta opción se habilita que se guarden los datos de cada compra mediante Webpay" class="label label-info">?</div> <strong>Activar Registro: </strong></td>
												<td class="tbk_table_td">
													<?php
														if ($lockfile){
															echo '<input type="checkbox" id="action_check" name="action_check" checked data-size="small" value="activate">
															<script>
																	document.cookie="action_check=true; path=/";
															</script>';
														}
														else
														{
															echo '<input type="checkbox" id="action_check" name="action_check" data-size="small" state="false">';
														}
													?>
												</td>
											</tr>
										</table>
										<script> $("[name=\'action_check\']").bootstrapSwitch();</script>
										<table class="tbk_table_info">
											<tr>
												<td><div title="Cantidad de días que se conservan los datos de cada compra mediante Webpay" class="label label-info">?</div> <strong>Cantidad de Dias a Registrar</strong></td>
												<td class="tbk_table_td"><input id="days" name="days" type="number" min="1" max="30" value="<?php echo $log_days ?>"> días</td>
											</tr>
											<tr>
												<td><div title="Peso máximo (en Megabytes) de cada archivo que guarda los datos de las compras mediante Webpay" class="label label-info">?</div> <strong>Peso máximo de Registros: </strong></td>
												<td class="tbk_table_td"><select style="width: 100px; display: initial;" id="size" name="size">
													<?php
														for ($c=1;$c<10;$c++){
															echo '<option value="'.$c.'"';
															if ($c == $log_size)
																echo ' selected';
															echo '>'.$c.'</option>';
														}
													?>
												</select> Mb</td>
											</tr>
										</table>
										<div class="tbk_btn tbk_danger_btn" onclick="swap_action()" href="" target="_blank">
											Actualizar Parametros
										</div>

										<h3 class="tbk_title_h3">Información de Registros</h3>
										<table class="tbk_table_info">
											<tr>
												<td><div title="Informa si actualmente se guarda la información de cada compra mediante Webpay" class="label label-info">?</div> <strong>Estado de Registros: </strong></td>
												<td class="tbk_table_td"><span id="action_txt" class="label label-success">Registro activado</span><br> </td>
											</tr>
											<tr>
												<td><div title="Carpeta en el servidor en donde se guardan los archivos con la informacón de cada compra mediante Webpay" class="label label-info">?</div> <strong>Directorio de registros: </strong></td>
												<td class="tbk_table_td"><?php echo json_decode($this->log->getResume(),true)['log_dir']?></td>
											</tr>
											<tr>
												<td><div title="Cantidad de archivos que guardan la información de cada compra mediante Webpay" class="label label-info">?</div> <strong>Cantidad de Registros en Directorio: </strong></td>
												<td class="tbk_table_td"><?php echo json_decode($this->log->getResume(),true)['logs_count']['log_count'] ?></td>
											</tr>
											<tr>
												<td><div title="Lista los archivos archivos que guardan la información de cada compra mediante Webpay" class="label label-info">?</div> <strong>Listado de Registros Disponibles: </strong></td>
												<td class="tbk_table_td">
													<ul style="font-size:0.8em;list-style: disc">
														<?php
															$logs_list = (isset (json_decode($this->log->getResume(),true)['logs_list'])) ? json_decode($this->log->getResume(),true)['logs_list'] : array();
															foreach ($logs_list as $index){
															echo '<li>'.$index.'</li>';
															}
														?>
													</ul>
												</td>
											</tr>
										</table>

										<h3 class="tbk_title_h3">Últimos Registros</h3>
										<table class="tbk_table_info">
											<tr>
												<td><div title="Nombre del útimo archivo de registro creado" class="label label-info">?</div> <strong>Último Documento: </strong></td>
												<td class="tbk_table_td"><?php echo (isset( json_decode($this->log->getLastLog(),true)['log_file'])) ?  json_decode($this->log->getLastLog(),true)['log_file'] : NULL ?> </td>
											</tr>
											<tr>
												<td><div title="Peso del último archivo de registro creado" class="label label-info">?</div> <strong>Peso del Documento: </strong></td>
												<td class="tbk_table_td"><?php echo (isset( json_decode($this->log->getLastLog(),true)['log_weight'])) ?  json_decode($this->log->getLastLog(),true)['log_weight'] : NULL ?></td>
											</tr>
											<tr>
												<td><div title="Cantidad de líneas que posee el último archivo de registro creado" class="label label-info">?</div> <strong>Cantidad de Líneas: </strong></td>
												<td class="tbk_table_td"><?php echo (isset( json_decode($this->log->getLastLog(),true)['log_regs_lines'])) ?  json_decode($this->log->getLastLog(),true)['log_regs_lines'] : NULL ?> </td>
											</tr>
										</table>
										<br>
										<pre>
											<span style="font-size: 10px; font-family:monospace; display: block; background: white;width: fit-content;" >
											<?php echo (null !== ( $this->log->getLastLog())) ?  json_decode($this->log->getLastLog(),true)['log_content'] : NULL; ?>
										</span></pre>
									</fieldset>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<script type="text/javascript">
				function swap_action(){

					if (document.getElementById("action_check").checked){
						document.getElementById('action_txt').innerHTML = 'Registro activado';
						$('#action_txt').removeClass("label-warning").addClass("label-success");
						document.cookie="action_check=true; path=/";
						document.cookie = "size=" + document.getElementById('size').value + "; path=/";
						document.cookie = "days=" + document.getElementById('days').value + "; path=/";
					}
					else{
						document.getElementById('action_txt').innerHTML = 'Registro desactivado';
						$('#action_txt').removeClass("label-success").addClass("label-warning");
						document.cookie="action_check=false; path=/";
						document.cookie = "size=" + document.getElementById('size').value + "; path=/;expires=Thu, 01 Jan 1970 00:00:01 GMT";
						document.cookie = "days=" + document.getElementById('days').value + "; path=/;expires=Thu, 01 Jan 1970 00:00:01 GMT";
					}
					window.open("../wp-content/plugins/woocommerce-transbank/call_loghandler.php")
				}
		
				jQuery().ready(function($){

					$(document).on('click', '#tbk_pdf_button', function(e){
						// Create the iFrame used to send our data
						var iframe = document.createElement("iframe");
						iframe.name = "myTarget";

						// Next, attach the iFrame to the main document
						window.addEventListener("load", function () {
						  iframe.style.display = "none";
						  document.body.appendChild(iframe);
						});
					
						data = {'document': 'report'};
						var name,
							form = document.createElement("form"),
							node = document.createElement("input");

						// Define what happens when the response loads
						iframe.addEventListener("load", function () {
					
						});

						form.action = "../wp-content/plugins/woocommerce-transbank/createpdf.php";
						form.method = 'POST';
						form.target = iframe.name;

						for(name in data) {
						  node.name  = name;
						  node.value = data[name].toString();
						  form.appendChild(node.cloneNode());
						}
						// To be sent, the form needs to be attached to the main document.
						form.style.display = "none";
						document.body.appendChild(form);
						form.submit();
						// Once the form is sent, remove it.
						document.body.removeChild(form);

					});
				})
			</script>
			<?php
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
    function pay_content($order_id)
    {
        $order_info = new WC_Order($order_id);
        $transbank_data = new WC_Gateway_transbank;

        if ($order_info->payment_method_title == $transbank_data->title) {

            if (WC()->session->get($order_info->order_key . "_transaction_paid") == "" && WC()->session->get($order_info->order_key) == "") {

                wc_add_notice(__('Compra <strong>Anulada</strong>', 'woocommerce') . ' por usuario. Recuerda que puedes pagar o
                    cancelar tu compra cuando lo desees desde <a href="' . wc_get_page_permalink('myaccount') . '">' . __('Tu Cuenta', 'woocommerce') . '</a>', 'error');
                wp_redirect($order_info->get_checkout_payment_url());

                die;
            }

        } else {
            return;
        }

        $finalResponse = WC()->session->get($order_info->order_key);
        WC()->session->set($order_info->order_key, "");

        $paymentTypeCode = $finalResponse->detailOutput->paymentTypeCode;
        $paymenCodeResult = $transbank_data->config['VENTA_DESC'][$paymentTypeCode];

        if ($finalResponse->detailOutput->responseCode == 0) {
            $transactionResponse = "Aceptado";
        } else {
            $transactionResponse = "Rechazado [" . $finalResponse->detailOutput->responseCode . "]";
        }

        $date_accepted = new DateTime($finalResponse->transactionDate);

        if ($finalResponse != null) {

            echo '</br><h2>Detalles del pago</h2>' .
            '<table class="shop_table order_details">' .
            '<tfoot>' .
            '<tr>' .
            '<th scope="row">Respuesta de la Transacci&oacute;n:</th>' .
            '<td><span class="RT">' . $transactionResponse . '</span></td>' .
            '</tr>' .
            '<tr>' .
            '<th scope="row">Orden de Compra:</th>' .
            '<td><span class="RT">' . $finalResponse->buyOrder . '</span></td>' .
            '</tr>' .
            '<tr>' .
            '<th scope="row">Codigo de Autorizaci&oacute;n:</th>' .
            '<td><span class="CA">' . $finalResponse->detailOutput->authorizationCode . '</span></td>' .
            '</tr>' .
            '<tr>' .
            '<th scope="row">Fecha Transacci&oacute;n:</th>' .
            '<td><span class="FC">' . $date_accepted->format('d-m-Y') . '</span></td>' .
            '</tr>' .
            '<tr>' .
            '<th scope="row"> Hora Transacci&oacute;n:</th>' .
            '<td><span class="FT">' . $date_accepted->format('H:i:s') . '</span></td>' .
            '</tr>' .
            '<tr>' .
            '<th scope="row">Tarjeta de Cr&eacute;dito:</th>' .
            '<td><span class="TC">************' . $finalResponse->cardDetail->cardNumber . '</span></td>' .
            '</tr>' .
            '<tr>' .
            '<th scope="row">Tipo de Pago:</th>' .
            '<td><span class="TP">' . $paymenCodeResult . '</span></td>' .
            '</tr>' .
            /*'<tr>' .
            '<th scope="row">Tipo de Cuotas:</th>' .
            '<td><span class="TP"></span></td>' .
            '</tr>' .*/
            '<tr>' .
            '<th scope="row">Monto Compra:</th>' .
            '<td><span class="amount">' . $finalResponse->detailOutput->amount . '</span></td>' .
            '</tr>' .
            '<tr>' .
            '<th scope="row">N&uacute;mero de Cuotas:</th>' .
            '<td><span class="NC">' . $finalResponse->detailOutput->sharesNumber . '</span></td>' .
            '</tr>' .
            '</tfoot>' .
            '</table><br/>';
        }
    }

    add_action('woocommerce_thankyou', 'pay_content', 1);
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_transbank_gateway');
}

if (strpos($_SERVER['REQUEST_URI'], "wc_gateway_transbank") && is_user_logged_in()){
    ?>
        <script src="../wp-content/plugins/woocommerce-transbank/integration/js/integration.js"></script>
    <?php
}
