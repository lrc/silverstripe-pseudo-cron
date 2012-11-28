<?php

/**
 * Represents a single cron job and defines what should be run and when.
 *
 * @package pseudocron
 */
class CronJob extends DataObject {
	
	public static $db = array(
		'Name' => 'Varchar(100)',
		'Callback' => 'Varchar(100)',
		'LastRun' => 'Int',
		'NextRun' => 'Int',
		'Increment' => 'Int', //86400, // one day
		'StartTime' => 'Int',
		'EndTime' => 'Int', //NULL,
		'Result' => 'Text',
		'Description' => 'Text',
		'Notify' => 'Varchar'
	);
	
	public static $defaults = array(
		'Name' => '',
		'Callback' => '',
		'LastRun' => null,
		'NextRun' => null,
		'Increment' => 86400, // one day
		'StartTime' => null,
		'EndTime' => 0,
		'Result' => '',
		'Description' => '',
		'Notify' => null
	);
	
	/**
	 * Create some dynamic default values.
	 */
	public function populateDefaults() {
		$this->NextRun = time();
		$this->StartTime = time();
		parent::populateDefaults();
	}
	
	/**
	 * Runs this job.
	 */
	public function execute() {	
		$now = time();
		
		if ( is_callable( $this->Callback ) ) {
			try {
				
				$this->Result = @call_user_func( $this->Callback, $paramarray );
				
			} catch (Exception $e) {
				
				$this->Result = 'failed';
			
				if ($this->Notify) {
					$to = ($this->Notify == 'admin') ? Email::getAdminEmail() : $this->Notify;
					$email = new Email('no-reply@'.$_SERVER['HTTP_HOST'], $to, "CronJob '$this->Name' failed", $e->getMessage());
					$email->send();
				}
				
				// Log it (twiced for good measure).
				CronLog::log($e, CronLog::ERR);
				SS_Log::log($e, SS_Log::ERR);
			}
		}

		// it ran successfully, so check if it's time to delete it.
		if ( $this->EndTime > 0 && $now >= $this->EndTime ) {
			$this->delete();
			CronLog::log("Cron job '$this->Name' run for the last time in " . (microtime(true)-$now) . ' seconds.', CronLog::NOTICE);
			return;
		}

		$this->LastRun = $now;
		$this->NextRun = $now + $this->Increment;
		
		// Execute isn't in the business of creating jobs
		if ( $this->ID ) $this->write();
		CronLog::log("Cron job '$this->Name' took " . (microtime(true)-$now) . ' seconds to run.', CronLog::NOTICE);
	}
	
	/**
	 * Setter for Callback field so it can be automatically serialized.
	 *
	 * @param mixed $value 
	 */
	public function setCallback($value) {
		if ( is_array($value) || is_object($value) ) {
			$value = serialize($value);
		}
		$this->setField('Callback', $value);
	}
	
	/**
	 * Getter for Callback field so it can be automatically unserialized.
	 */
	public function getCallback() {
		$val = $this->getField('Callback');
		if ( false !== $result = @unserialize($val) ) {
			return $result;
		}
		return $val;
	}
}