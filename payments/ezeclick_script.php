<?php
// if (!defined('AREA')) {die('Access denied');}

    include("ezeclick/ClientAPI.php");
    class ezeclick_script{

        private $redirecturl;
        private $redirect_response;
        private $order_info;
        private $paymentReqMsg;

        public function __construct($order_info, $payment_parameters, $current_location){
            $this->order_info = $order_info;
            $this->payment_parameters = $payment_parameters;
            $this->paymentService= new PaymentService();
            $this->redirecturl  = $current_location."?dispatch=payment_notification.return&payment=ezeclick_script&order_id=".$order_info['order_id'] ;
        }

        public function redirect(){
            // $form_name = "frm_mobikwik";
            $prepaymentData = $this->pre_redirect();
            $this->paymentService->savePrePaymentData($prepaymentData);
            $target_url = $this->payment_parameters['payment_method']['params']['url'];
            $form_fields = $this->getFormFields();
            return array($target_url,$form_fields);
        }

        private function pre_redirect(){
            $order_data = $this->getFormattedOrderData();
            return array("order_id" => $this->order_info['order_id'], "amount" => $this->order_info['total'], "payment_gateway" => "EZECLICK", "order_data" => addslashes(serialize($order_data)));
        }
        
        private function getFormattedOrderData(){
            $order_data = array();
            $order_data['merchant_id'] = $this->payment_parameters['payment_method']['params']['merchantid'];
            $order_data['order_id']    = $this->order_info['order_id'];
            $order_data['other']       = $this->order_info;
            $paymentReqMsg = $this->getpaymentReqMsg();
            $order_data['requestparameter'] = $paymentReqMsg;
            return $order_data;
        }

        private function getpaymentReqMsg(){
            $ClientAPI = new ClientAPI();

            $paymentReqMsg = $ClientAPI->generateDigitalOrder($this->payment_parameters['payment_method']['params']['merchantid'], $this->order_info['order_id'], (int)($this->order_info['total']*100), $this->redirecturl, $this->payment_parameters['payment_method']['params']['enc_key']);

            $this->paymentReqMsg = $paymentReqMsg;
            return $paymentReqMsg;
        }

        private function getFormFields(){
            $form_fields = array("merchantRequest" => $this->paymentReqMsg,"MID"=>$this->payment_parameters['payment_method']['params']['merchantid']);
            return $form_fields;
        }

        public function return($redirect_response){
            $this->redirect_response = $redirect_response;
            $this->order_info = fn_get_order_info($this->redirect_response['order_id'], true);

            $result = $this->getResponseData();            
        
            $insert_array = array("direcpayreferenceid" => $result['transaction_id'], "order_id" => $redirect_response['order_id'], "flag" => $result['status'], "other_details" => addslashes(serialize($result['response_array'])), "amount" => $result['captured_amount'], "payment_gateway" => "EZECLICK");
            $this->paymentService->saveAfterPaymentData($insert_array);
            $this->checkResponse($result);
        }

        private function getResponseData(){
            $ClientAPI       = new ClientAPI(); 
            $responseDTO     = new ResponseDTO(); 
            $responseDTO     = $ClientAPI->getDigitalReceipt($this->redirect_response['merchantResponse'], $this->payment_parameters['payment_method']['params']['enc_key']);
            $captured_amount = $responseDTO->getVpc_CapturedAmount();
            $transaction_id  = $responseDTO->getQp_TransRefNo();
            $status          = $responseDTO->getQp_PaymentStatus();
            $response_array  = (array)$responseDTO;
            return array("captured_amount" => $captured_amount,"response_array" => $response_array,"status" => $status);
        }

        private function checkResponse($result){
            if (fn_check_payment_script('ezeclick_script.php', $this->redirect_response['order_id'])){
                $this->callChangeStatus($result);
            }              
        }

        private function callChangeStatus($result){
            if ($result['status'] == 'S') {
                if($this->order_info['total'] == ($result['captured_amount']/100)){
                    return array("Success"=>1,"order_id"=>$this->redirect_response['order_id'] , "status" => "P");
                }else{
                    return array("Success"=>1, "order_id"=>$this->redirect_response['order_id'] , "status" => "K", "payment_amount" => $result['captured_amount'] , "order_total" => $this->order_info['details']);
                }
            }else{
                /*if authrization response is not S means authrizaiton is failed and its a fail order.*/
                return array("Success"=>1, "order_id"=>$this->redirect_response['order_id'], "status" => "F");                    
            }
        }
    }
?>