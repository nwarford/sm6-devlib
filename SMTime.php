<?php
	
/*------------------------------------------------------------------------------------------

File: SMTime.php 
Summary: Stores a start and end time for an Sitemason® Item
Version: 6.0
	  
For version 6.0, the constructor receives a custom data model from SMItem, since we have
separated the start and end timestamps (and SM4 data shoves them into the same block)

Since SMTime is used to contain any timestamp, the "timestamp" property doubles as the 
start timestamp (where applicable, like in SMItems).
  
Copyright (C) 2013 Sitemason, Inc. All Rights Reserved. 
 
------------------------------------------------------------------------------------------*/

	class SMTime extends SMObject {
		private $timestamp;
		private $cleanTimestamp;
		private $endTimestamp;
		private $isAllDay;
		private $timezone;
		
		private $repeatFrequency;
		private $repeatCount;
		private $repeatInterval;
		private $repeatByDay;
		private $repeatByWeekNumber;
		private $repeatByMonth;
		private $repeatByMonthDay;
		private $repeatByYearDay;
		private $repeatUntilTimestamp;

		// The data is a special array originating in SMTime's constructor
		function __construct(array $data) {
			
			$this->apiData = $data;
			$this->id = $data['id'];
			
			$this->setTimestamp($data['start_time'] ? $data['start_time'] : $data['startTime']);
			
			$this->setEndTimestamp($data['end_time'] ? $data['end_time'] : $data['endTime']);
			$this->setTimezone($data['timezone'] ? $data['timezone'] : $data['timeZone']);
			
			if ($data['isAllDay']) {
				$this->isAllDay = true;
			}
			else {
				$this->isAllDay = false;
			}
			
			$this->setRepeatFrequency($data['repeat_freq'] ? $data['repeat_freq'] : $data['repeatFrequency']);
			$this->setRepeatCount($data['repeat_count'] ? $data['repeat_count'] : $data['repeatCount']);
			$this->setRepeatInterval($data['repeat_interval'] ? $data['repeat_interval'] : $data['repeatInterval']);
			$this->setRepeatByDay($data['repeat_byday'] ? $data['repeat_byday'] : $data['repeatByDay']);
			$this->setRepeatByWeekNumber($data['repeat_byweekno'] ? $data['repeat_byweekno'] : $data['repeatByWeekNumber']);
			$this->setRepeatByMonth($data['repeat_bymonth'] ? $data['repeat_bymonth'] : $data['repeatByMonth']);
			$this->setRepeatByMonthDay($data['repeat_bymonthday'] ? $data['repeat_bymonthday'] : $data['repeatByMonthDay']);
			$this->setRepeatByYearDay($data['repeat_byyearday'] ? $data['repeat_byyearday'] : $data['repeatByYearDay']);
			$this->setRepeatUntilTimestamp($data['repeat_until'] ? $data['repeat_until'] : $data['repeatUntilTimestamp']);
		}
		
//! 
//! Basic get/set methods
//!------------------------------

		public function getTimestamp() { return $this->timestamp; }
		public function setTimestamp($timestamp) { $this->setStartTimestamp($timestamp); }
		public function getStartTimestamp() { return $this->getTimestamp(); }
		public function setStartTimestamp($timestamp) { $this->timestamp = $timestamp; }
		
		
		public function getCleanTimestamp() { 
			$time = new DateTime($this->timestamp);
			return $time; 
		}
		
		public function getEndTimestamp() { return $this->endTimestamp; }
		public function setEndTimestamp($endTimestamp) { $this->endTimestamp = $endTimestamp; }
		
		public function getTimezone() { return $this->timezone; }
		public function setTimezone($timezone) { $this->timezone = $timezone; }
		
		public function isAllDay() { return $this->isAllDay; }
		
		public function getRepeatFrequency() { return $this->repeatFrequency; }
		public function setRepeatFrequency($repeatFrequency) { $this->repeatFrequency = $repeatFrequency; }
		
		public function getRepeatCount() { return $this->repeatCount; }
		public function setRepeatCount($repeatCount) { $this->repeatCount = $repeatCount; }
		
		public function getRepeatInterval() { return $this->repeatInterval; }
		public function setRepeatInterval($repeatInterval) { $this->repeatInterval = $repeatInterval; }
		
		public function getRepeatByDay() { return $this->repeatByDay; }
		public function setRepeatByDay($repeatByDay) { $this->repeatByDay = $repeatByDay; }
		
		public function getRepeatByWeekNumber() { return $this->repeatByWeekNumber; }
		public function setRepeatByWeekNumber($repeatByWeekNumber) { $this->repeatByWeekNumber = $repeatByWeekNumber; }
		
		public function getRepeatByMonth() { return $this->repeatByMonth; }
		public function setRepeatByMonth($repeatByMonth) { $this->repeatByMonth = $repeatByMonth; }
		
		public function getRepeatByMonthDay() { return $this->repeatByMonthDay; }
		public function setRepeatByMonthDay($repeatByMonthDay) { $this->repeatByMonthDay = $repeatByMonthDay; }
		
		public function getRepeatByYearDay() { return $this->repeatByYearDay; }
		public function setRepeatByYearDay($repeatByYearDay) { $this->repeatByYearDay = $repeatByYearDay; }
		
		public function getRepeatUntilTimestamp() { return $this->repeatUntilTimestamp; }
		public function setRepeatUntilTimestamp($repeatUntilTimestamp) { $this->repeatUntilTimestamp = $repeatUntilTimestamp; }


//! 
//! PHP DateTime-related methods
//!------------------------------

		/**
			Returns a PHP DateTime object for this SMTime's start timestamp
		*/
		public function getStartDateTime() {
			$startDateTime = new DateTime($this->getStartTimestamp());
			return $startDateTime;
		}
		
		public function getEndDateTime() {
			$endDateTime = new DateTime($this->getEndTimestamp());
			return $endDateTime;
		}
		
		public function getRepeatUntilDateTime() {
			$repeatUntilDateTime = new DateTime($this->getRepeatUntilTimestamp());
			return $repeatUntilDateTime;
		}
		
//! 
//! Other methods
//!------------------------------

		/**
			Convenience method for people trying to get a start date (without time)
		*/
		public function getStartDate() {
			$startTimestamp = new DateTime($this->getTimestamp());
			return $startTimestamp->format('Y-m-d');
		}
		
		/* REMOVED 20140306: doesn't appear to be used.  DO NOT PUBLISH.
		public function setStartDate($startDateString) {
			$startDate = new DateTime($startDateString);
		}
		*/
		
		
		/**
			Create some JSON to attempt to sensibly describe the properties of this object
			Flag "returnDescription" to return the results instead of appending to the smConsoleOutput string.
		*/
		public function describe() {
			global $smConsoleOutput;
			
			$description = array(
				'startTimestamp'	=> $this->getTimestamp(),
				'endTimestamp'		=> $this->getEndTimestamp(),
				'isAllDay'			=> $this->isAllDay(),
				'timezone'			=> $this->getTimezone()
			);
			
			// filter
			#$src = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c");
			#$repl = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");
			#foreach ($description as $key => $value) {
			#	$description[$key] = str_replace($src, $repl, $value);
			#}
			
			return $description;
		}		
	}
?>