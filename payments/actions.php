<?php
//$order_id = order creation
$order_info = fn_get_order_info($order_id);

$redirect = notify_wholesale_subscription($order_info['items'],$_SESSION['auth']['user_id'],Registry::get('config.ws_membership_type'),$edit_step);
if($redirect)
{
    return array(CONTROLLER_STATUS_REDIRECT, "checkout.checkout?edit_step=step_three");
}

list($is_processor_script, $processor_data) = fn_check_processor_script($order_info['payment_id'], '');

if ($is_processor_script) {

    set_time_limit(300);
            
	$_data = db_get_field("select data from ?:order_data where order_id = ?i and type = 'S'", $order_id);
	if(empty($_data)){          
	  db_query('INSERT INTO ?:order_data ?e', array('order_id' => $order_id, 'type' => 'S', 'data' => TIME));
	}
	else{                   
		db_query('UPDATE ?:order_data SET ?u WHERE order_id=?i and type=?s', array('order_id' => $order_id, 'type' => 'S', 'data' => TIME), $order_id, 'S');
	}
    $index_script = INDEX_SCRIPT;
    $mode = MODE;

    //$processor_data['processor_script'] will be class name of that particular PGW
    // $pgw_class = new $processor_data['processor_script']();
    include(DIR_PAYMENT_FILES . $processor_data['processor_script']);
    // return fn_finish_payment($order_id, $pp_response, $force_notification);

	$pgw = new PGW();
	$pgw->start_payment($order_info, $force_notification);
}
?>