<?php

/*------------------------------------------------------------------------------------------

File: SMImageSize.php 
Summary: The SMImageSize class definition
Version: 6.0
	  
Sitemason® Image Size (Thumbnail, Main, etc.).  Particularly useful for mobile
apps or applications requiring various sizes (size) of an image.  SMImageSizes should
all be of the same aspect ratio of their associated SMImage.  In other words, they are resized 
versions of the SMImage.
  
Copyright (C) 2013 Sitemason, Inc. All Rights Reserved. 
 
------------------------------------------------------------------------------------------*/

	class SMImageSize extends SMObject {
		private $fileSize;
		private $height;
		private $image;
		private $label;
		private $multiplier;
		private $URL;
		private $width;	

		public function __construct($requestData = null) {
			$this->image = $requestData['image'];
			$this->fileSize = $requestData['size'];
			$this->height = $requestData['height'];
			$this->label = $requestData['label'];
			$this->multiplier = $requestData['multiplier'];
			$this->URL = $requestData['url'];
			$this->width = $requestData['width'];
		}
		
		
//! 
//! Basic get/set methods
//!------------------------------
		
		public function getFileSize() {
			return $this->fileSize;
		}
		
		public function getHeight() {
			return $this->height;
		}
		
		public function getImage() {
			return $this->image;
		}
		
		public function getLabel() {
			return $this->label;
		}
		
		public function getMultiplier() {
			return $this->multiplier;
		}
		
		/**
			Returns the image's URL.  If the page is being viewed over SSL and the image is stored
			using the old file manager, alter the URL to make it secure.
		*/
		public function getURL() {
			$url = $this->URL;
			
			if (smProtocol == 'https' && preg_match('/http:\/\/www\.sitemason\.com\/files/',$url)) {
				$url = preg_replace('/http:\/\/www.sitemason.com/','https://secure.sitemason.com',$url);
			}
			
			return $url;
		}
		
		public function getWidth() {
			return $this->width;
		}

//! 
//! SMImage-level methods
//!------------------------------
		
		
		/**
			Get the alt tag (from the parent SMItem)
		*/
		public function getAlt() {
			return $this->image->getAlt();
		}
		
		/**
			Get the caption (from the parent SMItem)
		*/
		public function getCaption() {
			return $this->image->getCaption();
		}
		
		/**
			Get the copyright (from the parent SMItem)
		*/
		public function getCopyright() {
			return $this->image->getCopyright();
		}
		
		/**
			Get the title (from the parent SMItem)
		*/
		public function getTitle() {
			return $this->image->getTitle();
		}
		
		
//! 
//! Debug methods
//!------------------------------		
		
		
		/**
			Create some JSON to attempt to sensibly describe the properties of this object
			Flag "returnDescription" to return the results instead of appending to the smConsoleOutput string.
		*/
		public function describe($returnDescription = false) {
			global $smConsoleOutput;
			
			$description = array(
				'label'		=> $this->getLabel(),
				'fileSize'	=> $this->getFileSize(),
				'height'	=> $this->getHeight(),
				'URL'		=> $this->getURL(),
				'width'		=> $this->getWidth()
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
				$smConsoleOutput .= 'console.info(\'SMImageSize ('. $this->getTitle() .')\'); console.dir(JSON.parse(\''. json_encode($description) .'\'));';
			}
		}
		
	}	
?>