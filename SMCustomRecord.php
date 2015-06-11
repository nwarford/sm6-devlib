<?php
	
/*------------------------------------------------------------------------------------------

File: SMCustomRecord.php 
Summary: The SMCustomRecord class definition
Version: 6.0
	  
Sitemason® Custom Record implementation
  
Copyright (C) 2012 Sitemason, Inc. All Rights Reserved. 
 
------------------------------------------------------------------------------------------*/


	class SMCustomRecord extends SMObject {
		private $value;
				
		function __construct($id, $value) {
			$this->id = $id;
			$this->value = $value;
		}
	}
	
?>