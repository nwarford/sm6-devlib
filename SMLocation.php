<?php
	
/*------------------------------------------------------------------------------------------

File: SMLocation.php 
Summary: The SMLocation class definition
Version: 6.0
	  
Defines a location for an Sitemason® Item
  
Copyright (C) 2012 Sitemason, Inc. All Rights Reserved. 
 
------------------------------------------------------------------------------------------*/

	class SMLocation extends SMObject {
		// id
		// title
		private $address1;
		private $address2;
		private $city;
		private $countryCode;
		private $state;
		private $zip;
		private $latitude;
		private $longitude;
		
		function __construct($data = null) {
			$this->apiData = $data;

			$this->setTitle($data['title']);
			$this->setAddress1($data['address1']);
			$this->setAddress2($data['address2']);
			$this->setCity($data['city']);
			$this->setCountryCode($data['country_code']);
			$this->setState($data['state']);
			$this->setZip($data['zip']);
			$this->setLatitude($data['latitude']);
			$this->setLongitude($data['longitude']);
		}
		
//! 
//! Basic get/set methods
//!------------------------------
		
		public function getAddress() { return $this->getAddress1(); }
		public function setAddress($address) { $this->setAddress1($address); }
		public function getAddress1() { return $this->address1; }
		public function setAddress1($address1) { $this->address1 = $address1; }
		
		public function getAddress2() { return $this->address2; }
		public function setAddress2($address2) { $this->address2 = $address2; }
		
		public function getCity() { return $this->city; }
		public function setCity($city) { $this->city = $city; }
		
		public function getCountryCode() { return $this->countryCode; }
		public function setCountryCode($countryCode) { $this->countryCode = $countryCode; }
		
		public function getState() { return $this->state; }
		public function setState($state) { $this->state = $state; }
		
		
		public function getPostCode() { return $this->zip; }
		public function setPostCode($postCode) { $this->zip = $postCode; }
		public function getZip() { return $this->zip; }
		public function setZip($zip) { $this->zip = $zip; }
		
		public function getLatitude() { return $this->latitude; }
		public function setLatitude($latitude) { $this->latitude = $latitude; }
		
		public function getLongitude() { return $this->longitude; }
		public function setLongitude($longitude) { $this->longitude = $longitude; }


//! 
//! Other methods
//!------------------------------

		/**
			Returns the coordinates of this SMLocation in decimal degrees (DD)
			36.166667, -86.783333
		*/
		public function getCoordinatesInDecimalDegrees() {
			if ($this->latitude && $this->longitude) {
				return $this->latitude .','. $this->longitude;
			}
		}
		
		
		/**
			Create some JSON to attempt to sensibly describe the properties of this object
			Flag "returnDescription" to return the results instead of appending to the smConsoleOutput string.
		*/
		public function describe($returnDescription = false) {
			global $smConsoleOutput;
			
			$description = array(
				'address'		=> $this->getAddress1(),
				'address2'		=> $this->getAddress2(),
				'city'			=> $this->getCity(),
				'countryCode'	=> $this->getCountryCode(),
				'id'			=> $this->getID(),
				'latitude'		=> $this->getLatitude(),
				'longitude'		=> $this->getLongitude(),
				'state'			=> $this->getState(),
				'title'			=> $this->getTitle(),
				'zip'			=> $this->getZip()
			);
			
			// filter
			$src = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c");
			$repl = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");
			foreach ($description as $key => $value) {
				$description[$key] = str_replace($src, $repl, $value);
			}
			
			if ($returnDescription) {
				return $description;
			}
			else {
				$smConsoleOutput .= 'console.info(\'SMLocation ('. $this->getTitle() .')\'); console.dir(JSON.parse(\''. json_encode($description) .'\'));';
			}
		}
		
	}
	
?>