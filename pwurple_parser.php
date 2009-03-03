<?php
/**
* Version: MPL 1.1
* @licence
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
* Portions created by the Initial Developer are Copyright (C) 2005
* the Initial Developer. All Rights Reserved.
*
* Contributor(s): Herouth Maoz.
*
*/

/*
 * $Id$
 * $RCSfile: pwurple_parser.php,v $ v2.1 beta3 (Feb, 28 2006)
 * Author: Andrea Trasatti ( atrasatti AT users DOT sourceforge DOT net )
 * Multicache implementation: Herouth Maoz ( herouth AT spamcop DOT net )
 *
*/

/**
* This is a VERY simple PHP script to demonstrate how you could parse the WURFL
* and have an associative array. Once you have the array you can obtain all the
* data you need. You will certanly need some filters and take in consideration,
* mainly, the fall_back feature. It's not natively implemented here, that is to
* say, if you don't know a phone's feature and it's not listed in its
* characteristics, you (read "your software") will need to search in its
* parent.
*
* In order to let this parser work you will NEED "wurfl.xml" in the same
* directory as the parser, otherwise you will need to change WURFL_FILE define.
*
* To speed up I also implemented a simple caching system, I serialize $wurfl
* and the mtime of wurfl.xml. Then I check that mtime with wurfl's mtime, if
* they are different I update the cache.
* NOTE: if wurfl's mtime is older than the one stored in the cache file I
* will not update the cache.
*
* From some tests I ran, it looks like NOT using a php cache will make this
* caching system not faster than reparsing the XML (tested with PHP 4.2.3 on
* a P3 600). A php caching system is strongly suggested.
*
* Logging. Logging is not really part of the tasks of this library. I included
* basic logging just to survive BIG configuration mistakes. Warnings and
* errors will be logged to the WURFL_LOG_FILE or, if not present, in the
* webserver's log file using the error_log function.
* Really bad events such as a wrong XML file, missing WURFL and a few more
* will still generate a die() along with a log to file.
* Someone might also want to send an e-mail or something, but this is WAY
* ahead of the scope of this script.
*
* More info can be found here in the PHP section:
* http://wurfl.sourceforge.net/
*
* Questions or comments can be sent to
*   "Andrea Trasatti" <atrasatti AT users DOT sourceforge DOT net>
*
* Please, support this software, send any suggestion and improvement to me
* or the mailing list and we will try to keep it updated and make it better
* every day.
*
* If you like it and use it, please let me know or contact the wmlprogramming
* mailing list: wmlprogramming@yahoogroups.com
* @file PWURPLE Parser
*/

if ( !defined('WURFL_CONFIG') ) {
  require_once('./pwurple_config.php');
}

if ( !defined('WURFL_CONFIG') ) {
  die("NO CONFIGURATION");
}

// $wurfl = array();
// $pwurple_agents = array();
// $patch_params = Array();



// Author: Herouth Maoz
// Check if var_export has the bug that eliminates empty string keys. Returns
// true if the bug exists
function var_export_bug() {
  if ( ! function_exists( 'var_export' ) ) {
    return false;
  }
  $a = array( '' => '!' );
  $export_a = var_export( $a, true );
  eval ( "\$b = $export_a;" );
  
  return count( $b ) != count( $a );
}
// this function check WURFL patch integrity/validity
function checkpatch($name, $attr) {
  global $wurfl, $patch_params, $checkpatch_result;
  
  if ( $name == 'pwurple_patch' ) {
    $checkpatch_result['pwurple_patch'] = true;
    return true;
  } else if ( !$checkpatch_result['pwurple_patch'] ) {
    $checkpatch_result['pwurple_patch'] = false;
    pwurple_log('checkpatch', "no pwurple_patch tag! Patch file ignored.");
    return false;
  }
  if ( $name == 'devices' ) {
    $checkpatch_result['devices'] = true;
    return true;
  } else if ( !$checkpatch_result['devices'] ) {
    $checkpatch_result['devices'] = false;
    pwurple_log('checkpatch', "no devices tag! Patch file ignored.");
    return false;
  }
  if ( $name == 'device' ) {
    if ( isset($wurfl['devices'][$attr['id']]) ) {
      if ( $wurfl['devices'][$attr['id']]['user_agent'] != $attr['user_agent'] ) {
        $checkpatch_result['device']['id'][$attr["id"]]['patch'] = false;
        $checkpatch_result['device']['id'][$attr["id"]]['reason'] = 'user agent mismatch, orig='.$wurfl['devices'][$attr['id']]['user_agent'].', new='.$attr['user_agent'].', id='.$attr['id'].', fall_back='.$attr['fall_back'];
      }
    }
    /*
     * checking of the fall_back is disabled. I might define a device's fall_back which will be defined later in the patch file.
     * fall_backs checking could be done after merging.
    if ( $attr['id'] == 'generic' && $attr['user_agent'] == '' && $attr['fall_back'] == 'root' ) {
      // generic device, everything's ok.
    } else if ( !isset($wurfl['devices'][$attr['fall_back']]) ) {
      $checkpatch_result['device']['id'][$attr["id"]]['patch'] = false;
      $checkpatch_result['device']['id'][$attr["id"]]['reason'] .= 'wrong fall_back, id='.$attr['id'].', fall_back='.$attr['fall_back'];
    }
     */
    if ( isset($checkpatch_result['device']['id'][$attr["id"]]['patch'])
         && !$checkpatch_result['device']['id'][$attr["id"]]['patch'] ) {
           pwurple_log('checkpatch', "ERROR:".$checkpatch_result['device']['id'][$attr["id"]]['reason']);
           return false;
         }
  }
  return true;
}

function pwurple_start_element($parser, $name, $attr) {
  global $wurfl, $curr_event, $curr_device, $curr_group, $fp_cache, $check_patch_params, $checkpatch_result;
  
  if ( $check_patch_params ) {
    // if the patch file checks fail I don't merge info retrived
    if ( !checkpatch($name, $attr) ) {
      pwurple_log('pwurple_start_element', "error on $name, ".$attr['id']);
      $curr_device = 'dump_anything';
      return;
    } else if ( $curr_device == 'dump_anything' && $name != 'device' ) {
      // this capability is referred to a device that was erroneously defined for some reason, skip it
      pwurple_log('pwurple_start_element', $name." cannot be merged, the device was skipped because of an error");
      return;
    }
  }
  
  switch($name) {
    case "ver":
      case "last_updated":
      case "official_url":
      case "statement":
      //cdata will take care of these, I'm just defining the array
      $wurfl[$name]="";
      //$curr_event=$wurfl[$name];
    break;
    case "maintainers":
      case "maintainer":
      case "authors":
      case "author":
      case "contributors":
      case "contributor":
      if ( sizeof($attr) > 0 ) {
        // dirty trick: author is child of authors, contributor is child of contributors
        while ($t = each($attr)) {
          // example: $wurfl["authors"]["author"]["name"]="Andrea Trasatti";
          $wurfl[$name."s"][$name][$attr["name"]][$t[0]]=$t[1];
        }
      }
    break;
    case "device":
      if ( ($attr["user_agent"] == "" || ! $attr["user_agent"]) && $attr["id"]!="generic" ) {
        die("No user agent and I am not generic!! id=".$attr["id"]." HELP");
      }
    if ( sizeof($attr) > 0 ) {
      $patch_values = '';
      if ( !isset($wurfl["devices"][$attr["id"]])  ) {
        $new_device = true;
      }
      while ($t = each($attr)) {
        if ( $check_patch_params && defined('WURFL_PATCH_DEBUG') && WURFL_PATCH_DEBUG === true ) {
          if ( !isset($wurfl["devices"][$attr["id"]][$t[0]])  ) {
            if ( $new_device !== true ) {
              $patch_values .= 'adding ';
            }
            $patch_values .= $t[0].'='.$t[1].', ';
          } else if ( $wurfl["devices"][$attr["id"]][$t[0]] != $t[1] ) {
            $patch_values .= $t[0].', '.$wurfl["devices"][$attr["id"]][$t[0]].'=>'.$t[1].', ';
          }
        }
          // example: $wurfl["devices"]["ericsson_generic"]["fall_back"]="generic";
        $wurfl["devices"][$attr["id"]][$t[0]]=$t[1];
      }
    }
    if ( $check_patch_params && defined('WURFL_PATCH_DEBUG') && WURFL_PATCH_DEBUG === true ) {
      if ( $new_device === true ) {
        $log_string = 'Adding device '.$attr["id"].' ';
        $new_device = false;
      } else {
        $log_string = 'Updating device '.$attr["id"].' ';
      }
      if ( strlen($patch_values) > 0 )
        $log_string .= ': '.$patch_values;
      pwurple_log('parse', $log_string);
    }
    $curr_device=$attr["id"];
    break;
    case "group":
      // this HAS NOT to be executed or we will define the id as string and then reuse it as array: ERROR
      //$wurfl["devices"][$curr_device][$attr["id"]]=$attr["id"];
      $curr_group=$attr["id"];
    break;
    case "capability":
      if ( $attr["value"] == 'true' ) {
        $value = true;
      } else if ( $attr["value"] == 'false' ) {
        $value =  false;
      } else {
        $value = $attr["value"];
        $intval = intval($value);
        if ( strcmp($value, $intval) == 0 ) {
          $value = $intval;
        }
      }
    if ( $curr_device != 'generic' && !isset($wurfl["devices"]['generic'][$curr_group][$attr["name"]]) ) {
      pwurple_log('parse', 'Capability '.$attr["name"].' in group '.$curr_group.' is not defined in the generic device, can\'t set it for '.$curr_device.'.');
    } else {
      if ( $check_patch_params && defined('WURFL_PATCH_DEBUG') && WURFL_PATCH_DEBUG === true ) {
        if ( isset($wurfl["devices"][$curr_device][$curr_group][$attr["name"]]) ) {
          pwurple_log('parse', $curr_device.': updating '.$attr["name"].', '.$wurfl["devices"][$curr_device][$curr_group][$attr["name"]].'=>'.$value);
        } else {
          pwurple_log('parse', $curr_device.': setting '.$attr["name"].'='.$value);
        }
      }
      $wurfl["devices"][$curr_device][$curr_group][$attr["name"]]=$value;
    }
    break;
    case "devices":
      // This might look useless but it's good when you want to parse only the devices and skip the rest
      if ( !isset($wurfl["devices"]) )
      $wurfl["devices"]=array();
    break;
    case "pwurple_patch":
      // opening tag of the patch file
      case "wurfl":
      // opening tag of the WURFL, nothing to do
      break;
    case "default":
      // unknown events are not welcome
      die($name." is an unknown event<br>");
    break;
  }
}


function pwurple_end_element($parser, $name) {
  global $wurfl, $curr_event, $curr_device, $curr_group;
  switch ($name) {
    case "group":
      break;
    case "device":
      break;
    case "ver":
      case "last_updated":
      case "official_url":
      case "statement":
      $wurfl[$name]=$curr_event;
      // referring to $GLOBALS to unset curr_event because unset will not destroy
      // a global variable unless called in this way
    unset($GLOBALS['curr_event']);
    break;
    default:
      break;
  }
  
}

function characterData($parser, $data) {
  global $curr_event;
  if (trim($data) != "" ) {
    $curr_event.=$data;
    //echo "data=".$data."<br>\n";
  }
}

function load_cache() {
  // Setting default values
  $cache_stat = 0;
  $wurfl = $pwurple_agents = array();
  
  if ( WURFL_USE_CACHE && file_exists(CACHE_FILE) ) {
    include(CACHE_FILE);
  }
  return Array($cache_stat, $wurfl, $pwurple_agents);
}

function stat_cache() {
  $cache_stat = 0;
  if ( WURFL_USE_CACHE && file_exists(CACHE_FILE) ) {
    $cache_stat = filemtime(CACHE_FILE);
  }
  return $cache_stat;
}


function pwurple_log($func, $msg, $logtype=3) {
  echo "PWURPLE: [$func] $msg\n";
  error_log("PWURPLE: [$func] $msg");
//   // Thanks laacz
//   $_textToLog = date('r')." [".php_uname('n')." ".getmypid()."]"."[$func] ".$msg;
//   
//   if ( $logtype == 3 && is_file(WURFL_LOG_FILE) ) {
//     if ( !@error_log($_textToLog."\n", 3, WURFL_LOG_FILE) ) {
//       error_log("Unable to log to ".WURFL_LOG_FILE." log_message:$_textToLog"); // logging in the webserver's log file
//     }
//   } else {
//     error_log($_textToLog); // logging in the webserver's log file
//   }
// }
// 
// if ( !file_exists(WURFL_FILE) ) {
//   pwurple_log('main', WURFL_FILE." does not exist");
//   die(WURFL_FILE." does not exist");
// }
// 
// if ( WURFL_AUTOLOAD === true ) {
//   $pwurple_stat = filemtime(WURFL_FILE);
//   $cache_stat = stat_cache();
//   if ( defined('WURFL_PATCH_FILE') && file_exists(WURFL_PATCH_FILE) ) {
//     $patch_stat = filemtime(WURFL_PATCH_FILE);
//   } else {
//     $patch_stat = $pwurple_stat;
//   }
//   if (WURFL_USE_CACHE && $pwurple_stat <= $cache_stat && $patch_stat <= $cache_stat) {
//     // cache file is updated
//     //echo "wurfl date = ".$pwurple_stat."<br>\n";
//     //echo "patch date = ".$patch_stat."<br>\n";
//     //echo "cache date = ".$cache_stat."<br>\n";
//     
//     list($cache_stat, $wurfl, $pwurple_agents) = load_cache();
//     
//     // echo "cache loaded";
//   } else {
//     list($cache_stat, $wurfl, $pwurple_agents) = parse();
//   }
}
