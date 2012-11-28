<?php

/**
 * Extend silverstripe logging to give cron a log separate to the general SS log.
 * 
 * @package pseudocron
 */
class CronLog extends SS_Log {
	protected static $logger;
	const INFO = Zend_Log::INFO;
}