<?php

/**
 * A core controller extension which kicks pseudo cron into action
 * on every page load.
 *
 * @package pseudocron
 */
class CronControllerExtension extends Extension {
	// Every time a request is made.
	public function onAfterInit() {
		
		// Run cron jobs
		if ( $this->owner instanceof Page_Controller ) {
			CronTab::run();
		}
	}
}