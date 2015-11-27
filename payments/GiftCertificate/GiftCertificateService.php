<?php

class GiftCertificateService implements IGiftCertificateService{
	private $giftCertificateDao;
	private static $giftCertificateObj=null;
	private function __construct(){
		$this->giftCertificateDao=new GiftCertificateDao();
		// initialize the gift certificate service 
	}

	public static function getInstance(){
		if(is_null(self::$giftCertificateObj)){
			self::$giftCertificateObj= new GiftCertificateService();
		}
		return self::$giftCertificateObj;	
	}

	public function getGiftCertificate($gcCode){

	}

	public function createGiftCertificate($userId,$amt){

	}

	public function debitAmount($gcCode,$amt){

	}		

	public function getAmount($gcCode){

	}

} 
