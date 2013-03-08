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
		
		$now = microtime(true);
		$class_config = Config::inst()->forClass(__CLASS__);
		
		// Check that cron should run at all.
		if (CronConfig::g('NextCron') > $now) return;
		if (CronConfig::g('CronRunning') && CronConfig::g('CronRunning') > $now) return;
		
		// Record that cron is running and set a timeout for 10 mins from now.
		$run_time = $now + 600;
		CronConfig::s('CronRunning', $run_time);
		
		// Connection details
		if ($class_config->ssl === true) {
			$tran = 'ssl';
			$port = 443;
		} elseif ($class_config->ssl === false) {
			$tran = 'tcp';
			$port = 80;
		} else {
			$tran = (Director::protocol() === 'https://') ? 'ssl' : 'tcp';
			$port = $_SERVER['SERVER_PORT'];
		}
		
		// IP Address is faster because it avoids a DNS lookup, but it doesn't work over SSL.
		$host = ($tran === 'ssl') ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_ADDR']; 
		
		// Open the socket
		try {
			$errno = null;
			$errstr = null;
			$fp = @fsockopen("$tran://$host", $port, $errno, $errstr, 1);
			if ( $fp === false || $errno != 0) {
				throw new CronException("A socket could not be established" . ((strlen($errstr)) ? " ($errstr)" : ''));
			}
		} catch (Exception $e) {
			// Never let CronTab stop execution, but log it (twice for good measure).
			CronLog::log("Cron system failed to run because a socket could not be established.", CronLog::ERR);
			SS_Log::log($e, SS_Log::ERR);
			CronConfig::s('CronRunning', null);
			return;
		}

		// Construct the request
		$req = "GET " . Director::baseURL() . __CLASS__ . "/call/$run_time HTTP/1.1\r\n";
		$req.= "Host: " . $_SERVER['HTTP_HOST'] . "\r\n";
		$req.= "Connection: close\r\n\r\n";
		
		// Fire the request and move on. 
		// There is a potential error here which can't be caught because we don't wait for the server response.
		$written = fwrite($fp, $req);
		if ( !$written ) {
			// Never let CronTab stop execution, but log it (twice for good measure).
			CronLog::log("Cron system failed to run because the socket could not be written to.", CronLog::ERR);
			SS_Log::log("Cron system failed to run because the socket could not be written to.", SS_Log::ERR);
			CronConfig::s('CronRunning', null);
			return;
		}
		CronLog::log('Initiating the pseudo cron system took ' . number_format( (microtime(true)-$now), 2 ) . ' seconds.', CronLog::NOTICE);
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
		
		if ( !$request->latestParam('ID') || $request->latestParam('ID') != CronConfig::g('CronRunning') ) {
			throw new CronException('CronTab->call run with incorrect ID.');
		}

		$time = time();
		
		// Get all the jobs (and give extensions a chance).
		$jobs = DataObject::get('CronJob', "StartTime <= $time AND NextRun <= $time");
		
		$count = $jobs->count();
		
		// Attempt increase timelimit.
		increase_time_limit_to();
		
		// Execute crons that need running.
		if ( $count > 0 ) foreach( $jobs as $job ) $job->execute();

		// set the next run time to the lowest next_run OR a max of one day.
		$next_cron = DB::query( 'SELECT NextRun FROM CronJob ORDER BY NextRun ASC LIMIT 1' )->value();
		CronConfig::s('NextCron', min( intval($next_cron), time()+86400 ));
		CronConfig::s('CronRunning', null);
		CronLog::log('Running the pseudo cron system took ' . number_format( (microtime(true)-$start), 2 ) . ' seconds ('. $count . ' jobs).', CronLog::NOTICE);
	}
}
