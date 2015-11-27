<?php

class CluesBuckService{
	private $cluesBuckDao;
	private static $cluesBuckServiceObj=null;
	private function __construct(){
		$this->cluesBuckDao= new CluesBuckDao();
	}

	public static  function getInstance(){
		if(is_null(self::$cluesBuckServiceObj)){
			self::$cluesBuckServiceObj= new CluesBuckService();
		}
		return self::$cluesBuckServiceObj;
	}


	public function getCbForUser($userId){
		return $this->cluesBuckDao->getCbForUser($userId);
	}

	public function addCluesBuck($user_id,$amt){
		$this->cluesBuckDao->addCluesBuck($user_id,$amt);
	}
	
	public function blockCB($user_id,$amt){
		$this->cluesBuckDao->blockCB($user_id,$amt);
	}

	public function debitCluesBuck($user_id,$amt){
		$this->cluesBuckDao->debitCluesBuck($user_id,$amt);
	}


}

