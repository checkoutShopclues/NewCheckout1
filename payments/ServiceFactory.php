<?php

class ServiceFactory{
	const GIFT_CERTIFICATE=1;
	const CLUES_BUCK=2;
	const WALLET=3;

	public static function getService($type){
		switch($type){
			case self::GIFT_CERTIFICATE:
				return new GiftCertificateService();
			case self::CLUES_BUCK:
				return new CluesBuckService();
			case self::WALLET:
				return new walletService();:	
		
		}
	}
		
}

