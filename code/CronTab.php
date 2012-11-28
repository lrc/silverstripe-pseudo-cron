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
		
		// Open the socket
		$fp = @fsockopen($_SERVER['SERVER_ADDR'], $_SERVER['SERVER_PORT'], $errno, $errstr, .001);
		if ( $fp === false ) {
			// Log it (twiced for good measure).
			CronLog::log("Cron system failed to run because a socket could not be established.", CronLog::ERR);
			SS_Log::log("Cron system failed to run because a socket could not be established.", SS_Log::ERR);
			return;
		}

		// Construct the request
		$out = "GET " . Director::baseURL() . __CLASS__ . "/call/$run_time HTTP/1.1\r\n";
		$out.= "Host: " . $_SERVER['HTTP_HOST'] . "\r\n";
		$out.= "Content-Type: application/x-www-form-urlencoded\r\n";
		$out.= "Content-Length: 1\r\n";
		$out.= "Connection: Close\r\n\r\n ";
		
		// Fire the request and move on.
		fwrite($fp, $out);
		fclose($fp);
		CronLog::log('Initiating the pseudo cron system took ' . (microtime(true)-$now) . ' seconds.', CronLog::NOTICE);
	}
	
	/**
	 * A convenience method for add a single CronJob to the database.
	 * 
	 * @param array $params An array of parameters to setup the job.
	 */
	public static function add($params) {
		$job = CronJob::create();
		
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
		$this->extend('augmentCronJobs', $jobs);
		
		$count = $jobs->count();
		
		// Attempt increase timelimit.
		increase_time_limit_to();
		
		// Execute crons that need running.
		if ( $count > 0 ) foreach( $jobs as $job ) $job->execute();

		// set the next run time to the lowest next_run OR a max of one day.
		$next_cron = DB::query( 'SELECT NextRun FROM CronJob ORDER BY NextRun ASC LIMIT 1' )->value();
		$conf->NextCron = min( intval($next_cron), time()+86400 );
		$conf->CronRunning = null;
		$conf->write();
		CronLog::log('Running the pseudo cron system took ' . (microtime(true)-$start) . ' seconds ('. $count . ' jobs).', CronLog::NOTICE);
	}
}
