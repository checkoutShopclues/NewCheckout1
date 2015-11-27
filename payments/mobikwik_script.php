<?php
// if (!defined('AREA')) {die('Access denied');}
class mobikwik_script{

	private $redirecturl;
	private $amount;
	private $orderid;
	private $cell;
	private $version = 2;
	private $checksum;
	private $secret_key;
	private $txnresponse;
	private $validchecksum;
	private $status_code;
	private $order_info;
    private $paymentService;

	public function __construct($order_info,$current_location){
		$this->order_info   = $order_info;
		$this->paymentService= new PaymentService();
		$arr = $this->paymentService->getUserDetailsFromUserId($userId);
		$this->cell= $arr['phone'];
		$this->redirecturl 	= $current_location."?dispatch=payment_notification.return&payment=mobikwik_script&order_id=".$this->order_info['order_id'] ;
	}

	public function phone_format($cell){		
		$cell = str_replace(' ','',$cell);
		$cell = str_replace('-','',$cell);
		$cell = str_replace('+','',$cell);
		$cell = str_replace('(','',$cell);
		$cell = str_replace(')','',$cell); 
		return $cell;
	}

	public function formatting_parameters($value){
		$patterns = array();
		$patterns[0] = '/^and\s/i';
		$patterns[1] = '/\sand\s/i';
		$patterns[2] = '/\sand$/i';
		$patterns[3] = '/^or\s/i';
		$patterns[4] = '/\sor\s/i';
		$patterns[5] = '/\sor$/i';
		$patterns[6] = '/^between\s/i';
		$patterns[7] = '/\sbetween\s/i';
		$patterns[8] = '/\sbetween$/i';
		$replacements = array();
		$replacements[0] = '';
		$replacements[1] = '';
		$replacements[2] = '';
		$replacements[3] = '';
		$replacements[4] = '';
		$replacements[5] = '';
		$replacements[6] = '';
		$replacements[7] = '';
		$replacements[8] = '';
		return preg_replace($patterns, $replacements, trim(preg_replace('/[^a-zA-Z0-9\s]/', " ", $value)));
	}

	public function redirect_form_parameters(){
		// $form_name = "frm_mobikwik";
		$prepaymentData= $this->pre_redirect();
		$this->paymentService->savePrePaymentData($prepaymentData);
		$target_url = $this->order_info['payment_method']['params']['url'];
		$form_fields= $this->getFormFields();
		return array($target_url,$form_fields);
	}

	public function return($redirect_response){
		$this->txnresponse    = $redirect_response;
		$this->order_info     = fn_get_order_info($this->txnresponse['orderid'], true);
		
		// $orderid        = $redirect_response['orderid'];
		// $amount         = $redirect_response['amount'];
		// $status         = $redirect_response['statusmessage'];
		// $sc             = $redirect_response['statuscode'];
		// $checksum       = $redirect_response['checksum'];
		// $mid            = $redirect_response['mid'];
		// $refid          = $redirect_response['refid'];

		$string = "'" . $this->txnresponse['mid'] . "''" . $this->txnresponse['orderid'] . "'"  ; 
		$hash = hash_hmac('sha256', $string, $this->order_info['payment_method']['params']['secretkey']);
		$validchecksum = $hash;   
		$url = $this->order_info['payment_method']['params']['checkstatusurl'];       
		$param = "mid=".$this->txnresponse['mid']."&orderid=".$this->txnresponse['orderid']."&checksum=".$validchecksum;
		$ch = curl_init(); 
		curl_setopt($ch, CURLOPT_POST,1); 
		curl_setopt($ch, CURLOPT_POSTFIELDS,$param); 
		curl_setopt($ch, CURLOPT_URL,$url); 
		curl_setopt($ch, CURLOPT_PORT, 443);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,0); 
		$response = curl_exec($ch);
		curl_close($ch);
		$testXML = "<xmltg>" . $response . "</xmltg>";
		$xmlstr = simplexml_load_string( $response,null,true);
		$statuscode = $xmlstr->statuscode;
		$statusmessage = $xmlstr->statusmessage;         
		$this->status_code = $statuscode;
		return array("direcpayreferenceid" => $this->txnresponse['refid'],"order_id" => $this->txnresponse['orderid'] , "flag" => $statusmessage, "other_details" => addslashes(serialize($this->txnresponse)),"amount" => $this->txnresponse['amount'] , "payment_gateway" => "MOBIKWIK");
	}

	public function after_save_response(){
		$string = "'" . $this->txnresponse['orderid'] . "''" . $this->txnresponse['amount'] . "''" . $this->txnresponse['statusmessage'] . "''" . $this->txnresponse['refid'] ."''" . $this->txnresponse['mid'] . "''" ;
		$hash = hash_hmac('sha256', $string , $this->order_info['payment_method']['params']['secretkey']);
		$validchecksum = $hash;          
		$authentication = '0';
		if(strcmp($validchecksum,$this->txnresponse['checksum']))
		{         
			$authentication = '1';		
		}
		if($this->status_code != '0'){
			Analog::log($txnresponse['statusmessage'], json_encode($txnresponse) , payment, "WEB",Registry::get('config.LOG_LEVELS.INFO')); 
		}

		if (fn_check_payment_script('mobikwik_script.php', $this->txnresponse['orderid'])) 
		{
			if($authentication && $this->status_code == '0')
			{
				//echo "<br>Thank you for shopping with us. Your credit card has been charged and your transaction is successful. We will be shipping your order to you soon.";
				if( $this->txnresponse['amount'] == $this->order_info['total']) {
					return array("Success"=>1,"order_id"=>$this->orderid , "status" => "P");
					// fn_change_order_status($orderid, 'P', '', true);
				}else{
					return array("Success"=>1,"order_id"=>$this->txnresponse['orderid'] , "status" => "K", "payment_amount" => $this->txnresponse['amount'] , "order_total" => $this->order_info['details']);
					// fn_change_order_status($orderid, 'K', '', true);
					// $details = '******PAYMENT AMOUNT Rs. '.$this->txnresponse['amount'].' NOT SAME AS ORDER TOTAL.******'.$this->order_info['details'];
					// db_query("update cscart_orders set details='".$details."' where order_id=".$orderid);
				}

			}
			else if($authentication && in_array($this->status_code,array('30','31','32','33','40','41','42','43','60','70','71','40','1')))
			{
				//echo "<br>Thank you for shopping with us.However,the transaction has been declined.";
				return array("Success"=>1,"order_id"=>$this->txnresponse['orderid'] , "status" => "F");
				// fn_change_order_status($orderid, 'F', '', true);
			}else if($authentication && $this->status_code=='99')
			{
				//echo "<br>Thank you for shopping with us.However,the transaction has been declined.";
				return array("Success"=>1,"order_id"=>$this->txnresponse['orderid'] , "status" => "D");
				// fn_change_order_status($orderid, 'D', '', true);
			}else if(!$authentication)
			{
				//echo "<br>Thank you for shopping with us.However,the transaction has been declined.";
				return array("Success"=>1,"order_id"=>$this->txnresponse['orderid'] , "status" => "F");
				// fn_change_order_status($orderid, 'F', '', true);
			}
			else
			{
				//echo "<br>Security Error. Illegal access detected";
				return array("Success"=>0);
				// fn_set_notification('E','Order','There is some error with the order. Please try again','I');
				// fn_redirect('index.php?dispatch=checkout.cart');
			}
		}
	}

	
	private function pre_redirect(){
		
        $this->checksum = $this->getChecksum();		
        $order_data= $this->getFormattedOrderData();
		$insert_array = array("order_id" => $this->order_info['order_id'],"amount" => $this->order_info['total'], "payment_gateway" => $order_data['payment_gateway'], "order_data" => addslashes(serialize($order_data)));
		return $insert_array;
	}


	private function getFormFields(){
		$form_fields = array("email" => $this->order_info['email'],"amount"=>$this->order_info['total'],"cell"=>$this->cell,"orderid"=>$this->order_info['order_id'],"mid"=>$this->order_info['payment_method']['params']['merchantid'],"merchantname"=>$this->order_info['payment_method']['params']['merchantname'],"redirecturl"=>$this->redirecturl,"version"=>$this->version,"checksum"=>$this->checksum);
	}

	private function getChecksum(){


		$string = "'" . $this->cell . "''" . $this->order_info['email'] . "''" . $this->order_info['total'] . "''" . $this->order_info['orderid'] . "''" . $this->redirecturl . "''" . $this->order_info['merchantid'] . "'";
		return hash_hmac('sha256', $string , $this->order_info['payment_method']['params']['secretkey']);
	}

  private function getFormattedOrderData(){
  	cell = phone_format($this->cell);
		$s_phone = phone_format($this->order_info['s_phone']);
		$b_phone = phone_format($this->order_info['b_phone']);

		$billing_cust_name		= formatting_parameters($this->order_info['b_firstname'].' '.$this->order_info['b_lastname']);
		$billing_cust_address   = formatting_parameters($this->order_info['b_address'].' '.$this->order_info['b_address_2']);
		$billing_cust_state		= formatting_parameters($this->order_info['b_state']);
		$billing_cust_country   = formatting_parameters($this->order_info['b_country_name']);
		$billing_cust_tel		= formatting_parameters($b_phone);
		$billing_cust_email		= $this->order_info['email'];
		$delivery_cust_name		= formatting_parameters($this->order_info['s_firstname'].' '.$this->order_info['s_lastname']);
		$delivery_cust_address  = formatting_parameters($this->order_info['s_address'].' '.$this->order_info['s_address_2']);
		$delivery_cust_state    = formatting_parameters($this->order_info['s_state']);
		$delivery_cust_country  = formatting_parameters($this->order_info['s_country_name']);
		$delivery_cust_tel		= formatting_parameters($this->order_info['s_phone']);
		$delivery_cust_notes    = '';
		$billing_city 			= formatting_parameters($this->order_info['b_city']);
		$billing_zip 			= formatting_parameters($this->order_info['b_zipcode']);
		$delivery_city 			= formatting_parameters($this->order_info['s_city']);
		$delivery_zip 			= formatting_parameters($this->order_info['s_zipcode']);
		$firstname              = formatting_parameters($this->order_info['b_firstname']);

		
		$order_data = array();
		$order_data['cust_name'] = $billing_cust_name;
		$order_data['custAddress'] = $billing_cust_address;
		$order_data['custCity'] = $billing_city;
		$order_data['custState'] = $billing_cust_state;
		$order_data['custPinCode'] = $billing_zip;
		$order_data['custCountry'] = $billing_cust_country;
		$order_data['custMobileNo'] = $billing_cust_tel;
		$order_data['custEmailId'] = $billing_cust_email;
		$order_data['deliveryName'] = $delivery_cust_name;
		$order_data['deliveryAddress'] = $delivery_cust_address;
		$order_data['deliveryCity'] = $delivery_city;
		$order_data['deliveryState'] = $delivery_cust_state;
		$order_data['deliveryPinCode'] = $delivery_zip;
		$order_data['deliveryCountry'] = $delivery_cust_country;
		$order_data['deliveryMobileNo'] = $delivery_cust_tel;

		$order_data['otherNotes'] = $delivery_cust_notes; // customer notes not to send to payment gateway
		
		$order_data['requestparameter'] = $this->redirect_Url.'|'.$this->order_info['order_id'].'|'.$this->order_info['total'].'|'.$this->order_info['payment_method']['params']['merchantid'];
		$order_data['payment_gateway'] = 'MOBIKWIK';
		$order_data['amount'] = $this->order_info['total'];
		// $order_data['pgw_promo_code'] = $pgw_promo_code;
		// $order_data['user_credentials'] = $user_credentials;
		$order_data['URL'] = $this->order_info['payment_method']['params']['url'];
		$order_data['MID'] = $this->order_info['payment_method']['params']['merchantid'];
		$order_data['merchantname'] = $this->order_info['payment_method']['params']['merchantname'];
		$order_data['CHECKSUM'] = $this->checksum;
		$order_data['email'] = $this->order_info['email'];
		$order_data['cell'] = $this->cell;
		return $order_data;
  }


}
?>


