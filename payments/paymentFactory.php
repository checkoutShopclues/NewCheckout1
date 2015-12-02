<?php

	class payment_factory{
		private $payment_id;
		private $payment_parameters;

		public function __construct($payment_id){
			$this->payment_id = $payment_id;
		}

		public function set_payment_parameters(){
			list($is_processor_script, $processor_data) = fn_check_processor_script($this->payment_id, '');
			if($is_processor_script){
				$processor_data = fn_get_processor_data($this->payment_id);
				if(!$processor_data){
				 	$this->payment_parameters = $processor_data;
				 	return true;
				}
				return false;
			}
			return false;
		}

		public function get_payment_parameters(){
			return $this->payment_parameters;
		}
	}
?>