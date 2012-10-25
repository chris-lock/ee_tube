<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * cURL Library
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Library
 * @author		Jesse Bunch & Chris Lock
 * @link		http://paramore.is/
 */

class Ee_tube_curl_library {
    
	/**
	 * Constructor
	 * @author Jesse Bunch
	*/
	public function __construct() {
		$this->EE =& get_instance();
	}

	/**
	 * Wraps the cURL library for use in the addon classes with post option
	 * @param string $url The URL to post to
	 * @param array $fields_array The field names and the paired values
	 * @param bool $is_json If data is JSON encoded
	 * @return string|bool FALSE if failure
	 * @author Jesse Bunch & Chris Lock
	*/
	public function do_curl($url, $fields_array = array(), $is_json = false, $headers_array = array()) {

		// Do we have fopen?
		// PHP Docs say this is preferred over cURL
		if (ini_get('allow_url_fopen') === TRUE && empty($fields_array)) {
			$response = file_get_contents($url);
		}

		// Do we have curl?
		elseif (function_exists('curl_init')) {

			// Our cURL options
			$options = array(
				CURLOPT_URL =>  $url, 
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_CONNECTTIMEOUT => 10
			); 

			if (!empty($fields_array)) {

				// Add cURL options for post
				$options[CURLOPT_POSTFIELDS] = ($is_json) ? '['.json_encode($fields_array).']' : http_build_query($fields_array, '', '&amp;');

			}

			if (!empty($headers_array)) {

				// Must be in the format:
				// array('Content-type: text/plain', 'Content-length: 100')
				$options[CURLOPT_HTTPHEADER] = $headers_array;
				
			}
			
			// Initialize cURL
		    $curl = curl_init();
			curl_setopt_array($curl, $options);

			// Get response
			$response = curl_exec($curl);
			$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			
			// Validate
			if ($response === FALSE || $http_code != '200') {
				return FALSE;
			}

			// Close the request
			curl_close($curl);

		}
		
		// Shucks...
		else {
			$response = FALSE;
		}

		// Return the response
		return $response;

	}
	
}

/* End of file ee_tube_curl_library.php */
/* Location: /system/expressionengine/third_party/ee_tube/libraries/ee_tube_curl_library.php */