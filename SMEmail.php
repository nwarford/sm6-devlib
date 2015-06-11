<?php
	
/*------------------------------------------------------------------------------------------

File: SMEmail.php 
Summary: Supports creating an email and sending it.
Version: 6.0

PRE-RELEASE

Copyright (C) 2014 Sitemason, Inc. All Rights Reserved. 
 
------------------------------------------------------------------------------------------*/

	include ('PHPMailer/class.phpmailer.php');

	class SMEmail extends SMObject {

		protected $attachmentPath;
		protected $bccRecipients;
		protected $body;
		protected $ccRecipients;
		protected $debugLevel;
		protected $language;
		protected $recipients;
		protected $sender;
		protected $senderName;
		protected $subject;
		
		protected $smtpAccounts;
		protected $phpMailer;
		
		
				
		function __construct() {
			$this->smtpAccounts = array();
			
			// TODO: get this data from Sitemason
			/*
			$smtpAccount = array(
				'server' => 'smtp.emailsrvr.com',
				'username' => 'user@domain.tld',
				'password' => 'password'
			);
			
			$this->addSMTPAccount($smtpAccount);
			*/
			
			
			$this->language = 'en';
			$this->isHTML = false;
			$this->recipients = array();
			$this->debugLevel = 0;
		}
		
//! 
//! Basic get/set methods
//!------------------------------

		public function getAttachmentPath() { return $this->attachmentPath; }
		public function setAttachmentPath($attachmentPath) { $this->attachmentPath = $attachmentPath; }

		public function getBCCRecipients() { return $this->bccRecipients; }
		public function addBCCRecipient($recipient) {
			array_push($this->bccRecipients, $recipient);
		}
		public function setBccRecipients(array $recipients) { $this->bccRecipients = $recipients; }

		public function getBody() { return $this->body; }
		public function setBody($body) { $this->body = $body; }

		public function getCCRecipients() { return $this->ccRecipients; }
		public function addCCRecipient($recipient) {
			array_push($this->ccRecipients, $recipient);
		}
		public function setCCRecipients(array $recipients) { $this->ccRecipients = $recipients; }

		public function getDebugLevel() { return $this->debugLevel; }
		public function setDebugLevel($debugLevel) { $this->debugLevel = $debugLevel; }
		
		public function getError() { return $this->phpMailer->ErrorInfo; }
		
		public function isHTML() { 
			if ($this->isHTML) { return true; }
			else { return false; }
		}
		public function setIsHTML($isHTML) { $this->isHTML = $isHTML; }
		
		public function getLanguage() { return $this->language; }
		public function setLanguage($language) { $this->language = $language; }

		public function getRecipients() { return $this->recipients; }
		public function addRecipient($recipient) {
			array_push($this->recipients, $recipient);
		}
		public function setRecipients(array $recipients) { $this->recipients = $recipients; }
		
		public function getSender() { return $this->sender; }
		public function setSender($sender) { $this->sender = $sender; }
			
		public function getSenderName() { return $this->senderName; }
		public function setSenderName($senderName) { $this->senderName = $senderName; }

		/**
			Add an SMTP Account to the 
		*/
		public function addSMTPAccount($smtpAccountData) {
			
			if ($smtpAccountData['username'] && $smtpAccountData['password'] && $smtpAccountData['server']) {
				$accounts = $this->getSMTPAccounts();
				array_push($accounts, $smtpAccountData);
				$this->setSMTPAccounts($accounts);
				
				return true;
			}
			else {
				return false;
			}
		}
		
		public function getSMTPAccounts() { return $this->smtpAccounts; }
		protected function setSMTPAccounts($smtpAccounts) { $this->smtpAccounts = $smtpAccounts; }
		

		public function getSubject() { return $this->subject; }
		public function setSubject($subject) { $this->subject = $subject; }

//! 
//! Email-sending methods
//!------------------------------		
		
		protected function selectRandomSMTPAccount() {
			$accounts = $this->getSMTPAccounts();
			
			$size = sizeof($accounts);
			$random = rand(0,$size-1);
			$account = $accounts[$random];

			return $account;
		}
		

		public function send() {
			$account = $this->selectRandomSMTPAccount();
			
			if ($account && count($this->getRecipients()) > 0) {
				$this->phpMailer = new PHPMailer();

				// Recipients
				foreach ($this->getRecipients() as $email) {
					$this->phpMailer->AddAddress($email);
				}
				
				// CC Recipients
				if (count($this->getCCRecipients()) > 0) {
					foreach ($this->getCCRecipients() as $email) {
						$this->phpMailer->AddCC($email);
					}
				}
				
				// BCC Recipients
				if (count($this->getBCCRecipients()) > 0) {
					foreach ($this->getBCCRecipients() as $email) {
						$this->phpMailer->AddBCC($email);
					}
				}
				
				$this->phpMailer->Host		= $account['server'];
				$this->phpMailer->SMTPAuth	= 'true';
				$this->phpMailer->SMTPDebug  = $this->getDebugLevel();
				$this->phpMailer->Mailer	= 'smtp';
				$this->phpMailer->Username	= $account['username'];
				$this->phpMailer->Password	= $account['password'];
		
				if ($this->getAttachmentPath()) {
					$this->phpMailer->AddAttachment($this->getAttachmentPath(), $name = "", $encoding = "base64", $type = "application/octet-stream");
				}
		
				$this->phpMailer->From = $this->getSender();
				$this->phpMailer->FromName = $this->getSenderName();
				$this->phpMailer->IsHTML($this->isHTML());
				$this->phpMailer->Subject = $this->getSubject();
				$this->phpMailer->Body = $this->getBody();
				$this->phpMailer->SetLanguage($this->getLanguage());
				
				$test = $this->phpMailer->Send();
				$this->phpMailer->ClearAddresses();
				$this->phpMailer->ClearAttachments();
				
		
				if ($test) { 
					return true; 
				}
				else { 
					return false; 
				}
			}
		}
	}
?>