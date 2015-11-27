<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of giftCertificate
 *
 * @author shopclues
 */

class GiftCertificate{
        private $gcId;
        private $gcCode;
        private $amount;
        private $expiryDate;
        private $issueDate;
	
	private $paymentService;
	public function __construct(){
		$this->paymentService= new PaymentService();
	}       
 
        function getGcId() {
            return $this->gcId;
        }

        function getGcCode() {
            return $this->gcCode;
        }

        function getAmount() {
            return $this->amount;
        }

        function getExpiryDate() {
            return $this->expiryDate;
        }

        function getIssueDate() {
            return $this->issueDate;
        }

        function setGcId($gcId) {
            $this->gcId = $gcId;
        }

        function setGcCode($gcCode) {
            $this->gcCode = $gcCode;
        }

        function setAmount($amount) {
            $this->amount = $amount;
        }

        function setExpiryDate($expiryDate) {
            $this->expiryDate = $expiryDate;
        }

        function setIssueDate($issueDate) {
            $this->issueDate = $issueDate;
        }

	function isValid(){
		if(isset($this->expiryDate)){
			return ($this->expiryDate > date())?true:false;
		}	
	}
}
