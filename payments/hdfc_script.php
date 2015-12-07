<?php

class hdfc_script{
    //need to make a function to find order info details w.r.t. order_id
    private $order_info;// get order info main fields including country by name
    private $payment_parameters;
    private $paymentService;

    public function __construct($order_info, $payment_parameters, $current_location){
        $this->order_info = $order_info;
        $this->payment_parameters = $payment_parameters;
        $this->paymentService = new PaymentService();
    }

    public function getDataForRedirection(){
        $form_name = "frmhdfc";
        $target_url = "https://".Registry::get('config.https_host').Registry::get('config.https_path')."/payments/hdfc/SendPerformREQ.php";
        $form_fields = $this->getFormFields();
        $form_array = array("name" => $form_name, "method" => "post", "action" => $target_url);
        return array("form_array" => $form_array, "form_fields" => $form_fields);
    }

    public function paymentReturn($redirect_response){
        $this->txnresponse    = $redirect_response;
        $this->order_info     = fn_get_order_info($this->txnresponse['order_id'], true);//fn_get_order_info - replace this function with own made function
        $response = $this->paymentService->get_prepayment_details($this->txnresponse['order_id']);
        $this->checkResponse($response)?$this->callChangeStatus($response):'';
    }

    private function getFormFields(){
        $form_fields = array(array(
                            'name'=>'MTrackid',
                            'value'=>$this->order_info['order_id']
                        ),
                        array(
                            'name'=>'MAmount',
                            'value'=>$this->order_info['total']
                        )
                    );
        return $form_fields;
    }

    private function checkResponse(){		
        if (fn_check_payment_script('hdfc_script.php', $this->txnresponse['orderid'])){
            return true;
        }
        else{
            return false;
        }
    }

    private function callChangeStatus($response){
        if($response['flag'] != 'CAPTURED' || $response['flag'] != 'APPROVED'){
            Analog::log($response['flag'], json_encode($response) , payment, "WEB",Registry::get('config.LOG_LEVELS.INFO')); 
        }
        if($response['flag'] == 'CAPTURED' || $response['flag'] == 'APPROVED'){
            if( $response['amount'] == $this->order_info['total']) {
                    return array("Success"=>1,"order_id"=>$this->order_info['orderid'] , "status" => "P");
            }else{
                    return array("Success"=>1,"order_id"=>$this->txnresponse['orderid'] , "status" => "K", "payment_amount" => $response['amount'] , "order_total" => $this->order_info['details']);
            }
        }
        else{
            return array("Success"=>1,"order_id"=>$this->txnresponse['orderid'] , "status" => "F");
        }
    }
}
?>


