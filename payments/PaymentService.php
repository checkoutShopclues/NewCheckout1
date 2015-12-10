<?php

class PaymentService{
    public function getUserDetailsFromUserId($user_id){
            return;
    }

    public function savePrePaymentData($prepaymentData){
            db_query("INSERT INTO clues_order_pgw (". implode(",",array_keys($prepaymentData)) . ") VALUES ('" . implode("','",$prepaymentData) . "')");
    }

    public function saveAfterPaymentData($insert_array){
            db_query("INSERT INTO clues_prepayment_details (". implode(",",array_keys($insert_array)) . ") VALUES ('" . implode("','",$insert_array) . "')");
    }

    public function get_prepayment_details($order_id){
            return db_get_row("SELECT direcpayreferenceid, order_id, flag, other_details, amount, payment_gateway,txn_response, 3dstatus FROM clues_prepayment_details WHERE order_id ='".$order_id."'");
    }

    public function getOrderInfoById($order_id){

    }
    
    public function getFirstPriorityPaymentDataByPaymentOptId($payment_option_id){
        $sql = "SELECT cp.payment_option_id, cp.payment_gateway_id, cp.payment_option_pgw_id, cp.bank_code, cp.priority, cpo.payment_type_id
                FROM clues_payment_option_pgw cp
                JOIN clues_payment_options cpo ON cpo.payment_option_id = cp.payment_option_id
                WHERE cp.priority='1' and cp.status='A' and cp.payment_option_id='".$payment_option_id."'";
        $payment_details = db_get_row($sql);
        return $payment_details;
    }
    
    public function getEmiPaymentDataByPaymentOptId($payment_option_id){
        $sql = "select cpe.payment_option_id, cpe.payment_gateway_id, cpo.payment_type_id
                    from clues_payment_options_emi_pgw cpe
                    join clues_payment_options cpo on cpo.payment_option_id = cpe.payment_option_id
                    where cpe.status='A' and cpe.payment_option_id='".$payment_option_id."'";
        $payment_details = db_get_row($sql);
        return $payment_details;
    }
    
    public function getEmiCodeByEmiId($emi_id){
        $emi_code_sql = "select emi_code from clues_payment_options_emi_pgw where id = ".$emi_id;
        $emi_code = db_get_row($emi_code_sql);
    }
    
    public function getPgwPromotionDataByPaymentIdAndPromotionId($payment_id, $promotion_ids, $batch_time = ''){
        $sql = "select * from clues_pgw_promotion where payment_id='".$payment_id."' and promotion_id in (".$promotion_ids.")";
        if($promotion_ids == 0){
            $sql .= " and startdate <= '".$batch_time."' and enddate >= '".$batch_time."'";
        }
        $result = db_get_array($sql);
        return $result;        
    }
    
    public function getSourceByOrderId($order_id){
        return db_get_field("SELECT data from cscart_order_data where order_id=?i and type = 'Y'",$order_id);
    }
    
    public function update_net_debit_amount($amount,$order_id){
        db_query('UPDATE cscart_orders SET total = ?i where order_id = ?i',$amount, $order_id);
    }
    
    public function create_order_data($array){
        db_query('INSERT INTO ?:order_data ?e', $array);        
    }
    
    public function getChildOrder($order_id){
        return db_get_array("SELECT order_id,total FROM cscart_orders WHERE parent_order_id = ?i", $order_id);
    }
}
?>