<?php

/**
 * Extend the site config object to add some DB fields required by Cron
 * 
 * @package pseudocron
 */
class CronSiteConfigExtension extends DataExtension {
	
	public static $db = array(
		'NextCron' => 'Int',
		'CronRunning' => 'Varchar(20)'
	);
	
}