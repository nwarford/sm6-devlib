<?php
	
/*------------------------------------------------------------------------------------------

File: SMTagGroup.php 
Summary: Defines a Sitemason® Tag Group
Version: 6.0
	  
Sitemason Tag Group implementation
  
Copyright (C) 2012 Sitemason, Inc. All Rights Reserved. 
 
------------------------------------------------------------------------------------------*/

	class SMTagGroup extends SMObject {

		private $tagSetId;
		private $tags;
		
		function __construct($data) {
			$this->apiData = $data;
		
			$this->id = $data['id'];
			$this->description = $data['description'];
			$this->title = $data['name'];
			$this->tagSetId = $data['tag_set_id'];
			
			// define tags...
			$this->tags = array();

			if ($data['tag']) {
				$tags = array();
				foreach ($data['tag'] as $tagData) {
					$tags[] = new SMTag($tagData);
				}
				$this->tags = $tags;
			}
		}


//! 
//! Basic get/set methods
//!------------------------------
	
		public function getDescription() {
			return $this->description;
		}
	
		public function getTags() {
			return (array)$this->tags;
		}
		
		public function getTagSetID() {
			return $this->tagSetId;
		}
		
		public function getTitle() {
			return $this->title;
		}


//! 
//! Other methods
//!------------------------------
	
		/**
			Create some JSON to attempt to sensibly describe the properties of this object
			Flag "returnDescription" to return the results instead of appending to the smConsoleOutput string.
		*/
		public function describe($returnDescription = false) {
			global $smConsoleOutput;
			
			$description = array(
				'id'			=> $this->getID(),
				'title'			=> $this->getTitle(),
				'tagSetId'		=> $this->getTagSetID()
			);
			
			// filter
			$src = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c");
			$repl = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");
			foreach ($description as $key => $value) {
				$description[$key] = str_replace($src, $repl, $value);
			}
			
			// tags
			if (count($this->getTags()) > 0) {
				$tagsDescriptions = array();
				foreach ($this->getTags() as $tag) {
					$tagsDescriptions[] = $tag->describe(true);	
				}
				$description['tags'] = $tagsDescriptions;
			}
			
			
			if ($returnDescription) {
				return $description;
			}
			else {
				$smConsoleOutput .= 'console.info(\'SMTagGroup ('. $this->getTitle() .')\'); console.dir(JSON.parse(\''. json_encode($description) .'\'));';
			}
		}
	}	
?>