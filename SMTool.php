<?php
	
/*------------------------------------------------------------------------------------------

File: SMTool.php 
Summary: Object for all things Sitemason® Tool (tool/page) related.
Version: 6.0
	  
Converts Sitemason data into an SMTool object.  This code also parses the items, sending 
that data to SMItem to instantiate the SMItem objects, then assinging those Items to this
Tool.

Most of the Item data is parsed by the SM4/5 list view, but we also support cal_grid for
the month and week (translating that format into standard Items) - the strategy being,
eventually cal_grid and cal_list can be removed for straight-up list view.
  
Copyright (C) 2013 Sitemason, Inc. All Rights Reserved. 
 
------------------------------------------------------------------------------------------*/

	class SMTool extends SMObject {
		private $customFields;
		private $folder;				// the folder containing this tool
		private $foxyCartAPIKey;
		private $foxyCartSubdomain;
		private $includeInNavigation;
		private $includeInSiteMap;
		private $items;
		private $layout;
		private $metaDescription;
		private $metaKeywords;
		private $numberOfItems;			// since, due to URL manipulation, we may only be seeing a few of the items, we need this number from Sitemason.
		private $offset;
		private $path;
		private $redirectURL;
		private $request;
		private $slug;
		private	$set;
		private $span;
		private $tagGroups;
		private $toolType;
		private $URL;
		private $view;
		private $windowTitle;
		
		// calendar-specific properties
		private $nextPageURL;
		private $previousPageURL;
		private $displayedDate;

		private $debug = 0;
		
		/**
			Create an SMTool object from SM5 JSON

			$requestData = request data (array) - send to SMRequest to get JSON back from Sitemason
			$responseData = existing data obtained elsewhere (simply to save an additional call to the app layer)
		*/
		
		function __construct($requestData = null, $responseData = null) {
			global $smConsoleDebug;

			if ($this->debug) { echo 'SMTool construct()..<br>'; }
			$debugStart = microtime();


			// if we got responseData, set up a SMRequest to store it			
			if ($responseData) {
				$this->request = new SMRequest($requestData, $responseData);
			}

			// find our data if it doesn't already exist!
			else {
				if ($this->debug) { echo 'No responseData given.  instantiating new SMRequest for requestData: <br>'; print_r($requestData); echo '<br />';}
				$this->request = new SMRequest($requestData);
				$responseCode = $this->request->getResponseCode();
				$responseData = $this->request->getResponseData();
				
				if ($this->debug) { echo 'SMTool constructor.  $responseCode: '. $responseCode .'<br>'; }
			}
			
			
			//
			// Create this SMTool from data (responseData array)
			//
			
			// ?sitejson or ?json
			if ($responseData['content']['element']) {
				$data = $responseData['content']['element'];
			}
			
			// ?tooljson
			else if ($responseData['element']) {
				$data = $responseData['element'];
			}
			
			// other (used for navigation in SMFolder)
			else if ($responseData) {
				$data = $responseData;
			}
			
			//
			// ID
			//
			$this->id = (int)$data['id'];
			
			// URL
			$this->URL = $data['base_url'];
			
			$this->layout = $data['settings']['layout'];
			
			// Timestamps
			$item = (array)$data['item'];
			$itemData = $item[0];
			
			$creationTime = new SMTime(array('startTime' => (string)$data['settings']['creation_timestamp'], 'timezone' => (string)$itemData['time_zone']));
			$this->creationTime = $creationTime;

			$lastModifiedTime = new SMTime(array('startTime' => (string)$data['settings']['modified_timestamp'], 'timezone' => (string)$itemData['time_zone']));
			$this->lastModifiedTime = $lastModifiedTime;
			
			//
			// PATH
			//
			
			// called "path" in navjson
			if ($data['path']) {
				$this->path = (string)$data['path'];
			}

			// folder path + tool path
			else {
				if ($data['settings']['site']['path']) {
					$path .= '/'. $data['settings']['site']['path'];
				}
				
				if ($data['settings']['path']) {
					$path .= '/'. $data['settings']['path'];
				}
				
				$this->path = $path;
			}
			
			
			//
			// SEO
			//
			
			if ($data['settings']['meta_description']) {
				$this->metaDescription = $data['settings']['meta_description'];
			}
			
			if ($data['settings']['meta_keywords']) {
				$this->metaKeywords = $data['settings']['meta_keywords'];
			}
			
			if ($data['settings']['page_title']) {
				$this->windowTitle = $data['settings']['page_title'];
			}
			
			
			//
			// Instance Custom Fields
			//
			
			// convert SM4/5 style custom fields into SM6 style (these are legacy settings and are not even used in SM6)
			
			$customFields = array(
				'cf1'	=> $data['settings']['instance_custom_field_1'],
				'cf2'	=> $data['settings']['instance_custom_field_2'],
				'cf3'	=> $data['settings']['instance_custom_field_3'],
				'cf4'	=> $data['settings']['instance_custom_field_4'],
				'cf5'	=> $data['settings']['instance_custom_field_5'],
				'cf6'	=> $data['settings']['instance_custom_field_6'],
				'cf7'	=> $data['settings']['instance_custom_field_7'],
				'cf8'	=> $data['settings']['instance_custom_field_8']
			);
			
			
			// handle new-style custom_field_json
			if ($data['settings']['custom_field_json']) {
				$customFieldsJson = (string)$data['settings']['custom_field_json'];
				$customFieldsJson = preg_replace("/\t\n/", '', $customFieldsJson);
				$customFieldsJson = json_decode($customFieldsJson, true);
				
				// Merge the two arrays, convert to (back into) JSON, then set customFields
				$customFields = array_merge($customFields,$customFieldsJson);	
			}
			
			$this->customFields = json_encode($customFields);
			

			
			//
			// TOOL TYPE
			//
			
			// used in navjson
			if ($data['toolType']) {
				$this->toolType = (string)$data['toolType'];
			}
			
			// in the SM4 data, this is called "type" in Page 
			// (and "app" in XML navigation block, but that doesn't matter since we're using navjson now)
			else if ($data['type']) {
				$this->toolType = (string)$data['type'];	
			}
			
			// in the SM4 data, form case
			else if ($responseData['current_nav']['app']) {
			
				// convert form > customForm
				if ((string)$responseData['current_nav']['app'] == 'form') {
					$this->toolType = 'customForm';
				}
				// other (unknown) case
				else {
					$this->toolType = (string)$responseData['current_nav']['app'];
				}
			}
			
			
			//
			// Site map / Navigation
			//
			
			// used in navjson
			if ($data['includeInNavigation']) {
				$this->includeInNavigation = (bool)$data['includeInNavigation'];	
			}
			
			// navjson
			if ($data['includeInSiteMap']) {
				$this->includeInSiteMap = (bool)$data['includeInSiteMap'];
			}
			// sitejson
			else if ($data['settings']['include_in_sitemap']) {
				$this->includeInSiteMap = (bool)$data['settings']['include_in_sitemap'];
			}


			//
			// Slug
			//

			// navjson
			if ($data['slug']) {
				$this->slug = (string)$data['slug'];
			}
			// sitejson
			else if ($data['settings']['slug']) {
				$this->slug = (string)$data['settings']['slug'];
			}


			//
			// Title
			// 
			
			// if the tool is being instantiated normally, then the title = settings.name
			if ($data['settings']['name']) {
				$this->title = (string)$data['settings']['name'];
				#$this->title = (string)$data['title']; // this is always set to the same as "window title" in this case, which is wrong.
			}
			
			// if we're creating a partial SMTool from the navigation data, then the name will be in the root of the data
			else if ($data['title']) {
				$this->title = (string)$data['title'];
			}
			// if all else fails, try to pull it from current_nav, which will only work under scenario 1
			else {
				$this->title = (string)$responseData['current_nav']['title'];
			}
			
			// Update title for site search, otherwise it's just the website URL
			if ($this->getToolType() == 'site_search') {
				$this->title = 'Search Results';
			}
			

			//
			// element-specific properties
			//
			
			$this->span = (string)$data['span'];
			$this->set = (int)$data['set'];
			$this->offset = (int)$data['offset'];
			$this->view = (string)$data['view'];
			$this->numberOfItems = (int)$data['total_items'];
			
			#$this->encodedId = (string)$data['id_enc'];
			#$this->navEncodedId = (string)$data['navEncodedId'];	// set by SMFolder when generating a navigation item.
			#$this->secureURL = (string)$data['settings']['secure_url'];
			
			//
			// Tag Groups
			//
			$this->tagGroups = array();
			if ($data['included_tag_groups']) {
				$tagGroups = array();
				foreach ($data['included_tag_groups']['tag_group'] as $tagGroup) {
					$tagGroups[] = new SMTagGroup($tagGroup);
				}
				$this->tagGroups = $tagGroups;
			}
			
			
			// make Items now
			$this->items = array();
			
			// list view
			if ($data['item']) {
				$newItems = array();
			
				foreach ($data['item'] as $item) {
					$smItem = new SMItem($item, $this);
					$smItem->setTool($this);
					$newItems[] = $smItem;
				}
				$this->items = $newItems;
			}

			// month (cal_list & cal_grid)
			else if ($data['month']['day']) {
				$newItems = array();
			
				foreach ($data['month']['day'] as $day) {
					if ($day['item']) {
						foreach ((array)$day['item'] as $item) {
							$item['url'] = $this->getURL() . $item['url'];
							$smItem = new SMItem($item);
							$smItem->setTool($this);
							$newItems[] = $smItem;
						}
					}
				}
				$this->items = $newItems;
			}
			
			// week (cal_list & cal_grid)
			else if ($data['week']['day']) {
				$newItems = array();
				// day
				foreach ((array)$data['week']['day'] as $day) {
					// cal_grid: organized by hour
					if ($day['hour']) {
						foreach ((array)$day['hour'] as $hour) {
							if ($hour['item']) {
								foreach ((array)$hour['item'] as $item) {
									// we need to modify item here, since the URL is a path relative to the tool
									$item['url'] = $this->getURL() . $item['url'];
									$smItem = new SMItem($item);
									$smItem->setTool($this);
									$newItems[] = $smItem;
								}
							}
						}	
					}
					// cal_list: organized by item
					else {
						foreach ((array)$day['item'] as $item) {
							// we need to modify item here, since the URL is a path relative to the tool
							$item['url'] = $this->getURL() . $item['url'];
							$smItem = new SMItem($item);
							$smItem->setTool($this);
							$newItems[] = $smItem;
						}
					}
					
				}
				$this->items = $newItems;
			}
			
			// day (cal_list & cal_grid)
			else if ($data['day']) {
				$newItems = array();
			
				// cal_grid: day:hour:item
				if ($data['day']['hour']) {
					foreach ((array)$data['day']['hour'] as $hour) {
						if ($hour['item']) {
							foreach ((array)$hour['item'] as $item) {
								// we need to modify item here, since the URL is a path relative to the tool
								$item['url'] = $this->getURL() . $item['url'];
								$smItem = new SMItem($item);
								$smItem->setTool($this);
								$newItems[] = $smItem;
							}
						}
					}
				}
				// cal_list: day:item
				else {
					foreach ((array)$data['day']['item'] as $item) {
						// we need to modify item here, since the URL is a path relative to the tool
						$item['url'] = $this->getURL() . $item['url'];
						$smItem = new SMItem($item);
						$smItem->setTool($this);
						$newItems[] = $smItem;
					}
				}
				
				$this->items = $newItems;
			}
			
			
			//
			// Links (calendar-specific, for now, but may not be soon)
			//
			
			if ($data['links']) {
				$nextPageURL = $this->getPath() . $data['links']['next'];
				$previousPageURL = $this->getPath() . $data['links']['previous'];
				
				// if there is a query string, remove it and replace it with this one.
				if ($_SERVER['QUERY_STRING']) { 
					$nextPageURL .= '?'. $_SERVER['QUERY_STRING']; 
					$previousPageURL .= '?'. $_SERVER['QUERY_STRING']; 
				}
				
				$this->nextPageURL = $nextPageURL;
				$this->previousPageURL = $previousPageURL;
			}
			
			
			//
			// Calendar-specific properties
			//
			
			if ($data['date_display']) {
				$this->displayedDate = $data['date_display'];
			}
			
			//
			// Store-specific settings
			//
			$this->foxyCartAPIKey = $data['settings']['store_api_key'];
			$this->foxyCartSubdomain = $data['settings']['store_name'];
			
			
			
			
			if (smShouldDebugApiRequests > 8) { 
				$smConsoleDebug .= ' console.info("instantiated new SMTool: '. $this->getTitle() .', URL: '. $this->getURL() .', Path: '. $this->getPath() .'");'."\n"; 
			}
			
			if ($this->debug) { echo 'Finished with SMTool constructor.  Tool has '. count($this->items) .' items<br>'; }
			
			$debugStop = microtime();
			$debugDuration = $debugStop - $debugStart;
			
			if (smShouldDebugTiming) {
				$smConsoleDebug .= 'console.info("TIMING: instantiate SMTool: '. $this->getTitle() .': '. $debugDuration .'s");'."\n"; 
			}
			
		}
		
		
		
//! 
//! Basic get/set methods
//!------------------------------
	
		public function getFolder() {
			return $this->folder;
		}
		
		public function setFolder(SMFolder $folder) {
			$this->folder = $folder;
		}
		
		public function getFoxyCartAPIKey() {
			return $this->foxyCartAPIKey;
		}
		
		public function getFoxyCartSubdomain() {
			return $this->foxyCartSubdomain;
		}
		
		public function getLayout() {
			return $this->layout;
		}
		
		/**
			Returns the Items belonging to this SMTool.  Prior to 6.0.10 on the detail view,
			it would simply return the one Item.  As of 6.0.10, if the visitor is on a detail
			view, it does some trickery to find all Items belonging to the Tool.
			
			$shouldForceCurrentFeed forces the method to use the current Sitemason feed.  This
			is called from getItem().
		*/
		public function getItems($shouldForceCurrentFeed = false) {
			global $smCurrentSite;
			
			$toolType = $this->getToolType();
			
			// detail view: get ALL of the Items (instead of just one)
			if ($this->getView() == 'detail' && $toolType != 'page' && !$shouldForceCurrentFeed) {
				$thisTool = new SMTool(array('url' => $this->getURL()));
				$items = $thisTool->getItems();
			}
			
			// any other view: just return what is here
			else {
				$items = $this->items;
			}
			return $items;
		}
		
		public function setItems($items) {
			$this->items = $items;
		}
		
		public function getMetaDescription() {
			return $this->metaDescription;
		}
		
		public function getMetaKeywords() {
			return $this->metaKeywords;
		}
		
		public function getNextPageURL() {
			return $this->nextPageURL;
		}
		
		public function getNumberOfItems() {
			return $this->numberOfItems;
		}

		public function getOffset() {
			return $this->offset;
		}
		
		public function getPath() {
			return $this->path;
		}
		
		public function getPreviousPageURL() {
			return $this->previousPageURL;
		}
		
		public function getRedirectURL() {
			return $this->redirectURL;
		}
		
		public function setRedirectURL($redirectURL) {
			$this->redirectURL = $redirectURL;
		}
		
		public function getRequest() {
			return $this->request;
		}
		
		public function getSet() {
			return $this->set;
		}
		
		public function getSlug() {
			return $this->slug;
		}
		
		public function getSpan() {
			return $this->span;
		}
		
		public function getTagGroups() {
			return $this->tagGroups;
		}
		
		/**
			does nothing - simply here in case someone calls it
			while doing navigation functions
		*/
		public function getTools() {
			return null;
		}
		
		public function getToolType() {
			return $this->toolType;
		}
		
		public function getView() {
			return $this->view;
		}
		
		/**
			Thought about adding this to convert list to cal_grid, but cal_grid
			needs other things from Sitemason in that view, so it doesn't really work
			as intended.
		*/
		/*
		public function setView($view) {
			$this->view = $view;
		}
		*/
		
		public function getWindowTitle() {
			return $this->windowTitle;
		}


//! 
//! Calendar-specific methods
//!------------------------------

		public function getDisplayedDate() {
			return $this->displayedDate;
		}
	
	

//! 
//! PRIVATE get/set methods
//!------------------------------
		
		
		/**
			returns a JSON string of this SMItem's custom fields
		*/
		private function getCustomFields() {
			return $this->customFields;
		}
		
		
		
//! 
//! Other Methods
//!------------------------------

		/**
			Creates a cumulative window title from the Tool's level and up
		*/
		public function getCumulativeWindowTitle($delimiter = '|') {

			// if this tool is on the detail view and it's not a Page tool, return the Item's window title
			if ($this->getView() == 'detail' && $this->getToolType() != 'page') {
				$item = $this->getItem();
				$itemTitle = $item->getTitle();
			}
			
			// Get the tool's window title or use the tool title if no window title was defined
			$toolTitle = $this->getWindowTitle();
			if (!$toolTitle) {
				$toolTitle = $this->getTitle();
			}
			
			// Get the Folder's window title
			$folder = $this->getFolder();
			if ($folder) {
				$folderTitle = $folder->getCumulativeWindowTitle();
			}
			
			$windowTitle = null;
			$x = 0;
			if ($itemTitle) {
				$windowTitle .= $itemTitle;
				$x++;
			}
			
			if ($toolTitle) {
				if ($x > 0) { $windowTitle .= ' '. $delimiter .' '; }
				$windowTitle .= $toolTitle;
				$x++;
			}
			
			if ($folderTitle) {
				// check for a duplicate...
				#preg_replace('/'. $toolTitle .' '. $delimiter .' /','',$folderTitle);
				
				if ($x > 0) { $windowTitle .= ' '. $delimiter .' '; }
				$windowTitle .= $folderTitle;
				$x++;
			}

			
			return $windowTitle;
		}
		

		/**
			Access the tool's custom fields (instance custom fields)
		*/
		public function getCustomFieldWithKey($key) {
			$customFields = json_decode($this->getCustomFields(), true);
			
			// if $key = 1-8, add the "cf" prefix!
			if ($key > 0 && $key < 8) {
				$key = 'cf'. $key;
			}
			
			return $customFields[$key];
		}
		
		
		/**
			Returns one Item.  Prior to 6.0.10, it simply returned the first item
			in the array.  As of 6.0.10, it does the same if called from the list
			view, but if called from the detail view, it returns the item being examined.
		*/
		public function getItem() {
			
			// detail view: we just want what the current feed has (one Item)
			if ($this->getView() == 'detail') {
				// call getItems and force it to use the current feed
				$items = $this->getItems(true);
			}
			
			// any other view: look up all of the Items and return the first one.
			else {
				$items = $this->getItems();
			}
			
			return $items[0];
		}
		
		
		public function getImportantItems() {
			$items = $this->getItemsWithOptions(array('isImportant' => true));
			return $items;
		}
		
		
		/**
			returns the subset of items with an SMTime equal to the given date
			Date format: YYYY-MM-DD
		*/
		public function getItemsWithDate($date) {
			$filteredItems = array();
			$items = $this->getItems();
			foreach ($items as $item) {
				if ($item->getStartDate() == $date) {
					$filteredItems[] = $item;
				}
			}

			return $filteredItems;
		}
		
		
		
		
		/**
			returns a subset of the items, based on the given limit and offset
		*/
		public function getItemsWithLimitAndOffset($limit = 25, $offset = 0) {
			return array_slice($this->getItems(), $offset, $limit);
		}
		
		
		/**
			PRELIMINARY - wait until all options are fully tested before making "live"
			
			Returns Items with the given options.  This is a bit of a hack for the
			time being, but will be simplified when Sitemason 7's API becomes available.
			
			Valid options are:
			- isImportant
		*/
		
		public function getItemsWithOptions(array $options) {
			$items = $this->getItems();

			// isImportant
			if ($options['isImportant']) {
				$newItems = array();
				foreach ($items as $item) {
					if ($item->isImportant()) {
						$newItems[] = $item;
					}
				}
				$items = $newItems;
			}
			
			return $items;
		}
		


		/**
			Returns an array of SMItems that contain the given Tag key
		*/

		public function getItemsWithTagWithKey($tagKey) {
			$items = $this->getItems();
			$returnItems = array();

			foreach ($items as $item) {
				$tags = $item->getTags();
				foreach ($tags as $tag) {
					if ($tag->getKey() == $tagKey) {
						$returnItems[] = $item;
						continue;
					}
				}
			}

			return $returnItems;
		}
		
		
		
		/**
			Returns an array of SMItems that contain the given Tag Title
		*/

		public function getItemsWithTagWithTitle($tagTitle) {
			$items = $this->getItems();
			$returnItems = array();

			foreach ($items as $item) {
				$tags = $item->getTags();
				foreach ($tags as $tag) {
					if ($tag->getTitle() == $tagTitle) {
						$returnItems[] = $item;
						continue;
					}
				}
			}

			return $returnItems;
		}
		
		/**
			Returns all of the Items in this tool that have been tagged with a
			Tag contained in the Tag Group with the given title (Tag Group's title).
		*/
		public function getItemsWithTagInTagGroupWithTitle($tagGroupTitle) {
			$items = array();
			$itemIds = array();
			$tags = $this->getTagsInTagGroupWithTitle($tagGroupTitle);
			
			foreach ($tags as $tag) {
				$tagKey = $tag->getKey();
				$newItems = $this->getItemsWithTagWithKey($tagKey);
				
				// we need to make sure the items are unique
				foreach ($newItems as $item) {
					$itemId = $item->getID();
					if (!in_array($itemId, $itemIds)) {
						$itemIds[] = $itemId;
						$items[] = $item;
					}
				}
			}
			
			return $items;
		}


		/**
			Returns an empty array, since Tools cannot have navigation Tools
			This is meant as a convenience when working with navigation structures
		*/
		public function getNavigationTools() {
			$returnVal = array();
			return $returnVal;
		}
		
		
		/**
			Returns false, since Tools cannot have navigation Tools
			This is meant as a convenience when working with navigation structures
		*/
		public function hasNavigationTools() {
			return false;
		}

		
		/**
			Returns a random item from within the items array
		*/
		public function getRandomItem() {
			$items = $this->getItems();
			$lastItem = count($items) - 1;
			$random = rand(0, $lastItem);
			return $items[$random];
		}
		
		/**
			Returns the given number of random items from within the items array
		*/
		public function getRandomItems($number = 2) {
		
			// ERROR: no items in the tool
		
			$items = $this->getItems();
			$returnItems = array(); 		// the SMItems to return
			$shouldContinue = true;
			
			// the requested number of items to return cannot be larger than the number of Items!
			if ($number > count($items)) {
				$number = count($items);
			}
			
			while ($shouldContinue) {
				$lastItem = count($items) - 1;
				$random = rand(0, $lastItem);
				$item = $items[$random];

				// add it to the array
				$returnItems[] = $item;
				
				// we have the requested number of items
				if (count($returnItems) == $number) {
					$shouldContinue = false;
				}
				// else remove this item from $items
				else {
					array_splice($items, $random, 1);
				}
			}
			
			return $returnItems;
		}
		
		
		/**
			Returns an array of all Tags (from all Items) in this Tool.
			Since we don't have a concise list from the API, we're doing
			this the hard way for now...
		*/
		public function getTags() {
			$returnTags = array();
			$tagIds = array();

			$items = $this->getItems();
			foreach ($items as $item) {
				$tags = $item->getTags();
				foreach ($tags as $tag) {
					if (!in_array($tag->getID(), $tagIds)) {
						$tagIds[] = $tag->getID();
						$returnTags[] = $tag;
					}
				}
			}
			
			return $returnTags;
		}
		
		
		public function getTagGroupWithTitle($tagGroupTitle) {
			$tagGroups = $this->getTagGroups();
			$returnVal = null;
			foreach ($tagGroups as $tagGroup) {
				if ($tagGroup->getTitle() == $tagGroupTitle) {
					$returnVal = $tagGroup;
					break;
				}
			}

			return $returnVal;
		}


		/**
			Returns an array of SMTags contained in the Tag Group with the given name
		*/
		
		public function getTagsInTagGroupWithTitle($tagGroupTitle) {
			$tags = array();
			$tagGroups = $this->getTagGroups();
			
			foreach ($tagGroups as $tagGroup) {
				if ($tagGroup->getTitle() == $tagGroupTitle) {
					$tags = $tagGroup->getTags();
					break;
				}
			}
			
			return $tags;
		}
		
		/**
			Returns the titles of the subset of SMTags belonging to this SMTool where 
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
			Specilized getView called by the Tool Template index script.
			Needed in order to do some conversions for Calendar tool types...
		*/
		public function getToolView() {
			$toolView = $this->getView();
			
			// if the view is a cal_grid, change that to the span (month, week, day)
			if ($toolView == 'cal_grid') {
				$toolView = $this->getSpan();
			}
			// if the view is cal_list, change to list
			else if ($toolView == 'cal_list') {
				$toolView = 'list';
			}
			
			return $toolView;
		}

		
		/**
			Constructs the full URL to this Tool by tacking on the siteURL to the path. Unless it's a link tool...
		*/
		public function getURL() {
			if ($this->getToolType() == 'link') {
				$url = $this->getPath();
			}
			else {
				$url = siteURL . $this->getPath();
			}

			return $url;
		}
		
		
		public function shouldIncludeInNavigation() {
			return $this->includeInNavigation;
		}
		
		public function shouldIncludeInSiteMap() {
			return $this->includeInSiteMap;
		}
		
		
		/**
			Sorts the items property based on the given key.
			Currently supports sorting by: startTimestamp or title (ascending or descending)
		*/
		public function sortItemsByKeyWithOrder($orderBy = 'startTimestamp', $order = 'descending') {
			
			// sort ascending based on the given order
			
			// sort by start timestamp
			if ($orderBy == 'startTimestamp') {
				$this->sortItemsByStartTimestampAscending();	
			}
			
			// (default) sort by start timestamp
			else {
				$this->sortItemsByTitleAscending();
			}
			
			// if the user has selected descending, reverse the array
			if ($order == 'descending') {
				$this->setItems(array_reverse($this->getItems()));
			}	
		}
		
		
		/**
			Perform a quicksort on the items by each SMItem's SMTime.startTimestamp
		*/
		private function sortItemsByStartTimestampAscending() {
			$items = $this->getItems();
			
			$pivot = 1;
			$stack[1]['low'] = 0;
			$stack[1]['high'] = count($items)-1;
			
			do {
				$low = $stack[$pivot]['low'];
				$high = $stack[$pivot]['high'];
				$pivot--;
				
				do {
					$i = $low;
					$j = $high;
					$tmp = $items[(int)(($low + $high) / 2)];
					
					$tmpStartTime = (int)$tmp->getTime()->getCleanTimestamp();
					
					// partion the array into low and high sections
					do {
						while ($items[$i]->getTime()->getCleanTimestamp() < $tmpStartTime) {
							$i++;
						}
						
						while ($tmpStartTime < $items[$j]->getTime()->getCleanTimestamp()) {
							$j--;						
						}
	
						// swap elements from the two sides
						if ($i <= $j) {
							$w = $items[$i];
							$items[$i] = $items[$j];
							$items[$j] = $w;
							
							$i++;
							$j--;
						}
				
					}
					while ($i <= $j);
				
					if ($i < $high) {
						$pivot++;
						$stack[$pivot]['low'] = $i;
						$stack[$pivot]['high'] = $high;
					}
				
					$high = $j;
				
				}
				while($low < $high);
				
			}
			while($pivot != 0);
			
			$this->setItems($items);
		}
		
		
		
		/**
			Perform a quicksort on the items by title
		*/
		private function sortItemsByTitleAscending() {
			$items = $this->getItems();
			
			$pivot = 1;
			$stack[1]['low'] = 0;
			$stack[1]['high'] = count($items)-1;
			
			do {
				$low = $stack[$pivot]['low'];
				$high = $stack[$pivot]['high'];
				$pivot--;
				
				do {
					$i = $low;
					$j = $high;
					$tmp = $items[(int)(($low + $high) / 2)];
					
					// partion the array into low and high sections
					do {
						while ($items[$i]->getTitle() < $tmp->getTitle()) {
							$i++;
						}
							
						while ($tmp->getTitle() < $items[$j]->getTitle()) {
							$j--;						
						}
	
						// swap elements from the two sides
						if ($i <= $j) {
							$w = $items[$i];
							$items[$i] = $items[$j];
							$items[$j] = $w;
							
							$i++;
							$j--;
						}
				
					}
					while ($i <= $j);
				
					if ($i < $high) {
						$pivot++;
						$stack[$pivot]['low'] = $i;
						$stack[$pivot]['high'] = $high;
					}
				
					$high = $j;
				
				}
				while($low < $high);
				
			}
			while($pivot != 0);
			
			$this->setItems($items);
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
				'creationTimestamp'			=> $this->getCreationTimestamp(),
				'includeInNavigation'		=> $this->shouldIncludeInNavigation(),
				'layout'					=> $this->getLayout(),
				'metaDescription'			=> $this->getMetaDescription(),
				'metaKeywords'				=> $this->getMetaKeywords(),
				'path'						=> $this->getPath(),
				'slug'						=> $this->getSlug(),
				'toolType'					=> $this->getToolType(),
				'title'						=> $this->getTitle(),
				'URL'						=> $this->getURL(),
				'view'						=> $this->getView(),
				'windowTitle'				=> $this->getWindowTitle()
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

			// Items
			$items = $this->getItems();
			if (count($items) > 0) {
				$itemsDescriptions = array();
				foreach ($items as $item) {
					$itemsDescriptions[] = $item->describe(true);	
				}
				$description['items'] = $itemsDescriptions;
			}		

			// Tag Groups
			$tagGroupsDescription = array();
			$tagGroups = $this->getTagGroups();
			foreach ($tagGroups as $tagGroup) {
				$tagGroupsDescription[] = $tagGroup->describe(true);
			}
			if (count($tagGroupsDescription) > 0) {
				$description['tagGroups'] = $tagGroupsDescription;
			}
			
			// Tags
			$tagsDescription = array();
			$tags = $this->getTags();
			foreach ($tags as $tag) {
				$tagsDescription[] = $tag->describe(true);
			}
			if (count($tagsDescription) > 0) {
				$description['tags'] = $tagsDescription;
			}

			
				
			if ($returnDescription) {
				return $description;
			}
			else {
				$smConsoleOutput .= 'console.info(\'SMTool ('. $this->getTitle() .')\'); console.dir(JSON.parse(\''. json_encode($description) .'\'));';
			}
		}
		
		
		/**
			Debug method to validate (print out) tool structure. Should be called from smCurrentSite!
		*/
		public function debugBreadcrumbTrail(SMFolder $folder = null) {
			$trail = array();
			
			if ($folder) {
				$value = array(
					'title' => $folder->getTitle(),
					'path'	=> $folder->getPath()
				);
				$trail[] = $value;
				
				// SMTool, call getFolder
				if ($folder->getFolder()) {

					$newFolder = $folder->getFolder();
					$values = $this->debugBreadcrumbTrail($newFolder);

					foreach ($values as $value) {
						$trail[] = $value;
					}
				}
				
				return $trail;
			}
			// the tool
			else {

				$value = array(
					'title' => $this->getTitle(),
					'path'	=> $this->getPath()
				);
				$trail[] = $value;

				if ($this->getFolder()) {
					$folder = $this->getFolder();
					$values = $this->debugBreadcrumbTrail($folder);

					foreach ($values as $value) {
						$trail[] = $value;
					}
				}
			}			
		}
		


//! 
//! Data output methods
//!------------------------------
	
		/**
			Renders HTML for this object (SMTool) using the default Tool Template Set
			Selects the appropriate view based on the object's $view instnace variable (which is modified by getToolView())
		*/
		public function printHTML() {
			global $smCurrentSite, $smCurrentFolder, $smCurrentTool, $smToolTemplateSetPath;
			
			$filePath = '..'. $smToolTemplateSetPath .'/toolType/'. $this->getToolType() .'/'. $this->getToolView() .'.php';
			if (file_exists($filePath)) {
				include($filePath);
			}
			#else {
			#	echo 'missing tool template set: '. $filePath .'<br>';
			#}
		}
		
		public function printHTMLHead() {
			global $smCurrentSite, $smCurrentFolder, $smCurrentTool, $smToolTemplateSetPath, $smConsoleDebug;
			
			// include the Tool Template Set main head.php file
			$filePath = '..'. $smToolTemplateSetPath .'/head.php';
			if (file_exists($filePath)) {
				include($filePath);
			}
			
			// include the Tool views head.php file
			$filePath = '..'. $smToolTemplateSetPath.'/toolType/'. $this->getToolType() .'/'. $this->getToolView() .'.head.php';
			if (file_exists($filePath)) {
				include($filePath);
			}
		}
		
		public function printHTMLBodyLast() {
			global $smCurrentSite, $smCurrentFolder, $smCurrentTool, $smToolTemplateSetPath, $smConsoleOutput, $smConsoleDebug;
			
			// include the Tool Template Set main last.php file
			$filePath = '..'. $smToolTemplateSetPath .'/last.php';
			if (file_exists($filePath)) {
				include($filePath);
			}
			
			// include the Tool view's last.php file
			$filePath = '..'. $smToolTemplateSetPath .'/toolType/'. $this->getToolType() .'/'. $this->getToolView() .'.last.php';
			if (file_exists($filePath)) {
				include($filePath);
			}
			
			// debugging console output
			echo '<script type="text/javascript">'. $smConsoleDebug .'</script>';
			
			// debugging console output
			echo '<script type="text/javascript">'. $smConsoleOutput .'</script>';
		}
	
	
		/**
			Creates a JSON-encoded version of this SMTool object.
			Initially, it mimics a cleaned-up version of the SM4-5 JSON code.
		*/
		public function printJson() {
			echo $json;
		}
	}	
?>