<?php

interface IGiftCertificateService{
	public function getGiftCertificate($gcCode);
	public function createGiftCertificate($userId,$amt);
	public function debitAmount($gcCode,$amt);
	public function getAmount($gcCode);
	

}


