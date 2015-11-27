<?php

class Payment{
	private $userId;
	private $cart;
	private $giftCertificate=null;
	private $promotions= array();
	private $cb=0;
	private $walletAmount=0;
	private $totalDiscount=0;
	private $gcAmount=0;
	private $cluesBuckService;
	private $giftCertificateService;
	private $walletServic;

	private $paymentMode;
	private $paymentOption;
	private $finalPrice;

	public function __construct($cart,$userId=''){
		// this cart will contain all the products with all the promotions

		$this->cart= $cart;
		// we need userId to fetch user CB and user wallet
		$this->userId= $userId;
		// All this will be fetched from Factory
		$this->cluesBuckService= ServiceFactory::getService(ServiceFactory::CLUES_BUCK);
		$this->giftCertificateService= ServiceFactory::getService(ServiceFactory::GIFT_CERTIFICATE);
		$this->walletService = ServiceFactory::getService(ServiceFactory::WALLET);

		// getTotalAmount() should return the amount after the product discounts	
		$this->finalPrice= $cart->getTotalAmount();
		$this->setTotalDiscount();

	}

	public function setTotalDiscount(){
		$this->totalDiscount=0;
		$products= $cart->getProducts();
		foreach($product as $product){
			$this->totalDiscount+=$product->getProductDiscount();
		}
	}

	public function applyPromotion($promotion){
		if($this->promotionApplicable($promotion)){
			array_push($this->promotions,$promotion);
			$discount=$this->calculateDiscount($promotion);
			$this->totalDiscount+=$discount;
			$this->finalPrice-=($this->finalPrice > $discount)$discount:$this->finalPrice;
		}
	}

	public function removePromotion($promotion){
		$this->removePromotionFromList($promotion);
		$discount=$this->calculateDiscount($promotion);
		$this->totalDiscount-=$discount;	
		$this->finalPrice+=$discount;	
	}

	public function  applyGC($gcCode){
		$gc= $this->giftCertificateService->getGiftCertificate($gcCode);
		if($gc->isValid()){
			$this->giftCertificate= $gc;
			$this->gcAmount=($this->finalPrice >0)?($this->giftCertificate->getAmount()> $this->finalPrice)?
				$this->finalPrice:($this->finalPrice-$this->giftCertificate->getAmount()):0;
			$this->finalPrice-= $this->gcAmount;
		}
	}

	public function removeGC(){
		$this->giftCertificate=null;
		$this->finalPrice+=$this->gcAmount;
		$this->gcAmount=0;
	}

	public function applyCB($cb){
		if($this->ifUserHasCB($cb,$user_id)){
			$this->cb= ($this->finalPrice >0)?($cb> $this->finalPrice)?$this->finalPrice:($this->finalPrice-$cb):0;
			$this->finalPrice-=$this->cb;
		}
	}

	public function removeCB(){
		$this->finalPrice+=$this->cb;
		$this->cb=0;
	}

	public function applyWallet($amt){
		if($this->ifUserHasWalletAmt($amt,$userId)){
			$this->walletAmount= ($this->finalPrice >0)?($amt > $this->finalPrice)?$this->finalPrice:($this->finalPrice-$amt):0;
			$this->finalPrice-= $walletAmount;
		}
	}	

	public function removeWallet(){
		$this->finalPrice+=$this->walletAmount;
		$this->walletAmount=0;
	}

	private function calculateDiscount($promo){
		$type=$promo->getType();
		switch ($type){
			case PROMOTIONS::CATEGORY:
				return $this->getCategoryDiscount($promo);
			case PROMOTIONS::CART_VALUE:
				return $this->getCartValueDiscount($promo);

				// all other types of promotions
		}

	}
	private function getCategoryDiscount($promo){
		// get category discunt
	}

	private function getCartValueDiscount($promo){
		// get cart value discount
	}

	public function getGiftCerificateAmount(){
		return !is_null($this->giftCertificate)?$this->giftCertificate->getAmount():0;
	}

	public function getCbAmount(){
		return $this->cb;
	}


	public function getWalletAmount(){
		return $this->walletAmount;
	}

	public function getTotalDiscount(){
		return $this->getTotalDiscount;
	}

	public function getTotalPrice(){
		return  $this->cart->getTotalAmount();
	}

	public function getFinalPrice(){
		return $this->finalPrice;
	}

	private function ifUserHasCB($cb){
		$totalCb= $this->cluesBuckService->getCbForUser($this->userId);
		return ($totalCb > $cb)?true:false;
	}

	private function ifUserHasWalletAmt($amt){
		$wallet =$this->walletService->getWallet($this->userId);
		return ($wallet->getAmount() > $amt)?true:false;	
	}

	public function setPaymentMode($mode){
		$this->paymentMode= $mode;
	}

	public function setPaymentOption($paymentOption){
		$this->paymentOption=$paymentOption;
	}

	public function getPaymentMode(){
		return $this->paymentMode;
	}

	public function getPaymentOption(){
		return $this->paymentOption;
	}

	public function getPGWAmount(){
		return $this->getFinalPrice();
	}


	public function debitMoneyFromGC(){
		$this->giftCertificateService->debitAmount($this->giftCertificate->getGcCode(),$this->gcAmount);					
	}

	public function debitMoneyFromWallet(){
		$this->walletService->debitAmount($userId,'',$this->walletAmount);
	}

	public function debitCb(){
		$this->cluesBuckService->debitCluesBuck($user_id,$this->cb);
	}

	private function removePromotionFromList($promo){
		foreach($this->promotions as $key=>$promotion){
			if($promo->getId()== $promotion->getId()){
				unset($this->promotions[$key]);
			}
		}

	}

	public function validateFinalPriceBeforeCheckout(){
		$this->cart->calculate();
		$this->recalculatePromotions();
		$this->recalculatCB();
		$this->recalculateWallet();
		$this->recalculateGC();
	}

	private function recalculatePromotions(){
		$this->finalPrice+=$this->totalDiscount;
		$this->setTotalDiscount();
		foreach($this->promotions as $key=>$promotion){
			if(!$this->promotionApplicable($promotion)){
				unset($this->promotions[$key]);
			}
			else{
				$discount=$this->calculateDiscount($promotion);
				$this->totalDiscount+=$discount;
			}
		}
		$this->finalPrice-=$this->totalDiscount;	
	}

	private function recalculatCB(){
		$cb=$this->ifUserHasCB($this->cb)?$this->cb:0;
		if($cb==0){
			$this->finalPrice+=$this->cb;
			$this->cb=$cb;
		}
	}

	private function recalculateWallet(){
		$walletAmt=$this->ifUserHasWalletAmt($this->walletAmount,$userId)?$this->walletAmount:0;
		if($walletAmt==0){
			$this->finalPrice+=$this->walletAmount;
			$this->walletAmount=walletAmt;
		}
	}


	private function recalculateGC(){
		if(!is_null($this->giftCertificate)){
			$gcCode= $this->giftCertificate->getGcCode();
			if($this->gcCode!=''){
				// reset the final price and gc amount and again set it
				$this->finalPrice+=$this->gcAmount;
				$this->gcAmount=0;


				$this->giftCertificate=$this->giftCertificateService->getGiftCertificate($gcCode);
				$this->gcAmount=($this->finalPrice >0)?($this->giftCertificate->getAmount()> $this->finalPrice)?$this->finalPrice:($this->finalPrice-$this->giftCertificate->getAmount()):0;
				$this->finalPrice-= $this->gcAmount;

			}	
		}
	}

}
