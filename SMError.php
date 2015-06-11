<?php
	
/*------------------------------------------------------------------------------------------

File: SMError.php 
Summary: 
Version: 6.0

PRE-RELEASE

Copyright (C) 2014 Sitemason, Inc. All Rights Reserved. 
 
------------------------------------------------------------------------------------------*/



class SMError {

    public function __construct($message, $code = 0) {
		die('<h3>Oh no.  This doesn\'t look good...</h3>'. $message);
    }
}