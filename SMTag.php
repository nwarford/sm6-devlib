<?php
	
/*------------------------------------------------------------------------------------------

File: SMTag.php 
Summary: The SMTag class definition
Version: 6.0
	  
Sitemason® Tags (SMTags) are created via the SMItem API calls.
  
Copyright (C) 2012 Sitemason, Inc. All Rights Reserved. 
 
------------------------------------------------------------------------------------------*/

	class SMTag extends SMObject {
		private $key;
		private $tagSetId;
		private $description;
		
		function __construct($tagData) {

			// tagData comes from Sitemason API
			if (is_array($tagData)) {
				$this->apiData = $tagData;
				$this->id = $tagData['id'];
				$this->key = $tagData['name'];
				$this->tagSetId = $tagData['tag_set_id'];
				$this->title = $tagData['label'];
				$this->description = $tagData['description'];	
			}
			
			// else $tagData is just the label
			else {
				$this->title = $tagData;
			}
		}
		
//! 
//! Basic get/set methods
//!------------------------------
		
		// in since 6.0, but not used in SM and not documented
		public function getDescription() {
			return $this->description;
		}
		
		public function getKey() {
			return $this->key;
		}
		
		public function getTagSetID() {
			return $this->tagSetId;
		}

		public function getTitle() {
			return $this->title;
		}
		
		
		/**
			Create some JSON to attempt to sensibly describe the properties of this object
			Flag "returnDescription" to return the results instead of appending to the smConsoleOutput string.
		*/
		public function describe($returnDescription = false) {
			global $smConsoleOutput;
			
			$description = array(
				'id'						=> $this->getID(),
				'key'						=> $this->getKey(),
				'title'						=> $this->getTitle()
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
				$smConsoleOutput .= 'console.info(\'SMTag ('. $this->getTitle() .')\'); console.dir(JSON.parse(\''. json_encode($description) .'\'));';
			}
		}
	}
?>