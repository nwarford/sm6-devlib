<?php

/*------------------------------------------------------------------------------------------

File: SMFolder.php 
Summary: The Sitemason® Folder class definition
Version: 6.0
	  
An SMFolder defines a hierarchical collection of SMTools within a site or section of a site.
the "tools" property is an array of SMTool or other SMFolder objects that can be used
to construct the hierarchical nav for this SMFolder.

SMFolder should be instantiated ONLY ONCE (by Sitemason.php) - it is NOT meant for the
developer to instantiate directly.
  
Copyright (C) 2013 Sitemason, Inc. All Rights Reserved. 
 
------------------------------------------------------------------------------------------*/

	class SMFolder extends SMObject {
		private $currentSite;
		private $currentTool;
		private $isSmCurrentFolder;
		private $isRootFolder;
		private $smFoundNavigationDataForCurrentFolderMatch;
		private $request;		// sitejson request
		private $tools;
		private $folder;
		private $path;
		private $URL;			// the URL to this folder within the site.
		
		// folder settings
		private $copyright;
		private $footer;
		private $googleAnalyticsID;
		private $googleVerificationCode;
		private $metaDescription;
		private $metaKeywords;
		private $windowTitle;

		// for navigation needs
		private $includeInNavigation;
		private $includeInSiteMap;

		public function __construct($requestData = null, $responseData = null) {
			global $smConsoleDebug, $smCurrentFolder, $smCurrentTool, $smSiteNavigationData;
			
			$debug = 0;
			if (smDebugToolStructure) { $debug = 1; }


			/*
				Case 1: instantiate smCurrentFolder
				
				We're given no requestData and no responseData (pre-requested data)
				This is the case for which Sitemason.php instantiates an SMFolder for the 
				currently-viewed page with no requestData and no responseData.
				
				In this case, we want to set up the currentFolder, set up the currentTool,
				then check to see if this folder is the root or not.  If the folder is NOT
				the root/site, instantiate another SMFolder for the root and set $this->currentSite 
				to that SMFolder.
				
				NOTE: as of 6.0, we have not implemented the various feeds into the template layer.
				Sitemason could return XML/RSS, or iCalendar feeds.  We need to support that for now,
				so we need to check for an appropriate content-type before anything is processed here.
				
			*/
			if (!$requestData && !$responseData) {
				if ($debug) { echo 'SMFolder constructor Case 1: NO requestData, no responseData<br>'; }
				if (smShouldDebugApiRequests > 1) { $smConsoleDebug .= "console.info('SMFolder constructor Case 1: no requestData, no responseData');";}
				
				
				// make sitejson request on the currently-viewed page
				#$this->URL = pageURL; // this will lop off any query string params!
				$this->URL = currentURL;
				
				$requestData = array('url' => $this->URL, 'feedType' => 'sitejson');
				$this->request = new SMRequest($requestData);
				$responseData = $this->request->getResponseData();
				$responseCode = $this->request->getResponseCode();
				$responseContentType = $this->request->getResponseContentType();

				if ($debug) { echo 'SMFolder Request $responseCode: '. $responseCode .'<br>'; }
				if (smShouldDebugApiRequests > 1) { $smConsoleDebug .= "console.info('SMFolder SMRequest content-type: ". $responseContentType ."');";}
				
				//
				// the folder exists (and it's Sitemason / JSON)
				//
				if ($responseCode == 200 && $responseContentType == 'application/json') {
					if ($debug) { echo 'SMFolder setting properties for this folder...<br>'; }
					
					//
					// set up smCurrentFolder (this folder)
					//
					
					$this->windowTitle = $responseData['window_title'];
					$this->copyright = $responseData['copyright_html'];
					$this->footer = $responseData['footer_html'];
					$this->googleAnalyticsID = $responseData['google_analytics_id'];
					$this->googleVerificationCode = $responseData['google_verification_code'];
					$this->metaDescription = $responseData['meta_description'];
					$this->metaKeywords = $responseData['meta_keywords'];
					
					// manually created from navjson
					if ($responseData['name']) {
						$this->title = $responseData['name'];
					}
					// ususally it's here
					else if ($responseData['content']['element']['settings']['site']['name']) {
						$this->title = $responseData['content']['element']['settings']['site']['name'];	
					}

					// element-based properties
					$element = $responseData['content']['element'];
					$this->id = $element['settings']['site']['id'];
					$this->path = '/'. $element['settings']['site']['path'];
					
					// if there is no path, then this is the root/site folder
					if (!$element['settings']['site']['path']) {
						$this->title = null;
						$this->isRootFolder = true;
					}

					// These aren't set in sitejson for smCurrentFolder calls - they're in navjson.
					$this->includeInNavigation = $responseData['is_navigable'];
					$this->includeInSiteMap = $responseData['include_in_sitemap'];
					$this->slug = $responseData['slug'];
						
					
					//					
					// Overrides from navjson
					//
					if (smDebugToolStructure) { echo '<b>calling navigationDataForCurrentFolder()</b><br />'; }
					$data = $this->navigationDataForCurrentFolder();
					if (smDebugToolStructure) { echo '<b>back to SMFolder constructor case 1.  navigationDataForCurrentFolder() data:</b><pre>'; print_r($data); echo '</pre>'; }
					if ($data) {
						if (smDebugToolStructure) { echo 'getNavigationDataForThisObject() overriding slug, nav, and site map!<br>'; }
						$this->slug = $data['slug'];
						$this->includeInNavigation = $data['is_navigable'];
						$this->includeInSiteMap = $data['include_in_sitemap'];
					}
					
					
					if (smDebugToolStructure) { 
						echo 'SMFolder: id: '. $this->id .' | path: '. $this->getPath() .' |';
						echo ' title: '. $this->getTitle() .' | WindowTitle: '. $this->getWindowTitle() .'<br>'; 
					}
					
					//
					// Set up smCurrentTool
					// 

					if ($debug) { echo '<h3>Instantiating $smCurrentTool (SMTool) from current folder data</h3>'; }
					if (smShouldDebugApiRequests > 1) { $smConsoleDebug .= "console.info('Instantiating \$smCurrentTool (SMTool) from current folder data');";}
					
					$currentlyDisplayedTool = new SMTool(null, $responseData); // create an SMTool from the currentFolder's response data
					$currentlyDisplayedTool->setFolder($this);
					$this->currentTool = $currentlyDisplayedTool;
				}
			}

			/*
				Case 2: We have responseData, but no requestData.  This is the case where this SMFolder is a subfolder 
				within the site and we're instantiating it from the root SMFolder (while creating navigation data, which 
				takes place in Case 1 of this constructor).
				
				Here, responseData will be the block of JSON representing the subfolder found in the  API call.
				
			*/
			else if (!$requestData && $responseData) {
				if ($debug) { echo 'SMFolder constructor CASE 2: $responseData only.  Created in Site Nav.<br>'; }
				if (smShouldDebugApiRequests > 1) { $smConsoleDebug .= "console.info('SMFolder constructor: no requestData, responseData');";}
				

				if ($responseData['app_name'] == 'folder') {			
					$this->id = $responseData['site_instance_id'];
					$this->includeInNavigation = $responseData['is_navigable'];
					$this->includeInSiteMap = $responseData['include_in_sitemap'];
					$this->path = '/'. $responseData['full_path'];
					$this->slug = $responseData['slug'];
					$this->title = $responseData['name'];
					
					//					
					// Overrides from navjson
					//
					#$data = $this->getNavigationDataForThisObject();
					$data = $this->navigationDataForCurrentFolder();
					if (smDebugToolStructure) { echo 'navigationDataForCurrentFolder() data:<pre>'; print_r($data); echo '</pre>'; }
					if ($data) {
						if (smDebugToolStructure) { echo 'navigationDataForCurrentFolder() overriding slug, nav, and site map!<br>'; }
						$this->slug = $data['slug'];
						$this->includeInNavigation = $data['is_navigable'];
						$this->includeInSiteMap = $data['include_in_sitemap'];
					}
				}
			}
			/*
				Case 3: We have requestData.  This is used in the case where the currently-viewed folder is not the root folder
				and a new SMFolder for the root/site is needed.
			*/
			else if ($requestData) {
				if (smDebugToolStructure) { echo 'SMFolder constructor Case 3: only requestData<br>'; }

				$this->request = new SMRequest($requestData);
				$responseData = $this->request->getResponseData();
				$responseCode = $this->request->getResponseCode();
				
				//
				// set up the folder properties
				//
				
				$this->copyright = $responseData['copyright_html'];
				$this->footer = $responseData['footer_html'];
				$this->googleAnalyticsID = $responseData['google_analytics_id'];
				$this->googleVerificationCode = $responseData['google_verification_code'];
				$this->includeInNavigation = $responseData['is_navigable'];
				$this->includeInSiteMap = $responseData['include_in_sitemap'];
				$this->metaDescription = $responseData['meta_description'];
				$this->metaKeywords = $responseData['meta_keywords'];
				$this->slug = $responseData['slug'];
					
				$this->title = $responseData['name'];
				$this->windowTitle = $responseData['window_title'];
				
				// element-based properties
				$element = $responseData['content']['element'];
				
				$this->id = $element['settings']['site']['id'];
				$this->path = '/'. $element['settings']['site']['path'];
				
				
				//					
				// Overrides from navjson
				//
				$data = $this->navigationDataForCurrentFolder();
				if (smDebugToolStructure) { echo 'navigationDataForCurrentFolder() data:<pre>'; print_r($data); echo '</pre>'; }
				if ($data) {
					if (smDebugToolStructure) { echo 'navigationDataForCurrentFolder() overriding slug, nav, and site map!<br>'; }
					$this->slug = $data['slug'];
					$this->includeInNavigation = $data['is_navigable'];
					$this->includeInSiteMap = $data['include_in_sitemap'];
				}
			}
			else {
				echo '<h1>ERROR: SMFolder instantiation error!<h1>';
			}


			/*
				if we have $smCurrentFolder already defined (which should have happened first
				AND if this folder's ID == $smCurrentFolder's ID, then this folder IS smCurrentFolder
				and we should set it so and skip the navigation stuff (which was already created
				when we instantiated $smCurrentFolder.
			*/

			if ($smCurrentFolder && $smCurrentFolder->getID() == $this->getID()) {
				if (smDebugToolStructure) { echo '<h1>smCurrentFolder found: '. $this->getTitle() .'!  Skip</h1>'; }
				$this->tools = $smCurrentFolder->getTools();
				$this->isSmCurrentFolder = true;	// set this flag!
				
				if (smDebugToolStructure) { 
					echo 'top-level tool titles from this->tools (where this = '. $this->getTitle() .'):<pre>'; 
					foreach ($this->getTools() as $tool) {
						echo $tool->getTitle() .'<br>';
					}
					echo '</pre>';
				}
			}
			
			else {
				if (smDebugToolStructure) { echo '$this (<b>'. $this->getTitle() .' | id: '. $this->getID() .'</b>) is NOT the existing smCurrentFolder (or smCurrentFolder has not been fully set up yet).  Create partial objects NOW.<br>'; }

				//
				// FOLDER NAVIGATION
				// 
	
				$this->tools = array();
				
				// find the pages for this SMFolder
				if ($debug) { echo 'calling navigationDataForCurrentFolder:<br>'; }
				$navData = $this->navigationDataForCurrentFolder();
	
				if ($debug) { 
					echo '<h2>Back to SMFolder constructor.  navigationData for smCurrentFolder:</h2>';
					echo 'NAVDATA: <pre>'; print_r($navData); echo '</pre>';
				}
				
				if ($navData) {
					if ($debug) { echo '<h4>Setting up Tool Structure for $this folder ('. $this->getTitle() .') ['. $this->getPath() .']</h4>'; }
					$navDataPages = (array)$navData['pages'];
					$tools = array();
					
					
					//
					// set a few URLs to determine isCurrentlyDisplayed as we go
					//
					
					$siteURLEscaped = preg_replace('/\//','\/',siteURL);
					$pageURLEscaped = preg_replace('/\//','\/',pageURL);
					$browserPath = $_SERVER['PHP_SELF'];
		
					// remove trailing slash (could be added by Sitemason in some cases)
					if (substr($browserPath,-1) == '/') {
						$browserPath = substr($browserPath,0,-1);
					}

					$browserPathEscaped = preg_replace('/\//','\/',$browserPath);
					
					
					//
					// Now iterate through the data...
					//
					
					foreach ($navDataPages as $toolData) {
	
						if ($debug) { echo 'SMSite nav Examining tool: '. $toolData['name'] .' | '. $toolData['app_name'] .' ('. $toolData['slug'] .', '. $toolData['id'] .')</br>'; }
	
						// Create a partial SMFolder object for navigation purposes
						if ($toolData['app_name'] == 'folder') {
							if ($debug) {
								echo '<b>instantiating new SMFolder called "'. $toolData['name'] .'" with toolData:</b><pre>';
								print_r($toolData); echo '</pre>';
							}
	
							/*
								NOTE: Thought about trying to detect smCurrentFolder here, but the id is the site_id, not the instance_id...
								So, it didn't work out.
							*/

							$folder = new SMFolder('', $toolData, 1);
							if (smDebugToolStructure) { echo 'back to $this folder ('. $this->getTitle() .')<br>'; }

							// if the folder we just made was $smCurrentFolder, then scrap what we just made and set things properly now
							if ($folder->isSmCurrentFolder) {
								if (smDebugToolStructure) { echo $folder->getTitle() .' is smCurrentFolder!<br>'; }
								$folder = $smCurrentFolder;
							}

							$folder->setFolder($this);	// make a reference to this SMFolder in the child SMFolder
							

							//
							// Check on isCurrentlyDisplayed
							//
							$folderPathEscaped = preg_replace('/\//','\/',$folder->getPath());
							
							// from 6.0.5
							if (preg_match('/'. $folderPathEscaped .'/', $browserPath) || ($browserPath == '/')) {
								if ($debug) { echo '<b>SETTING isCurrentlyDisplayed</b><br>'; }
								$folder->setIsCurrentlyDisplayed(true);
							}
							
							
							/*
								Handle case for setting index page of the folder to isCurrentlyDisplayed
								When we're just on the folder itself.
								
								If NOTHING in $pages is isCurrentlyDisplayed, set the first page to that.
							*/
							
							$foundChildCurrentlyDisplayed = false;
							$pages = $folder->getTools();
							foreach ($pages as $firstLevelPageOrFolder) {
								if ($firstLevelPageOrFolder->isCurrentlyDisplayed()) {
									if ($debug) { echo '<b>found $firstLevelPageOrFolder isCurrentlyDisplayed: ('. $firstLevelPageOrFolder->getTitle() .')</b><br>'; }
									$foundChildCurrentlyDisplayed = true;
									break;	
								}
							}
							
							$tool0 = $pages[0];
							
							if (!$foundChildCurrentlyDisplayed && $folder->isCurrentlyDisplayed() && !$tool0->isCurrentlyDisplayed()) {
								if (smDebugNavigation) { echo '<b>NOTHING WAS SET TO CURRENTLY DISPLAYED AND PAGE PATH MATCHES!  Set first page.<br /></b>'; }
								$tool0->setIsCurrentlyDisplayed(true);
								$pages[0] = $tool0;
								$folder->setTools($pages);
							}
														
							$tools[] = $folder;
							
							if ($debug) {
								echo '<h3>Done with folder for '. $toolData['name'] .'</h3><hr />';
							}
						}
	
						// Create a partial SMTool object for navigation purposes
						else {
							$path = null;
							
							// don't add any of this stuff to the path if the tool is a link!
							if ($toolData['app_name'] != 'link') {
								if ($this->getPath()) {
									$path = $this->getPath();
								}
		
								// if the first character is a slash (custom link and ?), don't add another one!
								if (substr($toolData['path'],0,1) != '/' && $path != '/') {
									$path .= '/';
								}
							}
							
							$path .= $toolData['path'];
							
							if ($debug) {
								echo 'Creating a new SMTool for '. $toolData['name'] .' (path: '. $path .')</br>';
							}
							
							// convert SM4 nav data into basic SMTool responseData
							$toolResponseData = array(
								'id'					=> $toolData['id'],
								'path'					=> $path,
								'includeInSiteMap'		=> $toolData['include_in_sitemap'],
								'includeInNavigation'	=> $toolData['is_navigable'],
								'slug'					=> $toolData['slug'],
								'title'					=> $toolData['name'],
								'toolType'				=> $toolData['app_name']
							);
	
							$tool = new SMTool('', $toolResponseData);
							$tool->setFolder($this);
							
							//
							// Check on isCurrentlyDisplayed
							//
							$toolPathEscaped = preg_quote($tool->getPath(), '/');

							if (preg_match('/'. $toolPathEscaped .'/', $browserPath) || ($browserPath == '/')) {
								if ($debug) { echo '<b>SETTING isCurrentlyDisplayed</b><br>'; }
								$tool->setIsCurrentlyDisplayed(true);
							}
							
							$tools[] = $tool;
							if ($debug) { 
								$title = $this->getTitle();
								if (!$title) { $title = "Site"; }
								echo 'SMTool "'. $tool->getTitle() .'" was added to "'. $title .'"\'s tools.<br>';
							}
						}
					}
					
					$this->tools = $tools;
					
					if ($debug) { echo '<h4>Finished Setting up Tool Structure for '. $this->getTitle() .'!</h4>'; }
				}
			}

			
			if (smDebugToolStructure) { echo 'SMFolder: id: '. $this->id .' | path: '. $this->path .' | title: '. $this->title .'<br>';	}
			
			if ($debug) { 
				echo '<b>finished with SMFolder constructor</b><hr>';
				if ($this->isSmCurrentFolder) {
					echo '<h1>SM CURRENT FOLDER WAS FOUND!</h1>';
				}
			}
		}
		
		
		public function getFolder() {
			return $this->folder;
		}
		
		public function setFolder(SMFolder $smFolder) {
			$this->folder = $smFolder;
		}
		
		public function getPath() {
			return $this->path;
		}

		public function getRequest() {
			return $this->request;
		}
		
		public function getSlug() {
			return $this->slug;
		}
		
		public function getTools() {
			return (array)$this->tools;
		}
		
		private function setTools(array $tools) {
			$this->tools = $tools;
		}
		
		public function getToolType() {
			return 'folder';
		}
		
		
		/*	
			Folder Settings
			
			For a few of these, we want this folder to override any data that is further up in the hierarchy.
			For each value here, if there is no data set, but this Folder has a parent Folder, return the parent 
			Folder's value instead.
		*/
		public function getCopyright() {
			$returnValue = $this->copyright;
			if (!$returnValue && !$this->isRootFolder()) {
				$parentFolder = $this->getFolder();
				if ($parentFolder) {
					$returnValue = $parentFolder->getCopyright();	
				}
			}
			
			return $returnValue;
		}
		
		public function getFooter() {
			$returnValue = $this->footer;
			if (!$returnValue && !$this->isRootFolder()) {
				$parentFolder = $this->getFolder();
				if ($parentFolder) {
					$returnValue = $parentFolder->getFooter();	
				}
			}
			
			return $returnValue;
		}
		
		public function getGoogleAnalyticsID() {
			$returnValue = $this->googleAnalyticsID;
			if (!$returnValue && !$this->isRootFolder()) {
				$parentFolder = $this->getFolder();
				if ($parentFolder) {
					$returnValue = $parentFolder->getGoogleAnalyticsID();	
				}
			}
			
			return $returnValue;
		}
		
		public function getGoogleVerificationCode() {
			$returnValue = $this->googleVerificationCode;
			if (!$returnValue && !$this->isRootFolder()) {
				$parentFolder = $this->getFolder();
				if ($parentFolder) {
					$returnValue = $parentFolder->getGoogleVerificationCode();	
				}
			}
			
			return $returnValue;
		}
		
		public function getMetaDescription() {
			$returnValue = $this->metaDescription;
			if (!$returnValue && !$this->isRootFolder()) {
				$parentFolder = $this->getFolder();
				if ($parentFolder) {
					$returnValue = $parentFolder->getMetaDescription();	
				}
			}
			
			return $returnValue;
		}
		
		public function getMetaKeywords() {
			$returnValue = $this->metaKeywords;
			if (!$returnValue && !$this->isRootFolder()) {
				$parentFolder = $this->getFolder();
				if ($parentFolder) {
					$returnValue = $parentFolder->getMetaKeywords();	
				}
			}
			
			return $returnValue;
		}
		
		public function getWindowTitle() {
			return $this->windowTitle;
		}
		
		
//! 
//! Navigation methods
//!------------------------------
		
		/**
			Returns navigation data as an array of SMTool and SMFolder objects
		*/
		public function getNavigationTools($shouldFlattenArray = false) {
			if (smDebugNavigation) { echo 'tools:'; $this->debugTools(); }
			
			$tools = $this->tools;
			$navCounter = 0; // hack to count the pages.  this is in place because URLs for pages in folders are not complete
			$navigation = $this->getNavAsArray($tools, $navCounter);
			
			if ($shouldFlattenArray) {
				$navigation = $this->flattenNavigationToolsArray($navigation);
			}
			
			return $navigation;
		}
		
		/**
			Returns true/false depending on whether this folder has navigation tools (getNavigationTools > 0)
		*/
		public function hasNavigationTools() {
			$returnVal = false;
			
			if (count($this->getNavigationTools()) > 0) {
				$returnVal = true;
			}
			
			return $returnVal;
		}
		
		private function flattenNavigationToolsArray($navigation) {
			$flatArray = array();
			
			foreach ($navigation as $object) {
				// folder that has sub-tools
				if (get_class($object) == 'SMFolder') {
					$folderTools = $object->getTools();
					if (count($folderTools > 0)) {
						$folderArray = $this->flattenNavigationToolsArray($folderTools);
						$flatArray = array_merge($flatArray,$folderArray);
					}
				}
				// must be a tool
				else if (get_class($object) == 'SMTool') {
					$flatArray[] = $object;
				}
			}
			
			return $flatArray;
		}
		
		
		/**
			private helper function for getNavigationTools()
		*/
		private function getNavAsArray($tools = null, $navCounter) {
			$debug = 0;
			if (smDebugNavigation) { $debug = 1; }
			if ($debug) { echo 'SMFolder getNavAsArray() with navCounter: '. $navCounter .'<br>'; }

			// if there are no tools given, look them up.
			if (!$tools) { $tools = $this->getTools(); }

			$navigation = array();
			
			if ($debug) { echo 'SMFolder has: '. count($tools) .' tools/folders to examine.<br>'; }
			
			// iterate through the tools in this SMFolder
			foreach ($tools as $toolOrFolder) {
				
				if ($debug) {
					echo '<hr /><b>examining tool: '. $toolOrFolder->getTitle() .'</b> (class: '. get_class($toolOrFolder) .')';
					if ($toolOrFolder->shouldIncludeInNavigation()) { echo ' INCLUDE!'; } else { echo ' DO NOT INCLUDE!'; }
					echo '<br>'; 
				}
					
				// Case: SMTool
				if (get_class($toolOrFolder) == 'SMTool' && $toolOrFolder->shouldIncludeInNavigation()) {
					$navigation[] = $toolOrFolder;
				}
				
				// Case: SMFolder
				else if (get_class($toolOrFolder) == 'SMFolder' && $toolOrFolder->shouldIncludeInNavigation()) {
					// if we don't check for tools here, the case of a Folder with no pages becomes an infinite loop
					if (count($toolOrFolder->getTools()) > 0) {
						$pages = $this->getNavAsArray($toolOrFolder->getTools(), $navCounter);

						/*
							we must clone this and setTools on the new object! Otherwise we're overriding the original folder's 
							tools, which causes issues if some of those tools are not in navigation!
						*/
						$newFolder = clone $toolOrFolder;
						$newFolder->setTools($pages);
						$navigation[] = $newFolder;
					}
				}
				
				$navCounter++;
			}
			
			return $navigation;
		}
		
		
		/**
			Examines a flattened navigation array for this Folder, iterates through each tool, comparing
			it to the currently-displayed tool ($smCurrentTool).  If a match is found, then it returns
			the next tool in that structure, otherwise it returns null.
			
			By default, it does not loop, so if you're on the first page of the array, null will be returned.
			Pass "true" as a parameter to force the array to loop.
			
			Essentially, this method flattens the folder/site structure, then returns the next tool in the list.
		*/
		public function getNextNavigationTool($shouldLoop = false) {
			global $smCurrentTool, $smCurrentSite;
			
			$nextTool = null;
			$navTools = $this->getNavigationTools(true);
			
			$i = 0;
			foreach ($navTools as $navTool) {
				// found a match based on the Tool's UID
				if ($navTool->getID() == $smCurrentTool->getID()) {
					// if a next tool exists...
					if ($navTools[$i + 1]) {
						$nextTool = $navTools[$i + 1];
					}
					// else if we should loop
					else if ($shouldLoop) {
						$nextTool = $navTools[0];
					}
					
					break;
				}
				$i++;
			}
			
			/*
				We need to make a new SMTool.  We have a slug, but what if the next tool doesn't have a slug?
				Therefore, we need to instantiate a new SMTool manually.
			*/
			if ($nextTool) {
				$options = array(
					'URL' => $nextTool->getURL()
				);
				
				$nextTool = $smCurrentSite->getToolWithOptions($options);
			}
			
			return $nextTool;
		}
		
		
		/**
			Examines a flattened navigation array for this Folder, iterates through each tool, comparing
			it to the currently-displayed tool ($smCurrentTool).  If a match is found, then it returns
			the previous tool in that structure, otherwise it returns null.
			
			By default, it does not loop, so if you're on the first page of the array, null will be returned.
			Pass "true" as a parameter to force the array to loop.
			
			Essentially, this method flattens the folder/site structure, then returns the previous tool in the list.
		*/
		public function getPreviousNavigationTool($shouldLoop = false) {
			global $smCurrentTool, $smCurrentSite;
			
			$previousTool = null;
			$navTools = $this->getNavigationTools(true);
			
			$i = 0;
			foreach ($navTools as $navTool) {
				// found a match based on the Tool's UID
				if ($navTool->getID() == $smCurrentTool->getID()) {
					// if a previous tool exists...
					if ($navTools[$i - 1]) {
						$previousTool = $navTools[$i - 1];
					}
					// else if we should loop
					else if ($shouldLoop) {
						$last = count($navTools) - 1;
						$previousTool = $navTools[$last];
					}
					break;
				}
				$i++;
			}
			
			/*
				We need to make a new SMTool.  We have a slug, but what if the next tool doesn't have a slug?
				Therefore, we need to instantiate a new SMTool manually.
			*/
			if ($previousTool) {
				$options = array(
					'URL' => $previousTool->getURL()
				);
				
				$previousTool = $smCurrentSite->getToolWithOptions($options);
			}
			
			return $previousTool;
		}

		
		public function isRootFolder() {
			return $this->isRootFolder;
		}
		

		public function shouldIncludeInNavigation() {
			return $this->includeInNavigation;
		}
		

		public function shouldIncludeInSiteMap() {
			return $this->includeInSiteMap;
		}


		/**
			Construct the full URL to this folder
		*/
		public function getURL() {
			return siteURL . $this->getPath();
		}


//! 
//! Other methods
//!------------------------------
		
		
		public function debugNavigation($tools = null) {
			if (!$tools) { echo '<h1>DEBUG NAVIGATION</h1>'; }
			echo '<ul style="list-style-type: circle; padding: 4px 8px; margin: 4px 8px;">';
			
			if (!$tools) {
				$tools = $this->getNavigationTools();
			}
			
			foreach ($tools as $tool) {
				echo '<li>';
				echo $tool->getTitle() .' | ('. $tool->getSlug() .', '. $tool->getID() .') ['. $tool->getPath() .'] ';
				if ($tool->shouldIncludeInNavigation()) { echo ' [ INCLUDE IN NAV ]'; } else { echo '[do NOT include in nav]'; }
				if ($tool->isCurrentlyDisplayed()) { echo ' [ IS CURRENTLY DISPLAYED ]'; }
				if (get_class($tool) == 'SMFolder' && $tool->getTools()) {
					$this->debugNavigation($tool->getTools());
				}
				
				echo '</li>';
			}
			echo '</ul>';
		}
		
		/**
			Debug method to validate (print out) tool structure. Should be called from smCurrentSite!
		*/
		public function debugTools($tools = null) {
			if (!$tools) { echo '<h1>DEBUG TOOL HIERARCHY</h1>'; }
			
			echo '<ul style="list-style-type: circle; padding: 4px 8px; margin: 4px 8px;">';
			
			if (!$tools) {
				$tools = $this->getTools();
			}
			
			foreach ($tools as $tool) {
				echo '<li>';
				echo $tool->getTitle() .' | ('. $tool->getSlug() .', '. $tool->getID() .') ['. $tool->getPath() .'] ';
				if ($tool->shouldIncludeInNavigation()) { echo ' [ INCLUDE IN NAV ]';} else { echo '[do NOT include in nav]'; }
				if (get_class($tool) == 'SMFolder' && $tool->getTools()) {
					$this->debugTools($tool->getTools());
				}
				
				echo '</li>';
			}
			echo '</ul>';
		}
		
		
		
		/**
			Creates a cumulative window title from the Folder's level and up
		*/
		public function getCumulativeWindowTitle($delimiter = '|') {

			// Get the tool's window title or use the tool title
			$folderWindowTitle = $this->getWindowTitle();
			if (!$folderWindowTitle) {
				$folderWindowTitle = $this->getTitle();
			}
			
			$windowTitle = $folderWindowTitle;
			
			// Get the parent folder's window title
			$folder = $this->getFolder();
			$folderWindowTitle = null;
			if ($folder) {
				$folderWindowTitle = $folder->getCumulativeWindowTitle();
			}
			
			if ($folderWindowTitle) {
				$windowTitle .= ' '. $delimiter .' '. $folderWindowTitle;
			}
			
			return $windowTitle;
		}
		
		
		/**
			Returns an SMTool object for the given options (given as an associative array).
			Essentially just a wrapper for new SMTool($requestData).  NOTE: should be called
			from $smCurrentSite only to ensure proper function.  TODO: debugging for case
			when called from SMFolder other than $smCurrentSite.
			
			Added 6.0.3
		*/
		public function getToolWithOptions(array $options) {
			global $smConsoleDebug;
			
			$tool = new SMTool($options);
			return $tool;
		}
		
		
		
		/**
			looks through this SMSite's tools for the tool with the given slug,
			creates a SMTool instance and returns it.  Used by SMRequest when creating
			an SMTool using a slug.
		*/
		public function getToolWithSlug($slug) {
			global $smConsoleDebug;
			
			$tools = (array)$this->getTools();
			$returnVal = null;
			$debug = 0;
			
			if ($debug) { echo '<b>Num tools in '.  $this->getTitle() .': '. count($tools) .'</b><br />'; }
			
			
			foreach ($tools as $tool) {
				$class = get_class($tool);
				if ($debug) { echo 'Examining tool: '. $tool->getTitle() .'<br>'; }
				
				// if it's an SMTool object, look for the slug
				if ($class == 'SMTool' && $tool->getSlug() == $slug) {
					if ($debug) { echo 'getToolWithSlug() located '. $slug .'<br><pre>'; print_r($tool); echo '</pre>'; }
					
					// get the full URL for the SMRequest (within new SMTool)
					if ($debug) { echo 'match found. Calling SMTool->getURL()<br>'; }
					$fullURL = $tool->getURL();
					if ($debug) { echo 'fullURL: '. $fullURL .'<br>'; }
					
					// make an SMRequest to get this full SMTool
					$requestData = array(
						'url'	=> $fullURL
					);
					if (smShouldDebugApiRequests > 5) { 
						$smConsoleDebug .= ' console.info("getToolWithSlug('. $slug .') instantiating SMTool with URL: '. $requestData['url'] .'");'."\n"; 
					}
					$tool = new SMTool($requestData);
					
					if ($debug) { echo 'GOT TOOL.  title: '. $tool->getTitle() .'<br>'; }
					
					$returnVal = $tool;
					break;
				}
				else if ($class == 'SMFolder') {
					if ($debug) { echo 'found new folder: '. $tool->getTitle() .'<br>'; }
					$tool = $tool->getToolWithSlug($slug);
					if ($tool) {
						$returnVal = $tool;
						break;
					}
				}
			}
			
			return $returnVal;
		}


//! 
//! Non-published methods
//!------------------------------
		public function getCurrentSite() {
			return $this->currentSite;
		}
		
		/**
			returning null means that the currently-displayed folder is the site itself
		*/
		public function getCurrentFolder() {
			return $this->currentFolder;
		}
		
		public function getCurrentTool() {
			return $this->currentTool;
		}
		
		public function getCurrentURL() {
			$requestURI = $_SERVER['REQUEST_URI'];
			
			// if it's over SSL, then we need to remove a hack to the Sitemason Apache setup
			if (smProtocol == 'https') {
				// ?HTTPS=on&SomethingEelse=
				if (preg_match('/HTTPS=on&/',$requestURI)) {
					$requestURI = preg_replace('/HTTPS=on&/','',$requestURI);
				}
				// ?HTTPS=on
				else {
					$requestURI = preg_replace('/\?HTTPS=on/','',$requestURI);
				}
			}
			
			$currentURL = $protocol .'://'. smCurrentHost . $requestURI;
			
			return $currentURL;
		}
		
		/**
			Traverses $smSiteNavigationData looking for this SMFolder.  When
			found, it returns that block (the folder data block)
		*/
		private function navigationDataForCurrentFolder($incomingData = null) {
			global $smSiteNavigationData;
			$returnData = null;
			
			// choose navData based on what we got (since this is a recursive method)
			if ($incomingData) {
				if (smDebugToolStructure) { echo 'navigationDataForCurrentFolder found incomingData<br>'; }
				$navData = $incomingData;
			}
			else {
				if (smDebugToolStructure) { echo 'navigationDataForCurrentFolder was NOT called recursively.  Using smSiteNavigtationData (navjson).<br>'; }
				$navData = $smSiteNavigationData;
			}

			if (smDebugToolStructure) { 
				echo 'navigationDataForCurrentFolder name: '. $navData['name'] .' | app_name: '. $navData['app_name'] .' | ';
				echo 'id: '. $navData['site_instance_id'] .' | this id: '. $this->getID() .'<br>';
			}


			// check to see if we're at the site level
			if ($navData['app_name'] == 'site' && $navData['site_instance_id'] == $this->getID()) {
				if (smDebugToolStructure) { echo 'AT SITE LEVEL!  Return now.<br>'; }
				$returnData = $navData;
			}
			
			/*
				comb through this level of navData. If app_name = folder && site_instance_id == $this->id
				then we've found the match
			*/
			else {
				// start looking for folders
				foreach ($navData['pages'] as $pageOrFolder) {
				
					// if we found a match, don't continue to search for another...
					if ($this->smFoundNavigationDataForCurrentFolderMatch) {
						if (smDebugToolStructure) { echo '<b>Continued with loop.  This folder: '. $pageOrFolder['name'] .'. TERMINATING LOOP.</b><br>'; }
						break; 
					}
					
					// found a folder
					if ($pageOrFolder['app_name'] == 'folder') {
						if (smDebugToolStructure) { echo 'Examining Folder with id: '. $pageOrFolder['id'] .' | site_instance_id: '. $pageOrFolder['site_instance_id'] .' | this->id: '. $this->getID() .'<br>'; }
						
						// found the folder
						if ($pageOrFolder['site_instance_id'] == $this->getID()) {
							if (smDebugToolStructure) { echo '<b>FOUND MATCH: '. $pageOrFolder['name'] .'</b><br>'; }
							$this->smFoundNavigationDataForCurrentFolderMatch = true;
							
							$returnData = $pageOrFolder;
							break;
						}
						// else if there are pages in the folder, recursively check those
						else if ($pageOrFolder['pages']) {
							if (smDebugToolStructure) { echo 'calling navigationDataForCurrentFolder with subpages<br>'; }
							$returnData = $this->navigationDataForCurrentFolder($pageOrFolder);
						}
					}
				}
			}
			
			// if we're back on the root call, reset smFoundNavigationDataForCurrentFolderMatch because this method is used more than once...
			if (!$incomingData) {
				$this->smFoundNavigationDataForCurrentFolderMatch = false;
			}
			
			return $returnData;
		}
	}
?>