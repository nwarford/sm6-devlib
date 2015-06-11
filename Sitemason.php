<?php

/*------------------------------------------------------------------------------------------

Sitemason, Inc.
www.sitemason.com

Sitemason® PHP Template Development Library
v6.0

This script sets up the currently-viewed materials: Site, Folder, and Tool, then presents
these objects as "smCurrent" variables to the template.
	  
History:
20120208	tgraham		initial content
20121127	tgraham		overhauled for SM6+SM4/JSON approach
20130703	tgraham		re-worked for devlib 6.0b16

Copyright (C) 2013 Sitemason, Inc. All Rights Reserved. 
 
------------------------------------------------------------------------------------------*/


	function __autoload($className) {
		$parts = explode('_', $className);
		$path = implode(DIRECTORY_SEPARATOR, $parts);
		require_once $path . '.php';
	}

	//
	// fetch navigation data
	//
	
	if (smShouldDebugApiRequests > 1) { $smConsoleDebug .= "console.info('Making nav data request');"; }
	$navRequest = new SMRequest(array('url' => siteURL, 'feedType' => 'navjson'));
	$smSiteNavigationData = $navRequest->getResponseData();
	if (!$smSiteNavigationData || $navRequest->getResponseCode() != '200') {
		$url = $navRequest->getDataURL();
		
		die('<h3>Oh no.  This doesn\'t look good...</h3><p>The library could not fetch navigation data for this site.  The request returned a '. $navRequest->getResponseCode() .'.</p><p>'. $url .'</p>');
	}
	
	//
	// $smCurrentFolder
	//
	$debugStart = microtime();
	if (smShouldDebugApiRequests > 1) { $smConsoleDebug .= "console.info('instantiating \$smCurrentFolder in Sitemason.php');"; }
	$smCurrentFolder = new SMFolder();
	if (smShouldDebugApiRequests > 1) { $smConsoleDebug .= "console.info('Finished with \$smCurrentFolder in Sitemason.php');"; }

	if (smDebugToolStructure) { echo '<h1>Done with smCurrentFolder!</h1>'; }
	$debugStop = microtime();
	$debugDuration = $debugStop - $debugStart;
				
	if (smShouldDebugTiming) {
		$smConsoleDebug .= 'console.info("TIMING: create smCurrentFolder: '. $debugDuration .'s");'."\n"; 
	}


	//
	// Handle special cases: 404, JSON, XML, etc.
	// 
	
	// look for 404 errors on the currently-viewed page
	$currentFolderHttpResponseCode = $smCurrentFolder->getRequest()->getResponseCode();
	$currentFolderHttpContentType = $smCurrentFolder->getRequest()->getResponseContentType();

	if ($currentFolderHttpResponseCode == 200) {

		//
		// Process Sitemason JSON
		//
		if ($currentFolderHttpContentType == 'application/json') {
			header('Content-type: text/html', true, $currentFolderHttpResponseCode);

			//
			// $smCurrentTool
			//
			
			$smCurrentTool = $smCurrentFolder->getCurrentTool();
			#$smCurrentTool->setFolder($smCurrentFolder); // set in SMFolder
			
			// Look for special query params
			parse_str($_SERVER['QUERY_STRING'],$queryString);
			
			// redirect
			if ($smCurrentTool->getRedirectURL()) {
				header('Location: '. $smCurrentTool->getRedirectURL());
				exit();
			}
		
			// special output parameters
			else if (isset($queryString['json'])) {
				$smCurrentTool->printJson();
				exit();
			}
			
			// 
			else if (isset($queryString['describe'])) {
				echo '<p>';
				echo '	<a href="#smCurrentSite">smCurrentSite</a><br />';
				echo '	<a href="#smCurrentTool">smCurrentTool</a><br />';
				echo '</p>';
				echo '<a name="smCurrentSite"></a><h2>smCurrentSite:</h2>';
				echo '<pre>'. $smCurrentSite->describe() .'</pre>';
				
				echo '<a name="smCurrentTool"></a><h2>smCurrentTool:</h2>';
				echo '<pre>'. $smCurrentTool->describe() .'</pre>';
				exit();
			}
			
			
			
			
			//
			// $smCurrentSite
			//
			
			$smCurrentFolderPath = $smCurrentFolder->getPath();
			if (smDebugToolStructure) { echo '<b>smCurrentFolder\'s Path is: '. $smCurrentFolderPath .'</b><br/>'; }
		
			//if $smCurrentFolder's path is "/", then it is the site.
			if ($smCurrentFolderPath == '/') {
				if (smDebugToolStructure) { echo '<b>smCurrentFolder is the root/site!  Setting smCurrentSite = smCurrentFolder!</b><br>'; }
				$smCurrentSite = $smCurrentFolder;
			}
		
			// otherwise, smCurrentTool is in a subfolder.  Instantiate a folder for the root/site.
			else {
				if (smDebugToolStructure) { echo '<b>smCurrentFolder is a subfolder.  Instantiating an SMFolder for the root/site</b><br>'; }
				$requestData = array('url' => siteURL, 'feedType' => 'sitejson');
				$debugStart = microtime();
				
				$smCurrentSite = new SMFolder($requestData);
				
				$debugStop = microtime();
				$debugDuration = $debugStop - $debugStart;
				if (smShouldDebugTiming) {
					$smConsoleDebug .= 'console.info("TIMING: create smCurrentFolder: '. $debugDuration .'s");'."\n"; 
				}
				
			}

			if (smDebugToolStructure) { echo '<h1>Done with smCurrentSite!</h1>'; }
		}

		/*
			if we didn't get JSON but we got a 200, then we're not going to build-out the Sitemason data structure.
			Send out the responseData as-is, but set the content-type appropriately
		*/
		else {
			header('Content-type: '. $currentFolderHttpContentType, true, $currentFolderHttpResponseCode);
			echo $smCurrentFolder->getRequest()->getResponseData();
			exit();
		}
	}

	//
	// Non-200 status codes
	//
	
	// Redirect
	if ($currentFolderHttpResponseCode == 301 || $currentFolderHttpResponseCode == 302) {
		header('Location: '. $smCurrentFolder->getRequest()->getResponseData());
		exit();
	}
	
	// bad URL
	else if ($currentFolderHttpResponseCode >= 400) {
		// allow $smCurrentSite to be present for any 404 page
		$requestData = array('url' => siteURL, 'feedType' => 'sitejson');
		$smCurrentFolder = new SMFolder($requestData);
		$smCurrentSite = $smCurrentFolder;

		header('Content-type: text/html', true, $currentFolderHttpResponseCode);
		
		// look for a custom 404 in the template directory
		if (file_exists(smFullPathForTemplateDirectory .'/404.php')) {
			require_once(smFullPathForTemplateDirectory .'/404.php');	
		}
		
		// look for the tool template's 404
		else if (file_exists(smFullPathForToolTemplateSetDirectory .'/404.php')) {
			require_once(smFullPathForToolTemplateSetDirectory .'/404.php');	
		}
		
		// if we're not already on the /404 page, redirect there
		else if ($_SERVER['PHP_SELF'] != '/404') {
			header ('Location: /404');
		}
		
		else {
			echo '404 - file not found.';
		}
		
		exit();
	}
?>