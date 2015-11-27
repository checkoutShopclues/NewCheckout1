<?php

class Wallet{
	private $userId;
	private $walletId;
	private $amount;
	
	public function getAmount(){
		return $this->amount;
	}

	public function setAmount($amount){
		$this->amount= $amount;
	}	

	public function getWalletId(){
		return $this->getWalletId;
	}
}
