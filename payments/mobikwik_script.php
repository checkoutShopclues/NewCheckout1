<?php

class mobikwik_script{
    //need to make a function to find order info details w.r.t. order_id
    private $redirecturl;	
    private $order_info;// get order info main fields including country by name
    private $payment_parameters;
    private $paymentService;
    private $cell;
    private $version = 2;
    private $checksum;
    private $txnresponse;
    private $statusResponse;
    private $responsechecksum;

    public function __construct($order_info, $payment_parameters, $current_location){
        $this->order_info = $order_info;
        $this->payment_parameters = $payment_parameters;
        $this->paymentService= new PaymentService();
        $this->cell= $order_info['phone'] ? $order_info['phone'] : $order_info['s_phone'];
        $this->redirecturl 	= $current_location."?dispatch=payment_notification.return&payment=mobikwik_script&order_id=".$order_info['order_id'] ;
    }

    public function getDataForRedirection(){
        $form_name = "frm_mobikwik";
        $prepaymentData = $this->getPaymentDataForOrder();
        $this->paymentService->savePrePaymentData($prepaymentData);
        $target_url = $this->payment_parameters['payment_method']['params']['url'];
        $form_fields = $this->getFormFields();
        $form_array = array("name" => $form_name, "method" => "post", "action" => $target_url);
        return array("form_array" => $form_array, "form_fields" => $form_fields);
    }

    public function paymentReturn($redirect_response){
        $this->txnresponse    = $redirect_response;
        $this->order_info     = fn_get_order_info($this->txnresponse['orderid'], true);//fn_get_order_info - replace this function with own made function

        $this->responsechecksum = $this->getResponseChecksum();
        $this->statusResponse = $this->getResponseStatusCode();

        $insert_array = array("direcpayreferenceid" => $this->txnresponse['refid'],"order_id" => $this->txnresponse['orderid'] , "flag" => $statusResponse['statusmessage'], "other_details" => addslashes(serialize($this->txnresponse)),"amount" => $this->txnresponse['amount'] , "payment_gateway" => "MOBIKWIK");
        $this->paymentService->saveAfterPaymentData($insert_array);
        $this->checkResponse()?$this->callChangeStatus():'';
    }

    private function getPaymentDataForOrder(){		
        $this->checksum = $this->getChecksum();		
        $order_data = $this->getFormattedOrderData();
        $insert_array = array("order_id" => $this->order_info['order_id'],"amount" => $this->order_info['total'], "payment_gateway" => $order_data['payment_gateway'], "order_data" => addslashes(serialize($order_data)));
        return $insert_array;
    }

    private function getChecksum(){
        $string = "'" . $this->cell . "''" . $this->order_info['email'] . "''" . $this->order_info['total'] . "''" . $this->order_info['orderid'] . "''" . $this->redirecturl . "''" . $this->payment_parameters['payment_method']['params']['merchantid'] . "'";
        return $this->find_hash($string);
    }

    private function getFormFields(){
        $form_fields = array(array(
                                'name'=>'email',
                                'value'=>$this->order_info['email']
                                ),
                            array(
                                'name'=>'amount',
                                'value'=>$this->order_info['total']
                                ),
                            array(
                                'name'=>'cell',
                                'value'=>$this->cell
                                ),
                            array(
                                'name'=>'orderid',
                                'value'=>$this->order_info['order_id']
                                ),
                            array(
                                'name'=>'mid',
                                'value'=>$this->payment_parameters['payment_method']['params']['merchantid']
                                ),
                            array(
                                'name'=>'merchantname',
                                'value'=>$this->payment_parameters['payment_method']['params']['merchantname']
                                ),
                            array(
                                'name'=>'redirecturl',
                                'value'=>$this->redirecturl
                                ),
                            array(
                                'name'=>'version',
                                'value'=>$this->version
                                ),
                            array(
                                'name'=>'checksum',
                                'value'=>$this->checksum
                                )        
                        );
        return $form_fields;
    }

    private function getFormattedOrderData(){
        $cell = $this->phone_format($this->cell);
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
        $delivery_cust_tel = $this->formatting_parameters($this->order_info['s_phone']);
        $delivery_cust_notes = '';
        $billing_city = $this->formatting_parameters($this->order_info['b_city']);
        $billing_zip = $this->formatting_parameters($this->order_info['b_zipcode']);
        $delivery_city = $this->formatting_parameters($this->order_info['s_city']);
        $delivery_zip = $this->formatting_parameters($this->order_info['s_zipcode']);
        $firstname = $this->formatting_parameters($this->order_info['b_firstname']);

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
        $order_data['requestparameter'] = $this->redirect_Url.'|'.$this->order_info['order_id'].'|'.$this->order_info['total'].'|'.$this->payment_parameters['payment_method']['params']['merchantid'];
        $order_data['payment_gateway'] = 'MOBIKWIK';
        $order_data['amount'] = $this->order_info['total'];		
        $order_data['URL'] = $this->payment_parameters['payment_method']['params']['url'];
        $order_data['MID'] = $this->payment_parameters['payment_method']['params']['merchantid'];
        $order_data['merchantname'] = $this->payment_parameters['payment_method']['params']['merchantname'];
        $order_data['CHECKSUM'] = $this->checksum;
        $order_data['email'] = $this->order_info['email'];
        $order_data['cell'] = $this->cell;
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
        if($this->statusResponse['statuscode'] != '0'){
            Analog::log($this->txnresponse['statusmessage'], json_encode($this->txnresponse) , payment, "WEB",Registry::get('config.LOG_LEVELS.INFO')); 
        }
        if (fn_check_payment_script('mobikwik_script.php', $this->txnresponse['orderid'])){
                return true;
        }
        else{
                return false;
        }
    }

    private function getResponseChecksum(){
        $string = "'" . $this->txnresponse['mid'] . "''" . $this->txnresponse['orderid'] . "'"  ; 
        return $this->find_hash($string);
    }

    private function getResponseStatusCode(){
        $url = $this->payment_parameters['payment_method']['params']['checkstatusurl'];    
        $param = "mid=".$this->txnresponse['mid']."&orderid=".$this->txnresponse['orderid']."&checksum=".$this->responsechecksum;
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
        return array("statuscode" => $statuscode, "statusmessage" => $statusmessage);	
    }

    private function find_hash($string){
        return hash_hmac('sha256', $string , $this->payment_parameters['payment_method']['params']['secretkey']);
    }

    private function checkResponseHash(){
        $string = "'" . $this->txnresponse['orderid'] . "''" . $this->txnresponse['amount'] . "''" . $this->txnresponse['statusmessage'] . "''" . $this->txnresponse['refid'] ."''" . $this->txnresponse['mid'] . "''" ;
        return $this->find_hash($string);
    }

    private function callChangeStatus(){
        $validchecksum = $this->checkResponseHash();          
        $authentication = '0';
        if(strcmp($validchecksum,$this->txnresponse['checksum']))
        {         
                $authentication = '1';		
        }

        switch ($authentication) {
            case '1':
                switch (true){
                    case ($this->statusResponse['statuscode'] == '0') : 
                            if( $this->txnresponse['amount'] == $this->order_info['total']) {
                                return array("Success"=>1,"order_id"=>$this->order_info['orderid'] , "status" => "P");
                                // fn_change_order_status($orderid, 'P', '', true);
                            }else{
                                    return array("Success"=>1,"order_id"=>$this->txnresponse['orderid'] , "status" => "K", "payment_amount" => $this->txnresponse['amount'] , "order_total" => $this->order_info['details']);
                            }

                    case (in_array($this->statusResponse['statuscode'],array('30','31','32','33','40','41','42','43','60','70','71','40','1'))): 
                        return array("Success"=>1,"order_id"=>$this->txnresponse['orderid'] , "status" => "F");

                    case ($this->statusResponse['statuscode'] == '99'): 
                        return array("Success"=>1,"order_id"=>$this->txnresponse['orderid'] , "status" => "D");

                    default: return array("Success" => 0);
                }
            case '0':
                return array("Success"=>1,"order_id"=>$this->txnresponse['orderid'] , "status" => "F");
            default:
                return array("Success"=>0);
        }
    }
}
?>


