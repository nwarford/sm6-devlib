<?php
	
/*------------------------------------------------------------------------------------------

File: SMFile.php 
Summary: Contains details about a file that is attached to an SMItem
Version: 6.0

Sitemason® File implementation
	  
Copyright (C) 2012 Sitemason, Inc. All Rights Reserved. 
 
------------------------------------------------------------------------------------------*/

	class SMFile extends SMObject {

		private $caption;
		private $filename;
		private $type;
		private $poster;
		private $URL;
		
		function __construct($data) {

			// SMObject properties
			$this->id = $data['id'];
			$this->title = $data['title'];
			$this->URL = $data['src'];

			// class properties
			$this->caption = $data['caption'];
			$this->filename = $data['filename'];
			$this->height = $data['video_height'];
			$this->poster = $data['poster'];
			$this->type = $data['type'];
			$this->width = $data['video_width'];
			
		}
		
//! 
//! Basic get/set methods
//!------------------------------

		public function getCaption() {
			return $this->caption;
		}
		
		public function getFilename() {
			return $this->filename;
		}
		
		public function getHeight() {
			return $this->height;
		}
		
		public function getPoster() {
			return $this->poster;
		}
		
		public function getType() {
			return $this->type;
		}
		
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
//! Other methods
//!------------------------------		

		/**
			Returns the media (MIME) type based on the extension of this file.  It's a bit crude,
			but until the file resides in the user's account, we can't access the file directly.
			
			TODO: complete this!
		*/
		public function getMediaType() {
			$mediaTypes = array(
				'm4v'		=> 'video/x-mp4',
				'pdf'		=> 'application/pdf',
				'zip'		=> 'application/zip'
			);
			
			$mediaType = mediaTypes(substr(strrchr($this->getFilename(),'.'),1));
			return $mediaType;
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
				'filename'		=> $this->getFilename(),
				'title'			=> $this->getTitle(),
				'type'			=> $this->getType(),
				'URL'			=> $this->getURL()
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
				$smConsoleOutput .= 'console.info(\'SMFile ('. $this->getTitle() .')\'); console.dir(JSON.parse(\''. json_encode($description) .'\'));';
			}
		}
		
	}
?>