<?php

interface iWalletService{
	public function createWallet($userId);
	public function addAmount($userId,$walletId,$amt);
	public function debitAmount($userId,$walletId,$amt);
	public function getAmount($userId,$walletId);
	public function getWallet($userId);

}
