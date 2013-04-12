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
		'Running' => 'Int',
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
		
		// Don't run if it's already running.
		if ($this->Running) {
			
			// Report if it's been running a while
			if ( $this->running < $now-3600 && $this->Notify) {
				// todo: Maybe use EmailLogWriter
				$to = ($this->Notify == 'admin') ? Email::getAdminEmail() : $this->Notify;
				$email = new Email('no-reply@'.$_SERVER['HTTP_HOST'], $to, "CronJob '$this->Name' still running after one hour");
				$email->send();
			}
			return;
			
		} else {
			
			// CronJob->execute() isn't in the business of creating jobs, but a DB entry must exist to monitor running 
			// of jobs. Setting end time to $now ensure's it gets deleted when the job finishes.
			if ( ! $this->ID ) $this->EndTime = $now;
			$this->Running = $now;
			$this->write();
			
		}
		
		if ( is_callable( $this->Callback ) ) {
			try {
				
				$this->Result = @call_user_func( $this->Callback );
				
			} catch (Exception $e) {
				
				$this->Result = 'failed';
			
				if ($this->Notify) {
					// todo: Maybe use EmailLogWriter
					$to = ($this->Notify == 'admin') ? Email::getAdminEmail() : $this->Notify;
					$email = new Email('no-reply@'.$_SERVER['HTTP_HOST'], $to, "CronJob '$this->Name' failed", $e->getMessage());
					$email->send();
				}
				
				// Log it (twice for good measure).
				CronLog::log($e, CronLog::ERR);
				SS_Log::log($e, SS_Log::ERR);
			}
		}

		$this->LastRun = $now;
		$this->NextRun = $now + max($this->Increment, $this->config()->minimum_increment);
		$this->Running = null;
		$this->write();
		
		// It ran successfully, so check if it's time to delete it.
		if ( $this->EndTime > 0 && $now >= $this->EndTime ) {
			$this->delete();
			CronLog::log("Cron job '$this->Name' run for the last time in " . number_format( (microtime(true)-$now), 2 ) . ' seconds.', CronLog::NOTICE);
			return;
		}
		
		CronLog::log("Cron job '$this->Name' took " . number_format( (microtime(true)-$now), 2 ) . ' seconds to run.', CronLog::NOTICE);
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
	
	/**
	 * Enforce that the Increment should be either null or greater than the minimum.
	 * @param int|null $value The value to set.
	 * @throws InvalidArgumentException
	 */
	public function setIncrement($value) {
		if ( !(is_null($value) || is_int($value)) || (is_int($value) && $value < $this->config()->minimum_increment) ) {
			throw new InvalidArgumentException('The increment must be greater than ' . $this->config()->minimum_increment . ' seconds.');
		}
		$this->setField('Increment', $value);
	}
}