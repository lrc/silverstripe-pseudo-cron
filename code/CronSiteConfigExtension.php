<?php

/**
 * Extend the site config object to add some DB fields required by Cron
 * 
 * @package pseudocron
 */
class CronSiteConfigExtension extends DataObjectDecorator {
	
	public function extraStatics() {
		return array('db'=>array(
			'NextCron' => 'Int',
			'CronRunning' => 'Varchar(20)'
		));
	}
	
}