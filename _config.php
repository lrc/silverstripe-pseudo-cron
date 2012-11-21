<?php
/** 
 * @package pseudocron
 */

// Extend site config with cron related data
Object::add_extension('SiteConfig', 'CronSiteConfigExtension');

// Extend Controller to run cron on each page load
Object::add_extension('Controller', 'CronControllerExtension');

// Check prerequisites
if ( ! function_exists( 'curl_init' ) ) user_error ('The Pseudo Cron module requires cURL.', E_USER_ERROR);