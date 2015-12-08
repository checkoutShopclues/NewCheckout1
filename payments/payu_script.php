<?php

class payu_script{
    //need to make a function to find order info details w.r.t. order_id
    private $redirecturl;
    private $cod_Url;
    private $order_info;// get order info main fields including country by name//here order_info needs "items" and "emi_id" and "promotion_ids" from cscart_orders as well
    private $payment_parameters;
    private $paymentService;
    private $txnresponse;
    private $post_response;
    private $txnRs;
    private $hash;
    
    public function __construct($order_info, $payment_parameters, $current_location){
        $this->order_info = $order_info;
        $this->payment_parameters = $payment_parameters;
        $this->paymentService = new PaymentService();
        $this->redirecturl 	= $current_location."?dispatch=payment_notification.return&payment=payu_script&order_id=".$order_info['order_id'] ;
        $this->cod_Url = $current_location."?dispatch=payment_notification.return&payment=payu_script&action=6&order_id=".$order_info['order_id'] ;
    }

    public function getDataForRedirection(){
        $form_name = "frm_payu";        
        $prepaymentData = $this->getPaymentDataForOrder();
        $this->paymentService->savePrePaymentData($prepaymentData);
        $target_url = $this->payment_parameters['payment_method']['params']['url'];
        $form_fields = $this->getFormFields($prepaymentData);
        $form_array = array("name" => $form_name, "method" => "post", "action" => $target_url);
        return array("form_array" => $form_array, "form_fields" => $form_fields);
    }

    public function paymentReturn($redirect_response, $post_response){
        $this->txnresponse    = $redirect_response;
        $this->post_response = $post_response;
        $this->order_info     = fn_get_order_info($this->txnresponse['order_id'], true);//fn_get_order_info - replace this function with own made function
        $cod_data = check_for_cod_eligible($this->txnresponse['order_id']);
        if(isset($cod_data) && !empty($cod_data)){
            if ($cod_data['cod_eligible'] == 1){
                ?> 
                    <form method="POST" action="<? echo fn_url(''); ?>" name="frm_payu_cod">
                        <input type="hidden" name="order_id" value="<? echo $cod_data['order_id']; ?>">
                        <input type="hidden" name="user_id" value="<? echo $cod_data['user_id']; ?>">
                        <input type="hidden" name="key" value="<? echo $cod_data['key']; ?>">
                        <input type="hidden" name="dispatch" value="checkout.place_cod_order">
                        <script type="text/javascript">
                                document.frm_payu_cod.submit();
                        </script>
                    </form>
                <?php    
            }
        }
        
        $insert_array = $this->getResponseArray();
        
        $this->paymentService->saveAfterPaymentData($insert_array);
        $this->checkResponse() ? $this->callChangeStatus():'';
    }

    private function getPaymentDataForOrder(){   
        $order_data = $this->getFormattedOrderData();
        $insert_array = array("order_id" => $this->order_info['order_id'],"amount" => $this->order_info['total'], "payment_gateway" => $order_data['payment_gateway'], "order_data" => addslashes(serialize($order_data)));
        return $insert_array;
    }

    private function getFormFields($prepaymentData){
        $form_fields = array(array(
                            'name'=>'key',
                            'value'=>$this->payment_parameters['payment_method']['params']['merchantid']
                        ),
                        array(
                            'name'=>'amount',
                            'value'=>$this->order_info['total']
                        ),
                        array(
                            'name'=>'txnid',
                            'value'=>$this->order_info['order_id']
                        ),
                        array(
                            'name'=>'firstname',
                            'value'=>$this->formatting_parameters($this->order_info['b_firstname'])
                        ),
                        array(
                            'name'=>'productinfo',
                            'value'=>$prepaymentData['product_info']
                        ),
                        array(
                            'name'=>'email',
                            'value'=>$this->order_info['email']
                        ),
                        array(
                            'name'=>'phone',
                            'value'=>$prepaymentData['custMobileNo']
                        ),
                        array(
                            'name'=>'surl',
                            'value'=>$this->redirecturl
                        ),
                        array(
                            'name'=>'furl',
                            'value'=>$this->redirecturl
                        )
                    ); 
                if ($this->order_info['cod_eligible'] == 1)
                {
                    $form_fields[] = array(
                            'name'=>'codurl',
                            'value'=>$this->cod_Url
                        );
                }
                $form_fields[] = array(
                            'name'=>'hash',
                            'value'=>$prepaymentData['hash']
                        );
                $form_fields[] = array(
                            'name'=>'pg',
                            'value'=>$prepaymentData['pg']
                        );
                $form_fields[] = array(
                            'name'=>'enforce_paymethod',
                            'value'=>$prepaymentData['payment_enforce']
                        );
                $form_fields[] = array(
                            'name'=>'user_credentials',
                            'value'=>$prepaymentData['user_credentials']
                        );
        
        if(isset($prepaymentData['bank_code']) && $prepaymentData['bank_code'] != ''){
            $form_fields[] = array(
                            'name'=>'bankcode',
                            'value'=>$prepaymentData['bank_code']
                        );
        }
        if(isset($prepaymentData['pgw_promo_code']) && $prepaymentData['pgw_promo_code'] != '') {
            $form_fields[] = array(
                            'name'=>'offer_key',
                            'value'=>$prepaymentData['pgw_promo_code']
                        );
        }
        return $form_fields;
    }

    private function getFormattedOrderData(){
        $product_info = $this->getProductInfo();
        
        $s_phone = $this->phone_format($this->order_info['s_phone']);
        $b_phone = $this->phone_format($this->order_info['b_phone']);
        $billing_cust_name = $this->formatting_parameters($this->order_info['b_firstname'].' '.$this->order_info['b_lastname']);
        $billing_cust_address = $this->formatting_parameters($this->order_info['b_address'].' '.$this->order_info['b_address_2']);
        $billing_cust_state = $this->formatting_parameters($this->order_info['b_state']);
        $billing_cust_country = $this->formatting_parameters($this->order_info['b_country_name']);
        $billing_cust_tel = $this->formatting_parameters($b_phone);
        $billing_cust_email = $this->order_info['email'];
        $delivery_cust_name = $this->formatting_parameters($this->order_info['s_firstname'].' '.$this->order_info['s_lastname']);
        $delivery_cust_address = $this->formatting_parameters($this->order_info['s_address'].' '.$this->order_info['s_address_2']);
        $delivery_cust_state = $this->formatting_parameters($this->order_info['s_state']);
        $delivery_cust_country = $this->formatting_parameters($this->order_info['s_country_name']);
        $delivery_cust_tel = $this->formatting_parameters($s_phone);
        $delivery_cust_notes = '';
        $billing_city = $this->formatting_parameters($this->order_info['b_city']);
        $billing_zip = $this->formatting_parameters($this->order_info['b_zipcode']);
        $delivery_city = $this->formatting_parameters($this->order_info['s_city']);
        $delivery_zip = $this->formatting_parameters($this->order_info['s_zipcode']);
        $firstname = $this->formatting_parameters($this->order_info['b_firstname']);
        $product_info= $this->formatting_parameters($product_info);
        $user_credentials = $this->payment_parameters['payment_method']['params']['merchantid'].':'.md5(trim($this->order_info['user_id']).trim($this->order_info['email']));
        
        $payment_details = $this->paymentService->getFirstPriorityPaymentDataByPaymentOptId($this->payment_parameters['payment_option_id']);       
                
        if(empty($payment_details)){
            $payment_details = $this->paymentService->getEmiPaymentDataByPaymentOptId($this->payment_parameters['payment_option_id']);
            $emi_code_sql = $this->paymentService->getEmiCodeByEmiId($this->order_info['emi_id']);
        }
                
        $pg = '';
        $bank_code = '';
        
        if($payment_details['payment_type_id'] == '1'){
            $pg = 'NB';
            $payment_enforce = 'netbanking';
            $bank_code = $payment_details['bank_code'];
        }elseif($payment_details['payment_type_id'] == '2'){
            $pg = 'CC';
            $payment_enforce = 'creditcard';
        }elseif($payment_details['payment_type_id'] == '3'){
            $pg = 'DC';
            $payment_enforce = 'debitcard';
        }elseif($payment_details['payment_type_id'] == '5'){
            if($payment_details['payment_option_id'] == '115'){
                $pg = 'Wallet';
                $bank_code = 'payuw';
            }
            else{
                $pg = 'CASH';
                $payment_enforce = 'cashcard';
            }
        }elseif($payment_details['payment_type_id'] == '6'){
            $pg = 'EMI';
            $payment_enforce = $emi_code['emi_code'];
        }
        
        $input_data = payment_authetication_codes($this->order_info);

        if(!empty($input_data)){    
            if(!empty($input_data['product_info']))
                $product_info = $input_data['product_info'];

            if(!empty($input_data['pg']))
                $pg           = $input_data['pg'];

            if(!empty($input_data['bank_code']))
                $bank_code    = $input_data['bank_code'];
        }
        
        $hash_string = $this->payment_parameters['payment_method']['params']['merchantid'].'|'.$this->order_info['order_Id'].'|'.$this->order_info['total'].'|'.$product_info.'|'.$firstname.'|'.$billing_cust_email.'|||||||||||'.$this->payment_parameters['payment_method']['params']['salt'];
        $hash = strtolower(hash('sha512', $hash_string));

        $batch_time = date('Y-m-d');
        $check_for_emi = check_for_emi($this->order_info['items']);              
        if(!empty($this->payment_parameters['payment_id']) && $check_for_emi == 'Y'){                
            $result = array();
            if($this->order_info['promotion_ids'] != ''){                
                $result = getPgwPromotionDataByPaymentIdAndPromotionId($this->payment_parameters['payment_id'],$order_info['promotion_ids']);
            }

            if(empty($result)){
                $result = getPgwPromotionDataByPaymentIdAndPromotionId($this->payment_parameters['payment_id'],0,$batch_time);
            }

            $batch_hour = date('H:m');
            foreach($result as $k=>$v){
                if($batch_hour > $v['starttime'] && $batch_hour < $v['endtime']){                           
                    $platform = getSourceByOrderId($this->order_info['order_id']);
                    $plat = explode(",", $v['platform']);
                    if(in_array($platform, $plat)){
                        $promo[] = $v['pgw_promo_code'];
                    }
                }
                else{
                    unset($result[$k]);
                }
            }
            $pgw_promo_code =implode(",",$promo);
        }
        
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
        if ($order_info['cod_eligible'] == 1){
            $order_data['codurl'] = $cod_Url;
        }
        $order_data['otherNotes'] = $delivery_cust_notes; // customer notes not to send to payment gateway
        $order_data['editAllowed'] = 'not a parameter in ccavenue';
        $order_data['requestparameter'] = $this->redirecturl.'|'.$this->order_info['order_Id'].'|'.$this->order_info['total'].'|'.$this->payment_parameters['payment_method']['params']['merchantid'];
        $order_data['payment_gateway'] = 'PAYU';
        $order_data['amount'] = $this->order_info['total'];
        $order_data['emi_id'] = $this->order_info['emi_id'];
        $order_data['emi_fee'] = $this->order_info['emi_fee'];
        $order_data['pgw_promo_code'] = $pgw_promo_code;
        $order_data['user_credentials'] = $user_credentials;
        $order_data['product_info'] = $product_info;
        $order_data['pg'] = $pg;
        $order_data['bank_code'] = $bank_code;
        $order_data['order_id'] = $this->order_info['order_Id'];
        $order_data['merchant_id'] = $this->payment_parameters['payment_method']['params']['merchantid'];
        $order_data['target_url'] = $this->payment_parameters['payment_method']['params']['url'];
        $order_data['Redirect_Url'] = $this->redirecturl;
        $order_data['hash'] = $hash;
        $order_data['pgw_promo_code'] = $pgw_promo_code;
        $order_data['payment_enforce'] = $payment_enforce;
        return $order_data;
    }

    private function phone_format($cell){		
        $str_replace_string = array(" ","-","+","(",")");	
	$str_replace_through = array("","","","","");
        $cell = str_replace($str_replace_string,$str_replace_through,trim($cell));
        return $cell;
    }

    private function formatting_parameters($value){
        $patterns = array();
        $patterns = array('/^and\s/i','/\sand\s/i','/\sand$/i','/^or\s/i','/\sor\s/i','/\sor$/i','/^between\s/i','/\sbetween\s/i','/\sbetween$/i');
        $replacements = array();
        $replacements = array('','','','','','','','','');
        return preg_replace($patterns, $replacements, trim(preg_replace('/[^a-zA-Z0-9\s]/', " ", $value)));
    }

    private function checkResponse(){
        if(strtolower($this->txnRs['status'])!="success"){
            Analog::log($this->txnRs['error_Message'], json_encode($this->txnRs) , payment, "WEB",Registry::get('config.LOG_LEVELS.INFO')); 
        }

        if(!empty($this->txnresponse['offer']) && $this->txnresponse['discount'] > 0 && strtolower($this->txnRs['status'])=="success"){
            $this->updateAmountAfterDiscount();
        }
        if (fn_check_payment_script('payu_script.php', $this->txnresponse['order_id'])){
            return true;
        }
        else{
            return false;
        }
    }

    private function callChangeStatus(){
        if($this->hash == $this->txnRs['hash']){
            switch (strtolower($txnRs['status'])){
                case "success":
                    if($this->txnRs['amount'] == $this->order_info['total']) {
                        return array("Success"=>1,"order_id"=>$this->txnresponse['order_id'] , "status" => "P");
                     }else{
                         return array("Success"=>1,"order_id"=>$this->txnresponse['order_id'] , "status" => "K", "payment_amount" => $this->txnRs['amount'] , "order_total" => $this->order_info['details']);
                     }
                case "pending":
                    if($this->txnRs['amount'] == $this->order_info['total']) {
                        return array("Success"=>1,"order_id"=>$this->txnresponse['order_id'] , "status" => "K", "details" => '******PAYMENT MAY BE SUCCESS ON Payu.******'.$this->order_info['details']);                        
                    }else{
                        return array("Success"=>1,"order_id"=>$this->txnresponse['order_id'] , "status" => "K", "details" => '******PAYMENT AMOUNT Rs. '.$this->txnRs['amount'].' NOT SAME AS ORDER TOTAL AND PAYMENT MAY BE SUCCESS ON payu.******'.$this->order_info['details']);
                    }
                case "failure":
                    return array("Success"=>1,"order_id"=>$this->txnresponse['order_id'] , "status" => "F");
                default:
                    return array("Success" => 0);
            }
        }
        else{
            return array("Success"=>1,"order_id"=>$this->txnresponse['order_id'] , "status" => "F");
        }
    }
    
    private function getProductInfo(){
        $send_product_name_to_payu = Registry::get('config.send_product_name_to_payu');
        $product_info = '';
        if($send_product_name_to_payu == '0'){
            $product_info = Registry::get('config.send_product_name_to_payu_fixed_value');
        }elseif($send_product_name_to_payu == '1'){
            if(count($this->order_info['items']) > 1){
                $product_info = 'Multiple Product';
            }else{
                $product_info = 'Single Product';
            }
        }elseif($send_product_name_to_payu == '2'){
            $product_info = array();
            foreach($this->order_info['items'] as $item){
                $product_info[] = $item['product'];
            }
            $product_info = implode(',',$product_info);
        }
        return $product_info;
    }
    
    public function getResponseArray(){
        $txnRs = array();
        foreach($this->post_response as $key => $value) {
            $txnRs[$key] = htmlentities($value, ENT_QUOTES);
        }
        $this->txnRs = $txnRs;
        $product_info = $this->getProductInfo();
        $product_info= $this->formatting_parameters($product_info);
                
        $response_data = payment_authetication_codes($this->order_info);

        if(!empty($response_data)){    
            if(!empty($response_data['product_info']))
                $product_info = $response_data['product_info'];

            if(!empty($response_data['pg']))
                $pg           = $response_data['pg'];

            if(!empty($response_data['bank_code']))
                $bank_code    = $response_data['bank_code'];
        }

        $hash_string    = $this->payment_parameters['payment_method']['params']['salt'].'|'.$txnRs['status'].'|||||||||||'.$this->order_info['email'].'|'.$this->formatting_parameters($this->order_info['b_firstname']).'|'.$product_info.'|'.$txnRs['amount'].'|'.$redirect_response['order_id'].'|'.$this->payment_parameters['payment_method']['params']['merchantid'];
        $hash = strtolower(hash('sha512', $hash_string));
        $this->hash = $hash;
        $insert_array = array("direcpayreferenceid" => $txnRs['mihpayid'], "order_id" => $redirect_response['order_id'], "flag" => $txnRs['status'], "other_details" => addslashes(serialize($txnRs)), "amount" => $txnRs['amount'], "payment_gateway" => "PAYU");
        return $insert_array;
    }
    
    public function updateAmountAfterDiscount(){
        $responsevalues = array();
        $responsevalues['couponcode'] = $this->txnresponse['offer'];
        $responsevalues['originalamount'] = $this->txnresponse['amount'];
        $responsevalues['alteredamount'] = $this->txnresponse['net_amount_debit'];
        $this->paymentService->update_net_debit_amount($this->txnresponse['net_amount_debit'],$this->txnresponse['order_id']);
        $this->paymentService->create_order_data(array('order_id' => $this->txnresponse['order_id'], 'type' => 'T', 'data' => serialize($responsevalues)));
        $this->paymentService->create_order_data(array('order_id' => $this->txnresponse['order_id'], 'type' => 'N', 'data' => $this->txnresponse['discount']));
        
        $this->divideChildAmount();        
    }
    
    private function divideChildAmount(){
        $child_ids = $this->paymentService->getChildOrder($this->txnresponse['order_id']);
        
        $childcount = 0;  
        $child_db_count = count($child_ids);                        
        foreach($child_ids as $key => $child){
            $childdiscount = 0;
            $childamount = 0;
            $childcount++;
            $childdiscount = round(($child['total'] * $this->txnresponse['discount'])/$this->txnresponse['amount']);                                                                          
            if($childcount == $child_db_count){
                $childdiscount = $this->txnresponse['discount'] - $discount_amount_yet;                                   
            }
            $discount_amount_yet += $childdiscount;
            $childamount = $child['total'] - $childdiscount;
            $this->paymentService->update_net_debit_amount($childamount,$child['order_id']);
            $this->paymentService->create_order_data(array('order_id' => $child['order_id'], 'type' => 'T', 'data' => serialize($responsevalues)));
            $this->paymentService->create_order_data(array('order_id' => $child['order_id'], 'type' => 'N', 'data' => $childdiscount));
        }
    }
}
?>


