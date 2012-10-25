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
 * EE Tube Plugin
 *
 * @package		ExpressionEngine
 * @subpackage	Addons
 * @category	Plugin
 * @author		Chris Lock
 * @link		http://paramore.is/chris
 */

$plugin_info = array(
	'pi_name'		=> 'EE Tube',
	'pi_version'	=> '1.1',
	'pi_author'		=> 'Chris Lock',
	'pi_author_url'	=> 'http://paramore.is/chris',
	'pi_description'=> 'Returns embed code, title, content, author, duration, comment count, categories, tags, & thumbnails for a YouTube url',
	'pi_usage'		=> Ee_tube::usage()
);


class Ee_tube {

	/**
	 * The EE cache group
	 * @param string
	 * @author Chris Lock
	*/
	const CACHE_GROUP = 'ee_ee_tube';

	/**
	 * Constructor
	 * Sets $ee_tube_tags as template tags for YouTube url
	 * 
	 *	- (string) eet_id
	 *	- (xhtml) eet_embed
	 *	- (string) eet_title
	 *	- (xhtml) eet_content
	 *	- (string) eet_author
	 *	- (string) eet_duration
	 *	- (int) eet_duration_seconds
	 *	- (int) eet_comment_count
	 *	- (array) eet_categories
	 *		- array
	 *			- (string) eet_category
	 *	- (array) eet_tags
	 *		- array
	 *			- (string) eet_tag
	 *	- (array) eet_thumbnails
	 *		- array
	 *			- (string) eet_thumbnail_src
	 *			- (string) eet_thumbnail_height
	 *			- (string) eet_thumbnail_width
	 *			- (string) eet_thumbnail_time
	 * 
	 * @author Chris Lock
	 */
	public function __construct() {
		
		$this->EE =& get_instance();

		$this->youtube_url = $this->EE->TMPL->fetch_param('url');
		$this->cache_timeout = $this->EE->TMPL->fetch_param('cache_timeout');

		$youtube_id = self::_get_youtube_id($this->youtube_url);

		$youtube_data_array = array();

		if ($this->_has_caching_enabled())
			$youtube_data_array = $this->_get_cached_ee_tags($youtube_id, $this->cache_timeout);

		if (empty($youtube_data_array)) {

			$youtube_data_array = $this->_get_youtube_data($youtube_id);

			// $youtube_url is invalid or YQL query failed
			// return no results
			if (empty($youtube_data_array)) {

				$this->return_data = $this->EE->TMPL->no_results();
				return;

			}

			// Cache this mess
			if ($this->_has_caching_enabled())
				$this->_set_cached_data($youtube_id, $youtube_data_array);

		}

		// Build EE tags from YQL results
		$ee_tags = $this->_build_ee_tags($youtube_data_array, $youtube_id);

		$this->return_data = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $ee_tags);

	}

	/**
	 * Retrieves video id from youtube url
	 * @param string $youtube_url The url to parse
	 * @return string $youtube_id the video id
	 * @author Chris Lock
	*/
	private static function _get_youtube_id($youtube_url) {

		$youtube_id = null;
		$youtube_url_parsed = parse_url($youtube_url);
		
		// xxxx only
		if (FALSE === strpos($youtube_url,'/')) {
			
			$youtube_id = $youtube_url;

		// http://youtu.be/xxxx
		} elseif ($youtube_url_parsed['host'] == 'youtu.be') { 
			
			$youtube_id = ltrim($youtube_url_parsed['path'], '/');

		// http://youtube.googleapis.com/v/xxxx
		} elseif ($youtube_url_parsed['host'] == 'googleapis.com') { 
			
			$youtube_id = ltrim($youtube_url_parsed['path'], '/v/');

		// http://youtube.com/v/xxxx
		} elseif (strpos($youtube_url_parsed['path'], 'v') == 1) { 
			
			$youtube_id = ltrim($youtube_url_parsed['path'], '/v/');

		// http://www.youtube.com/embed/xxxx
		} elseif (strpos($youtube_url_parsed['path'], 'embed') == 1) { 
			
			$youtube_id = end(explode('/', $youtube_url_parsed['path']));
		
		// http://www.youtube.com/watch?v=xxxx
		} elseif (isset($youtube_url_parsed['query'])) {
			
			parse_str($youtube_url_parsed['query'], $youtube_var);
			
			$youtube_id = $youtube_var['v'];
			
		}

		return $youtube_id;

	}

	/**
	 * Checks if caching is enabled
	 * @return boolean $caching_is_enabled
	 * @author Chris Lock
	*/
	private function _has_caching_enabled() {

		return ($this->cache_timeout) ? TRUE : FALSE;

	}

	/**
	 * Retrieves cached EE tags for a YouTube id
	 * @param string $youtube_id The video id to get EE tags for
	 * @return mixed $cached_data_array The cached EE tags for video id
	 * @author Chris Lock
	*/
	private function _get_cached_ee_tags($youtube_id, $cache_timeout) {

		// Must have a YouTube id
		if (!$youtube_id) return array();

		$cache_key = $youtube_id;
		$this->EE->load->library('ee_tube_caching_library');
		$cached_data = $this->EE->ee_tube_caching_library->read_cache(
			$cache_key,
			Ee_tube::CACHE_GROUP,
			$cache_timeout
		);
		
		// Return cached EE tags array or false
		return ($cached_data)
			? unserialize($cached_data)
			: array();

	}

	/**
	 * Retrieves data for a YouTube id
	 * @param string $youtube_id The video id to get data for
	 * @return mixed $youtube_data_array The data for video id
	 * @author Chris Lock
	*/
	private function _get_youtube_data($youtube_id) {

		// Must have a YouTube id
		if (!$youtube_id) return array();

		$youtube_query = "select * from youtube.video where id='".$youtube_id."'";
		$yql_params = array(
			'env' => 'store://datatables.org/alltableswithkeys'
		);

		$this->EE->load->library('ee_tube_yql_library');

		$youtube_result_array = $this->EE
			->ee_tube_yql_library
			->run_query($youtube_query, $yql_params);

		// Debug
		// die(var_dump($youtube_result_array));

		// Return results array or false
		return (isset($youtube_result_array['video']))
			? $youtube_result_array['video']
			: array();

	}

	/**
	 * Sets cached data for a YouTube id
	 * @param string $youtube_id The video id to get data for
	 * @param array $youtube_data_array Data for the video
	 * @return void
	 * @author Chris Lock
	*/
	private function _set_cached_data($youtube_id, $youtube_data_array) {

		// Must have a YouTube id
		if (!$youtube_id) return FALSE;

		$cache_key = $youtube_id;
		$cache_value = serialize($youtube_data_array);
		$this->EE->load->library('ee_tube_caching_library');
		$cached_data = $this->EE->ee_tube_caching_library->set_cache(
			$cache_key,
			$cache_value,
			Ee_tube::CACHE_GROUP
		);

	}

	/**
	 * Builds tags array with keys from YQL
	 * @param array $ee_tube_tags Array of tag parameters
	 * @param array $youtube_data_array Array of parameters from YQL
	 * @return array $ee_tags Refactored array of tag parameters
	 * @author Chris Lock
	*/
	private function _build_ee_tags($youtube_data_array, $youtube_id) {

		// Set tags so they parse if the YQL query fails
		$ee_tube_tags = array(
			'eet_id' => null,
			'eet_embed' => null,
			'eet_title' => null,
			'eet_content' => null,
			'eet_author' => null,
			'eet_duration' => null,
			'eet_duration_seconds' => 0,
			'eet_comment_count' => 0,
			'eet_categories' => array(
				array('eet_category' => null)
			),
			'eet_tags' => array(
				array('eet_tag' => null)
			),
			'eet_thumbnails' => array(
				array('eet_thumbnail' => null)
			)
		);

		// Make sure youtube_data_array is set and an array
		if ($youtube_data_array
			AND is_array($youtube_data_array)) {

			// Build tag parameters
			foreach($youtube_data_array as $youtube_data_key => $youtube_data_value) {

				// Not a tag parameter so skip
				if (!array_key_exists('eet_'.$youtube_data_key, $ee_tube_tags)) continue;
				
				// Item is array
				if (isset($ee_tube_tags['eet_'.$youtube_data_key]) 
					AND is_array($ee_tube_tags['eet_'.$youtube_data_key]) 
					AND isset($ee_tube_tags['eet_'.$youtube_data_key][0])) {

					$ee_tube_tag_key = key($ee_tube_tags['eet_'.$youtube_data_key][0]);
					$youtube_data_value_temp = array();

					// Build array of arrays
					if (is_array($youtube_data_value)) {

						// Check if $youtube_data_value is a nested array or an array with a single string
						$youtube_data_value_first_key = key($youtube_data_value);
						$youtube_data_value_array = $youtube_data_value[$youtube_data_value_first_key];

						// It's an array with a string so change it for the foreach
						if (!is_array($youtube_data_value_array)) $youtube_data_value_array = array($youtube_data_value_array);
						
						// Add count and total_results
						$youtube_data_value_array_count = 0;
						$youtube_data_value_array_total = count($youtube_data_value_array);

						foreach ($youtube_data_value_array as $youtube_data_value_item) {
							
							$youtube_data_value_item_index = $youtube_data_value_array_count++;
							$youtube_data_value_temp[$youtube_data_value_item_index]['count'] = $youtube_data_value_array_count;
							$youtube_data_value_temp[$youtube_data_value_item_index]['total_results'] = $youtube_data_value_array_total;

							// Thumbnail is an array and need to be set as keys
							if ($ee_tube_tag_key == 'eet_thumbnail') {

								foreach ($youtube_data_value_item as $thumbnail_key => $thumbnail_value) {

									if ($thumbnail_key == 'content') {

										$youtube_data_value_temp[$youtube_data_value_item_index]['eet_thumbnail_src'] = $thumbnail_value;

									} else {

										$youtube_data_value_temp[$youtube_data_value_item_index]['eet_thumbnail_'.$thumbnail_key] = $thumbnail_value;

									}

								}

							} else {

								$youtube_data_value_temp[$youtube_data_value_item_index][$ee_tube_tag_key] = $youtube_data_value_item;

							}

						}

					// Set the first array
					} else {

						$youtube_data_value_temp[][$ee_tube_tag_key] = $youtube_data_value;

					}

					// Swap the temp array for the value
					$youtube_data_value = $youtube_data_value_temp;

				}

				// Item is string, int, or has been formatted as array
				if ($youtube_data_key == 'content') {

					$ee_tube_tags['eet_'.$youtube_data_key] = self::_build_xhtml_tag($youtube_data_value);

				} else {

					$ee_tube_tags['eet_'.$youtube_data_key] = $youtube_data_value;

				}

			}

		}

		// Set embed paramter
		$embed_code = self::_build_embed_code(
			$youtube_id,
			$this->EE->TMPL->fetch_param('width'),
			$this->EE->TMPL->fetch_param('autoplay')
		);
		$ee_tube_tags['eet_embed'] = self::_build_xhtml_tag($embed_code);
		
		// Set swap duration and duration_seconds
		// so duration is formatted
		$ee_tube_tags['eet_duration_seconds'] = $ee_tube_tags['eet_duration'];
		$ee_tube_tags['eet_duration'] = self::_convert_to_minutes($ee_tube_tags['eet_duration']);

		// Debug
		// die(var_dump($ee_tube_tags['tags']));
		// die(var_dump($ee_tube_tags['thumbnails']));
		// die(var_dump($ee_tube_tags));

		$ee_tags[] = $ee_tube_tags;

		return $ee_tags;

	}

	/**
	 * Builds EE XHTML code
	 * @param string $tag_string String to be converted to XHTML tag
	 * @return array $xhtml_tag Array for XHTML tag
	 * @author Chris Lock
	*/
	private static function _build_xhtml_tag($tag_string) {

		return array(
			$tag_string,
			array(
				'text_format' => 'xhtml',
				'html_format' => 'all'
			)
		);
	}

	/**
	 * Builds YouTube embed code for the YouTube video id
	 * @param string $youtube_id YouTube video id
	 * @param string $embed_width_param Width of video embed
	 * @param boolean $embed_autoplay_param Should the video autoplay
	 * @return string $embed_code YouTube embed code
	 * @author Chris Lock
	*/
	private static function _build_embed_code($youtube_id, $embed_width_param, $embed_autoplay_param = FALSE) {

		$embed_width_default = 560;

		$embed_width = ($embed_width_param) 
			? $embed_width_param 
			: $embed_width_default;
		$embed_height = ceil($embed_width*.5625);
		$embed_autoplay = ($embed_autoplay_param) 
			? '&amp;autoplay=1' : '';

		$embed_code = '
			<iframe
				width="'.$embed_width.'" 
				height="'.$embed_height.'" 
				src="http://www.youtube.com/embed/'.$youtube_id.'?wmode=transparent'.$embed_autoplay.'" 
				frameborder="0" 
				allowfullscreen
			></iframe>';

		return $embed_code;

	}

	/**
	 * Converts seconds into a string formatted 00:00:00
	 * @param int $seconds Time in seconds
	 * @return string $time_stamp Duration formatted 00:00:00
	 * @author Chris Lock
	*/
	private static function _convert_to_minutes($seconds) {

		$seconds_int = intval($seconds);

		$time_stamp = gmdate('z:H:i:s', $seconds_int);

		// Remove leading zero and empty units
		$time_stamp = ltrim($time_stamp, '00:');
		$time_stamp = ltrim($time_stamp, '0');

		return $time_stamp;

	}
	
	/**
	 * Plugin Usage
	 */
	public static function usage() {

		ob_start();

		$dir = dirname(__file__);
		$read_me = file_get_contents($dir.'/README.md');

		echo $read_me;
		
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;

	}
}


/* End of file pi.ee_tube.php */
/* Location: /system/expressionengine/third_party/ee_tube/pi.ee_tube.php */