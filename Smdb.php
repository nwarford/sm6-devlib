<?php

/*------------------------------------------------------------------------------------------

File: Smdb.php 
Summary: Sitemason® Database (Cache) connector
Version: 6.0.8
	  
Smdb objects are used for looking up cached requests
  
Copyright (C) 2013 Sitemason, Inc. All Rights Reserved. 
 
------------------------------------------------------------------------------------------*/


	class Smdb {
		protected $smdbCacheCon;

		function __construct() {
			$cacheDbHost = 'localhost';
			$cacheDbPort = '9998'; #pg_pool
			$cacheDbUser = 'cache';
			$cacheDbName = 'mlicache';
			$cacheDbPassword = 'melon2ogler';
			if ($_ENV["ENTERPRISE"] == 'tnu' || $_ENV["ENTERPRISE"] == 'mmc') { $cacheDbName = $_ENV["ENTERPRISE"] .'cache'; }
			
			// disable error output here.  If the cache server fails, we want to gracefully proceed sans cache.
			$this->smdbCacheCon = @pg_connect("host=$cacheDbHost port=$cacheDbPort dbname=$cacheDbName user=$cacheDbUser password=$cacheDbPassword connect_timeout=5");
		}


		/**
			Checks for a good SMDBcache connection
		*/
		public function connectionIsOK() {
			$status = pg_connection_status($this->smdbCacheCon);
			if ($status === PGSQL_CONNECTION_OK) {
				return true;
			}
			else {
				return false;
			}
		}
		
		
		/**
			Lookup the request response based on the hash (key)
		*/
		public function fetchCache($url) {

			$returnVal = false;
			$qurl = $this->quote($url);
			$sql1 = "SELECT content, status_code FROM cache_url WHERE url=$qurl LIMIT 1";
			
			$result1 = pg_query($this->smdbCacheCon, $sql1);
			if ($row1 = pg_fetch_row($result1)) {
				$returnVal = array(
					'content' => $row1[0],
					'statusCode' => $row1[1]
				);
			}

			return $returnVal;
		}
		
		public function quote($string) {
			if ($string) {
				return "'" . pg_escape_string($string) . "'";
			} else {
				return 'NULL';
			}
		}
	}
?>