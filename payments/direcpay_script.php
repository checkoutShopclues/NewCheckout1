<?php

class direcpay_script{
    //need to make a function to find order info details w.r.t. order_id
    private $order_info;// get order info main fields including country by name
    private $payment_parameters;
    private $paymentService;
    private $redirect_url;
    private $config;
    
    public function __construct($order_info, $payment_parameters, $current_location){
        $this->order_info = $order_info;
        $this->payment_parameters = $payment_parameters;
        $this->paymentService = new PaymentService();
        $this->redirect_url = $current_location."?dispatch=payment_notification.return&payment=direcpay_script&order_id=".$this->order_info['order_id'];
    }

    public function getDataForRedirection(){
        $form_name = "ecom";
        $target_url = $this->config['direcpay_payment_url'];
        $prepaymentData = $this->getPaymentDataForOrder();
        $this->paymentService->savePrePaymentData($prepaymentData);
        $form_fields = $this->getFormFields($order_data);
        $form_array = array("name" => $form_name, "method" => "post", "action" => $target_url);
        return array("form_array" => $form_array, "form_fields" => $form_fields, "script" => array(array("name" => "src", "value" => "js/dpEncodeRequest.js")), "script_function" => array("function" => "encodeValue","variable" => "requestparameter"));
    }
    
    private function getPaymentDataForOrder(){		
        $order_data = $this->getFormattedOrderData();
        $insert_array = array("order_id" => $this->order_info['order_id'],"amount" => $this->order_info['total'], "payment_gateway" => 'Direcpay', "order_data" => addslashes(serialize($order_data)));
        return $insert_array;
    }
    
    private function getFormattedOrderData(){
        $b_phone = $this->phone_format($this->order_info['b_phone']);
        $s_phone = $this->phone_format($this->order_info['s_phone']);
        $custName 			= $this->formatting_parameters($this->order_info['b_firstname'].' '.$this->order_info['b_lastname']);
        $custAddress 		= $this->formatting_parameters($this->order_info['b_address'].' '.$this->order_info['b_address_2']);
        $custCity               = $this->formatting_parameters($this->order_info['b_city']);
        $custState 		= $this->formatting_parameters($this->order_info['b_state']);
        $custPinCode 		= $this->formatting_parameters($this->order_info['b_zipcode']);
        $custCountry 		= $this->formatting_parameters($this->order_info['b_country_name']);
        $custPhoneNo1 		= '';
        $custPhoneNo2 		= '';
        $custPhoneNo3 		= '';
        $custMobileNo 		= $b_phone;
        $custEmailId 		= $this->order_info['email'];
        $deliveryName 		= $this->formatting_parameters($this->order_info['s_firstname'].' '.$this->order_info['s_lastname']);
        $deliveryAddress 	= $this->formatting_parameters($this->order_info['s_address'].' '.$this->order_info['s_address_2']);
        $deliveryCity 		= $this->formatting_parameters($this->order_info['s_city']);
        $deliveryState 		= $this->formatting_parameters($this->order_info['s_state']);
        $deliveryPinCode 	= $this->formatting_parameters($this->order_info['s_zipcode']);
        $deliveryCountry 	= $this->formatting_parameters($this->order_info['s_country_name']);
        $deliveryPhNo1 		= '';
        $deliveryPhNo2 		= '';
        $deliveryPhNo3 		= '';
        $deliveryMobileNo 	= $s_phone;
        $otherNotes 		= ''; // customer notes not to send to payment gateway
        $editAllowed 		= 'N';
		
        $config['direcpay_merchantid'] = $this->payment_parameters['payment_method']['params']['merchantid'];

        if($this->payment_parameters['payment_method']['params']['testmode'] == 'on') {
            $config['direcpay_payment_url'] = 'https://test.timesofmoney.com/direcpay/secure/dpMerchantTransaction.jsp';
            $config['collaborator_id'] = 'TOML';
        } else {
            $config['direcpay_payment_url'] = 'https://www.timesofmoney.com/direcpay/secure/dpMerchantTransaction.jsp';	
            $config['collaborator_id'] = 'DirecPay';
        }
        $this->config = $config;
        $requestparameter = $this->payment_parameters['payment_method']['params']['merchantid'].'|DOM|IND|INR|'.$this->order_info['total'].'|'.$this->order_info['order_id'].'|others|'.$this->redirect_url.'|'.$this->redirect_url.'|'.$config['collaborator_id'];
        
        $order_data = array();
        $order_data['cust_name'] = $custName;
        $order_data['custAddress'] = $custAddress;
        $order_data['custCity'] = $custCity;
        $order_data['custState'] = $custState;
        $order_data['custPinCode'] = $custPinCode;
        $order_data['custCountry'] = $custCountry;
        $order_data['custMobileNo'] = $custMobileNo;
        $order_data['custEmailId'] = $custEmailId;
        $order_data['deliveryName'] = $deliveryName;
        $order_data['deliveryAddress'] = $deliveryAddress;
        $order_data['deliveryCity'] = $deliveryCity;
        $order_data['deliveryState'] = $deliveryState;
        $order_data['deliveryPinCode'] = $deliveryPinCode;
        $order_data['deliveryCountry'] = $deliveryCountry;
        $order_data['deliveryMobileNo'] = $deliveryMobileNo;
        $order_data['otherNotes'] = $otherNotes;
        $order_data['editAllowed'] = $editAllowed;
        $order_data['requestparameter'] = $requestparameter;
        $order_data['payment_gateway'] = 'DirecPay';
        $order_data['amount'] = $this->order_info['total'];
        $order_data['url'] = $config['direcpay_payment_url'];
        return $order_data;
    }
    
    public function paymentReturn($redirect_response){
        $this->txnresponse    = $redirect_response;
        
        $response = explode('|', $redirect_response['responseparams']);
        list($direcpayreferenceid, $flag, $country, $currency, $otherdetails, $merchantorderno, $amount) = $response;
        $insert_array = array("direcpayreferenceid" => $direcpayreferenceid, "order_id" => $merchantorderno, "flag" => $flag, "other_details" => addslashes($otherdetails), "amount" => $amount, "payment_gateway" => 'DirecPay');
        $this->paymentService->saveAfterPaymentData($insert_array);
        
        $this->order_info     = fn_get_order_info($this->txnresponse['order_id'], true);//fn_get_order_info - replace this function with own made function
        $this->checkResponse()?$this->callChangeStatus($response):'';
    }

    private function getFormFields($order_data){
        $form_fields = array(array(
                            'name'=>'custName',
                            'value'=>$order_data['custName']
                        ),
                        array(
                            'name'=>'custAddress',
                            'value'=>$order_data['custAddress']
                        ),
                        array(
                            'name'=>'custCity',
                            'value'=>$order_data['custCity']
                        ),
                        array(
                            'name'=>'custState',
                            'value'=>$order_data['custState']
                        ),
                        array(
                            'name'=>'custPinCode',
                            'value'=>$order_data['custPinCode']
                        ),
                        array(
                            'name'=>'custCountry',
                            'value'=>$order_data['custCountry']
                        ),
                        array(
                            'name'=>'custPhoneNo1',
                            'value'=>''
                        ),
                        array(
                            'name'=>'custPhoneNo2',
                            'value'=>''
                        ),
                        array(
                            'name'=>'custPhoneNo3',
                            'value'=>''
                        ),
                        array(
                            'name'=>'custMobileNo',
                            'value'=>$order_data['custMobileNo']
                        ),
                        array(
                            'name'=>'custEmailId',
                            'value'=>$order_data['custEmailId']
                        ),
                        array(
                            'name'=>'deliveryName',
                            'value'=>$order_data['deliveryName']
                        ),
                        array(
                            'name'=>'deliveryAddress',
                            'value'=>$order_data['deliveryAddress']
                        ),
                        array(
                            'name'=>'deliveryCity',
                            'value'=>$order_data['deliveryCity']
                        ),
                        array(
                            'name'=>'deliveryState',
                            'value'=>$order_data['deliveryState']
                        ),
                        array(
                            'name'=>'deliveryPinCode',
                            'value'=>$order_data['deliveryPinCode']
                        ),
                        array(
                            'name'=>'deliveryCountry',
                            'value'=>$order_data['deliveryCountry']
                        ),
                        array(
                            'name'=>'deliveryPhNo1',
                            'value'=>''
                        ),
                        array(
                            'name'=>'deliveryPhNo2',
                            'value'=>''
                        ),
                        array(
                            'name'=>'deliveryPhNo3',
                            'value'=>''
                        ),
                        array(
                            'name'=>'deliveryMobileNo',
                            'value'=>$order_data['deliveryMobileNo']
                        ),
                        array(
                            'name'=>'otherNotes',
                            'value'=>$order_data['otherNotes']
                        ),
                        array(
                            'name'=>'editAllowed',
                            'value'=>$order_data['editAllowed']
                        ),
                        array(
                            'name'=>'requestparameter',
                            'value'=>$order_data['requestparameter']
                        )
                    );
        return $form_fields;
    }

    private function checkResponse(){		
        if (fn_check_payment_script('direcpay_script.php', $this->txnresponse['order_id'])){
            return true;
        }
        else{
            return false;
        }
    }

    private function callChangeStatus($response){
        if($response['1'] == 'SUCCESS') {
            if ($this->order_info['status'] == 'N' || $this->order_info['status'] == 'T') {
                if($response['6'] == $this->order_info['total']) {
                    return array("Success"=>1,"order_id"=>$this->order_info['order_id'] , "status" => "P");
                }else{
                    return array("Success"=>1,"order_id"=>$this->txnresponse['order_id'] , "status" => "K", "payment_amount" => $response['6'] , "order_total" => $this->order_info['details']);
                }
            }
        } elseif($response['1'] == 'FAIL') {
            if ($this->order_info['status'] == 'N' || $this->order_info['status'] == 'T') {
                return array("Success"=>1,"order_id"=>$this->txnresponse['order_id'] , "status" => "F");
            }
        }
    }
}
?>


