<?php
/**
 * Version: MPL 1.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is WURFL PHP Libraries.
 *
 * The Initial Developer of the Original Code is
 * Andrea Trasatti.
 * Portions created by the Initial Developer are Copyright (C) 2004-2005
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s): Herouth Maoz.
 *
 * @file
 */

/**
 *
 * This is a working example of a class to read the WURFL xml, take a user agent
 * and make something useful with it. Once you will have created an object with
 * this class you have access to all its capabilities.
 *
 * More info can be found here in the PHP section:
 * http://wurfl.sourceforge.net/
 *
 * Questions or comments can be sent to
 * "Andrea Trasatti" <atrasatti AT users DOT sourceforge DOT net>
 *
 * Please, support this software, send any suggestion and improvement to me
 * or the mailing list and we will try to keep it updated and make it better
 * every day.
 *
 * If you like it and use it, please let me know or contact the wmlprogramming
 * mailing list: wmlprogramming@yahoogroups.com
 * @file 
 *
 */

if ( !defined('WURFL_CONFIG') )
	@require_once('./pwurple_config.php');

if ( !defined('WURFL_CONFIG') )
	die("NO CONFIGURATION");

if ( defined('WURFL_PARSER_FILE') )
	require_once(WURFL_PARSER_FILE);
else
	require_once("./pwurple_parser.php");


/**
 *
 * pwurple_class
 *
 * Example:
 * $myDevice = new pwurple_class($wurfl, $pwurple_agents);	// $wurfl is the parsed
 *			// XML, $pwurple_agents is the list of agents and id's. When you first 
 *			// start the class simply pass them as empty variables and will be filled.
 *			// Pass the variables with all the values if you already have them.
 * $myDevice->GetDeviceCapabilitiesFromAgent('SIE-S45');
 * if ( $myDevice->browser_is_wap )
 *	if ( $myDevice->capabilities['downloadfun']['downloadfun_support'] )
 *		echo "downloadfun supported";
 *	else
 *		echo "WAP is supported, downloadfun is not";
 *
 */
class pwurple_class {
	/**
	 * associative array created by pwurple_parser.php
	 * @var associative array
	 */
	var $_wurfl="";

	/**
	 * associative array user_agent=>id
	 * @var associative array
	 */
  private $_wurfl_agents = array();

	/**
	 * device's complete user agent (just in case)
	 * @var string
	 */
	private $user_agent="";

	/**
	 * best fitting user agent found in the xml
	 * @var string
	 */
	var $pwurple_agent="";

	/**
	 * pwurple_id
	 * @var string
	 */
	var $id="";

	/**
	 * if true, Openwave's GUI (mostly wml 1.3) is supported
	 * @var bool
	 */
	var $GUI=false;

	/**
	 * device brand (manufacturer)
	 * @var string
	 */
	var $brand='';

	/**
	 * device model
	 * @var string
	 */
	var $model='';

	/**
	 * if this is a WAP device, this is set to true
	 * @var bool
	 */
	var $browser_is_wap=false;

	/**
	 * associative array with all the device's capabilities.
	 * 
	 * Example :
	 * $this->capabilities['downloadfun']['downloadfun_support'] 
	 *	true if downloadfun is supported, otherwise false
	 *
	 * @var associative array
	 */
	var $capabilities = array();

	/**
	 * Constructor, checks the user agent and sets the variables.
	 *
	 * @param $_ua	device's user_agent
	 * @param $wurfl	wurfl in array format as provided by pwurple_parser
	 * @param $pwurple_agents	array set by pwurple_parser
	 * @param $_check_accept	if true will check accept headers for wml, wap, xhtml.
	 *					Note: any i-mode device might be cut out
	 *
	 * @access public
	 *
	 */
    function pwurple_class($ua, $wurfl_file, $patch_file='') {
      $this->user_agent = $ua;
      $this->wurfl_file_parse($wurfl_file, $patch_file);
//       $wurfl=Array(), $pwurple_agents=Array()) {

/*		$this->_wurfl = $wurfl;
		$this->_wurfl_agents = $pwurple_agents;
		$this->_toLog('constructor', 'Class Initiated', LOG_NOTICE);*/
	}


  function wurfl_file_parse($wurfl_file, $patch_file='') {
  //global $wurfl, $pwurple_stat, $check_patch_params, $checkpatch_result;
    $wurfl = Array();
    
    $xml_parser = xml_parser_create();
    xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, false);
    xml_set_element_handler($xml_parser, "pwurple_start_element", "pwurple_end_element");
    xml_set_character_data_handler($xml_parser, "characterData");
    
    if ( !file_exists($wurfl_file) ) {
      pwurple_log('parse', $wurfl_file." does not exist");
      return false;
    }
    if (!($fp = fopen($wurfl_file, "r"))) {
      pwurple_log('ERROR parse', "could not open XML input");
      return false;
    }
    
  //$count = 0;
    while ($data = fread($fp, 4096)) {
    //$count++;
      if (!xml_parse($xml_parser, $data, feof($fp))) {
        die(sprintf("XML error: %s at line %d",
                    xml_error_string(xml_get_error_code($xml_parser)),
                    xml_get_current_line_number($xml_parser)));
      }
    //if ( $count > 30 )
      //return;
    }
    
    fclose($fp);
    xml_parser_free($xml_parser);
    
    pwurple_log('INFO', 'WURFL Parsing done');

    pwurple_log('INFO', "Patchfile = $patch_file");

    $check_patch_params = false;
    if ( $patch_file && file_exists($patch_file) && is_file($patch_file)) {
      pwurple_log('parse', "Trying to load XML patch file: ". $patch_file);
      $check_patch_params = true;
      $xml_parser = xml_parser_create();
      xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, false);
      xml_set_element_handler($xml_parser, "pwurple_start_element", "pwurple_end_element");
      xml_set_character_data_handler($xml_parser, "characterData");
      
      if (!($fp = fopen($patch_file, "r"))) {
        pwurple_log('parse', "could not open XML patch file: ". $patch_file);
      }
      pwurple_log('parse', "Loaded, now parsing");
      while ($data = fread($fp, 4096)) {
        if (!xml_parse($xml_parser, $data, feof($fp))) {
          die(sprintf("XML error: %s at line %d",
                      xml_error_string(xml_get_error_code($xml_parser)),
                      xml_get_current_line_number($xml_parser)));
        }
      }
      fclose($fp);
      xml_parser_free($xml_parser);
    // logging? $checkpatch_result['device']['id']
    }
    else if ( defined('WURFL_PATCH_FILE') && !file_exists($patch_file) ) {
      pwurple_log('parse', $patch_file." does not exist");
    }
    else {
      pwurple_log('parse', "No XML patch file defined");
    }
    
    
  //reset($wurfl);
  //echo "<pre>";
  //print_r($wurfl);
  //echo "</pre>";
    
    reset($wurfl);
    $devices = $wurfl["devices"];
    
  // I check if var_export loses any empty key, in this case I force the generic
  // device.
    if ( var_export_bug() ) {
      $pwurple_agents['generic'] = 'generic';
    }
    foreach($devices as $one) {
      $pwurple_agents[$one['user_agent']] = $one['id'];
    }
    
    reset($wurfl);
    reset($pwurple_agents);
    if ( WURFL_USE_CACHE ) {
      if ( defined("WURFL_AGENT2ID_FILE") && file_exists(WURFL_AGENT2ID_FILE) && !is_writeable(WURFL_AGENT2ID_FILE) ) {
        pwurple_log('parse', "ERROR: Unable to remove ".WURFL_AGENT2ID_FILE);
      //die ('Unable to remove '.WURFL_AGENT2ID_FILE);
        return;
      }
      if ( isset($pwurple_stat) ) {
        $cache_stat = $pwurple_stat;
      } else {
        $cache_stat = $pwurple_stat = filemtime(WURFL_FILE);
        if ( defined('WURFL_PATCH_FILE') && file_exists(WURFL_PATCH_FILE) ) {
          $patch_stat = filemtime(WURFL_PATCH_FILE);
          if ( $patch_stat > $pwurple_stat ) {
          // if the patch file is newer than the WURFL I set pwurple_stat to that time
            $pwurple_stat = $patch_stat;
          }
        }
      }
      if ( WURFL_USE_MULTICACHE ) {
      // If using Multicache remove old cache files
        $pwurple_temp_devices = $wurfl['devices'];
        $wurfl['devices'] = array();
      //Attempt to remove all existing multicache files
        if ( defined("MULTICACHE_DIR") && is_dir(MULTICACHE_DIR) && !is_writeable(MULTICACHE_DIR) ) {
          pwurple_log('parse', "ERROR: Unable to remove files from".MULTICACHE_DIR);
          return;
        }
      // Get all the agent file names in the multicache directory. Use
      // glob if available
        if ( function_exists( 'glob' ) ) {
          $filelist = glob( MULTICACHE_DIR . "/*" . MULTICACHE_SUFFIX );
        } else {
          if ( $dh = @opendir( MULTICACHE_DIR ) ) {
            $filelist = array();
            while (false !== ($file = @readdir($dh))) {
              $filename = MULTICACHE_DIR . "/$file";
              if ( is_file( $filename ) ) {
                $filelist[] = $filename;
              }
            }
            @closedir( $dh );
          }
        }
        foreach ( $filelist as $filename ) {
          @unlink( $filename );
        }
      }
      $php_version = PHP_VERSION;
      list($php_main_version, $php_subversion, $php_subsubversion) = explode('.', $php_version);
      $fp_cache= fopen(CACHE_FILE, "w");
      fwrite($fp_cache, "<?php\n");
    // it seems until PHP 4.3.2 var_export had a problem with apostrophes in array keys
      if ( $php_main_version > 4
           || ($php_main_version == 4 && $php_subversion > 3)
           || ($php_main_version == 4 && $php_subversion == 3 && $php_subsubversion > 2) ) {
             
             if ( !WURFL_USE_MULTICACHE ) {
               $pwurple_to_file = var_export($wurfl, true);
             }
             $pwurple_agents_to_file = var_export($pwurple_agents, true);
             $cache_stat_to_file = var_export($cache_stat, true);
             fwrite($fp_cache, "\$cache_stat=$cache_stat_to_file;\n");
             if ( !WURFL_USE_MULTICACHE ) {
               fwrite($fp_cache, "\$wurfl=$pwurple_to_file;\n");
             }
             fwrite($fp_cache, "\$pwurple_agents=$pwurple_agents_to_file;\n");
           } else {
             if ( !WURFL_USE_MULTICACHE ) {
               $pwurple_to_file = urlencode(serialize($wurfl));
             }
             $pwurple_agents_to_file = urlencode(serialize($pwurple_agents));
             $cache_stat_to_file = urlencode(serialize($cache_stat));
             fwrite($fp_cache, "\$cache_stat=unserialize(urldecode(\"". $cache_stat_to_file ."\"));\n");
             if ( !WURFL_USE_MULTICACHE ) {
               fwrite($fp_cache, "\$wurfl=unserialize(urldecode(\"". $pwurple_to_file ."\"));\n");
             }
             fwrite($fp_cache, "\$pwurple_agents=unserialize(urldecode(\"". $pwurple_agents_to_file ."\"));\n");
           }
      fwrite($fp_cache, "?>\n");
      fclose($fp_cache);
      if ( defined("WURFL_AGENT2ID_FILE") && file_exists(WURFL_AGENT2ID_FILE) ) {
        @unlink(WURFL_AGENT2ID_FILE);
      }
      if ( WURFL_USE_MULTICACHE ) {
      // Return the capabilities to the wurfl structure
        $wurfl['devices'] = &$pwurple_temp_devices;
      // Write multicache files
        if ( @FORCED_UPDATE === true )
          $path = MULTICACHE_TMP_DIR;
        else
          $path = MULTICACHE_DIR;
        if ( !is_dir($path) )
          @mkdir($path);
        foreach ( $pwurple_temp_devices as $id => $capabilities ) {
          $fname = urlencode( $id );
          $varname = addcslashes( $id, "'\\" );
          
          $fp_cache = fopen( $path . "/$fname" . MULTICACHE_SUFFIX, 'w' );
          
          fwrite($fp_cache, "<?php\n");
          if ( ($php_main_version == 4 && $php_subversion > 2) || $php_main_version > 4 ) {
            $pwurple_to_file = var_export($capabilities, true);
            fwrite($fp_cache, "\$_cached_devices['$varname']=$pwurple_to_file;\n");
          } else {
            $pwurple_to_file = urlencode(serialize($capabilities));
            fwrite($fp_cache, "\$_cached_devices['$varname']=unserialize(urldecode(\"". $pwurple_to_file ."\"));\n");
          }
          fwrite($fp_cache, "?>\n");
          fclose($fp_cache);
        }
      }
    // It's probably not really worth encoding cache.php if you're using Multicache
      if ( 0 && function_exists('mmcache_encode') ) {
        $empty= '';
        set_time_limit(60);
        $to_file = mmcache_encode(CACHE_FILE, $empty);
        $to_file = '<?php if (!is_callable("mmcache_load") && !@dl((PHP_OS=="WINNT"||PHP_OS=="WIN32")?"TurckLoader.dll":"TurckLoader.so")) { die("This PHP script has been encoded with Turck MMcache, to run it you must install <a href=\"http://turck-mmcache.sourceforge.net/\">Turck MMCache or Turck Loader</a>");} return mmcache_load(\''.$to_file."');?>\n";
        $fp_cache= fopen(CACHE_FILE, "wb");
        fwrite($fp_cache, $to_file);
        fclose($fp_cache);
      }
    }
    else {
      $cache_stat = 0;
    }
    
    return Array($cache_stat, $wurfl, $pwurple_agents);
    
  } // end of function parse
  
  
  

	/**
	 * Given the device's id reads all its capabilities
	 *
	 * @param $_id	pwurple_id di un telefonino
	 *
	 * @access private
	 *
	 */
	function _GetFullCapabilities($_id) {
		$this->_toLog('_GetFullCapabilities', "searching for $_id", LOG_INFO);
		$$_id = $this->_GetDeviceCapabilitiesFromId($_id);
		$_curr_device = $$_id;
		$_fallback_list[] = $_id;
		while ( $_curr_device['fall_back'] != 'generic' && $_curr_device['fall_back'] != 'root' ) {
			$_fallback_list[] = $_curr_device['fall_back'];
			$this->_toLog('_GetFullCapabilities', 'parent device:'.$_curr_device['fall_back'].' now going to read its capabilities', LOG_INFO);
			$$_curr_device['fall_back'] = $this->_GetDeviceCapabilitiesFromId($_curr_device['fall_back']);
			$_curr_device = $$_curr_device['fall_back'];
		}
		$this->_toLog('_GetFullCapabilities', 'reading capabilities of \'generic\' device', LOG_INFO);
		$generic = $this->_GetDeviceCapabilitiesFromId('generic');
		$_fallback_list[] = 'generic';

		end($_fallback_list);

		$_final = $generic;
		for ( $i=sizeof($_fallback_list)-2; $i>= 0; $i-- ) {
			$curr_device = $_fallback_list[$i];
//echo "capabilities di $curr_device<br>\n";
			while ( list($key, $val) = each($$curr_device) ) {
				if ( is_array($val) ) {
//echo "array_merge per $key:<br>";
//echo "<pre>\n";
//var_export($_final[$key]);
//var_export($val);
//echo "</pre>\n";
					$_final[$key] = array_merge($_final[$key], $val);
				} else {
//echo "scrivo $key=$val<br>\n";
					$_final[$key] = $val;
				}
			}
		}

		$this->capabilities = $_final;
	}

	/**
	 * Given a device id reads its capabilities
	 *
	 * @param $_id	device's pwurple_id
	 *
	 * @access private
	 *
	 */
	function _GetDeviceCapabilitiesFromId($_id) {
		$this->_toLog('_GetDeviceCapabilitiesFromId', "reading id:$_id", LOG_INFO);
		if ( $_id == 'upgui_generic' ) {
			$this->GUI = true;
		}
		if ( in_array($_id, $this->_wurfl_agents) ) {
			$this->_toLog('_GetDeviceCapabilitiesFromId', 'I have it in pwurple_agents cache, done', LOG_INFO);
			// If the device for this id does not exist, and we use multicache,
			// attempt to load the cache entry that matches the current id.
			if ( ! isset( $this->_wurfl['devices'][$_id] ) && WURFL_USE_MULTICACHE ) {
				for ($i=0;$i<3;$i++) {
					if ( is_file(MULTICACHE_TOUCH) )
						sleep(5);
					else
						break;
				}
				if ( $i>=3 ) {
					$this->_toLog('_GetDeviceCapabilitiesFromId', "CACHE CORRUPTED! ".MULTICACHE_TOUCH." on my way", LOG_WARNING);
					die("Updating cache stuck");
				}
				$fname = MULTICACHE_DIR . "/" . urlencode( $_id ) . MULTICACHE_SUFFIX;
				$genericfname = MULTICACHE_DIR . "/generic" . MULTICACHE_SUFFIX;
				if ( !is_file($fname) && is_file($genericfname) ) {
					$this->_toLog('_GetDeviceCapabilitiesFromId', "the id $_id is not present in Multicache files, using the generic: CACHE CORRUPTED!", LOG_WARNING);
					$fname = $genericfname;
				} else if ( !is_file($fname) && !is_file($genericfname) ) {
					$this->_toLog('_GetDeviceCapabilitiesFromId', "the id $_id is not present in Multicache files, nor the generic: CACHE CORRUPTED!", LOG_ERR);
					die("the id $_id is not present in Multicache");
				}
				@include( $fname );
				$this->_wurfl['devices'][$_id] = $_cached_devices[$_id];
			}
			return $this->_wurfl['devices'][$_id];
		}
		$this->_toLog('_GetDeviceCapabilitiesFromId', "the id $_id is not present in pwurple_agents", LOG_ERR);
    error_log("PWURPLE: the id $_id is not present in pwurple_agents");
		// I should never get here!!
		return false;
	}

	/**
	 * Given the user_agent reads the device's capabilities
	 *
	 * @param $_user_agent	device's user_agent
	 *
	 * @access private
	 *
	 * @return boolean
	 *
	 */
    function GetDeviceCapabilitiesFromAgent() { //$_user_agent, $_check_accept=false) {

		// Would be cool to log user agent and headers to future use to feed WURFL
		// Resetting properties
		$this->user_agent = '';
		$this->pwurple_agent = '';
		$this->id = '';
		$this->GUI = false;
		$this->brand = '';
		$this->model = '';
		$this->browser_is_wap = false;
		$this->capabilities = array();
      
    $_user_agent = $this->user_agent;
		// removing the possible Openwave MAG tag
		$_user_agent = trim(ereg_replace("UP.Link.*", "", $_user_agent));
		/* This is being remove because too many devices use Mozilla, MSIE and so on as strings in the UA
			Use the web_browser_patch.xml if you want to catch web browsers
		if (	( stristr($_user_agent, 'Opera') && stristr($_user_agent, 'Windows') )
			|| ( stristr($_user_agent, 'Opera') && stristr($_user_agent, 'Linux') )
			|| stristr($_user_agent, 'Gecko')
			|| ( (stristr($_user_agent, 'MSIE 6') || stristr($_user_agent, 'MSIE 5') ) && !stristr($_user_agent, 'MIDP') && !stristr($_user_agent, 'Windows CE') && !stristr($_user_agent, 'Symbian') )
			) {
			// This is a web browser. Not even searching
			$this->_toLog('constructor', 'Web browser', LOG_INFO);
			$this->browser_is_wap=false;
			$this->capabilities['product_info']['brand_name'] = 'Generic Web browser';
			$this->capabilities['product_info']['model_name'] = '1.0';
			$this->capabilities['product_info']['is_wireless_device'] = false;
			$this->capabilities['product_info']['device_claims_web_support'] = true;
			return true;
		} else if ( $_check_accept == true ) {
		*/

// 		if ( $_check_accept == true ) {
// 			if (
// 			     !eregi('wml', $_SERVER['HTTP_ACCEPT'])
// 			     && !eregi('wap', $_SERVER['HTTP_ACCEPT'])
// 			     && !eregi('xhtml', $_SERVER['HTTP_ACCEPT'])
// 			     ) {
// 				$this->_toLog('constructor', 'This browser does not support wml, nor wap, nor xhtml, we will never know if it was an i-mode browser', LOG_WARNING);
// 				$this->browser_is_wap=false;
// 			}
// 		}
     
		$this->_toLog('GetDeviceCapabilitiesFromAgent', 'searching for '.$_user_agent, LOG_INFO);
// 		if ( trim($_user_agent) == '' || !$_user_agent ) {
// 			// NO USER AGENT??? This is not a WAP device
// 			$this->_toLog('GetDeviceCapabilitiesFromAgent', 'No user agent', LOG_ERR);
// 			$this->browser_is_wap=false;
// 			return false;
// 		}
// 		if ( WURFL_USE_CACHE === true ) {
// 			$this->_ReadFastAgentToId($_user_agent);
// 			// if I find the device in my cache I'm done
// 			if ( $this->browser_is_wap === true ) {
// 				$this->_toLog('GetDeviceCapabilitiesFromAgent', 'Device found in local cache, the id is '.$this->id, LOG_INFO);
// 				if ( count($this->capabilities) == 0 )
// 					$this->_GetFullCapabilities($this->id);
// 				else
// 					$this->_toLog('GetDeviceCapabilitiesFromAgent', 'capabilities found in cache', LOG_INFO);
// 				return true;
// 			} else if ( count($this->_wurfl) == 0 ) {
// 				$this->_toLog('GetDeviceCapabilitiesFromAgent', 'cache enabled, WURFL is not loaded, now loading', LOG_INFO);
// 				if ( $this->_cacheIsValid() ) {
// 					$this->_toLog('GetDeviceCapabilitiesFromAgent', 'loading WURFL from cache', LOG_INFO);
// 					list($cache_stat, $this->_wurfl, $this->_wurfl_agents) = load_cache();
// 				} else {
// 					$this->_toLog('GetDeviceCapabilitiesFromAgent', 'loading WURFL from XML', LOG_INFO);
// 					$xml_info = parse();
// 					$cache_stat = $xml_info[0];
// 					$this->_wurfl = $xml_info[1];
// 					$this->_wurfl_agents = $xml_info[2];
// 				}
// 			}
// 		} else if ( WURFL_AUTOLOAD === false ) {
// 			// if not using cache and for some reason AUTOLOAD is off, I need to load it
// 			if ( count($this->_wurfl) == 0 ) {
// 				$this->_toLog('GetDeviceCapabilitiesFromAgent', 'WURFL is not loaded, now loading', LOG_INFO);
// 				$xml_info = parse();
// 				$cache_stat = $xml_info[0];
// 				$this->_wurfl = $xml_info[1];
// 				$this->_wurfl_agents = $xml_info[2];
// 			}
// 		} else {
// 				// If I'm here it means cache is disabled and autoload is on
// 				global $wurfl, $pwurple_agents;
// 				$this->_wurfl = $wurfl;
// 				$this->_wurfl_agents = $pwurple_agents;
// 		}
// 		
		$_ua = $_user_agent;
		$_ua_len = strlen($_ua);
		$_pwurple_user_agents = array_keys($this->_wurfl_agents);
		// Searching in pwurple_agents
		// The user_agent should not become shorter than 4 characters
		$this->_toLog('GetDeviceCapabilitiesFromAgent', 'Searching in the agent database for '.$_ua, LOG_INFO);
		// Search for an exact match first
		if ( in_array($_ua, $_pwurple_user_agents) ) {
			$this->user_agent = $_ua;
			$this->pwurple_agent = $_ua;
			$this->id = $this->_wurfl_agents[$_ua];
			// calling FullCapabilities to define $this->capabilities
			$this->_GetFullCapabilities($this->id);
			$this->browser_is_wap = $this->capabilities['product_info']['is_wireless_device'];
			$this->brand = $this->capabilities['product_info']['brand_name'];
			$this->model = $this->capabilities['product_info']['model_name'];
			reset($this->_wurfl_agents);
			reset($_pwurple_user_agents);
			if ( WURFL_USE_CACHE ) {
				$this->_WriteFastAgentToId();
			}
			$this->_toLog('GetDeviceCapabilitiesFromAgent', 'I found an exact match for '.$_ua.' with id '.$this->id, LOG_INFO);
			return true;
		}

		// I request to set a short list of UA's among which I should search an unknown user agent
		$_short_ua_len = 4;
		$_set_short_pwurple_ua = true;
		$_last_good_short_ua = array();
		while ( $_ua_len > 4 ) {
			$_short_pwurple_ua = array();
			$_tmp_short_ua = substr($_ua, 0, $_short_ua_len); // The current user agent's first chars
			// DEBUG fast search echo "_tmp_short_ua=$_tmp_short_ua ";
			foreach ( $_pwurple_user_agents as $_x ) {
				// If it was requested to generate a short list of user agents AND the first
				//  characters of the searched user agent and the user agent in WURFL match,
				//  I add the current ID to the short list
				if ( $_set_short_pwurple_ua === true && substr($_x, 0, $_short_ua_len) == $_tmp_short_ua )
					$_short_pwurple_ua[] = $_x;

				if ( substr($_x, 0, $_ua_len) == $_ua ) {
					$this->user_agent = $_user_agent;
					$this->pwurple_agent = $_x;
					$this->id = $this->_wurfl_agents[$_x];
					// calling FullCapabilities to define $this->capabilities
					$this->_GetFullCapabilities($this->id);
					$this->browser_is_wap = $this->capabilities['product_info']['is_wireless_device'];
					$this->brand = $this->capabilities['product_info']['brand_name'];
					$this->model = $this->capabilities['product_info']['model_name'];
					reset($this->_wurfl_agents);
					reset($_pwurple_user_agents);
					if ( WURFL_USE_CACHE ) {
						$this->_WriteFastAgentToId();
					}
					return true;
				}
			} 
			// If the list of user agents that match the first 4 chars of the current user
			//  agent is empty I can quit searching
			if ( $_short_ua_len == 4 && count($_short_pwurple_ua) == 0 ) {
				// DEBUG fast search echo "no match even for the first 4 chars<br>\n";
				break;
			} else if ( count($_short_pwurple_ua) == 0 ) {
				// I restore the last good list of short user agents
				$_pwurple_user_agents = $_last_good_short_ua;
				// DEBUG fast search echo "restoring last_good_short_ua";
				// I won't continue building a new short user agent list (longer
				//  than this)
				$_set_short_pwurple_ua = false; 
			} else {
				// This is the last list of user agents that matched the first part of
				//  the agent
				$_last_good_short_ua = $_short_pwurple_ua;
				// Next round I search for a short_ua 1 char longer
				$_short_ua_len++;
				// I will search the user agent among a shorter list at the next round!!
				$_pwurple_user_agents = $_short_pwurple_ua;
				// DEBUG fast search echo "short list has ".count($_short_pwurple_ua)." elements";
			}

			// shortening the agent by one each time
			$_ua = substr($_ua, 0, -1);
			$_ua_len--;
			reset($_pwurple_user_agents);
			// DEBUG fast search echo "<br>\n";
		}

		$this->_toLog('GetDeviceCapabilitiesFromAgent', "I couldn't find the device in my list, the headers are my last chance", LOG_WARNING);
		if ( strstr($_user_agent, 'UP.Browser/') && strstr($_user_agent, '(GUI)') ) {
			$this->browser_is_wap = true;
			$this->user_agent = $_user_agent;
			$this->pwurple_agent = 'upgui_generic';
			$this->id = 'upgui_generic';
		} else if ( strstr($_user_agent, 'UP.Browser/') ) {
			$this->browser_is_wap = true;
			$this->user_agent = $_user_agent;
			$this->pwurple_agent = 'uptext_generic';
			$this->id = 'uptext_generic';
		} else if ( isset( $_SERVER['HTTP_ACCEPT'] ) && (eregi('wml', $_SERVER['HTTP_ACCEPT']) || eregi('wap', $_SERVER['HTTP_ACCEPT'])) ) {
			$this->browser_is_wap = true;
			$this->user_agent = $_user_agent;
			$this->pwurple_agent = 'generic';
			$this->id = 'generic';
		} else {
			$this->_toLog('GetDeviceCapabilitiesFromAgent', 'This should not be a WAP device, quitting', LOG_WARNING);
			$this->browser_is_wap=false;
			$this->user_agent = $_user_agent;
			$this->pwurple_agent = 'generic';
			$this->id = 'generic';
			return true;
		}
		if ( WURFL_USE_CACHE ) {
			$this->_WriteFastAgentToId($_user_agent);
		}
		// FullCapabilities defines $this->capabilities
		$this->_GetFullCapabilities($this->id);
		return true;
	}

	/**
	 * Given a capability name returns the value (true|false|<anythingelse>)
	 *
	 * @param $capability	capability name as a string
	 *
	 * @access public
	 *
	 */
	function getDeviceCapability($capability) {
		$this->_toLog('_GetDeviceCapability', 'Searching for '.$capability.' as a capability', LOG_INFO);
		$deviceCapabilities = $this->capabilities;
		foreach ( $deviceCapabilities as $group ) {
			if ( !is_array($group) ) {
				continue;
			}
			while ( list($key, $value)=each($group) ) {
				if ($key==$capability) {
					$this->_toLog('_GetDeviceCapability', 'I found it, value is '.$value, LOG_INFO);
					return $value;
				}
			}
		}
		$this->_toLog('_GetDeviceCapability', 'I could not find the requested capability, returning false', LOG_WARNING);
		return false;
	}

	/**
	 * Saves to file the correspondence between user_agent and pwurple_id
	 *
	 * @access private
	 *
	 */
	function _WriteFastAgentToId() {
		$_ua = $this->user_agent;
		if ( is_file(WURFL_AGENT2ID_FILE) && !is_writeable(WURFL_AGENT2ID_FILE) ) {
			$this->_toLog('_WriteFastAgentToId', 'Unable to write '.WURFL_AGENT2ID_FILE, LOG_ERR);
			return;
		} else if ( !is_writeable(dirname(WURFL_AGENT2ID_FILE)) ) {
			$this->_toLog('_WriteFastAgentToId', 'Unable to create file in '.dirname(WURFL_AGENT2ID_FILE), LOG_ERR);
			return;
		}
		$_ua = trim(ereg_replace("UP.Link.*", "", $_ua));
		if ( !is_readable(WURFL_AGENT2ID_FILE) ) {
			if ( is_file(WURFL_AGENT2ID_FILE) ) {
				$this->_toLog('_WriteFastAgentToId', 'Unable to read '.WURFL_AGENT2ID_FILE, LOG_WARNING);
			}
			$cached_agents = Array();
		} else {
			// $cached_agents[0]['user_agent'] = 'SIE-S45/00'; //ua completo
			// $cached_agents[0]['pwurple_agent'] = 'SIE-S45/00'; //ua nel WURFL
			// $cached_agents[0]['id'] = 'sie_s45_ver1';
			// $cached_agents[0]['is_wap'] = true;
			include(WURFL_AGENT2ID_FILE);
		}
		// check if the device is already cached
		foreach($cached_agents as $one) {
			if ( $one['user_agent'] == $_ua ) {
				$this->_toLog('_WriteFastAgentToId', $_ua.' is already cached', LOG_INFO);
				return;
			}
		}
		$new_item_id = count($cached_agents);
		$cached_agents[$new_item_id]['user_agent'] = $_ua; // full UA
//		$cached_agents[$new_item_id]['pwurple_agent'] = $this->pwurple_agent; // corresponding UA stored in WURFL
//		$cached_agents[$new_item_id]['id'] = $this->id; // WURFL unique id
		$cached_agents[$new_item_id]['is_wap'] = true;
		$cached_agents[$new_item_id]['capabilities'] = $this->capabilities;
		$new_item_id++; // increment by one so that it still reflects the array count

		// cache resize in case it gets bigger than MAX_UA_CACHE
		if ( $new_item_id > MAX_UA_CACHE ) {
			$resized_agents = array_slice($cached_agents, ($new_item_id-MAX_UA_CACHE), MAX_UA_CACHE);
			$cached_agents = $resized_agents;
			$this->_toLog('_WriteFastAgentToId', 'Cache resized to '.MAX_UA_CACHE.' elements', LOG_INFO);
		}
		// store in cache file
		$filename = uniqid(WURFL_AGENT2ID_FILE, true);
		$fp_cache = fopen($filename, 'w');
		if ( !$fp_cache ) {
			$this->_toLog('_WriteFastAgentToId', 'Unable to open temp file '.$filename.' for writing', LOG_WARNING);
			return;
		} else {
			$this->_toLog('_WriteFastAgentToId', 'Created temp file '.$filename.' ', LOG_INFO);
		}
		fwrite($fp_cache, "<?php \n");
		fwrite($fp_cache, '$cached_agents = '.var_export($cached_agents, true));
		// If you like serialization better comment the above line, uncomment
		// the following and the line in _ReadFastAgentToId
		//fwrite($fp_cache, '$cached_agents = \''.rawurlencode(serialize($cached_agents))."';\n");
		fwrite($fp_cache, "?>");
		fclose($fp_cache);
		$rv = @rename($filename,WURFL_AGENT2ID_FILE);
		/*
		if( !$rv ){
			$this->_toLog('_WriteFastAgentToId', 'Unable to rename '.$filename.' to '. WURFL_AGENT2ID_FILE, LOG_WARNING);
			return;
		}
		*/
		if (!$rv) {
			$this->_toLog('_WriteFastAgentToId', 'Unable to rename '.$filename.' to '. WURFL_AGENT2ID_FILE, LOG_WARNING);
			$unl = @unlink(WURFL_AGENT2ID_FILE);

			if (!$unl)
				$this->_toLog('_WriteFastAgentToId', 'Unable to delete '.  WURFL_AGENT2ID_FILE, LOG_WARNING);

			$rv = @rename($filename,WURFL_AGENT2ID_FILE);
			if ( !$rv )
				$this->_toLog('_WriteFastAgentToId', 'Still unable to rename '.$filename.' to '. WURFL_AGENT2ID_FILE, LOG_WARNING);
			return;
		}

		$this->_toLog('_WriteFastAgentToId', 'Done caching user_agent to pwurple_id', LOG_INFO);
		return;
	}

	/**
	 * Reads the file with the correspondence between user_agent and pwurple_id
	 *
	 * @param $_ua	device's user_agent
	 *
	 * @access private
	 *
	 */
	function _ReadFastAgentToId($_ua) {
		// check cache validity
		if ( !$this->_cacheIsValid() ) {
			return false;
		}
		// Load cache file
		if ( is_file(WURFL_AGENT2ID_FILE) || is_link(WURFL_AGENT2ID_FILE) ) {
			include(WURFL_AGENT2ID_FILE);
			// unserialization
			//$a = unserialize(rawurldecode($cache_agents));
		}
    else {
			return false;
		}
		foreach ( $cached_agents as $device ) {
			if ( $device['user_agent'] == $_ua ) {
				$this->user_agent = $device['user_agent'];
				$this->pwurple_agent = $device['capabilities']['user_agent'];
				$this->id = $device['capabilities']['id'];
				$this->browser_is_wap = $device['is_wap'];
				$this->capabilities = $device['capabilities'];
				$this->brand = $device['capabilities']['product_info']['brand_name'];
				$this->model = $device['capabilities']['product_info']['model_name'];
				$this->_toLog('_ReadFastAgentToId', 'Found '.$_ua.' with id='.$device['capabilities']['id'], LOG_INFO);
				break;
			}
		}
		return true;
	}

	/**
	 * Check filemtimes to see if the cache should be updated
	 *
	 * @access private
	 *
	 */
	function _cacheIsValid() {

		// First of all check configuration. If autoupdate is set to false always
		// return true, otherwise check
    if (WURFL_CACHE_AUTOUPDATE === false) {
			return true;
    }

		// WURFL hasn't been loaded into memory, I'll do it now
		$pwurple_stat = filemtime(WURFL_FILE);
		if ( defined('WURFL_PATCH_FILE') && file_exists(WURFL_PATCH_FILE) ) {
			$patch_stat = filemtime(WURFL_PATCH_FILE);
			if ( $patch_stat > $pwurple_stat ) {
				// if the patch file is newer than the WURFL I set pwurple_stat to that time
				$pwurple_stat = $patch_stat;
			}
		}
		$cache_stat = stat_cache();
		if ( $pwurple_stat <= $cache_stat ) {
			return true;
		}
    else {
			$this->_toLog('_cacheIsValid', 'cache file is outdated', LOG_INFO);
			return false;
		}
	}

	/**
	 * This function checks and prepares the text to be logged
	 *
	 * @access private
	 */
	function _toLog($func, $text, $requestedLogLevel=LOG_NOTICE){
    pwurple_log($func, $text, $requestedLogLevel); 
	}

} 
