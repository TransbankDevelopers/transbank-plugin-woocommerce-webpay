<?php
	require_once(dirname(__DIR__).'/loghandler.php');
	require_once(__DIR__.'/reportPDF.php');

	class reportPDFlog{
		private $ecommerce;

		function reportPDFlog($ecommerce, $document){
			$this->ecommerce = $ecommerce;
			$this->document = $document;
		}
		function getReport($myJSON){
			$log = new loghandler($this->ecommerce);
			$json = json_decode($log->getLastLog(),true);

			$obj = json_decode($myJSON,true);

			if (isset($json['log_content']) && $this->document == 'report'){
			 $html = str_replace("\r\n","<br>",$json['log_content']);
			 $html = str_replace("\n","<br>",$json['log_content']);
				$text = explode ("<br>" ,$html);
				$html='';
				foreach ($text as $row){
					$html .= '<b>'.substr($row,0,21).'</b> '.substr($row,22).'<br>';
				}
				$obj += array('logs' => array('log' => $html));
			}
			
			$report = new reportPDF();
			$report->getReport(json_encode($obj));
		}

	}
