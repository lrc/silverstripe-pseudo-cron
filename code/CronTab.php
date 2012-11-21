<?php

/**
 * The equivalent of a crontab. This is a controller which wrangles all the possible
 * CronJob objects and executes them on schedule.
 *
 * @package pseudocron
 */
class CronTab extends Controller {
	
	/**
	 * Check if there are CronJobs to run and run them asynchronously.
	 * 
	 * This function is called by the CronControllerExtension on every page load.
	 */
	public static function run() {
		
		$conf = SiteConfig::current_site_config();
		$now = microtime(true);
		
		// Check that cron should run at all.
		if ( $conf->NextCron > $now || ($conf->CronRunning && $conf->CronRunning > $now) ) return;
		
		// Record that cron is running and set a timeout for 10 mins from now.
		$run_time = $now + 600;
		$conf->CronRunning = $run_time;
		$conf->write();
		
		// Make the request
		$ch = curl_init();
		
		$options = array(
			
			// Use IP and Host header to attempt to avoid DNS lookup. cURL doesn't seem to be helping though.
			CURLOPT_URL => 'http://' . $_SERVER['SERVER_ADDR'] . (($_SERVER['SERVER_PORT'] == '80') ? '' : ':'.$_SERVER['SERVER_PORT'] ) . Director::baseURL() . __CLASS__ . "/call/$run_time",
			CURLOPT_HTTPHEADER => array('Host: ' . $_SERVER['HTTP_HOST']),
			CURLOPT_FOLLOWLOCATION => true,
			// The timeout is low because we want it to timeout quickly and continue. 
			// Although it's set to 1ms, in reality the timeout will almost always be approx 1 second due to cURL not 
			// including the nameserver lookup, and forcing a minimum 1 second timeout on that portion of the request
			// (which isn't even required).
			CURLOPT_TIMEOUT_MS => 1 
		);
		
		curl_setopt_array($ch, $options);
		
		// Fire the request and move on.
		$start = microtime(true);
		curl_exec($ch);
		SS_Log::log('Initiating the pseudo cron system took ' . (microtime(true)-$start) . ' seconds.', SS_Log::NOTICE);
	}
	
	/**
	 * A convenience method for add a single CronJob to the database.
	 * 
	 * @param array $params An array of parameters to setup the job.
	 */
	public static function add($params) {
		$job = Object::create('CronJob');
		
		// Populate with passed params
		foreach (array_keys(CronJob::$db) as $field) {
			if (isset($params[$field])) $job->$field = $params[$field];
		}
		
		$job->write();
		return $job;
	}
	
	/**
	 * Loop through each of the CronJobs that need to be executed (if any) and
	 * execute them one by one. 
	 * 
	 * @param HTTPRequest The request.
	 */
	public function call($request) {
		
		$start = microtime(true);
		
		$conf = SiteConfig::current_site_config();
		
		if ( $request->latestParam('ID') != $conf->CronRunning ) return;

		// Allow a longer than usual timelimit. This only works on host with safe mode DISABLED
		if ( !ini_get( 'safe_mode' ) ) {
			increase_time_limit_to( 600 );
		}
		
		$time = time();
		
		// Get all the jobs (and give extensions a chance).
		$jobs = DataObject::get('CronJob', 'StartTime <= ' . Convert::raw2sql($time) . ' AND NextRun <= ' . Convert::raw2xml($time));
		$count = $jobs->count();
		$this->extend('augmentCronJobs', $jobs);
		
		// Execute crons that need running.
		if ( $count > 0 ) foreach( $jobs as $job ) $job->execute();

		// set the next run time to the lowest next_run OR a max of one day.
		$next_cron = DB::query( 'SELECT NextRun FROM CronJob ORDER BY NextRun ASC LIMIT 1' )->value();
		$conf->NextCron = min( intval($next_cron), time()+86400 );
		$conf->CronRunning = null;
		$conf->write();
		SS_Log::log('Running the pseudo cron system took ' . (microtime(true)-$start) . ' seconds ('. $count . ' jobs).', SS_Log::NOTICE);
	}
}
