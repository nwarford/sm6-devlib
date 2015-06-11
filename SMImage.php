<?php

/*------------------------------------------------------------------------------------------

File: SMImage.php 
Summary: The SMImage class definition
Version: 6.0
	  
Describes a Sitemason® Image (which in turn has multiple sizes)
  
Copyright (C) 2013 Sitemason, Inc. All Rights Reserved. 
 
------------------------------------------------------------------------------------------*/

	class SMImage extends SMObject {
		private $caption;
		private $copyright;
		private $creationTimestamp;
		private	$filename;
		private $fileSize;
		private $height;
		private $imageSizes;
		private $key;
		private $sourceURL;
		private $width;		
		
		public function __construct($requestData) {
		
			// super properties
			$this->id = $requestData['id'];
		
			// class properties
			$this->alt = isset($requestData['alt']) ? $requestData['alt'] : $requestData['title'];
			$this->caption = $requestData['caption'];
			$this->copyright = $requestData['copyright'];
			$this->creationTimestamp = $requestData['creation_timestamp'];
			$this->filename = $requestData['filename'];
			$this->height = $requestData['original_height'];
			$this->fileSize = $requestData['size'];
			$this->key = 'image1'; // there is only one key for now
			$this->URL = $requestData['original_src'];
			$this->title = $requestData['title'];
			$this->width = $requestData['original_width'];

			// stock (SM4) image sizes:
			$thumbnailImageData = array(
				'image'		=> $this,
				'label'		=> 'thumbnail',
				'height'	=> $requestData['thumb_height'],
				'width'		=> $requestData['thumb_width'],
				'url'		=> $requestData['thumb_src']
			);
			
			$thumbnail = new SMImageSize($thumbnailImageData);
			
			$largeImageData = array(
				'image'		=> $this,
				'label'		=> 'large',
				'height'	=> $requestData['height'],
				'width'		=> $requestData['width'],
				'url'		=> $requestData['src']
			);
			$large = new SMImageSize($largeImageData);

			$this->imageSizes = array($thumbnail, $large);
		}
		
//! 
//! Basic get/set methods
//!------------------------------
		
		public function getAlt() {
			return $this->alt;
		}
		
		public function getCaption() {
			return $this->caption;
		}
		
		public function getCopyright() {
			return $this->copyright;
		}
		
		public function getCreationTimestamp() {
			return $this->creationTimestamp;
		}
		
		public function getFilename() {
			return $this->filename;
		}
		
		public function getHeight() {
			return $this->height;
		}
		
		public function getKey() {
			return $this->key;
		}
		
		public function getFileSize() {
			return $this->fileSize;
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
		
		public function getTitle() {
			return $this->title;
		}
				
		public function getWidth() {
			return $this->width;
		}
		
//! 
//! non-published methods
//!------------------------------	

		/**
			NON-PUBLISHED
			Returns an array of all ImageSizes for this SMImage.  Used by SMItem ImageSize-support method(s).
			This method is next-to-worthless for template-making, so it's not published
		*/
		public function getImageSizes() {
			return $this->imageSizes;
		}
		

//! 
//! Other methods
//!------------------------------

		/**
			Returns the Image Sizes of this Image with the given label
		*/
		public function getImageSizeWithLabel($imageSizeLabel) {
			$imageSizes = $this->getImageSizes();
			$imageSize = null;

			if ($imageSizes) {
				foreach ($imageSizes as $size) {
					if ($size->getLabel() == $imageSizeLabel) {
						$imageSize = $size;
					}
				}
			}
			
			return $imageSize;
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
				'alt'				=> $this->getAlt(),	
				'caption'			=> $this->getCaption(),
				'copyright'			=> $this->getCopyright(),
				'creationTimestamp'	=> $this->getCreationTimestamp(),
				'filename'			=> $this->getFilename(),
				'height'			=> $this->getHeight(),
				'key'				=> $this->getKey(),
				'title'				=> $this->getTitle(),
				'URL'				=> $this->getURL(),
				'width'				=> $this->getWidth()
			);
			
			// filter
			$src = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c");
			$repl = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");
			foreach ($description as $key => $value) {
				$description[$key] = str_replace($src, $repl, $value);
			}
			
			// Image Sizes
			if (count($this->getImageSizes()) > 0) {
				$imageSizeDescriptions = array();
				foreach ($this->getImageSizes() as $imageSize) {
					$imageSizeDescriptions[] = $imageSize->describe(true);	
				}
				$description['imageSizes'] = $imageSizeDescriptions;
			}
						
			if ($returnDescription) {
				return $description;
			}
			else {
				$smConsoleOutput .= 'console.info(\'SMImage ('. $this->getTitle() .')\'); console.dir(JSON.parse(\''. json_encode($description) .'\'));';
			}
		}
		
	}
?>