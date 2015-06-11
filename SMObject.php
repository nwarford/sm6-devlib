<?php

/*------------------------------------------------------------------------------------------

File: SMObject.php 
Summary: The SMObject class definition
Version: 6.0
	  
The SMObject class contains the properties and methods common to all objects in Sitemason®.
  
Copyright (C) 2014 Sitemason, Inc. All Rights Reserved. 
 
------------------------------------------------------------------------------------------*/


	abstract class SMObject {
		protected $id;
		protected $creationTime;	// SMTime
		protected $encodedId;
		protected $isCurrentlyDisplayed;
		protected $lastModifiedTime;
		protected $navEncodedId;
		protected $title;
		protected $titleNormalized;
		protected $encodedTitle;
		
		protected $debugJavascript;


//! 
//! Basic get/set methods
//!------------------------------
		
		
		public function getCreationTime() { 
			$creationTime = $this->creationTime;
			if (!$creationTime) {
				$creationTime = new DateTime();
			}
			
			return $creationTime;
		}
		public function setCreationTime(DateTime $time) { $this->creationTime = $time; }
		
		public function getCreationTimestamp() {
			$time = $this->getCreationTime();
			
			print_r($time);
			
			$timezoneOffset = $time->getOffset() / 60 / 60;
			return $time->format('Y-m-d H:i:s.u'. $timezoneOffset);
		}
		
		public function setCreationTimestamp($timestamp) {
			$this->creationTime = new DateTime($timestamp);
		}
		
		public function getEncodedID() { return $this->encodedId; }
		public function setEncodedID($encodedId) { $this->encodedId = $encodedId; }
		
		public function getEncodedTitle() { return $this->encodedTitle; }
		public function setEncodedTitle($encodedTitle) { $this->encodedTitle = $encodedTitle; }
		
		public function getID() { return $this->id; }
		protected function setID($id) { $this->id = $id; }
		
		public function isCurrentlyDisplayed() {
			if ($this->isCurrentlyDisplayed) {
				return true;
			}
			else {
				return false;
			}
		}
		
		public function setIsCurrentlyDisplayed($bool) {
			$this->isCurrentlyDisplayed = $bool;
		}
		
		
		public function getLastModifiedTime() { 
			$lastModifiedTime = $this->lastModifiedTime;
			if (!$lastModifiedTime) {
				$lastModifiedTime = new DateTime();
			}
			
			return $lastModifiedTime;
		}
		public function setLastModifiedTime(DateTime $time) { $this->lastModifiedTime = $time; }

		public function getLastModifiedTimestamp() {
			$time = $this->getLastModifiedTime();
			$timezoneOffset = $time->getOffset() / 60 / 60;
			return $time->format('Y-m-d H:i:s.u'. $timezoneOffset);
		}
		public function setLastModifiedTimestamp($timestamp) {
			$this->lastModifiedTime = new DateTime($timestamp);
		}
		
		
		public function getNavEncodedID() {
			return $this->navEncodedId;
		}
		
		public function getTitle() { return $this->title; }
		public function setTitle($title) { $this->title = $title; }

		/**
			Normalized titles replace spaces with _ and strip out any non-alpha-numeric characters.
		*/
		public function getTitleNormalized() { 
			$titleNormalized = $this->titleNormalized;
			if (!$titleNormalized) {
				// TODO: finish this?
			}
			
			return $titleNormalized;
		}
		public function setTitleNormalized($titleNormalized) { $this->titleNormalized = $titleNormalized; }
		
		
//! 
//! Other methods
//!------------------------------
		
		public function getDescription($var = null) {
			if ($var) {
				$returnVal = print_r($var, true);
			}
			else {
				$returnVal = print_r($this, true);
			}
		
			return $returnVal;
		}
		
		/**
			looks at the browser's URL and, if .sitemason.com is present,
			formats the given URL to include it.
		*/
		protected function formatURLForDevelopment($url) {
			if (preg_match('/\.sitemason\.com/',$_SERVER['HTTP_HOST'])) {
				if (!preg_match('/\.sitemason\.com/',$url)) {
					$urlPieces = parse_url($url);
					$url = preg_replace('/'. $urlPieces['host'] .'/', $urlPieces['host'] .'.sitemason.com', $url);
				}
			}
			
			return $url;
		}

		
		/**
			Parses $smNavigationData looking for the block pertaining
			to the current SMTool or SMFolder.  Returns it.
		*/
		protected function getNavigationDataForThisObject($navigationData = null) {
			global $smSiteNavigationData;
			if (!$navigationData) { $navigationData = $smSiteNavigationData; }
			$returnVal = null;
			
			// $this is an SMFolder: $this.id should equal $navigationData['site_instance_id']
			if (get_class($this) == 'SMFolder') {
				foreach ((array)$navigationData['pages'] as $page) {
					// matching folder
					if ($page['app_name'] == 'folder' && $page['site_instance_id'] == $this->getID()) {
						$returnVal = $page;
					}
					
					// non-matching folder
					else if ($page['app_name'] == 'folder') {
						$returnVal = $this->getNavigationDataForThisObject($page);
					}
				}
			}
			// $this is an SMTool: $this.id should eqla $navigationData['id']
			else if (get_class($this) == 'SMTool') {
				foreach ((array)$navigationData['pages'] as $page) {
					// matching tool
					if ($page['id'] == $this->getID()) {
						$returnVal = $page;
					}
					
					// folder: examine contents
					else if ($page['app_name'] == 'folder') {
						$returnVal = $this->getNavigationDataForThisObject($page);
					}
				}
			}
			
			return $returnVal;
		}
	}
?>