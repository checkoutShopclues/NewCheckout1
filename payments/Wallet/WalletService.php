<?php

Class WalletService implements iWalletService{
	private static $walletServiceObj=null;
	private function __construct(){
		// initialize the wallet service
	}
	public static function getInstance(){
		if(is_null(self::$walletServiceObj)){
			self::$walletServiceObj= new WalletService();
		}
		return self::$walletServiceObj;
		
	}

	public function createWallet($userId){

	}

	public function addAmount($userId='',$walletId='',$amt){

	}

	public function debitAmount($userId='',$walletId='',$amt){

	}
	
	public function getAmount($userId,$walletId){

	}
		
	public function getWallet($userId){

	}
}
