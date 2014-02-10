<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Ralph Schuster <typo3@ralph-schuster.eu>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 * Hint: use extdeveval to insert/update function index above.
 */

require_once(PATH_tslib.'class.tslib_pibase.php');


/**
 * Plugin 'FE User Profile' for the 'feuserprofile' extension.
 *
 * @author	Ralph Schuster <typo3@ralph-schuster.eu>
 * @package	TYPO3
 * @subpackage	tx_feuserprofile
 */
class tx_feuserprofile_pibase extends tslib_pibase {
	var $extKey        = 'feuserprofile';	// The extension key.
	var $fieldErrors; // Errors per field, e.g. $fieldErrors['password1'] = error_text;


	function init() {
		$this->pi_USER_INT_obj = 1;
		$this->pi_initPIflexForm();
		$this->pi_setPiVarDefaults();
		$this->local_cObj = t3lib_div::makeInstance("tslib_cObj");
		$this->local_cObj->setCurrentVal($GLOBALS["TSFE"]->id);
		$this->id = $GLOBALS["TSFE"]->id;
		$this->fieldErrors = array(); // Errors per field, e.g. $fieldErrors['password1'] = error_text;

		// Configuration
		$this->config = $this->conf;
		$this->setConfiguration('mode');
		$this->setConfiguration('userProfileFolder');
		$this->setConfiguration('templateFile');
		$this->setConfiguration('orderBy');
		$this->config['templateFile'] = $this->cObj->fileResource($this->config['templateFile']);
		$this->setConfiguration('showDisabledGroups');
		if ($this->config['showDisabledGroups']) $this->disabledGroups = explode(',', $this->config['showDisabledGroups']);
		$this->setConfiguration('specialWhereClause');
		$this->config['specialWhereClause'] = str_replace('###UID###', $GLOBALS["TSFE"]->fe_user->user['uid'], $this->config['specialWhereClause']);
	}

	function setConfiguration($varName) {
		$this->config[$varName] = $this->pi_getFFvalue( $this->cObj->data['pi_flexform'], $varName, 'sDEF' ) ? $this->pi_getFFvalue( $this->cObj->data['pi_flexform'], $varName, 'sDEF' ) : $this->conf[$varName];
        }
	
	function getGPvar($mode, $param) {
		$paramName = $this->getGPvarName($mode, $param);

		// Parse the name
		$matches = array();
		if (preg_match('/^([^\[]+)/', $paramName, $matches)) {
			$gp = t3lib_div::_GP($matches[1]);
			$offset = strlen($matches[1]);
			$matches = array();
			while ($gp && preg_match('/\[([^\]]+)\]/', $paramName, $matches, 0, $offset)) {
				$gp = $gp[$matches[1]];
				$offset += strlen($matches[1])+2;
			}
			if (isset($gp) && !is_array($gp)) $gp = trim($gp);
			return $gp;
		}
		return NULL;
	}

	function getGPvarName($mode, $param) {
		$paramName = $this->config[$mode.'.']['GPvar.'][$param];
		if (!$paramName) $paramName = $this->prefixId.'['.$param.']';
		return $paramName;
	}

	function getUserProfile($uid, $pid = 0) {
		if (!$pid) $pid = $this->config['userProfileFolder'];

		// Return non-deleted records
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_users', "uid=$uid AND deleted=0 AND pid=$pid");
		if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			return $row;
		}
		return NULL;
	}

	function getUsers($where, $orderBy = 'lastlogin DESC', $pid = 0) {
		if (!$pid) $pid = $this->config['userProfileFolder'];
		if (!$where) $where = "deleted=0 AND pid=$pid AND disable=0";
		else $where .= " AND pid=$pid";

		$rc = array();
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_users', $where, '', $orderBy);
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$row['_is_online'] = $this->isUserOnline($row['uid']);
			$rc[] = $row;
		}
		return $rc;
	}

	function isUserOnline($uid) {
		$where = "ses_userid=$uid";
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_sessions', $where);
		if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			return $this->isOnline($row['ses_tstamp']);
		}
		return 0;
	}

	function isAdminUser() {
		$groups = explode(',', $this->config['userAdminGroups']);
		foreach ($groups AS $gr) {
			if (t3lib_div::inList($GLOBALS['TSFE']->fe_user->user['usergroup'], $gr)) return 1;
		}
		return 0;
	}

	function getOnlineUserProfiles($pid = 0) {
		if (!$pid) $pid = $this->config['userProfileFolder'];

		// Order and Filter params

		// Return non-deleted records
		$rc = array();
		$where =  "a.deleted=0 AND a.pid=$pid AND a.disable=0 AND a.uid=b.ses_userid";
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_users a, fe_sessions b', $where, '', $orderBy);
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			if ($this->isOnline($row['ses_tstamp'])) {
				$row['_is_online'] = 1;
				$rc[] = $row;
			}
		}
		return $rc;
	}

	function getDisabledUserProfiles($pid = 0) {
		if (!$pid) $pid = $this->config['userProfileFolder'];

		// Order and Filter params

		// Return non-deleted records
		$rc = array();
		$where =  "deleted=0 AND pid=$pid AND disable > 0";
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'fe_users', $where, '', $orderBy);
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$addRow = 0;
			if ($this->disabledGroups) {
				$groups = explode(',', $row['usergroup']);
				foreach ($groups AS $group) {
					if (in_array($group, $this->disabledGroups)) {
						$addRow = 1;
					}
				}
			} else {
				$addRow = 1;
			}

			if ($addRow) {
				$row['_is_online'] = 0;
				$rc[] = $row;
			}
		}
		return $rc;
	}

	function getSpecialUsers($pid = 0) {
		if (!$pid) $pid = $this->config['userProfileFolder'];

		// Order and Filter
		$where = $this->config['specialWhereClause'];

		return $this->getUsers($where, $order, $pid);
	}

	function getSearchUserProfiles($pid = 0) {
		if (!$pid) $pid = $this->config['userProfileFolder'];

		// Order and Filter

		$search = "";
		$keywords = strtoupper($this->getGPvar('list', 'search'));
		$search = "(UPPER(name) like '%$keywords%') OR (UPPER(username) like '%$keywords%')";

		// Return non-deleted, enabled records
		$where =  "deleted=0 AND disable=0 AND ($search)";
		return $this->getUsers($where, $orderBy);
	}

	function isOnline($tstamp) {
		$max_idle_time = $this->config['maxIdleTime'];
		if (!$max_idle_time) $max_idle_time = 60;
		$time = time();
		$diff = $time - intval($tstamp);
		if ($diff < 0) $rc = 1;
		else if ($diff < $max_idle_time*60) $rc = 1;
		else $rc = 0;
		return $rc;
	}

	function fillTemplate($template, $mode, $valueArr) {
		$singleMarkers = array();
		$subpartMarkers = array();
		$wrapped = array();
		$this->getMarkers($template,$singleMarkers,$subpartMarkers,$wrapped, $mode, $valueArr);
		return $this->substituteMarkerArray($template, $singleMarkers,$subpartMarkers,$wrapped, $mode);
	}

	function getCustomizedObject($mode, $fieldname) {
		$objName = strtolower($this->config[$mode.'.'][$fieldname.'.']['markerClass']);
		if ($objName) {
			if (!$this->OBJECTS[$objName]) $this->OBJECTS[$objName] = t3lib_div::makeInstance($objName);
			$obj = $this->OBJECTS[$objName];
		} else {
			$obj = $this;
		}
		return $obj;
	}

	function getMarkers(&$template, &$singleMarkers, &$subpartMarkers, &$wrapped, $mode, $valueArr) {
		$conf = $this->config[$mode.'.'];
		$rc = array();
		$subMarkerCount = 0;

		// All markers <!-- ###SOME_MARKER### --> are handled by functions
		preg_match_all('!\<\!--[a-zA-Z0-9 ]*###([A-Z0-9_-]*)###[a-zA-Z0-9 ]*-->!is', $template, $match);
		$allMarkers = array_unique($match[1]);
		foreach ($allMarkers as $marker) {
			$fieldname = strtolower($marker);

			if ($this->isVisible($fieldname, $mode, $valueArr)) {
				$obj = $this->getCustomizedObject($mode, $fieldname);

				$funcName = 'get'.str_replace(' ','',ucwords(str_replace('_',' ',$fieldname))).'Markers';
				$subpartTemplate = $this->getSubpart($template, $marker);
				if (method_exists($obj, $funcName)) {
					// e.g. getThisAndThatMarkers (THIS_AND_THAT)
					$obj->$funcName($this, $subpartTemplate, $singleMarkers, $subpartMarkers, $wrapped, $mode, $valueArr);
				} else if (method_exists($this, $funcName)) {
					// e.g. getThisAndThatMarkers (THIS_AND_THAT)
					$this->$funcName($this, $subpartTemplate, $singleMarkers, $subpartMarkers, $wrapped, $mode, $valueArr);
				} else if (method_exists($obj, '_getFieldMarkers')) {
					// call a general function to take care of this value
					$obj->_getFieldMarkers($this, $subpartTemplate, $marker, $singleMarkers, $subpartMarkers, $wrapped, $mode, $valueArr);
				} else if (method_exists($this, '_getFieldMarkers')) {
					// call a general function to take care of this value
					$this->_getFieldMarkers($this, $subpartTemplate, $marker, $singleMarkers, $subpartMarkers, $wrapped, $mode, $valueArr);
				}
				$subMarkerCount++;
			} else {
				$subpartMarkers['###'.strtoupper($fieldname).'###'] = '';
			}
		}

		// Special markers
		$singleMarkers['###SUB_MARKER_COUNT###'] = $subMarkerCount;
		$singleMarkers['###PAGE_URI###'] = $this->pi_getPageLink($this->id, '');
		
		// All marker ###SOME_MARKER### are replaced directly
		preg_match_all('!\###([A-Z0-9_-|:]*)\###!is', $template, $match);
		$allSingleMarkers = array_unique($match[1]);
		//$allSingleMarkers = array_diff($allSingleMarkers, $allMarkers);
		foreach ($allSingleMarkers AS $marker) {
			// Beware: markers already set by methods above will be ignored
			if (isset($singleMarkers['###'.$marker.'###'])) continue;

			$fieldname = strtolower($marker);

			if ($this->isVisible($fieldname, $mode, $valueArr)) {
				$obj = $this->getCustomizedObject($mode, $fieldname);

				$funcName = 'get'.str_replace(' ','',ucwords(str_replace('_',' ',$fieldname))).'Marker';
				if (method_exists($obj, $funcName)) {
					// e.g. getThisAndThatMarker (THIS_AND_THAT)
					$obj->$funcName($this, $singleMarkers, $subpartMarkers, $wrapped, $mode, $valueArr);
				} else if (method_exists($this, $funcName)) {
					// e.g. getThisAndThatMarker (THIS_AND_THAT)
					$this->$funcName($this, $singleMarkers, $subpartMarkers, $wrapped, $mode, $valueArr);
				}
			} else {
				$singleMarkers['###'.$marker.'###'] = '';
			}
			
			// make error handling if marker was set and continue on next marker
			if (isset($singleMarkers['###'.$marker.'###'])) {
				$singleMarkers['###'.$marker.'###'] = $this->wrapError($mode, $fieldname, $singleMarkers['###'.$marker.'###']);
				continue;
			}

			if (preg_match('/^L_(.*)$/i', $marker, $temp)) {
				// Labels are prefixed with 'L_'
				$singleMarkers['###'.$marker.'###'] = $this->getLabel(strtolower($marker), $mode, $valueArr);
			} else if (preg_match('/^GPVAR_(.*)$/i', $marker, $temp)) {
				// This is a special marker to be replaced by the GPVAR name
				$field = strtolower($temp[1]);
				$singleMarkers['###'.$marker.'###'] = $this->getGPvarName($mode, $field);
			} else if (preg_match('/^GPVAL_(.*)$/i', $marker, $temp)) {
				// This is a special marker to be replaced by the value of GET/POST variable
				$field = strtolower($temp[1]);
				$singleMarkers['###'.$marker.'###'] = $this->getGPvar($mode, $field);
			} else {
				// Populated by values from $valueArr // stdWrapped
				$field = strtolower($marker);
				$value = $this->getFieldMarkerValue($field, $mode, $valueArr);
				$singleMarkers['###'.$marker.'###'] = $this->wrapError($mode, $field, $value);
			}
		}

	}

	function _getFieldMarkers($caller, $template, $marker, &$singleMarkers, &$subpartMarkers, &$wrapped, $mode, $valueArr) {
		$subpartMarkers['###'.$marker.'###'] = $template;
	}

	function getLabel($field, $mode, $valueArr) {
		if ($this->isVisible($field, $mode, $valueArr)) {
			$rc = $this->pi_getLL($mode.'_'.strtolower($field));
			if (!$rc) $rc = $this->pi_getLL(strtolower($field));
			return $rc;
		}
		return '';
	}

	function wrapError($mode, $field, $value) {
		// Do nothing if no field error is set
		if (!$this->fieldErrors[$field]) return $value;

		$data = array (
			'_error' => $this->fieldErrors[$field],
			'_value' => $value,
		);


		// Get the correct error config
		if ($this->config[$mode.'.'][$field.'.']['error.']) {
			$errConf1 = $this->config[$mode.'.'][$field.'.']['error'];
			$errConf2 = $this->config[$mode.'.'][$field.'.']['error.'];
		} else if ($this->config[$mode.'.']['default.']['error.']) {
			$errConf1 = $this->config[$mode.'.']['default.']['error'];
			$errConf2 = $this->config[$mode.'.']['default.']['error.'];
		} else {
			$errConf1 = NULL;
			$errConf2 = NULL;
		}

		// Wrap it
		if ($errConf1) {
			$this->local_cObj->data = $data;
			$rc = $this->local_cObj->cObjGetSingle($errConf1, $errConf2);
		} else {
			$rc = $value.$error;
		}
		
		return $rc;
	}

	function getFieldMarkerValue($field, $mode, $valueArr, $configField = '') {
		$conf = $this->config[$mode.'.'];
		$field = strtolower($field);
		if (!$configField) $configField = $field;
		$value = $valueArr[$field];

		// Shall field be displayed?

		if ($this->isVisible($configField, $mode, $valueArr)) {
			// Do we have a specific description?
			$valueConf = $this->getWrapConfig($field, $configField, $mode, $valueArr);
			$this->local_cObj->data = $valueArr;
			$this->local_cObj->data['_value'] = $valueArr[$field];
			$rc = $this->local_cObj->cObjGetSingle($valueConf[0], $valueConf[1]);

			// There need to be made some replacements before returning
			return $this->injectStdWrapVariables($rc, $field, $mode, $valueArr, $configField);
		}
		return '';
	}

	function getWrapConfig($field, $configField, $mode, $valueArr) {
		$conf = $this->config[$mode.'.'];

		// Return the special config if available
		if ($conf[$configField.'.']) {
			return array($conf[$configField], $conf[$configField.'.']);
		};

		// Return default for type of the field
		$type = $conf['type.'][$configField];
		if ($type && $conf['default.'][$type.'.']) {
			return array($conf['default.'][$type], $conf['default.'][$type.'.']);
		}

		// Return default
		return array($conf['default.']['default'], $conf['default.']['default.']);
	}

	function getTCAInputType($field) {
		if (($field == 'password1') || ($field == 'password2')) return 'password';
		t3lib_div::loadTCA('fe_users');
		$type = strtolower($GLOBALS['TCA']['fe_users']['columns'][$field]['config']['type']);
		//echo "Loaded: field=$field type=$type 1=".$GLOBALS['TCA']['fe_users']." 2=".$GLOBALS['TCA']['fe_users']['columns']." 3=".$GLOBALS['TCA']['fe_users']['columns'][$field]." 4=".$GLOBALS['TCA']['fe_users']['columns'][$field]['config']." 5=".$GLOBALS['TCA']['fe_users']['columns'][$field]['config']['type']."<br/>";
		if ($type == 'input') return 'default';
		if ($type == 'text') return 'textarea';
		if ($type == 'check') return 'check';
		if ($type == 'radio') return 'radio';
		if ($type == 'select') {
			if ($TCA['fe_users']['columns'][$field]['config']['maxitems'] > 1) return 'multiselect';
			return 'select';
		}
		if ($type == 'group') {
			if ($TCA['fe_users']['columns'][$field]['config']['maxitems'] > 1) return 'multiselect';
			return 'select';
		}
		return 'default';
	}

	function injectStdWrapVariables($template, $field, $mode, $valueArr, $configField) {
		$needles = array(
			'%%%GPVAR%%%',
			'%%%VALUE%%%',
			'%%%OPTIONS%%%',
			'%%%PREFIXID%%%',
			'%%%FIELD%%%',
			'%%%EXTPATH%%%',
			'%%%IDVAR%%%',
		);
		$values = array(
			$this->getGPvarName($mode, $field),
			htmlspecialchars($valueArr[$field]),
			$this->getSpecialOptions($field, $mode, $valueArr, $configField),
			$this->prefixId,
			$field,
			t3lib_extMgm::siteRelPath('feuserprofile'),
			'id_'.$configField,
		);
		$rc = str_replace($needles, $values, $template);
		//$rc = $template;
		return $rc;
	}

	function getSpecialOptions($field, $mode, $valueArr, $configField) {
		return '';
	}

	function getStatusMarker($caller, &$singleMarkers, &$subpartMarkers, &$wrapped, $mode, $valueArr) {
		$conf = $this->config[$mode.'.'];
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('b.*', 'fe_users AS a JOIN fe_sessions AS b ON a.uid=b.ses_userid', "a.uid=$valueArr[uid] AND deleted=0 AND a.pid=".$this->config['userProfileFolder']);
		if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$lastTime = $row['ses_tstamp'];
		}
		$max_idle_time = $conf['maxIdleTime'];
		$time = time();
		$diff = $time - intval($lastTime);
		if ($diff < 0) $valueArr['status'] = 1;
		else if ($diff < $max_idle_time*60) $valueArr['status'] = 1;
		else $valueArr['status'] = 0;
		$singleMarkers['###STATUS###'] = $this->getFieldMarkerValue('status', $mode, $valueArr);
	}

	function isVisible($field, $mode, $valueArr) {
		// Labels depend on their fieldname
		if (preg_match('/^L_/i', $field)) $field = substr($field, 2);

		// GPVar names depend on their fieldname
		if (preg_match('/^GPVAR_/i', $field)) $field = substr($field, 6);

		// GPVar values depend on their fieldname
		if (preg_match('/^GPVAL_/i', $field)) $field = substr($field, 6);

		// Is there a function to tell us?
		$obj = $this->getCustomizedObject($mode, $field);

		$funcName = 'is'.str_replace(' ','',ucwords(str_replace('_',' ',$field))).'MarkerVisible';
		if (method_exists($obj, $funcName)) {
			// e.g. isThisAndThatMarkerVisible (THIS_AND_THAT)
			return $obj->$funcName($this, $mode, $valueArr);
		} else if (method_exists($this, $funcName)) {
			// e.g. isThisAndThatMarkerVisible (THIS_AND_THAT)
			return $this->$funcName($this, $mode, $valueArr);
		}

		// Is there a value _is_visible_$field ?
		if (isset($valueArr["_is_visible_$field"])) return $valueArr["_is_visible_$field"];

		// Checking if there is visible rule in setup
		if (isset($this->config[$mode.'.'][$field.'.']['_isVisible'])) {
			// Make it with stdWrap???
			return $this->config[$mode.'.'][$field.'.']['_isVisible'];
		}

		// Check whether field is in field list
		$fieldList = explode(',', $this->config[$mode.'.']['fieldnames']);
		return in_array(strtolower($field), $fieldList);
	}

	function enableField($mode, $field) {
		$fieldList = explode(',', $this->config[$mode.'.']['fieldnames']);
		$fieldList[] = $field;
		$this->config[$mode.'.']['fieldnames'] = implode(',', $fieldList);
		
	}

	function tempnam($dir, $prefix, $suffix) {
		$tmpName = tempnam($dir, $prefix);
		$finalName = $tmpName.".$suffix";
		rename($tmpName, $finalName);
		$finalName = substr($finalName, strlen(PATH_site));
		return $finalName;
	}

	function substituteMarkerArray($content,$markContentArray,$subpartContentArray,$wrappedSubpartContentArray) {

		// If not arrays then set them
		if (!is_array($markContentArray))       
			$markContentArray=array();      // Plain markers
		if (!is_array($subpartContentArray))    
			$subpartContentArray=array();   // Subparts being directly substituted
		if (!is_array($wrappedSubpartContentArray))     
			$wrappedSubpartContentArray=array();    // Subparts being wrapped

		// Finding keys and check hash:
		$sPkeys = array_keys($subpartContentArray);
		$wPkeys = array_keys($wrappedSubpartContentArray);

		// Finding subparts and substituting them with the subpart as a marker
		foreach ($sPkeys AS $marker) {
			$content = $this->substituteSubpart($content, $marker, $subpartContentArray[$marker]);
		}

		// Finding subparts and wrapping them with markers
		reset($wPkeys);
		while(list(,$wPK)=each($wPkeys))	{
			if(is_array($wrappedSubpartContentArray[$wPK])) {
				$parts = &$wrappedSubpartContentArray[$wPK];
			} else {
				$parts = explode('|',$wrappedSubpartContentArray[$wPK]);
			}
			$content = $this->substituteSubpart($content,$wPK,$parts);
		}

		return $this->cObj->substituteMarkerArray($content,$markContentArray);
	}

	function getSubpart($template, $marker) {
		$info = $this->getSubpartInfo($template, $marker);
		if ($info) {
			return substr($template, $info['contentIndex'], $info['contentLength']);
		}
		return '';
	}

	function substituteSubpart($template, $marker, $replacement) {
		$info = $this->getSubpartInfo($template, $marker);
		if ($info) {
			return substr($template, 0, $info['subpartIndex']).$replacement.substr($template, $info['subpartIndex'] + $info['subpartLength']);
		}
		return $template;
	}

	function getSubpartInfo($template, $marker) {
		$marker = str_replace('#','',$marker);
		$beginMarker = '';
		$beginPos = -1;
		$endMarker = '';
		$endPos = -1;
		preg_match_all('!\<\!--[a-zA-Z0-9 ]*###([A-Z0-9_-|:]*)###([a-zA-Z0-9 ]*)-->!is', $template, $matches, PREG_OFFSET_CAPTURE);
		for ($i=0; $i<count($matches[0]); $i++) {
			$markerTag = $matches[0][$i][0];
			$markerPos = $matches[0][$i][1];
			$markerName = $matches[1][$i][0];
			$markerComment = strtolower(trim($matches[2][$i][0]));
			if ($marker == $markerName) {
				if (($markerComment == 'begin') && ($beginPos < 0)) {
					$beginPos = $markerPos;
					$beginMarker = $markerTag;
					$beginLength = strlen($beginMarker);
				}
				if (($markerComment == 'end') && ($beginPos >=0) && ($endPos < 0)) {
					$endPos = $markerPos;
					$endMarker = $markerTag;
					$endLength = strlen($endMarker);
					$contentStart = $beginPos + $beginLength;
					$contentEnd = $endPos;
					$contentLength = $contentEnd - $contentStart;
					$rc = array(
						'beginMarkerIndex' => $beginPos,
						'beginMarker' => $beginMarker,
						'beginMarkerLength' => $beginLength,
						'contentIndex' => $contentStart,
						'contentLength' => $contentLength,
						'endMarkerIndex' => $endPos,
						'endMarker' => $endMarker,
						'endMarkerLength' =>  $endLength,
						'subpartIndex' => $beginPos,
						'subpartLength' => $beginLength + $contentLength + $endLength,
					);
					return $rc;
				}
			}
		}
		return NULL;
	}
}


?>
