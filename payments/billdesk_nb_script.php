<?php

class billdesk_nb_script{
    //need to make a function to find order info details w.r.t. order_id
    private $redirecturl;	
    private $order_info;// get order info main fields including country by name
    private $payment_parameters;
    private $paymentService;
    private $status;
    private $checksum;
    private $txnresponse;
    private $return_checksum;
    private $request_message;
    
    public function __construct($order_info, $payment_parameters, $current_location){
        $this->order_info = $order_info;
        $this->payment_parameters = $payment_parameters;
        $this->paymentService= new PaymentService();
        $this->redirecturl = $current_location."?dispatch=payment_notification.return&payment=billdesk_nb_script&order_id=".$order_info['order_id'] ;
    }

    public function getDataForRedirection(){
        $form_name = "frm_billdesk";
        $prepaymentData = $this->getPaymentDataForOrder();
        $this->paymentService->savePrePaymentData($prepaymentData);
        $target_url = $this->payment_parameters['payment_method']['params']['payment_url'];
        $form_fields = $this->getFormFields();
        $form_array = array("name" => $form_name, "method" => "post", "action" => $target_url);
        return array("form_array" => $form_array, "form_fields" => $form_fields);
    }

    public function paymentReturn($redirect_response){
        $this->txnresponse    = $redirect_response;
        $this->order_info     = fn_get_order_info($this->txnresponse['order_id'], true);//fn_get_order_info - replace this function with own made function
        $response           = $redirect_response['msg'];
        $return_response    = explode('|',$response);
        $this->return_checksum    = $return_response['25'];
        unset($return_response['25']);
        $res                = implode('|',$return_response);
        $checksum_key  	= $this->payment_parameters['payment_method']['params']['checksum_key'];

        $this->checksum           = strtoupper(hash_hmac('sha256',$res,$checksum_key, false));
        $this->Amount             = $return_response['4'];
        $transaction_id     = $return_response['2'];
        $this->status             = $return_response['14'];
        
        $insert_array = array("direcpayreferenceid" => $transaction_id,"order_id" => $this->txnresponse['order_id'] , "flag" => $this->status, "other_details" => addslashes(serialize($response)),"amount" => $this->Amount , "payment_gateway" => "Billdesk NB");
        $this->paymentService->saveAfterPaymentData($insert_array);
        $this->checkResponse()?$this->callChangeStatus():'';
    }

    private function getPaymentDataForOrder(){
        $this->getReqMessage();
        $order_data = $this->getFormattedOrderData();
        $insert_array = array("order_id" => $this->order_info['order_id'],"amount" => $this->order_info['total'], "payment_gateway" => 'Billdesk', "order_data" => addslashes(serialize($order_data)));
        return $insert_array;
    }

    private function getReqMessage(){
        $payment_details = $this->paymentService->getFirstPriorityPaymentDataByPaymentOptId($this->payment_parameters['payment_option_id']);
        $bank_code = '';
        $item_code = '';
        if($payment_details['payment_type_id'] == '1'){
            $bank_code = $payment_details['bank_code'];
            $item_code = 'DIRECT';
        }elseif($payment_details['payment_type_id'] == '12' || $payment_details['payment_option_id'] == Registry::get('config.master_pass_payment_option_id')){
            //condition added for master pass wallet
            $bank_code = 'MPC';
            $item_code = 'DIRECT';
        }else{
            $bank_code = 'NA';
            $item_code = 'NA';
        }

        $response_url	= $this->redirecturl;

        $msg  		= $this->payment_parameters['payment_method']['params']['merchantid'].'|'.$this->order_info['order_id'].'|NA|'.$this->order_info['total'].'|'.$bank_code.'|NA|NA|INR|'.$item_code.'|R|'.'shopclues'.'|NA|NA|F|'.$this->order_info['email'].'|NA|NA|NA|NA|NA|NA|'.$response_url;
        $checksum 	=  strtoupper(hash_hmac('sha256',$msg,$this->payment_parameters['payment_method']['params']['checksum_key'], false));
        $this->request_message= $msg.'|'.$checksum;
        return;
    }

    private function getFormFields(){
        $form_fields = array(array(
                                'name'=>'msg',
                                'value'=>$this->request_message
                                ),
                            array(
                                'name'=>'hidOperation',
                                'value'=>"ME100"
                                ),
                            array(
                                'name'=>'hidRequestId',
                                'value'=>"PGIME1000"
                                )
                        );
        return $form_fields;
    }

    private function getFormattedOrderData(){
        $order_data = array();
        $order_data['request_message'] = $this->request_message;
        return $order_data;
    }
    
    private function checkResponse(){
        if($this->status!='0300'){
            Analog::log($this->status, json_encode($this->txnresponse['msg']) , payment, "WEB",Registry::get('config.LOG_LEVELS.INFO')); 
        }
        if (fn_check_payment_script('billdesk_emi_script.php', $this->txnresponse['order_id'])){
            return true;
        }
        else{
            return false;
        }
    }
    
    private function callChangeStatus(){
        if($this->return_checksum == $this->checksum){
            if($this->status == '0300'){
                if($this->Amount == $this->order_info['total']) {
                    return array("Success"=>1,"order_id"=>$this->order_info['order_id'] , "status" => "P");
                }
                else{
                    return array("Success"=>1,"order_id"=>$this->txnresponse['order_id'] , "status" => "K", "payment_amount" => $this->Amount , "order_total" => $this->order_info['details']);
                }
            }
            elseif($this->status == '0002'){
                if($this->Amount == $this->order_info['total']) {
                    $details = '******PAYMENT MAY BE SUCCESS ON billdesk.******'.$this->order_info['details'];
                }
                else{
                    $details = '******PAYMENT AMOUNT Rs. '.$this->Amount.' NOT SAME AS ORDER TOTAL AND PAYMENT MAY BE SUCCESS ON billdesk.******'.$this->order_info['details'];                    
                }
                return array("Success"=>1,"order_id"=>$this->txnresponse['order_id'] , "status" => "K", "details" => $details);
            }
            elseif(in_array($this->status, array('0001','NA','0399'))){
                return array("Success"=>1,"order_id"=>$this->txnresponse['order_id'] , "status" => "F");
            }
            else{
                return array("Success"=>0);
            }
        }
        else{
            return array("Success"=>1,"order_id"=>$this->txnresponse['order_id'] , "status" => "F");
        }       
    }
}
?>


