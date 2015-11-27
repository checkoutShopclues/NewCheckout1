<?php

class PGW{
	public function start_payment($order_info, $force_notification = array()){
	        return $processor_data['processor_script'];
	        // // include(DIR_PAYMENT_FILES . $processor_data['processor_script']);
	        // $this->create_script_parameters($order_info);
	        // return fn_finish_payment($order_id, $pp_response, $force_notification);
		}
	}

	public function create_script_parameters($order_info){
		$email   =$order_info['email'];
		$amount  =$order_info['total'];
		$orderid =$order_info['order_id'];
		$cell = db_get_field("SELECT phone from ?:users where user_id = ?i", $order_info['user_id']);
		if(empty($cell)){
			$cell = $order_info['s_phone'];
		}
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

		$cell = str_replace(' ','',$cell);
		$cell = str_replace('-','',$cell);
		$cell = str_replace('+','',$cell);
		$cell = str_replace('(','',$cell);
		$cell = str_replace(')','',$cell);               

		$s_phone = str_replace(' ','',$order_info['s_phone']);
		$s_phone = str_replace('-','',$s_phone);
		$s_phone = str_replace('+','',$s_phone);
		$s_phone = str_replace('(','',$s_phone);
		$s_phone = str_replace(')','',$s_phone);

		$b_phone = str_replace(' ','',$order_info['b_phone']);
		$b_phone = str_replace('-','',$b_phone);
		$b_phone = str_replace('+','',$b_phone);
		$b_phone = str_replace('(','',$b_phone);
		$b_phone = str_replace(')','',$b_phone);
		$b_country = fn_get_country_name($order_info['b_country']);
		$s_country = fn_get_country_name($order_info['s_country']);

		$billing_cust_name		= preg_replace($patterns, $replacements, trim(preg_replace('/[^a-zA-Z0-9\s]/', " ", $order_info['b_firstname'].' '.$order_info['b_lastname'])));
		$billing_cust_address           = preg_replace($patterns, $replacements, trim(preg_replace('/[^a-zA-Z0-9\s]/', " ", $order_info['b_address'].' '.$order_info['b_address_2'])));
		$billing_cust_state		= preg_replace($patterns, $replacements, trim(preg_replace('/[^a-zA-Z0-9\s]/', " ", $order_info['b_state'])));
		$billing_cust_country           = preg_replace($patterns, $replacements, trim(preg_replace('/[^a-zA-Z0-9\s]/', " ", $b_country)));
		$billing_cust_tel		= preg_replace($patterns, $replacements, trim(preg_replace('/[^a-zA-Z0-9\s]/', " ", $b_phone)));
		$billing_cust_email		= $order_info['email'];
		$delivery_cust_name		= preg_replace($patterns, $replacements, trim(preg_replace('/[^a-zA-Z0-9\s]/', " ", $order_info['s_firstname'].' '.$order_info['s_lastname'])));
		$delivery_cust_address          = preg_replace($patterns, $replacements, trim(preg_replace('/[^a-zA-Z0-9\s]/', " ", $order_info['s_address'].' '.$order_info['s_address_2'])));
		$delivery_cust_state            = preg_replace($patterns, $replacements, trim(preg_replace('/[^a-zA-Z0-9\s]/', " ", $order_info['s_state'])));
		$delivery_cust_country          = preg_replace($patterns, $replacements, trim(preg_replace('/[^a-zA-Z0-9\s]/', " ", $s_country)));
		$delivery_cust_tel		= preg_replace($patterns, $replacements, trim(preg_replace('/[^a-zA-Z0-9\s]/', " ", $s_phone)));
		$delivery_cust_notes            = '';
		if(isset($child_ids) && $child_ids !='') {
			$Merchant_Param		= $child_ids;
		}else {
			$Merchant_Param		= "";
		}
		$billing_city 			= preg_replace($patterns, $replacements, trim(preg_replace('/[^a-zA-Z0-9\s]/', " ", $order_info['b_city'])));
		$billing_zip 			= preg_replace($patterns, $replacements, trim(preg_replace('/[^a-zA-Z0-9\s]/', " ", $order_info['b_zipcode'])));
		$delivery_city 			= preg_replace($patterns, $replacements, trim(preg_replace('/[^a-zA-Z0-9\s]/', " ", $order_info['s_city'])));
		$delivery_zip 			= preg_replace($patterns, $replacements, trim(preg_replace('/[^a-zA-Z0-9\s]/', " ", $order_info['s_zipcode'])));	
		$firstname                      = preg_replace($patterns, $replacements, trim(preg_replace('/[^a-zA-Z0-9\s]/', " ", $order_info['b_firstname'])));
		$product_info			= preg_replace($patterns, $replacements, trim(preg_replace('/[^a-zA-Z0-9\s]/', " ", $product_info)));

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
		if ($order_info['cod_eligible'] == 1)
		{
			$order_data['codurl'] = $cod_Url;
		}
		$order_data['otherNotes'] = $delivery_cust_notes; // customer notes not to send to payment gateway
		
		$order_data['requestparameter'] = $redirect_Url.'|'.$order_Id.'|'.$amount.'|'.$mid;
		$order_data['payment_gateway'] = 'MOBIKWIK';
		$order_data['amount'] = $order_info['total'];
		$order_data['pgw_promo_code'] = $pgw_promo_code;
		$order_data['user_credentials'] = $user_credentials;
		$order_data['URL'] = $targeturl;
		$order_data['MID'] = $mid;
		$order_data['merchantname'] = $merchantname;
		$order_data['CHECKSUM'] = $checksum;
		$order_data['email'] = $email;
		$order_data['cell'] = $cell;

		$clues_order_pgw_sql = "insert into clues_order_pgw (order_id, amount, payment_gateway, order_data) values ('".$order_info['order_id']."','".$order_info['total']."','".$order_data['payment_gateway']."','".addslashes(serialize($order_data))."')";
		db_query($clues_order_pgw_sql);	
	}
}

?>