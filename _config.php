<?php
/** 
 * @package pseudocron
 */

// Check prerequisites
if ( ! function_exists( 'curl_init' ) ) user_error ('The Pseudo Cron module requires cURL.', E_USER_ERROR);