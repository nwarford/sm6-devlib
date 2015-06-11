<?php

/*------------------------------------------------------------------------------------------

File: SMItem.php 
Summary: The SMItem class definition
Version: 6.0
	  
An Item is a key component of Sitemason® CMS's organization scheme.
  
Copyright (C) 2013 Sitemason, Inc. All Rights Reserved. 
 
------------------------------------------------------------------------------------------*/

	class SMItem extends SMObject {
		private $access;
		private $alternateSource;
		private $alternateSourceURL;
		private $alternateURL;
		private $assignmentID;
		private $authorEmailAddress;
		private $authorName;
		private $body;
		private $customFields;
		private $customRecords;
		private $feedID;
		private $icsPath;
		private $isApproved;
		private $isImportant;
		private $isLead;
		private $isLive;
		private $meta_description;
		private $meta_keywords;
		private $ownerID;
		private $sequence;
		private $submitterID;
		private $subtitle;
		private $summary;
				
		private $files;
		private $images;
		private $locations;
		private $tags;
		private $times;
		private $tool;	// the tool that this SMItem belongs to.
		private $URL;
		
		/*
			STORE
		*/
		
		// ivars
		private $currentQuantity;
		private $minimumQuantityAllowedInCart;
		private $maximumQuantityAllowedInCart;
		private $price;
		private $productAttributes;
		private $productAvailabilityMessage;
		private $productCode;
		private $shippingAndDiscountCategory;
		private $weight;
		
		// bools
		private $shouldPreventBackorder;
		private $shouldTrackQuantity;


		/*--
			Create an SMItem object from SM5 XML
			$itemData = Item data array from the "API"
		*/
		function __construct($itemData, $parentTool = null) {
			$this->apidata = $itemData;
			
			//
			// SMObject properties
			//
			
			// Sitemason JSON
			if ($itemData['item_id']) {
				$this->id = (int)$itemData['item_id'];	
			}
			
			// API JSON
			else if ($itemData['id']) {
				$this->id = (int)$itemData['id'];
			}

			$creationTime = new SMTime(array('startTime' => (string)$itemData['create_timestamp'], 'timezone' => (string)$itemData['time_zone']));
			$this->creationTime = $creationTime;

			$lastModifiedTime = new SMTime(array('startTime' => (string)$itemData['modify_timestamp'], 'timezone' => (string)$itemData['time_zone']));
			$this->lastModifiedTime = $lastModifiedTime;
			
			$this->title = (string)$itemData['title'];
			$this->encodedTitle = (string)$itemData['title_encoded'];
			
			// typical
			if ($itemData['full_url']) {
				$this->URL = $this->formatURLForDevelopment((string)$itemData['full_url']);
			}
			
			// cal_grid
			else if ($itemData['url']) {
				$this->URL = (string)$itemData['url'];	// really the path
			}

			// SMItem properties
			$this->access = (string)$itemData['access'];
			$this->alternateSource = (string)$itemData['alternate_source'];
			$this->alternateSourceURL = (string)$itemData['alternate_source_url'];
			$this->alternateURL = (string)$itemData['alternate_url'];
			$this->assignmentID = $itemData['assignment_id'];
			$this->authorEmailAddress = (string)$itemData['editor_email_address'];
			$this->authorName = (string)$itemData['editor_name'];
			$this->body = (string)$itemData['description'];
			#$this->facebookEventID = (string)$itemData['facebook_event_id'];
			$this->setEncodedID((string)$itemData['encoded_id']);
			$this->feedID = $itemData['feed_id'];
			$this->setMetaDescription($itemData['meta_description']);
			$this->setMetaKeywords($itemData['meta_keywords']);
			$this->icsPath = (string)$itemData['ics_url'];
			$this->isApproved = $itemData['is_approved'] ? true : false;
			$this->isImportant = $itemData['is_important'] ? true : false;
			$this->isLead = $itemData['is_lead_item'] ? true : false;
			$this->isLive = $itemData['is_live'] ? true : false;
			$this->ownerID = $itemData['owner_id'];
			$this->sequence = $itemData['sequence'];
			$this->sumbitterID = $itemData['submitter_id'];
			$this->subtitle = (string)$itemData['subtitle'];
			$this->summary = (string)$itemData['summary'];

			
			// convert SM4/5 style custom fields into SM6 style
			$customFields = array(
				'cf1'	=> $itemData['custom_field_1'],
				'cf2'	=> $itemData['custom_field_2'],
				'cf3'	=> $itemData['custom_field_3'],
				'cf4'	=> $itemData['custom_field_4'],
				'cf5'	=> $itemData['custom_field_5'],
				'cf6'	=> $itemData['custom_field_6'],
				'cf7'	=> $itemData['custom_field_7'],
				'cf8'	=> $itemData['custom_field_8'],
				'cf9'	=> $itemData['custom_field_9'],
				'cf10'	=> $itemData['custom_field_10'],
				'cf11'	=> $itemData['custom_field_11'],
				'cf12'	=> $itemData['custom_field_12'],
				'cf13'	=> $itemData['custom_field_13'],
				'cf14'	=> $itemData['custom_field_14'],
				'cf15'	=> $itemData['custom_field_15'],
				'cf16'	=> $itemData['custom_field_16']
			);
			
			// handle new-style custom_field_json
			if ($itemData['custom_field_json']) {
				$customFieldsJson = (string)$itemData['custom_field_json'];
				$customFieldsJson = preg_replace("/\t\n/", '', $customFieldsJson);
				$customFieldsJson = json_decode($customFieldsJson, true);
				
				// Merge the two arrays, convert to (back into) JSON, then set customFields
				$customFields = array_merge($customFields,$customFieldsJson);	
			}

			$this->customFields = json_encode($customFields);


			/*-----------
				Images
			-----------*/
			if ($itemData['file'] && $itemData['file']['type'] == 'image') {
				// make a new itemData array so we can mess with it
				$imageItemData = $itemData['file'];

				// if there's no title defined in the image, set it to the Item title (Gallery tool)
				if (!$imageItemData['title']) {
					$imageItemData['title'] = $this->getTitle();
				}
				
				// if there is no caption defined in the image and this is a Photo Gallery, set the item description
				// as the image caption
				if (!$imageItemData['caption'] && $parentTool && $parentTool->getToolType() == 'gallery') {
					$imageItemData['caption'] = $this->getBody();
				}

				$this->images = array(new SMImage($imageItemData));
			}
			
			/*-----------
				Files
			-----------*/
			if ($itemData['media']) {
				
				$files = array();
				$mediaData = $itemData['media'];
				
				// modify the media array to include the poster (image URL) if applicable
				if ($mediaData['type'] == 'video' && $mediaData['video_background'] == 'picture') {
					$largeImage1ImageSize = $this->getImageSizeWithLabelOfImageWithKey('large','image1');
					$mediaData['poster'] = $largeImage1ImageSize->getURL();
				}
				
				$file = new SMFile($mediaData);
				$fileCreationTime = new SMTime(array('timestampString' => (string)$itemData['media']['create_timestamp'], 'timezone' => (string)$itemData['time_zone']));
				
				$files[] = $file;
				$this->files = $files;
			}
			
			
			/*---------------
				Locations
			----------------*/
			if ($itemData['location_address'] || $itemData['location_city'] || $itemData['location_latitude'] || $itemData['location_state'] || $itemData['location_title']) {
				
				$locationData = array(
					'title'		=> $itemData['location_title'],
					'address1'	=> $itemData['location_address'],
					'address2'	=> $itemData['location_address_2'],
					'city'		=> $itemData['location_city'],
					'state'		=> $itemData['location_state'],
					'zip'		=> $itemData['location_zip'],
					'latitude'	=> $itemData['location_latitude'],
					'longitude'	=> $itemData['location_longitude']
				);
				
				$locations = array();
				$locations[] = new SMLocation($locationData);
				$this->locations = $locations;
			}
			
			/*-----------
				Times
			-----------*/
			if ($itemData['item_time']) {
				$time = $itemData['item_time'];
				$data = array(
					'id'		=> $time['id'],
					'timeZone'	=> $itemData['time_zone'],
					'startTime'	=> $time['start_time'],
					'endTime'	=> $time['end_time'],
					'isAllDay'	=> $time['is_all_day']
				);
				$smTime = new SMTime($data);
				$this->times = array($smTime);
			}

			/*-----------
				Tags
			-----------*/
			if ($itemData['tags']) {
				$thisTags = array();
				foreach ($itemData['tags'] as $tagData) {
					$thisTags[] = new SMTag($tagData);
				}
				$this->tags = $thisTags;	
			}


			/*---------------------
				Product (Store)
			---------------------*/
			
			$this->currentQuantity = (string)$itemData['current_quantity'];
			$this->maximumQuantityAllowedInCart = (string)$itemData['quantity_max'];
			$this->minimumQuantityAllowedInCart = (string)$itemData['quantity_min'];
			$this->price = $itemData['price'];
			$this->productAttributes = $itemData['attributes'];
			$this->productAvailabilityMessage = $itemData['availability_message'];
			$this->productCode = (string)$itemData['code'];
			$this->shippingAndDiscountCategory = (string)$itemData['cart_category'];
			$this->weight = (string)$itemData['weight'];
			
			$this->shouldPreventBackorder = $itemData['should_prevent_backorder'] ? true : false;
			$this->shouldTrackQuantity = $itemData['should_track_quantity'] ? true : false;
			
		}
		
		
//! 
//! PUBLIC get/set methods
//!------------------------------
		
		public function getAccess() { return $this->access; }
		public function setAccess($access) { $this->access = $access;}
		
		public function getAlternateURL() { return $this->alternateURL; }
		public function setAlternateURL($alternateURL) { $this->alternateURL = $alternateURL; }
		
		public function getAlternateSource() { return $this->alternateSource; }
		public function setAlternateSource($alternateSource) { $this->alternateSource = $alternateSource; }
		
		public function getAlternateSourceURL() { return $this->alternateSourceURL; }
		public function setAlternateSourceURL($alternateURL) { $this->alternateSourceURL = $alternateURL; }
		
		public function getAssignmentID() { return $this->assignmentID; }
		public function setAssignmentID($assignmentID) { $this->assignmentID = $assignmentID; }
		
		public function getAuthorEmailAddress() { return $this->authorEmailAddress; }
		public function setAuthorEmailAddress($authorEmailAddress) { $this->authorEmailAddress = $authorEmailAddress; }
		
		public function getAuthorName() { return $this->authorName; }
		public function setAuthorName($authorName) { $this->authorName = $authorName; }
		
		public function getBody() {
			$body = $this->body;

			/*
				Look for these and remove them:
				[http|https]://[hostname|hostname.sitemason.com]
			*/
			
			$host = smCurrentHost;
			if (preg_match('/[^www|^boilerplate]\.sitemason\.com/',$host)) {
				$host = preg_replace('/\.sitemason\.com/','',$host);
			}

			$regex = "/https?:\/\/$host(\.sitemason\.com)?/";
			$body = preg_replace($regex,'',$body);

			return $body;
		}
		public function setBody($body) { $this->body = $body; }
		
		public function getFeedID() { return $this->feedID; }
		public function setFeedID($feedID) { $this->feedID = $feedID; }
		
		public function getFile() {
			$files = $this->getFiles();
			return $files[0];
		}
		
		public function getFiles() { return (array)$this->files; }
		public function setFiles($files) { $this->files = $files; }
		
		public function getIcsPath() {
			$parentTool = $this->getTool();
			return $parentTool->getPath() . $this->icsPath;
		}
		
		/*
		public function getFacebookEventID() {
			return $this->facebookEventID;
		}
		*/
		
		public function getImages() { return (array)$this->images; }
		public function setImages($images) { 
			if (is_array($images)) { 
				$this->images = $images; 
			} 
		}
		
		
		public function getLocation() {
			$locations = $this->getLocations();
			return $locations[0];
		}
		public function setLocation($location) { $this->locations = array($location); }
		
		public function getLocations() { return $this->locations; }
		public function setLocations($locations) {
			if (is_array($locations)) {
				$this->locations = $locations;	
			}
		}
		
		public function getMetaDescription() { return $this->meta_description; }
		public function setMetaDescription($meta_description) { $this->meta_description = $meta_description; }
		
		public function getMetaKeywords() { return $this->meta_keywords; }
		public function setMetaKeywords($meta_keywords) { $this->meta_keywords = $meta_keywords; }
		
		
		public function getOwnerID() { return $this->ownerID; }
		public function setOwnerID($ownerID) { $this->ownerID = $ownerID; }
		
		public function getTags() { return (array)$this->tags; }
		public function setTags($tags) { $this->tags = $tags; }
		
		public function getTool() { return $this->tool; }
		public function setTool($smTool) { $this->tool = $smTool; }


		public function isApproved() {
			if ($this->isApproved) {
				return TRUE;
			}
			else {
				return FALSE;
			}
		}
		public function setIsApproved($value) { $this->isApproved = $value; }

		public function isImportant() {
			if ($this->isImportant) {
				return TRUE;
			}
			else {
				return FALSE;
			}
		}
		public function setIsImportant($value) { $this->isImportant = $value; }
		
		public function isLead() {
			if ($this->isLead) {
				return TRUE;
			}
			else {
				return FALSE;
			}
		}
		public function setIsLead($value) { $this->isLead = $value; }
		
		public function isLive() {
			if ($this->isLive) {
				return TRUE;
			}
			else {
				return FALSE;
			}
		}
		public function setIsLive($value) { $this->isLive = $value; }
		
		
		public function getSequence() {
			return $this->sequence;
		}
		public function setSequence($sequence) {
			$this->sequence = $sequence;
		}
		
		public function getSubmitterID() { return $this->submitterID; }
		public function setSubmitterID($submitterID) { $this->submitterID = $submitterID; }
		
		public function getSubtitle() { return $this->subtitle; }
		public function setSubtitle($subtitle) { $this->subtitle = $subtitle; }
		
		public function getSummary() {
			$summary = $this->summary;

			/*
				Look for these and remove them:
				[http|https]://[hostname|hostname.sitemason.com]
			*/
			
			$host = smCurrentHost;
			if (preg_match('/[^www|^boilerplate]\.sitemason\.com/',$host)) {
				$host = preg_replace('/\.sitemason\.com/','',$host);
			}
			
			$regex = "/https?:\/\/$host(\.sitemason\.com)?/";
			$summary = preg_replace($regex,'',$summary);

			return $summary;
		}
		public function setSummary($summary) { $this->summary = $summary; }
		
		
		public function getTime() {
			$times = $this->getTimes();
			return $times[0];
		}
		public function setTime ($time) {
			$this->times = array($time);
		}
		
		public function getTimes() { return (array)$this->times; }
		public function setTimes(array $times) { $this->times = $times; }
		
		public function getURL() { return $this->URL; }


//! 
//! PUBLIC Product (Store) get/set methods
//!-----------------------------------------

		public function getCurrentQuantity() { return $this->currentQuantity; }
		public function setCurrentQuantity($currentQuantity) { $this->currentQuantity = $currentQuantity; }
		
		public function getMaximumQuantityAllowedInCart() { return $this->maximumQuantityAllowedInCart; }
		public function setMaximumQuantityAllowedInCart($maximumQuantityAllowedInCart) { $this->maximumQuantityAllowedInCart = $maximumQuantityAllowedInCart; }

		public function getMinimumQuantityAllowedInCart() { return $this->minimumQuantityAllowedInCart; }
		public function setMinimumQuantityAllowedInCart($minimumQuantityAllowedInCart) { $this->minimumQuantityAllowedInCart = $minimumQuantityAllowedInCart; }
		
		public function getFormattedPrice($currency = 'USD') {
			
			if ($currency == 'USD') {
				$price = '&#36;';
			}
			else if ($currency == 'GBP') {
				$price = '&#163;';
			}
			else if ($currency == 'EUR') {
				$price = '&#8364;';
			}
			else {
				$price = $currency;
			}
			
			$price .= number_format((double)$this->getPrice(),2,'.','');
			
			return $price;
		}
		
		public function getPrice() { return $this->price; }
		public function setPrice($price) { $this->price = $price; }
		
		public function getProductAttributes() { return $this->productAttributes; }
		public function setProductAttributes($productAttributes) { $this->productAttributes = $productAttributes; }
		
		public function getProductAvailabilityMessage() { return $this->productAvailabilityMessage; }
		public function setProductAvailabilityMessage($productAvailabilityMessage) { $this->productAvailabilityMessage = $productAvailabilityMessage; }
		
		public function getProductCode() { return $this->productCode; }
		public function setProductCode($productCode) { $this->productCode = $productCode; }
		
		public function getShippingAndDiscountCategory() { return $this->shippingAndDiscountCategory; }
		public function setShippingAndDiscountCategory($shippingAndDiscountCategory) { $this->shippingAndDiscountCategory = $shippingAndDiscountCategory; }
		
		public function getWeight() { return $this->weight; }
		public function setWeight($weight) { $this->weight = $weight; }
		
		public function shouldPreventBackorder() {
			if ($this->shouldPreventBackorder) {
				return TRUE;
			}
			else {
				return FALSE;
			}
		}
		public function setShouldPreventBackorder($value) { $this->shouldPreventBackorder = $value; }
		
		public function shouldTrackQuantity() {
			if ($this->shouldTrackQuantity) {
				return TRUE;
			}
			else {
				return FALSE;
			}
		}
		public function setShouldTrackQuantity($value) { $this->shouldTrackQuantity = $value; }



//! 
//! PRIVATE get/set methods
//!------------------------------

		/**
			returns a JSON string of this SMItem's custom fields
		*/
		private function getCustomFields() {
			return $this->customFields;
		}
		
		
		/**
			returns an array of SMCustomRecord objects
		*/
		private function getCustomRecords() {
		
			// lazily-load customRecords if they aren't here already
			if (count($this->customRecords) == 0) {

				// call this item's detail data (via an SMRequest), then parse the custom record data.
				$requestData = array(
					'url'	=> $this->url
				);
				$thisItemDetail = new SMRequest($requestData);
				$itemData = $thisItemDetail->getResponseData();
				
				if ($itemData['element']['item'][0]['custom']) {
					$customRecords = array();
					foreach ($itemData['element']['item'][0]['custom'] as $recordKey => $arrayOfRecords) {
						$customRecordSub = array();
						foreach ($arrayOfRecords as $customRecord) {
							$customRecordSub[] = $customRecord;
						}
						$customRecords[$recordKey] = $customRecordSub;
					}
					$this->setCustomRecords($customRecords);
				}
			}
			
			return (array)$this->customRecords;
		}
		
		
		private function setCustomRecords(array $customRecords) {
			$this->customRecords = $customRecords;
		}


//! 
//! Image-related methods
//!------------------------------


		//
		// SMImage Helper Methods
		//----------------------------------------
		
		/**
			Returns the first Image of the item (probably the Image with key = "image1")
		*/
		public function getImage() {
			$images = $this->getImages();
			return $images[0];
		}
		public function setImage($image) { $this->images = array($image); }
		
		
		
		/**
			NON-PUBLISHED method
			Returns an SMImage object for the Image with the given key ("image1")
		*/
		public function getImageWithKey($imageKey = 'image1') {
			$images = $this->getImages();
			$returnImage = null;
			
			foreach ($images as $image) {
				if ($image->getKey() == $imageKey) {
					$returnImage = $image;
				}
			}
			
			return $returnImage;
		}
		
		
		//
		// SMImageSize Helper Methods
		//----------------------------------------
		
		
		/**
			Returns the ImageSize with the label "large".  As of 6.0, this
			would be image1's "large" image.
		*/
		public function getLargeImageSize() {
			return $this->getImageSizeWithLabelOfImageWithKey('large','image1');
		}
		
		
		/**
			Returns the ImageSize with the label "thumbnail".  As of 6.0, this
			would be image1's "thumbnail" image.
		*/
		public function getThumbnailImageSize() {
			return $this->getImageSizeWithLabelOfImageWithKey('thumbnail','image1');
		}
		
		
		/**
			NON-PUBLISHED method
			Returns an associative array of SMImageSize objects (with the key = the Image Size's label)
			This is basically worthless for template-making, so it's not published
		*/
		
		public function getImageSizesOfImageWithKey($imageKey = 'image1') {
			$images = $this->getImages();
			$returnVal = array();
			
			// find the SMImage with the given key
			foreach ($images as $image) {
				if ($image->getKey() == $imageKey) {
					$imageSizes = (array)$image->getImageSizes();
					
					// iterate through all Image Sizes and create an associative array based on the label
					foreach ($imageSizes as $imageSize) {
						$label = $imageSize->getLabel();
						$returnVal[$label] = $imageSize;
					}
				}
			}
			
			return $returnVal;
		}

		
		/**
			NON-PUBLISHED method
			Returns an SMImageSize given a type label and size label
			Example: $imageSize = getImageSizeWithLabelOfImageWithKey('large','image1');
		*/
		public function getImageSizeWithLabelOfImageWithKey($sizeLabel, $imageKey) {
			$sizes = $this->getImageSizesOfImageWithKey($imageKey);
			return $sizes[$sizeLabel];
		}



//! 
//! Other methods
//!------------------------------
		
		/**
		
		*/
		public function getCustomFieldWithKey($key) {
			$customFields = json_decode($this->getCustomFields(), true);
			
			// if $key = 1-15, add the "cf" prefix!
			if ($key > 0 && $key < 17) {
				$key = 'cf'. $key;
			}
			
			return $customFields[$key];
		}
		
		
		/**
			returns the custom record with the given label (i.e. "locations")
		*/	
		public function getCustomRecordsOfType($typeLabel) {
			if (count($this->customRecords) == 0) {
				$this->getCustomRecords();
			}
		
			return $this->customRecords[$typeLabel];
		}
		
		
		/**
			Returns an array with the files of the given type: audio, video, file
		
		public function getFilesOfType($type) {
			$files = (array)$this->getFiles();
			$returnFiles = array();
			
			foreach ($files as $file) {
				$filetype = $file->getType();
				if ($type == $filetype) {
					$returnFiles[] = $file;
				}
			}
			
			return $returnFiles;
		}
		*/
		
		
		/**
			Returns the next SMItem in the list (sorted however it was in the Tool
			or null if there is no next item...
		*/
		public function getNextItem() {
			$thisItemId = $this->getID();
			
			// get all of the tool's items
			$tool = $this->getTool();
			$items = $tool->getItems();
			
			// if the above returned 0 or 1 item, like it would on a detail view, 
			// then make a more expensive call: create a new Tool and get the items
			// from that.
			if (count($items) < 2) {
				$url = $this->getTool()->getURL();
				$requestData = array('url' => $url);
				$tool = new SMTool($requestData);
				$items = $tool->getItems();
			}
			
			$nextItem = null;
			$i = 0;
			
			foreach ($items as $item) {
				$itemId = $item->getID();
				
				// found this item
				if ($itemId == $thisItemId) {
				
					// if there is a next item, then set it.
					if ($items[$i+1]) {
						$nextItem = $items[$i+1];	
					}
					break;
				}
				$i++;
			}
			
			return $nextItem;
		}
		
		
		/**
			Returns the previous SMItem in the list (sorted however it was in the Tool
			or null if there is no next item...
		*/
		public function getPreviousItem() {
			
			$tool = $this->getTool();
			$thisItemId = $this->getID();
			$items = $tool->getItems();
			
			// if the above returned 0 or 1 item, like it would on a detail view, 
			// then make a more expensive call: create a new Tool and get the items
			// from that.
			if (count($items) < 2) {
				$url = $this->getTool()->getURL();
				$requestData = array('url' => $url);
				$tool = new SMTool($requestData);
				$items = $tool->getItems();
			}
			
			$previousItem = null;
			$i = 0;
			
			foreach ($items as $item) {
				$itemId = $item->getID();
				
				// found this item
				if ($itemId == $thisItemId) {
					// if there is a next item, then set it.
					if ($i > 0 && $items[$i-1]) {
						$previousItem = $items[$i-1];	
					}
					break;
				}
				$i++;
			}
			
			return $previousItem;
		}
		
		/**
			Returns the subset of SMTags belonging to this SMItem where those SMTags are members 
			of the SMTagGroup with the given title
		*/
		public function getTagsInTagGroupWithTitle($tagGroupTitle) {
		
			// reference this SMItem's tool
			$tool = $this->getTool();
			$allGroupTags = $tool->getTagsInTagGroupWithTitle($tagGroupTitle);
			$allItemTags = $this->getTags();
			$tags = array();
			
			foreach ($allGroupTags as $groupTag) {
				foreach ($allItemTags as $itemTag) {
					if ($groupTag->getID() === $itemTag->getID()) {
						$tags[] = $itemTag;
					}
				}
			}
			
			return $tags;
		}
		
		
		/**
			Returns an array of the titles of the SMTags belonging to this SMItem
		*/
		public function getTitlesOfTags() {

			$tags = $this->getTags();
			$tagTitles = array();
			
			foreach ($tags as $tag) {
				$tagTitles[] = $tag->getTitle();
			}
			
			return $tagTitles;
		}
		
		
		
		/**
			Returns the titles of the subset of SMTags belonging to this SMItem where 
			those SMTags are members of the SMTagGroup with the given title
		*/
		public function getTitlesOfTagsInTagGroupWithTitle($tagGroupTitle) {

			$tags = $this->getTagsInTagGroupWithTitle($tagGroupTitle);
			$tagTitles = array();
			
			foreach ($tags as $tag) {
				$tagTitles[] = $tag->getTitle();
			}
			
			return $tagTitles;
		}
		
		
		
		/**
			Returns true if the item has been tagged with an SMTag with the given key.  Returns 
			false otherwise.
		*/
		public function hasTagWithKey($tagKey) {

			$tags = $this->getTags();
			$returnVal = false;
			
			foreach ($tags as $tag) {
				if ($tag->getKey() == $tagKey) {
					$returnVal = true;
					break;
				}
			}
			
			return $returnVal;
		}
		
		/**
			Returns true if the item has been tagged with an SMTag with the given title.  Returns 
			false otherwise.
		*/
		public function hasTagWithTitle($tagTitle) {

			$tags = $this->getTags();
			$returnVal = false;
			
			foreach ($tags as $tag) {
				if ($tag->getTitle() == $tagTitle) {
					$returnVal = true;
					break;
				}
			}
			
			return $returnVal;
		}

//! 
//! Date/Time methods
//!------------------------------

		/*
			These are handled mostly for convenience to abstract SMTime since,
			at the launch of 6.0, each Item only has one Time instance.
		*/
	
	
		/**
			Returns the first SMTime's start timestamp.
			In the future, SMItems could have multiple times, requiring several SMTime
			objects per Item.  For now, there is only one corresponding SMTime object,
			so we'll use this method for convenience.
		*/
		public function getEndTimestamp() {
			$times = $this->getTimes();
			$time = $times[0];
			return $time->getEndTimestamp();
		}
	
	
	
		/**
			Returns the first SMTime's start timestamp.
			In the future, SMItems could have multiple times, requiring several SMTime
			objects per Item.  For now, there is only one corresponding SMTime object,
			so we'll use this method for convenience.
		*/
		public function getStartTimestamp() {
			$times = $this->getTimes();
			$time = $times[0];
			return $time->getStartTimestamp();
		}
		
		
		/**
			Returns the first SMTime's start date.
			In the future, SMItems could have multiple times, requiring several SMTime
			objects per Item.  For now, there is only one corresponding SMTime object,
			so we'll use this method for convenience.
		*/
		public function getStartDate() {
			$times = $this->getTimes();
			$time = $times[0];
			return $time->getStartDate();
		}
		
		public function isAllDay() {
			$times = $this->getTimes();
			$time = $times[0];
			return $time->isAllDay();
		}


//! 
//! Debugging Methods
//!------------------------------

		/**
			Create some JSON to attempt to sensibly describe the properties of this object
			Flag "returnDescription" to return the results instead of appending to the smConsoleOutput string.
		*/
		public function describe($returnDescription = false) {
			global $smConsoleOutput;
			
			$description = array(
				'id'						=> $this->getID(),
				'title'						=> $this->getTitle(),
				'alternateURL'				=> $this->getAlternateURL(),
				'alternateSource'			=> $this->getAlternateSource(),
				'alternateSourceURL'		=> $this->getAlternateSourceURL(),
				'authorEmailAddress'		=> $this->getAuthorEmailAddress(),
				'authorName'				=> $this->getAuthorName(),
				'body'						=> $this->getBody(),
				'isImportant'				=> $this->isImportant(),
				'isLead'					=> $this->isLead(),
				'subtitle'					=> $this->getSubtitle(),
				'summary'					=> $this->getSummary(),
				'URL'						=> $this->getURL()
			);
			
			// Custom Fields
			$customFields = $this->getCustomFields();
			if (count($customFields) > 0) {
				$description['customFields'] = $customFields;
			}

			// filter
			$src = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c");
			$repl = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");
			foreach ($description as $key => $value) {
				$description[$key] = str_replace($src, $repl, $value);
			}
			
			//
			// Array properties
			//
	
			// Time(s)
			if (count($this->getTimes()) > 0) {
				$timeDescriptions = array();
				foreach ($this->getTimes() as $time) {
					$timeDescriptions[] = $time->describe(true);
				}
				$description['times'] = $timeDescriptions;
			}
			
			
			
			// Images
			if (count($this->getImages()) > 0) {
				$imageDescriptions = array();
				foreach ($this->getImages() as $image) {
					$imageDescriptions[] = $image->describe(true);
				}
				$description['images'] = $imageDescriptions;
			}
			
			// Files
			if (count($this->getFiles()) > 0) {
				$fileDescriptions = array();
				foreach ($this->getFiles() as $file) {
					$fileDescriptions[] = $file->describe(true);
				}
				$description['files'] = $fileDescriptions;
			}
			
			// Locations
			if (count($this->getLocations()) > 0) {
				$locationDescriptions = array();
				foreach ($this->getLocations() as $location) {
					$locationDescriptions[] = $location->describe(true);
				}
				$description['locations'] = $locationDescriptions;
			}
			
			
			// Tags
			if (count($this->getTags()) > 0) {
				$tagDescriptions = array();
				foreach ($this->getTags() as $tag) {
					$tagDescriptions[] = $tag->describe(true);
				}
				$description['tags'] = $tagDescriptions;
			}

			
			if ($returnDescription) {
				return $description;
			}
			else {
				$smConsoleOutput .= 'console.info(\'SMItem ('. $this->getTitle() .')\'); console.dir(JSON.parse(\''. json_encode($description) .'\'));';
			}
		}
	}
?>