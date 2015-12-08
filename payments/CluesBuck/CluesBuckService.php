<?php

class CluesBuckService{
        const CHANGE_DUE_ORDER= 'O';
        const CHANGE_DUE_USE= 'I';
        const CHANGE_DUE_RMA= 'R';
        const CHANGE_DUE_ADDITION= 'A';
        const CHANGE_DUE_SUBTRACT= 'S';
        const CHANGE_DUE_ORDER_DELETE= 'D';
        const CHANGE_DUE_ORDER_PLACE= 'P';
        const CB_TYPE_DEFAULT_COLUMNS=array('id','name','code','expiry_days');
        const CB_TYPE_DEFAULT_SEARCH=array('status'=>'A');
        const CB_LOG_DEFAULT_COLUMNS=array('change_id','user_id','amount','reason');
	private $cluesBuckDao;
	private static $cluesBuckServiceObj=null;
	private function __construct(){
		$this->cluesBuckDao= new CluesBuckDao();
	}

	public static  function getInstance(){
		if(is_null(self::$cluesBuckServiceObj)){
			self::$cluesBuckServiceObj = new CluesBuckService();
		}
		return self::$cluesBuckServiceObj;
	}

        /**
         *  Get total clues bucks of user
         * 
         *  @param int $userId user id of the user
         *  @return int total clues bucks available in user account
         */
	public function getCbForUser($userId){
		return $this->cluesBuckDao->getCbForUser($userId);
	}
        
        /**
         *  Get total clues bucks which will expired in given time interval(days) 
         *  of user
         * 
         *  @param int $userId user id of the user
         *  @param int $interval days interval in which cb will expired
         *  @return int total clues bucks available in user account
         */
	public function getTotalExpiringCbForUser($userId,$interval){
		return $this->cluesBuckDao->getTotalExpiringCbForUser($userId,$interval);
	}

        /**
         *  Gat all the cb types rows with active status(default columns defined
         *  in CB_LOG_DEFAULT_COLUMNS constant)
         * 
         *  @param array $where [optional] associative array contains key as column name 
         *                      and value that to be search e.g. array("id"=>3,"code"=>'TRN')
         *  @param array $extraColumns [optional] array of the columns which extra required more than
         *                             the default columns
         *  @param string $extraWhereString [optional] string of where condition which can not just come in equals e.g. like or is not null
         *  @param string $groupBy [optional] group by section e.g. column_name desc
         *  @param string $orderBy [optional] order by section e.g. column_name desc
         *  @param string $limit [optional] limit section e.g. 0,10
         *  @return array array of result set which match the where condition
         */
        public function getCbTypes($where=array(), 
                                    $extraColumns=array(),
                                    $extraWhereString="",
                                    $groupBy="",
                                    $orderBy="",
                                    $limit=""){
                $where = array_merge($extraColumns,  self::CB_TYPE_DEFAULT_SEARCH);
                $columns = array_unique(array_merge($extraColumns, self::CB_TYPE_DEFAULT_COLUMNS));
                return $this->cluesBuckDao->getCbTypes($columns, 
                                                        $where, 
                                                        $extraWhereString, 
                                                        $groupBy, 
                                                        $orderBy, 
                                                        $limit);
        }
        
        /**
         *  Gat all the cb types rows with active status(default columns defined
         *  in CB_TYPE_DEFAULT_COLUMNS constant and default serch condition in CB_TYPE_DEFAULT_SEARCH constant)
         * 
         *  @param array $where [optional] associative array contains key as column name 
         *                      and value that to be search e.g. array("id"=>3,"code"=>'TRN')
         *  @param array $extraColumns [optional] array of the columns which extra required more than
         *                             the default columns
         *  @param string $extraWhereString [optional] string of where condition which can not just come in equals e.g. like or is not null
         *  @param string $groupBy [optional] group by section e.g. column_name desc
         *  @param string $orderBy [optional] order by section e.g. column_name desc
         *  @param string $limit [optional] limit section e.g. 0,10
         *  @return array array of result set which match the where condition
         */
        public function getCbLog($where=array(), 
                                 $extraColumns=array(),
                                 $extraWhereString="",
                                 $groupBy="",
                                 $orderBy="",
                                 $limit=""){
                $columns = array_unique(array_merge($extraColumns, self::CB_LOG_DEFAULT_COLUMNS));
                return $this->cluesBuckDao->getCbLog($columns, 
                                                     $where, 
                                                     $extraWhereString, 
                                                     $groupBy, 
                                                     $orderBy, 
                                                     $limit);
        }
        
        /**
         * add clues bucks
         * 
         * @param int $userId
         * @param int $amount
         * @param string $action
         * @param string $reason
         * @param date-time $expireTime [optional]
         * @param int $cluesBucksType
         * @param int $orderId [optional]
         * @param int $updateByUserId
         * @param int $addTimestamp
         */
	public function addCluesBuck($userId, 
                                    $amount, 
                                    $action = self::CHANGE_DUE_ADDITION, 
                                    $reason = '', 
                                    $expireTime='', 
                                    $cluesBucksType='1',
                                    $orderId='',
                                    $updateByUserId='',
                                    $addTimestamp=''){
            
                $this->insertCbHistory($userId,$amount,$reason,$updateByUserId);
                if($orderId){
                    $this->insertCbAmountOrderRelation($orderId,$amount);
                }
                $this->insertCb($userId, 
                                $amount, 
                                $reason, 
                                $action, 
                                $expireTime, 
                                $cluesBucksType,
                                $orderId,
                                $addTimestamp);
	}
	
	public function blockCB($user_id,$amt){
		$this->cluesBuckDao->blockCB($user_id,$amt);
	}
        
        /**
         * debit clues bucks
         * 
         * @param int $userId
         * @param int $amount
         * @param string $action
         * @param string $reason
         * @param date-time $expireTime [optional]
         * @param int $cluesBucksType
         * @param int $orderId [optional]
         * @param int $updateByUserId
         * @param int $addTimestamp
         */
	public function debitCluesBuck($userId, 
                                        $amount, 
                                        $action = self::CHANGE_DUE_SUBTRACT, 
                                        $reason = '', 
                                        $expireTime='', 
                                        $cluesBucksType='1',
                                        $orderId='',
                                        $updateByUserId='',
                                        $addTimestamp=''){
            
		$amount = (-1*$amount);
                $this->insertCbHistory($userId,$amount,$reason,$updateByUserId);
                if($orderId && !$this->existCbAmountOrderDedit($orderId)){
                    $this->insertCbAmountOrderRelation($orderId,$amount);
                }
                $this->updateExpiringCbDetails($amount, $userId, $orderId, $cluesBucksType);
                $this->insertCb($userId, 
                                $amount, 
                                $reason, 
                                $action, 
                                $expireTime, 
                                $cluesBucksType,
                                $orderId,
                                $addTimestamp);
	}
        
        /**
         * insert row in cb history log related whether cb added or deducted
         * 
         * @access private
         * @param type $userId
         * @param type $amount
         * @param type $reason
         * @param type $updateByUserId
         * @return boolean true on success else false
         */
        private function insertCbHistory($userId,$amount,$reason,$updateByUserId){
                return $this->cluesBuckDao->insertCbHistory($userId,$amount,$reason,$updateByUserId);
        }
        
        /**
         * insert cb in user account whether its added or deducted
         * 
         * @access private
         * @param type $userId
         * @param type $amount
         * @param type $reason
         * @param type $action
         * @param type $expireTime [optional]
         * @param type $cluesBucksType
         * @param type $orderId [optional]
         * @param type $addTimestamp
         */
        private function insertCb($userId, 
                                  $amount, 
                                  $reason = '', 
                                  $action, 
                                  $expireTime='', 
                                  $cluesBucksType='1',
                                  $orderId='',
                                  $addTimestamp=''){
                $insertArray = array(
				'user_id' => $userId,
				'amount' => $amount,
				'timestamp' => $addTimestamp,
				'action' => $action,
				'reason' => $reason,
				'type_id' => $cluesBucksType,
				'order_id' => $orderId
				);
                if(!empty($expireTime)){
                    $insertArray['expire_on'] = $expireTime;
                    $insertArray['balance'] = $amount;		
                }
                return $this->cluesBuckDao->insertCb($insertArray);
        }
        
        /**
         * this function calls when cb deducted to update expiry related details for previous cbs which will be going to expire
         *
         * @access private
         * @param int $amount total cb that will deducting
         * @param int $userId user_id  user
         * @param int $orderId [optional] order_id if deduction related to any order
         */
        private function updateExpiringCbDetails($amount, $userId, $orderId=""){
            $res= $this->getCbLog(array("user_id"=>$userId),
                                array("balance","order_payment_history"),  
                                "expire_on is not NULL and balance>0",
                                "",
                                "expire_on");
            $remainAmount = abs($amount);
            foreach($res as $result)
            {
                    if($remainAmount == 0)
                    {
                            break;
                    }
                    $balance = $result['balance'];
                    $am = 0;
                    if((int)$balance >= (int)$remainAmount)
                    {
                            $am = $remainAmount;
                            $balance = $balance - $remainAmount;
                            $remainAmount = 0;
                    }
                    else
                    {
                            $remainAmount = $remainAmount - $balance;
                            $am = $balance;
                            $balance = 0;
                    }
                    $orderPaymentHistory = $result['order_payment_history'];
                    if($orderId)
                    {
                            $sep = '';
                            if($orderPaymentHistory!='')
                            {
                                    $sep = '|';
                            }
                            $orderPaymentHistory = $orderPaymentHistory . $sep . $orderId . "," . $am. ",Alive";
                            $this->insertExpireCbOrderRelation($result['change_id'],$orderId,$am,'Alive');
                    }
                    $this->updateCb(array("balance"=>$balance,
                                          "order_payment_history"=>$orderPaymentHistory),
                                    array("change_id"=>$result['change_id']));
            }
        }
        
        /**
         * update clues bucks log rows
         * 
         * @param array $columnValue associative array of key as column and value as column value to be update
         * @param array $whereArray associative array for where condition 
         * @param string $extraWhere string for where condition which can not be comes in equal operator e.g. like, is null etc.
         * @return boolean true on success else false
         */
        public function updateCb($columnValue,$whereArray=array(),$extraWhere=""){
            return $this->cluesBuckDao->updateCb($columnValue,$whereArray,$extraWhere);
        }
        
        /**
         * insert a rows on deduction of clues bucks to relate cb change_id with order_id
         * 
         * @param int $changeId chnage_id of cb logs whose balance being deducted
         * @param int $orderId order_id in whose reference deduction of cb done
         * @param int $amount amount of cb
         * @param string $status status of transaction
         * @return boolean true on success else false
         */
        private function insertExpireCbOrderRelation($changeId,$orderId,$amount,$status='Alive'){
            return $this->cluesBuckDao->insertCbOrderRelation($changeId,$orderId,$amount,$status);
        }
        
        /**
         * check whether the for given order already deduction done or not
         * 
         * @param int $orderId order_id for which check deduction done or not
         * @return boolean true if entry exist else false
         */
        private function existCbAmountOrderDedit($orderId){
            $amount = $this->cluesBuckDao->totalCbAmountOrder($orderId);
            if($amount<0){
                return true;
            }
            return false;
        }
        
        /**
         * insert row for cb amount(debit or credit) and order_id relation
         * 
         * @param int $orderId order_id for which transaction done 
         * @param int $amount amount of cb(credit/debit cb amount)
         * @return bolean true on success else false
         */
        private function insertCbAmountOrderRelation($orderId,$amount){
            return $this->cluesBuckDao->insertCbAmountOrderRelation($orderId,$amount);
        }
}

