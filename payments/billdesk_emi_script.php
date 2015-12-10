<?php

class billdesk_emi_script{
    //need to make a function to find order info details w.r.t. order_id
    private $redirecturl;	
    private $order_info;// get order info main fields including country by name
    private $payment_parameters;
    private $paymentService;
    private $status;
    private $checksum;
    private $txnresponse;
    private $return_checksum;
    private $request_param;
    
    public function __construct($order_info, $payment_parameters, $current_location){
        $this->order_info = $order_info;
        $this->payment_parameters = $payment_parameters;
        $this->paymentService= new PaymentService();
        $this->redirecturl = $current_location."?dispatch=payment_notification.return&payment=billdesk_emi_script&order_id=".$order_info['order_id'] ;
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
        
        $insert_array = array("direcpayreferenceid" => $transaction_id,"order_id" => $this->txnresponse['order_id'] , "flag" => $this->status, "other_details" => addslashes(serialize($response)),"amount" => $this->Amount , "payment_gateway" => "Billdeskemi");
        $this->paymentService->saveAfterPaymentData($insert_array);
        $this->checkResponse()?$this->callChangeStatus():'';
    }

    private function getPaymentDataForOrder(){	
        $this->getChecksum();
        $order_data = $this->getFormattedOrderData();
        $insert_array = array("order_id" => $this->order_info['order_id'],"amount" => $this->order_info['total'], "payment_gateway" => 'Billdeskemi', "order_data" => addslashes(serialize($order_data)));
        return $insert_array;
    }

    private function getChecksum(){
        $request_param  = $CheckSum  = $this->payment_parameters['payment_method']['params']['merchantid'].'|'.$this->order_info['order_id'].'|'.NA.'|'.$this->order_info['total'].'|'.$this->payment_parameters['payment_method']['params']['bank_id'].'|'.NA.'|'.NA.'|'.'INR'.'|'.$this->payment_parameters['payment_method']['params']['item_code'].'|'.'R'.'|'.'shopclues'.'|'.NA.'|'.NA.'|'.'F'.'|'.$this->order_info['email'].'|'.NA.'|'.NA.'|'.NA.'|'.NA.'|'.NA.'|'.NA.'|'.$this->redirecturl;
                
        $checksum       = hash_hmac('sha256',$CheckSum,$this->payment_parameters['payment_method']['params']['checksum_key'], false);
        $checksum 	= strtoupper($checksum);
        $this->request_param     = $request_param.'|'.$checksum;
        return;
    }

    private function getFormFields(){
        $form_fields = array(array(
                                'name'=>'MerchantID',
                                'value'=>$this->payment_parameters['payment_method']['params']['merchantid']
                                ),
                            array(
                                'name'=>'CustomerID',
                                'value'=>$this->order_info['order_id']
                                ),
                            array(
                                'name'=>'TxnAmount',
                                'value'=>$this->order_info['total']
                                ),
                            array(
                                'name'=>'BankID',
                                'value'=>$this->payment_parameters['payment_method']['params']['bank_id']
                                ),
                            array(
                                'name'=>'CurrencyType',
                                'value'=>'INR'
                                ),
                            array(
                                'name'=>'ItemCode',
                                'value'=>$this->payment_parameters['payment_method']['params']['item_code']
                                ),
                            array(
                                'name'=>'TypeField1',
                                'value'=>'R'
                                ),
                            array(
                                'name'=>'SecurityID',
                                'value'=>'shopclues'
                                ),
                            array(
                                'name'=>'TypeField2',
                                'value'=>'F'
                                ),
                            array(
                                'name'=>'AdditionalInfo1',
                                'value'=>$this->order_info['email']
                                ),
                            array(
                                'name'=>'RU',
                                'value'=>$this->redirecturl
                                ),
                            array(
                                'name'=>'msg',
                                'value'=>$this->request_param
                                )
                        );
        return $form_fields;
    }

    private function getFormattedOrderData(){
        $order_data = array();
        $order_data['request_message'] = $this->request_param;
        $order_data['url'] = $this->payment_parameters['payment_method']['params']['payment_url'];
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


