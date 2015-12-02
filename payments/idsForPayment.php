<?php

class ids_for_payment{

	private $payment_id;
	private $payment_option_id;
	private $payment_option_pgw_id;
	private $payment_gateway_id;
	private $payment_type;
	private $payment_option;

	public function __construct($payments){
		$this->payment_id = $payments['payment_id'];
		$this->payment_option_id = $payments['payment_option_id'];
		$this->payment_option_pgw_id = $payments['payment_option_pgw_id'];
		$this->payment_gateway_id = $payments['payment_gateway_id'];
		$this->payment_type = $payments['payment_type'];
		$this->payment_option = $payments['payment_option'];
	}
	

	public function get_payment_id(){
		return $this->payment_id;
	}

	public function get_payment_option_id(){
		return $this->payment_option_id;
	}

	public function get_payment_option_pgw_id(){
		return $this->payment_option_pgw_id;
	}

	public function get_payment_gateway_id(){
		return $this->payment_gateway_id;
	}

	public function get_payment_type(){
		return $this->payment_type;
	}

	public function get_payment_option(){
		return $this->payment_option;
	}
}
?>