<?php

/**
 * A store for pseudo-cron state and anything else that needs to persist.
 *
 * @author Simon Elvery
 * @package pseudocron
 */
class CronConfig extends DataObject {
	
	public static $db = array(
		'Name' => 'Varchar(300)',
		'Value' => 'Text'
	);
	
	// Static cache of config objects to avoid excess DB queries.
	private static $_cache = array();
	
	/**
	 * Simple static getter for cron config options.
	 * 
	 * @param string $name The name of the config item to get.
	 * @return null|string The config value for the given name (if there is one)
	 */
	public static function g($name) {
		if (!array_key_exists($name, self::$_cache)) {
			self::$_cache[$name] = CronConfig::get()->filter('Name', $name)->First();
		}
		return (self::$_cache[$name]) ? self::$_cache[$name]->Value : null;
	}
	
	/**
	 * Simple static setter for cron config records
	 * @param string $name The name of the config item to set
	 * @param string $value The value to set
	 */
	public static function s($name, $value) {
		if (!array_key_exists($name, self::$_cache)) {
			self::$_cache[$name] = CronConfig::get()->filter('Name', $name)->First();
		}
		
		if ( ! self::$_cache[$name] instanceof CronConfig ) {
			self::$_cache[$name] = new CronConfig();
		}
		
		self::$_cache[$name]->Name = $name;
		self::$_cache[$name]->Value = $value;
		self::$_cache[$name]->write();
	}
	
}

