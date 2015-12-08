<?php
class CluesBuckDao{
    /**
     *  Get total clues bucks of user
     * 
     *  @param int $userId user id of the user
     *  @return int total clues bucks available in user account
     */
    public function getCbForUser($userId){
            $query = "SELECT sum(amount) as amount FROM cscart_reward_point_changes WHERE user_id = ".$userId . " limit 1";
            $totalAmount = db_get_field($query);
            return $totalAmount;
    }
    
    /**
     *  Get total clues bucks which will expired in given time interval(days) of user
     * 
     *  @param int $userId user id of the user
     *  @param int $interval days interval in which cb will expired
     *  @return int total clues bucks available in user account
     */
    public function getTotalExpiringCbForUser($userId,$interval){
            $query = "select sum(balance) as amount from cscart_reward_point_changes where user_id=".$userId." and expire_on>=CURDATE() && expire_on<=DATE_ADD(CURDATE(),INTERVAL ".$interval." DAY)";
            $totalCbExpired= db_get_field($query);
            return $totalCbExpired;
    }
    
    /**
    *  Gat all the cb types rows with given serch condition
    * 
    *  @param array $columns array of the columns to be select in result set
    *  @param array $where associative array contains key as column name 
    *                      and value that to be search e.g. array("id"=>3,"code"=>'TRN')
    *  @param string $extraWhereString [optional] string of where condition which can not just come in equals e.g. like or is not null
    *  @param string $groupBy [optional] group by section e.g. column_name desc
    *  @param string $orderBy [optional] order by section e.g. column_name desc
    *  @param string $limit [optional] limit section e.g. 0,10
    *  @return array array of result set which match the where condition
    */
    public function getCbTypes($columns, $whereArray, $extraWhereString="",$groupBy="", $orderBy="",$limit=""){
            return $this->getSelect("clues_bucks_type",$columns, $whereArray, $extraWhereString,$groupBy, $orderBy,$limit);
    }
    
    /**
     * insert a row in cb table(cscart_reward_point_changes)
     * 
     * @param array $array associate array with key as column name and value as column value 
     */
    public function insertCb($array){
            return db_query('INSERT INTO ?:reward_point_changes ?e', $array);
    }
    
    /**
     * insert a row in cb history table(clues_bucks_history)
     * 
     * @param int $amount amount that will credit or debit, in case of debit it will be negative value 
     * @param string $reason reason of cb changes  
     * @param int $updatedByUserId user_id of the user who had made this changes
     * @param int $userId user_id of that user in whose account cb changes
     */
    public function insertCbHistory($userId,$amount,$reason,$updatedByUserId){
            $updateTime = date('Y-m-d h:i:s');
            $sql = "insert into clues_bucks_history (amount, reason, update_by, update_time, to_user) value ('".$amount."','".$reason."','".$updatedByUserId."','".$updateTime."','".$userId."')";
            return db_query($sql);
    }
    
    public function blockCB($user_id,$amt){
        
    }
    
    /**
    *  Gat all the cb types rows with given serch condition
    * 
    *  @param array $columns array of the columns to be select in result set
    *  @param array $where associative array contains key as column name 
    *                      and value that to be search e.g. array("change_id"=>3)
    *  @param string $extraWhereString [optional] string of where condition which can not just come in equals e.g. like or is not null
    *  @param string $groupBy [optional] group by section e.g. column_name desc
    *  @param string $orderBy [optional] order by section e.g. column_name desc
    *  @param string $limit [optional] limit section e.g. 0,10
    *  @return array array of result set which match the where condition
    */
    public function getCbLog($columns, $whereArray, $extraWhereString="",$groupBy="",$orderBy="",$limit=""){
            return $this->getSelect("cscart_reward_point_changes", $columns, $whereArray, $extraWhereString,$groupBy, $orderBy, $limit);
    }
    
    /**
    *  update all the cb rows with given serch condition
    * 
    *  @param array $columns array of the columns to be select in result set
    *  @param array $where associative array contains key as column name 
    *                      and value that to be search e.g. array("change_id"=>3)
    *  @param string $extraWhereString [optional] string of where condition which can not just come in equals e.g. like or is not null
    *  @return boolean true if success else false on failed
    */
    public function updateCb($columns, $whereArray, $extraWhereString=""){
            return $this->updateSql("cscart_reward_point_changes", $columns, $whereArray, $extraWhereString);
    }
    
    /**
    *  Gat all the rows from the specified table with given serch condition
    * 
    *  @param string $table 
    *  @param array $columns array of the columns to be select in result set
    *  @param array $where associative array contains key as column name 
    *                      and value that to be search e.g. array("change_id"=>3)
    *  @param string $extraWhereString [optional] string of where condition which can not just come in equals e.g. like or is not null
    *  @param string $groupBy [optional] group by section e.g. column_name desc
    *  @param string $orderBy [optional] order by section e.g. column_name desc
    *  @param string $limit [optional] limit section e.g. 0,10
    *  @return array array of result set 
    */
    public function getSelect($table, $columns, $whereArray, $extraWhereString="", $groupBy="", $orderBy="", $limit=""){
        if(!empty($columns)){
            $whereCondition = '';
            if(!empty($whereArray) && is_array($whereArray)){
                foreach($whereArray as $col=>$val){
                    $whereCondition.=($whereCondition=="")?$col."='".$val."'":" and ".$col."='".$val."'";
                }
            }
            $whereCondition .= !empty($extraWhereString)?(($whereCondition!="")?" and ".$extraWhereString:$extraWhereString):"";
            $whereCondition = ($whereCondition!="")?" where ".$whereCondition:"";
            $orderBy=($orderBy!="")?" order by ".$orderBy:"";
            $limit = ($limit!="")?" limit ".$limit:"";
            $groupBy = ($groupBy!="")?" group by ".$groupBy:"";
            $sql = "select ".implode(',',$columns)." from ".$table.$whereCondition.$groupBy.$orderBy.$limit;
            return db_get_array($sql);
        }
    }
    
    /**
    *  update all the rows with given serch condition in specified table
    * 
    *  @param string $table table in which update to be perform
    *  @param array $columns array of the columns to be select in result set
    *  @param array $where associative array contains key as column name 
    *                      and value that to be search e.g. array("change_id"=>3)
    *  @param string $extraWhereString [optional] string of where condition which can not just come in equals e.g. like or is not null
    *  @return boolean true if success else false on failed
    */
    public function updateSql($table, $columns, $whereArray, $extraWhereString=""){
        if(!empty($columns) && is_array($columns)){
            $columnValues = '';
            foreach($columns as $col=>$val){
                $columnValues.=($columnValues=="")?$col."='".$val."'":", ".$col."='".$val."'";
            }
           
            $whereCondition = '';
            if(!empty($whereArray) && is_array($whereArray)){
                foreach($whereArray as $col=>$val){
                    $whereCondition.=($whereCondition=="")?$col."='".$val."'":" and ".$col."='".$val."'";
                }
            }
            $whereCondition .= !empty($extraWhereString)?(($whereCondition!="")?" and ".$extraWhereString:$extraWhereString):"";
            $whereCondition = ($whereCondition!="")?" where ".$whereCondition:"";
            $sql = "update ".$table." set ".$columnValues." ".$whereCondition;
            return db_query($sql);
        }
    }
    
    /**
     * insert Clues Bucks change_id, order_id & clues bucks amount for making relation between them, this action done because when 
     * clues bucks deduction update also done on the previous cb(which going to be expire) change_id for the particular balance used from that deduction
     *  
     * @param int $changeId change_id from which deduction done
     * @param int $orderId order_id for which deduction done
     * @param int $amount amount which deducted from the particular row(change_id)
     * @param string $status status of transaction
     * @return boolean true on success else false
     */
    public function insertCbOrderRelation($changeId,$orderId,$amount,$status='Alive'){
        $sql = "insert into clues_expiry_clues_bucks_order_relation set change_id='".$changeId."',
                                                                        order_id='".$orderId."',
                                                                        amount='".$amount."',
                                                                        status='".$status."'";
        return db_query($sql);
    }
    
    /**
     * get total clues bucks amount which already debit/credit for that order_id
     * 
     * @param int $orderId order_id for which total amount search
     * @return int total order used for that order_id
     */
    public function totalCbAmountOrder($orderId){
        $sql = "select sum(amount) as amt from clues_bucks_order_relation where order_id='".$orderId."'";
        $ret = db_get_row($sql);
        return $ret['amt'];
    }
    
    /**
     * insert rows for relation of clues bucks amount & order_id
     * 
     * @param int $orderId
     * @param int $amount
     * @return boolean true on success else false
     */
    public function insertCbAmountOrderRelation($orderId,$amount){
        $sql = "insert into clues_bucks_order_relation set order_id='".$orderId."',amount='".$amount."'";
	return db_query($sql);
    }
}