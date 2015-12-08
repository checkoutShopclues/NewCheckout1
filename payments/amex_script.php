<?php

class amex_script{
    
    //need to make a function to find order info details w.r.t. order_id
    private $redirecturl;	
    private $order_info;// get order info main fields including country by name
    private $payment_parameters;
    private $paymentService;
    private $txnresponse;

    public function __construct($order_info, $payment_parameters, $current_location){
        $this->order_info = $order_info;
        $this->payment_parameters = $payment_parameters;
        $this->paymentService = new PaymentService();
        $this->redirecturl 	= 'https://'.Registry::get('config.https_host').Registry::get('config.https_path')."/payments/amex/PHP_VPC_3Party_Auth_Capture_Order_DR.php" ;//your redirect URL where your customer will be redirected after authorisation from AMEX
    }

    public function getDataForRedirection(){
        $form_name = "frmamex";
        $prepaymentData = $this->getPaymentDataForOrder();
        $this->paymentService->savePrePaymentData($prepaymentData);
        $target_url = "https://".Registry::get('config.https_host').Registry::get('config.https_path')."/payments/amex/PHP_VPC_3Party_Auth_Capture_Order_DO.php";
        $form_fields = $this->getFormFields();
        $form_array = array("name" => $form_name, "method" => "post", "action" => $target_url, "accept-charset" => "UTF-8");
        return array("form_array" => $form_array, "form_fields" => $form_fields);
    }

    public function paymentReturn($redirect_response){
        $this->txnresponse    = $redirect_response;
        $this->order_info     = fn_get_order_info($this->txnresponse['order_id'], true);//fn_get_order_info - replace this function with own made function
        $response = $this->paymentService->get_prepayment_details($this->txnresponse['order_id']);
        if($response['flag'] != '0'){
            Analog::log($response['txn_response'], json_encode($response) , payment, "WEB",Registry::get('config.LOG_LEVELS.INFO')); 
        }
        $this->checkResponse()?$this->callChangeStatus($response):'';
    }

    private function getPaymentDataForOrder(){		
        $order_data = $this->getFormattedOrderData();
        $insert_array = array("order_id" => $this->order_info['order_id'],"amount" => $this->order_info['total'], "payment_gateway" => 'AMEX', "order_data" => addslashes(serialize($order_data)));
        return $insert_array;
    }

    private function getFormFields(){
        $form_fields = array(array(
                            'name'=>'Title',
                            'value'=>"PHP VPC 3 Party Super Transacion"
                            ),
                        array(
                            'name'=>'virtualPaymentClientURL',
                            'value'=>"https://vpos.amxvpos.com/vpcpay",
                            'size' => "65",
                            'maxlength' => "250"
                            ),
                        array(
                            'name'=>'vpc_Version',
                            'value'=>"1",
                            'size' => "20",
                            'maxlength' => "8"
                            ),
                        array(
                            'name'=>'vpc_Command',
                            'value'=>"pay",
                            'size' => "20",
                            'maxlength' => "16"
                            ),
                        array(
                            'name'=>'vpc_AccessCode',
                            'value'=>$this->payment_parameters['payment_method']['params']['access_code'],
                            'size' => "20",
                            'maxlength' => "8"
                            ),
                        array(
                            'name'=>'vpc_MerchTxnRef',
                            'value'=>$this->order_info['orderid'],
                            'size' => "20",
                            'maxlength' => "40"
                            ),
                        array(
                            'name'=>'vpc_Merchant',
                            'value'=>$this->redirecturl,
                            'size' => "20",
                            'maxlength' => "16"
                            ),
                        array(
                            'name'=>'vpc_OrderInfo',
                            'value'=>$this->order_info['orderid'],
                            'size' => "20",
                            'maxlength' => "34"
                            ),
                        array(
                            'name'=>'vpc_Amount',
                            'value'=>$this->order_info['total'],
                            'maxlength' => "10"
                            ),
                        array(
                            'name'=>'vpc_ReturnURL',
                            'value'=>$this->redirecturl,
                            'size' => "65",
                            'maxlength' => "250"
                            ),
                        array(
                            'name'=>'vpc_Locale',
                            'value'=>"en",
                            'size' => "20",
                            'maxlength' => "5"
                            ),
                        array(
                            'name'=>'vpc_BillTo_Title',
                            'value'=>"N/A"
                            ),
                        array(
                            'name'=>'vpc_BillTo_Firstname',
                            'value'=>"N/A"
                            ),
                        array(
                            'name'=>'vpc_BillTo_Middlename',
                            'value'=>"N/A"
                            ),
                        array(
                            'name'=>'vpc_BillTo_Lastname',
                            'value'=>"N/A"
                            ),
                        array(
                            'name'=>'vpc_BillTo_Phone',
                            'value'=>"N/A"
                            ),
                        array(
                            'name'=>'vpc_AVS_Street01',
                            'value'=>"N/A",
                            'maxlength' => "20"
                            ),
                        array(
                            'name'=>'vpc_AVS_City',
                            'value'=>"N/A"
                            ),
                        array(
                            'name'=>'vpc_AVS_StateProv',
                            'value'=>"N/A",
                            'maxlength' => "5"
                            ),
                        array(
                            'name'=>'vpc_AVS_PostCode',
                            'value'=>"N/A",
                            'maxlength' => "9"
                            ),
                        array(
                            'name'=>'vpc_AVS_Country',
                            'value'=>"N/A"
                            )         
                    );
        return $form_fields;
    }

    private function getFormattedOrderData(){
        $s_phone = $this->phone_format($this->order_info['s_phone']);
        $b_phone = $this->phone_format($this->order_info['b_phone']);

        $billing_cust_name		= $this->formatting_parameters($this->order_info['b_firstname'].' '.$this->order_info['b_lastname']);
        $billing_cust_address   = 'NA';
        $billing_cust_state	= 'NA';
        $billing_cust_country   = 'NA';
        $billing_cust_tel	= 'NA';
        $billing_cust_email	= $order_info['email'];
        $delivery_cust_name     = 'NA';
        $delivery_cust_address	= 'NA';
        $delivery_cust_state 	= 'NA';
        $delivery_cust_country 	= 'NA';
        $delivery_cust_tel      = 'NA';
        $delivery_cust_notes	= 'NA';

        $billing_city 		= 'NA';
        $billing_zip 		= 'NA';
        $delivery_city 		= 'NA';
        $delivery_zip 		= 'NA';

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

        $order_data['requestparameter'] = $this->redirecturl.'|'.$this->order_info['order_id'].'|'.$this->order_info['total'].'|'.$this->payment_parameters['payment_method']['params']['merchantid'];
        $order_data['payment_gateway'] = 'AMEX';
        $order_data['amount'] = $this->order_info['total'];
        $order_data['url'] = $this->redirecturl;
        $order_data['mid'] = $this->payment_parameters['payment_method']['params']['merchantid'];
        $order_data['access_code'] = $this->payment_parameters['payment_method']['params']['access_code'];
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
        if (fn_check_payment_script('amex_script.php', $this->txnresponse['order_id'])) 
        {
            return true;
        }
        else{
            return false;
        }
    }

    private function callChangeStatus($response){
        $response['amount'] = ($response['amount']/100);
    /*if payment capture status = 0 and authrization response is 0  and 3d status is either Y or A then its a successful order*/
        if($response['flag'] == '0' && $response['txn_response'] == '0' && ($response['3dstatus'] == 'Y' || $response['3dstatus'] == 'A')){                       
            if($response['amount'] == $order_info['total']) {
                return array("Success"=>1,"order_id"=>$this->order_info['order_id'] , "status" => "P");
            }else{
                return array("Success"=>1,"order_id"=>$this->txnresponse['order_id'] , "status" => "K", "payment_amount" => $this->response['amount'] , "order_total" => $this->order_info['details']);
            }
        }elseif($response['flag'] != '0' && $response['txn_response'] == '0' && ($response['3dstatus'] == 'Y' || $response['3dstatus'] == 'A')){
            /*if payment capture status not 0 and authrization response is 0  and 3d status is either Y or A then its a may be a successful order so put it in payment pending.*/
            return array("Success"=>1,"order_id"=>$this->txnresponse['orderid'] , "status" => "K", "details" => 'Capture failed at payment gateway');
        }else{
            /*if authrization response is not 0 means authrizaiton is failed and its a fail order.*/
            return array("Success"=>1,"order_id"=>$this->txnresponse['order_id'] , "status" => "F");
        }
    }
}
?>


