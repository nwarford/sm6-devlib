<?php

/*------------------------------------------------------------------------------------------

File: SMRequest.php 
Summary: The SMRequest class definition
Version: 6.0
	  
Queries Sitemason® CMS App Layer for data and stores in $responseData;
  
Copyright (C) 2013 Sitemason, Inc. All Rights Reserved. 
 
------------------------------------------------------------------------------------------*/

class SMRequest extends SMObject {

	private $dataURL;	// holds the URL to get the data from Sitemason
	private $cacheURL;	// URL key for caching
	private $requestType;
	private $requestMethod; // post or get

	private $responseCode;			// HTTP response code
	private $responseContentType;	// HTTP content-type
	private $responseData;			// JSON or XML/RSS
	private $responseError;
	
	// debugging properties
	private $convertedDataURL;

	/**
		If only $requestData is given, an SMRequest will be constructed for that request data
		If $responseData is given (as is the case when we have all of the data
		for the currently-viewed Tool from the currently-viewed Folder), then the object will
		be instantiated, but no cURL/cache call will be made
	*/	
	public function __construct($requestData, $responseData = null) {
		global $smCurrentSite, $smConsoleDebug;
		
		$this->requestData = $requestData;
		
		// if we got response data, set it now and return
		if ($responseData) {
			$this->responseData = $responseData;
			return;
		}
		
		$host = smCurrentHost;
		$scriptURL = $_SERVER['SCRIPT_URL'];
		$queryString = $_SERVER['QUERY_STRING'];
		$feedType = 'sitejson';

		$debug = 0;
		if (smDebugToolStructure || smShouldDebugApiRequestsInOutput) { $debug = 1; }
		
		if ($debug) {
			if ($debug) { echo '<hr /> --- new SMRequest ---<br />'; }
		}
		
		
		/*
			if we have requestData, that means that the template developer is defining a specific
			query. We need to parse the requestData and create a dataUrl for this SMRequest to 
			send to the API.
		*/
		if ($requestData) {
		
			if ($debug) {
				echo 'requestData: <pre>';
				print_r($requestData);
				echo '</pre>';
			}
		

			// if the request data is a slug or is NOT an array: look for a slug
			/* changed 6.0.2
			if ($requestData['slug'] || !is_array($requestData)) {
				if (!is_array($requestData)) {
					$slug = $requestData;
				}
				else {
					$slug = $requestData['slug'];
				}
			
				// partial tool data (nav-section only)
				$tool = $smCurrentSite->getToolWithSlug($slug);
				$urlPieces = parse_url($tool->getURL());
			}
			*/
			if (!is_array($requestData)) {
				$slug = $requestData;
			
				// partial tool data (nav-section only)
				$tool = $smCurrentSite->getToolWithSlug($slug);
				$urlPieces = parse_url($tool->getURL());
			}
			
			// Typical scenario: the requestData is an array
			else {
				$scriptURL = null;
				
				/*
					respond to URL or url as a param.  Until 6.0.10, "url" was the key, but we should probably make that "URL" before
					publishing getToolWithOptions()
				*/
				if ($requestData['URL']) {
					$requestData['url'] = $requestData['URL'];
				}
				
				/*
					if the URL has a ? in it, then treat this as a legacy case!
					Use the URL pretty much as-is
				*/
				if ($requestData['url'] && preg_match('/\?/',$requestData['url'])) {
					if ($debug) { echo 'SMRequest URL Case 1 (contains query string): '. $requestData['url'] .'<br>'; }
					if (smShouldDebugApiRequests > 5) { $smConsoleDebug .= ' console.info("SMRequest URL Case 1 (contains query string): '. $requestData['url'] .'");'."\n"; }
					$urlPieces = parse_url($requestData['url']);

					if ($debug) {
						echo '<div class="smDebugContainer">';
						echo 'Parsed URL: ';
						print_r($urlPieces);
						echo '</div>';
					}
					
					if ($urlPieces['host']) {
						$host = $urlPieces['host'];
					}
					
					if ($urlPieces['path']) {
						$scriptURL = $urlPieces['path'];
						
						/*
							tack on "/list" if it's not a Page Builder tool, didn't have a view set, and doesn't alreay have "list" in the URL
							It is critical to establish that this is a list-capable tool before NOT a Page tool (or anything else that 
						*/
						
						/* THIS IS BAD.  Why are we forcing list?  We can't force /list on page or calendar or else Sitemason won't resopnd...
						if (!$isPageBuilder && !$requestData['view'] && !preg_match('/list/',$scriptURL)) {
						
							// if the last character is not a slash, add it now
							if (substr($scriptURL,-1) != '/') { $scriptURL .= '/'; }
							$scriptURL .= 'list';
						}
						*/
					}
					
					// override queryString: without this, getToolWithSlug calls tack on whatever API params are in the query string
					$queryString = $urlPieces['query'];
					
					// if we have a query string, strip out any feedTypes from it!
					if ($queryString) {
						if ($debug) { echo 'queryString: '. $queryString .'<br>'; }
						$queryString = preg_replace('/&?tooljson/','',$queryString);
						$queryString = preg_replace('/&?sitejson/','',$queryString);
					}
				}
				
				/*
					New-style request: parameters				
				*/
				else {
					if ($debug) { 
						echo 'SMRequest URL Case 2 (no query string in url): '. $requestData['url'] .'<br>'; 
						echo '$requestData<pre>'; print_r($requestData); echo '</pre>';
					}
					if (smShouldDebugApiRequests > 5) { 
						$smConsoleDebug .= ' console.info("SMRequest URL Case 2 (NEW STYLE / no query string): '. $requestData['url'] .'");'."\n"; 
					}
					
					// We need the URL.  It was either passed as "url" or we have a "slug" and should look it up
					if ($requestData['slug'] && !$requestData['url']) {

						// partial tool data (nav-section only)
						$tool = $smCurrentSite->getToolWithSlug($requestData['slug']);
						$url = $tool->getURL();
					}
					else if ($requestData['url']) {
						$url = $requestData['url'];
						unset($requestData['url']);
					}
					
					// no slug or URL!
					else {
						throw new Exception('No slug or URL given to SMRequest / SMFolder->getToolWithOptions()');
					}

					
					
					// get the path
					$urlPieces = parse_url($url);
					
					// update the feedType, if present in the requestData
					if ($requestData['feedType']) {
						$feedType = $requestData['feedType'];
						unset($requestData['feedType']);
					}
					// if no feedType was defined, since this is a specific query, we can assume it's tooljson...
					else {
						$feedType = 'tooljson';
					}
					
					if ($debug) {
						echo '<div class="smDebugContainer">';
						echo 'Parsed URL: ';
						print_r($urlPieces);
						echo '</div>';
					}
					
					
					// Set the initial scriptURL
					$scriptURL = $urlPieces['path'];
					
					// if the scriptURL ends with a slash, remove it now.
					$scriptURL = rtrim($scriptURL, '/');
					
					
					// VIEW: calendarList (cal_list), calendarGrid (cal_grid), List (list), rss, ics, detail, 
					if ($requestData['view']) {
						$scriptURL .= '/'. $requestData['view'];
						unset($requestData['view']);
					}
					// force list view if certain other params are set
					else {
						// limit
						if ($requestData['limit']) {
							$scriptURL .= '/list';
						}
						
					}
					
					
					// LIMIT: 
					
					if ($requestData['limit']) {
						$scriptURL .= '/set/'. $requestData['limit'];
					}
					
					
					// OFFSET: offset
					if ($requestData['offset'] && $requestData['limit']) {
						$scriptURL .= '/'. $requestData['offset'];
						unset($requestData['limit']);
						unset($requestData['offset']);
					}
					else {
						unset($requestData['limit']);
					}
					
					//
					// Convert query-string params
					// 
					
					$queryString = array();
					
					// ORDER (sort)
					if ($requestData['orderBy']) {
						$sortQueryString = $requestData['orderBy'];
						unset($requestData['orderBy']);
						
						// SORT (second param to sort)
						if ($requestData['sort']) {
							$sortQueryString .= ','. $requestData['sort'];
							unset($requestData['sort']);	
						}
						
						$queryString['sort'] = $sortQueryString;
					}
					
					// QUERY: q
					if ($requestData['q']) {
						$queryString['q'] = $requestData['q'];
						unset($requestData['q']);
					}
					
					
					// tag.title => xtags, xatags
					if ($requestData['SMTag.title']) {
						// array of tags...
						if (is_array($requestData['SMTag.title'])) {
							$logic = 'OR';
							
							// look for AND logic
							$tags = array();
							foreach ($requestData['SMTag.title'] as $tag) {
								if ($tag == 'AND') {
									$logic = 'AND';
								}
								else {
									$tags[] = urlencode($tag);
								}
							}
							
							//
							// now construct the data
							//
							
							// AND logic
							if ($logic == 'AND') {
								$queryString['xatags'] = $tags;
							}
							// OR logic
							else {
								$queryString['xtags'] = $tags;
							}		
						}
						else {
							$queryString['xtags'] = urlencode($requestData['SMTag.title']);
						}
						
						unset($requestData['SMTag.title']);
					}
					
					//
					// Basic conversions
					//
					
					// Item title
					if ($requestData['SMItem.title']) {
						$queryString['title'] = $requestData['SMItem.title'];
						unset($requestData['SMItem.title']);
					}
					
					// Item subtitle
					if ($requestData['SMItem.subtitle']) {
						$queryString['subtitle'] = $requestData['SMItem.subtitle'];
						unset($requestData['SMItem.subtitle']);
					}
					
					// Item alternateURL
					if ($requestData['SMItem.alternateURL']) {
						$queryString['alternate_url'] = $requestData['SMItem.alternateURL'];
						unset($requestData['SMItem.alternateURL']);
					}
					
					// Item assignment_id
					if ($requestData['SMItem.assignmentID']) {
						$queryString['assignment_id'] = $requestData['SMItem.assignmentID'];
						unset($requestData['SMItem.assignmentID']);
					}
					
					// Item old custom fields
					if ($requestData['SMItem.customFieldKey1']) {
						$queryString['custom_field_1'] = $requestData['SMItem.customFieldKey1'];
						unset($requestData['SMItem.customFieldKey1']);
					}
					if ($requestData['SMItem.customFieldKey2']) {
						$queryString['custom_field_2'] = $requestData['SMItem.customFieldKey2'];
						unset($requestData['SMItem.customFieldKey2']);
					}
					if ($requestData['SMItem.customFieldKey3']) {
						$queryString['custom_field_3'] = $requestData['SMItem.customFieldKey3'];
						unset($requestData['SMItem.customFieldKey3']);
					}
					if ($requestData['SMItem.customFieldKey4']) {
						$queryString['custom_field_4'] = $requestData['SMItem.customFieldKey4'];
						unset($requestData['SMItem.customFieldKey4']);
					}
					if ($requestData['SMItem.customFieldKey5']) {
						$queryString['custom_field_5'] = $requestData['SMItem.customFieldKey5'];
						unset($requestData['SMItem.customFieldKey5']);
					}
					if ($requestData['SMItem.customFieldKey6']) {
						$queryString['custom_field_6'] = $requestData['SMItem.customFieldKey6'];
						unset($requestData['SMItem.customFieldKey6']);
					}
					if ($requestData['SMItem.customFieldKey7']) {
						$queryString['custom_field_7'] = $requestData['SMItem.customFieldKey7'];
						unset($requestData['SMItem.customFieldKey7']);
					}
					if ($requestData['SMItem.customFieldKey8']) {
						$queryString['custom_field_8'] = $requestData['SMItem.customFieldKey8'];
						unset($requestData['SMItem.customFieldKey8']);
					}
					
					// Item isImportant
					if ($requestData['SMItem.isImportant']) {
						$queryString['is_important'] = 1;
						unset($requestData['SMItem.isImportant']);
					}
					
					// Item ownerID
					if ($requestData['SMItem.ownerID']) {
						$queryString['owner_id'] = $requestData['SMItem.ownerID'];
						unset($requestData['SMItem.ownerID']);
					}
					
					// Item submitterID
					if ($requestData['SMItem.submitterID']) {
						$queryString['submitter_id'] = $requestData['SMItem.submitterID'];
						unset($requestData['SMItem.submitterID']);
					}
					
					// Convert anything remaining as-is
					/* causes issues
					foreach ($requestData as $key => $value) {
						$queryString[$key] = $value;
					}
					*/

					// Construct the query string and add it to $scriptURL
					$finalQueryString = null;
					$i = 0;
					foreach ($queryString as $key => $value) {
						// if the value is an array, then we need to convert it.
						if (is_array($value)) {
							foreach ($value as $aValue) {
								if ($i > 0) { $finalQueryString .= '&'; }
								$finalQueryString .= $key .'='. $aValue;
								$i++;
							}
						}
						else {
							if ($i > 0) { $finalQueryString .= '&'; }
							$finalQueryString .= $key .'='. $value;
							$i++;
						}
					}
					
					$queryString = (string)$finalQueryString;
					
					if ($debug) { echo 'Final assembled $queryString: '. $queryString .'<br>'; }
				}
			}
		}
		
		
		
		// if scriptURL is just "/" (for the index page of the site), remove it
		// this doesn't cache, which is probably an SM5 bug, but this workaround works...
		if ($scriptURL == '/') { $scriptURL = null; }
		
		// store data and cache URLs. Note: cacheURL is always stored as http.
		$this->dataURL = 'http://'. smAppServer .'/vhost'. smVersion .'/'. $host . $scriptURL;
		$this->cacheURL = 'http://'. $host . $scriptURL;


		/*
			Determine if we should add the feedType or not.  There are a few cases where we shouldn't:
			"rss", ".ics", Foxycart data feed
		*/
		
		$shouldAddFeedType = true;
		if (preg_match('/\/rss/',$this->dataURL) || preg_match('/\.ics/',$this->dataURL) || ($_POST['FoxyData'] && $feedType != 'navjson')) {
			$shouldAddFeedType = false;
			
			// remove the feedType, or else rss and other back-end Sitemason feeds won't work!
			$feedType = null;
		}

		if ($queryString) {
			$this->dataURL .= '?'. $queryString;
			if ($shouldAddFeedType) { $this->dataURL .= '&'. $feedType; }
			
			$this->cacheURL .= '?'. $queryString;
			if ($shouldAddFeedType) { $this->cacheURL .= '&'. $feedType; }
		}
		else if ($shouldAddFeedType) {
			$this->dataURL .= '?'. $feedType;
			$this->cacheURL .= '?'. $feedType;
		}

		$this->convertedDataURL = preg_replace('/(\w+\.)?'. smAppServer .'/','dev.sitemason.com',$this->dataURL);

		if ($debug) {
			echo 'SMRequest FINAL URLs:<br>';
			echo 'dataURL: '. $this->convertedDataURL .'<br>';
			echo 'cacheURL: '. $this->cacheURL .'<br>';
		}
	
		if (smShouldDebugApiRequests) {
			$devConvertedDataURL = preg_replace('/(\w+\.)?'. smAppServer .'/', 'dev.sitemason.com', $this->dataURL);
			$smConsoleDebug .= ' var devConvertedDataURL = "'. $devConvertedDataURL .'";'."\n";
			$smConsoleDebug .= ' var devCacheURL = "'. $this->cacheURL .'";'."\n";
			$smConsoleDebug .= ' console.info("SMRequest dataURL: "+ devConvertedDataURL);'."\n";
			$smConsoleDebug .= ' console.info("SMRequest cacheURL: "+ devCacheURL);'."\n";
			
			// print it out now if we've already printed the consoleDebug in the document HEAD
			/* breaks things
			if (smDidPrintHTMLHead) {
				echo '<script type="text/javascript>'. $smConsoleDebug .'</script>';  // breaks things
			}
			*/
		}
		
		
		// look for POST'ed data
		if ($_POST) {
			$this->requestMethod = 'POST';
		}
		else {
			$this->requestMethod = 'GET';
		}
		
		
		// automatically make the request
		$this->makeRequest();
		
		//
		// error check the results based on feedType!
		//
		
		// JSON
		if ($this->getResponseCode() == 200 && preg_match('/json/i',$feedType) && $this->getResponseContentType() != 'application/json') {
			$message = 'SMRequest error. '. $this->getDataURL() .' expected content with type: application/json, but got: '. $this->getResponseContentType();
			new SMError($message);
		}
		// XML
		else if ($this->getResponseCode() == 200 && preg_match('/xml/i',$feedType) && $this->getResponseContentType() != 'text/xml') {
			$message = 'SMRequest error. '. $this->getDataURL() .' expected content with type: text/xml, but got: '. $this->getResponseContentType();
			new SMError($message);
		}
		
		
		if ($debug) { echo '--- END SMRequest --- <hr />'; }
	}
	
	
	function getCacheURL() {
		return $this->cacheURL;
	}
	
	function getConvertedDataURL() {
		return $this->convertedDataURL;
	}
	
	function getDataURL() {
		return $this->dataURL;
	}
	
	function getRequestMethod() {
		return $this->requestMethod;
	}
	
	function getResponseContentType() {
		return $this->responseContentType;
	}
	
	
	/*--
		Return a "property" from the data array (raw data returned from the API)
	*/
	function getRequestData() {
		return $this->requestData;
	}

	function getResponseData() {
		return $this->responseData;
	}

	function getResponseCode() {
		return $this->responseCode;
	}
	
	
	/*--
		Converts POST'ed parameters to a URL-encoded string suitable for cURL'ing to the Sitemason app server.
	*/
	private function convertPostParametersToString($params) {
		
		$postParams = null;
		foreach ($params as $name => $value) {
			if (is_array($value)) {
				$postParams .= $this->convertPostParametersToString($value);
			}
			else {
				$postParams .= urlencode($name) .'='. urlencode($value) .'&';
			}
		}

		$postParams = substr($postParams, 0, strlen($postParams) - 1);
		
		return $postParams;
	}
	


	/*--
		Make a cURL request to the Sitemason API system and return the result in an SMResponse object
 	*/

	public function makeRequest() {
		global $smConsoleDebug;
		
		#echo 'SMRequest makeRequest()!<br>';
		
		$this->responseData = null;
		
		if (smShouldEnableCaching && $this->getRequestMethod() == 'GET') {
			if (smShouldDebugCaching) { $smConsoleDebug .= 'console.info("CACHE: caching system is enabled");'."\n"; }
			$debugStart = microtime();
			
			/*
			try {
				$smdb = new Smdb;
				$responseData = $smdb->fetchCache($this->cacheURL);
			}
			catch (Exception $e) {  
				throw new Exception('Something really gone wrong', 0, $e);
			}
			*/
			
			$smdb = new Smdb;
			if ($smdb->connectionIsOK()) {
				$responseData = $smdb->fetchCache($this->cacheURL);	
			}
			
			$debugStop = microtime();
			$debugDuration = $debugStop - $debugStart;
			
			if (smShouldDebugTiming) {
				$smConsoleDebug .= 'console.info("TIMING: fetch cache ('. $this->cacheURL .'): '. $debugDuration .'s");'."\n"; 
			}

			// if we got data, do a few things to make this SMRequest match one that was done via cURL
			if ($responseData) {
				$this->responseData = $responseData['content'];
				$this->responseCode = $responseData['statusCode'];
			}
		}
		else {
			if (smShouldDebugCaching) { $smConsoleDebug .= ' console.info("CACHE: caching system is disabled");'."\n"; }
			$requestData = $this->requestData;
		}

		// if we don't have responseData by now, fetch it from the app server
		if (!$this->responseData) {
			
			if (smShouldDebugCaching) {
				$smConsoleDebug .= 'console.info("CACHE: did NOT find data in cache or has POST data");'."\n";
			}
			
			$debugStart = microtime();
			
			// get data from Sitemason via cURL
			$ch = curl_init();
			$headers = array("Expect:");
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_URL, $this->dataURL);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
			
			if ($this->getRequestMethod() == 'POST') {
				$postParams = $this->convertPostParametersToString($_POST);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postParams);
			}

			$this->responseData = curl_exec($ch);
			$this->responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$smConsoleDebug .= ' console.info("cURL responseContentType: '. curl_getinfo($ch, CURLINFO_CONTENT_TYPE) .'");'."\n";
			$this->responseError = curl_error($ch);
			
			curl_close($ch);
			
			$debugStop = microtime();
			$debugDuration = $debugStop - $debugStart;
			
			if (smShouldDebugApiRequests) {
				$devConvertedDataURL = preg_replace('/'. smAppServer .'/', 'dev.sitemason.com', $this->dataURL);
				$smConsoleDebug .= ' var devConvertedDataURL = "'. $devConvertedDataURL .'";'."\n";
				$smConsoleDebug .= ' var dataURL = "'. $this->dataURL .'";'."\n";
				$smConsoleDebug .= ' console.info("SMRequest original dataURL: "+ dataURL);'."\n";
				$smConsoleDebug .= ' console.info("SMRequest converted dataURL: "+ devConvertedDataURL);'."\n";
				$smConsoleDebug .= ' console.info("cURL responseCode: '. $this->responseCode .'");'."\n";
			}
			
			if (smShouldDebugTiming) { 
				$smConsoleDebug .= ' console.info("TIMING: fetch non-cached URL ('. $this->dataURL .'): '. $debugDuration .'s");'."\n"; 
			}
			
			
		}
		else if (smShouldDebugCaching) {
			$smConsoleDebug .= ' console.info("CACHE: Found data in cache");'."\n";
		}
		
		//
		// At this point, responseCode = 200 and responseData is filled if we found a match in cache or from the back-end
		//
		
		// if it's good, then proceed
		if ($this->responseCode == 200) {
		
			//
			// Set the application-type based on what kind of data we can get out of Sitemason
			//
			
			// JSON: first character is {
			if (substr($this->responseData,0,1) == '{') {
				$this->responseContentType = 'application/json';
			}
			
			// XML
			else if (preg_match('/<\?xml/',$this->responseData)) {
				$this->responseContentType = 'text/xml';
			}
			
			// RSS
			else if (preg_match('/<rss (?:version|xmlns)/',$this->responseData)) {
				$this->responseContentType = 'text/xml';
			}
			
			// iCalendar
			else if (preg_match('/BEGIN:VCALENDAR/',$this->responseData)) {
				$this->responseContentType = 'text/calendar';
			}
			// anything else
			else {
				$this->responseContentType = 'text/html';
			}
			
			if (smShouldDebugApiRequests > 1) {
				$smConsoleDebug .= ' var responseContentType = "'. $this->responseContentType .'";'."\n";
				$smConsoleDebug .= ' console.info("detected content type: "+ responseContentType);'."\n";
			}

			//
			// decode JSON
			//
			
			if ($this->responseContentType == 'application/json') {
				if (smShouldDebugApiRequests > 1) {
					$smConsoleDebug .= ' console.info("decoding JSON");'."\n";
				}
			
				// decode the JSON now...
				$this->responseData = json_decode($this->responseData, true);
				$error = json_last_error();
				if ($error) {
					if ($error == JSON_ERROR_DEPTH) {
						$errorDesc = 'Maximum stack depth exceeded';
					}
					else if ($error == JSON_ERROR_STATE_MISMATCH) {
						$errorDesc = 'Underflow or the modes mismatch';
					}
					else if ($error == JSON_ERROR_CTRL_CHAR) {
						$errorDesc = 'Unexpected control character found';
					}
					else if ($error == JSON_ERROR_SYNTAX) {
						$errorDesc = 'Syntax error, malformed JSON';
					}
					else if ($error == JSON_ERROR_UTF8) {
						$errorDesc = 'Malformed UTF-8 characters, possibly incorrectly encoded';
					}
					else {
						$errorDesc = 'Unknown error';
					}
		
					echo '<h3 class="error">Sitemason Error: invalid data feed in SMRequest. '. $errorDesc .'</h3>';
				}
			}
		}
	}
}
	
?>