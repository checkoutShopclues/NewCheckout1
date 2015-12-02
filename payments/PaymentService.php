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
			return db_get_row("select direcpayreferenceid, order_id, flag, other_details, amount, payment_gateway,txn_response, 3dstatus from clues_prepayment_details where order_id='".$order_id."'");
		}
	}
?>