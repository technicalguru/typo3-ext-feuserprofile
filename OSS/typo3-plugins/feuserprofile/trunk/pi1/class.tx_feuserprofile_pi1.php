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

require_once(t3lib_extMgm::extPath('feuserprofile').'res/class.tx_feuserprofile_pibase.php');


/**
 * Plugin 'FE User Profile' for the 'feuserprofile' extension.
 *
 * @author	Ralph Schuster <typo3@ralph-schuster.eu>
 * @package	TYPO3
 * @subpackage	tx_feuserprofile
 */
class tx_feuserprofile_pi1 extends tx_feuserprofile_pibase {
	
	var $relPath       = 'pi1';
	var $prefixId      = 'tx_feuserprofile_pi1';
	var $scriptRelPath = 'pi1/class.tx_feuserprofile_pi1.php';
	
	// Files to be copied upon submit
	var $submittedFiles = array();
	
	// Files to be removed upon submit
	var $submittedRemoveFiles = array();
	
	// Fields to be restored upon failure
	var $resetFields = array();

	/**
	 * The HTML content.
	 */
	function getPluginContent() {
		$this->setConfiguration('specialWhereClause');
		$this->setConfiguration('orderBy');
		$this->config['specialWhereClause'] = str_replace('###UID###', $GLOBALS["TSFE"]->fe_user->user['uid'], $this->config['specialWhereClause']);
		
		//print_r($this->config);		
		$content='';
		//$this->pi_linkToPage('get to this page again',$GLOBALS['TSFE']->id);

		$action = $this->config['mode'];
		if ($action == 'VIEW') {
			$content = $this->getViewProfile();
		} else if ($action == 'EDIT') {
			$content = $this->getEditProfile();
		} else if ($action == 'LIST') {
			$content = $this->getListProfiles();
		} else if ($action == 'ONLINE') {
			$content = $this->getOnlineProfiles();
		} else if ($action == 'DISABLED') {
			$content = $this->getDisabledProfiles();
		} else if ($action == 'SEARCH') {
			$content = $this->getSearchProfiles();
		} else if ($action == 'SPECIAL') {
			$content = $this->getSpecialProfiles();
		}
		return $this->pi_wrapInBaseClass($content);
	}

	function getViewProfile() {
		$template = $this->getSubTemplate('VIEW_PROFILE');
		$uid = $this->getGPvar('view', 'uid');
		if (!$uid && $GLOBALS['TSFE']->fe_user) {
			$uid = $GLOBALS['TSFE']->fe_user->user['uid'];
		}
		if ($uid) {
			$profile = $this->db->getUser($uid);
		}

		if ($profile) {
			$profile['_is_online'] = $this->db->isUserOnline($uid);
			$rc = $this->fillTemplate($template, 'view', $profile);
		} else {
			$rc = 'no such user';
		}
		return $rc;
	}

	function getEditProfile() {
		$template = $this->getSubTemplate('EDIT_PROFILE');
		$profile = $this->retrieveProfile();
		if (count($profile) > 0) {
			$this->processGPvars('edit', $profile);
			// check if MD5 was enabled
			if ($this->config['edit.']['useMD5'] && 
			    (!t3lib_extMgm::isLoaded('saltedpasswords') || !tx_saltedpasswords_div::isUsageEnabled('FE'))) {
				$this->enableField('edit', 'md5');
				$this->enableField('edit', 'md5_submit');
			}
			// TODO: UID als hidden abspeichern falls nicht die eigene
			$rc = $this->fillTemplate($template, 'edit', $profile);
		} else {
			$rc = 'no such user';
		}
		return $rc;
	}

	function getSearchProfiles() {
		$template = $this->getSubTemplate('SEARCH_PROFILES');
		$keywords = $this->getGPvar('search', 'search');
		$values = array (
			'search' => $keywords,
			'list_page_uri' => $this->config['listProfilesPID'],
			'id' => $this->config['listProfilesPID'],
		);
		$rc = $this->fillTemplate($template, 'search', $values);
		return $rc;
	}

	function retrieveProfile() {
		$uid = $this->getGPvar('edit', 'uid');
		if (!$this->db->isAdminUser() || (!$uid && $GLOBALS['TSFE']->fe_user)) {
			$uid = $GLOBALS['TSFE']->fe_user->user['uid'];
		}
		if ($uid) {
			$profile = $this->db->getUser($uid);
		} else {
			$profile = array();
		}
		return $profile;	
	}

	function processGPvars($mode, &$valueArr) {
		// Attention: Do not process unless form was submittes
		if (!$this->getGPvar($mode, 'submit')) return;

		$rc = parent::processGPvars($mode, $valueArr);
		
		// Do save only if all processing was successful
		if ($rc) {
			$this->submitRecord($valueArr);
		} else {
			$this->resetRecord($valueArr);
		}
	}

	function submitRecord(&$valueArr) {
		$originalProfile = $this->retrieveProfile();

		foreach ($this->submittedFiles as $fileAction) {
			$src = $fileAction[0];
			$dst = $fileAction[1];

			if ($src != $dst) {
				if (copy($src, $dst)) {
					if ($fileAction[2]) unlink($src);
				}
			}
		}

		foreach ($this->submittedRemoveFiles as $file) {
			unlink($file);
		}
		$this->enableField('edit', 'notice');

		// Now save the record
		$updateRecord = $valueArr;
		unset($updateRecord['uid']);
		unset($updateRecord['pid']);
		unset($updateRecord['_is_online']);
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('fe_users', "uid=$valueArr[uid] AND pid=$valueArr[pid]", $updateRecord);
		$newProfile = $this->retrieveProfile();

		// Hook to allow more actions on successful profile changes
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['post_submit_hook'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['post_submit_hook'] as $_classRef) {
				$_procObj = &t3lib_div::getUserObj($_classRef);
				$_procObj->UserProfilePostSubmitHook($originalProfile, $newProfile);
			}
		}
	}

	function resetRecord(&$valueArr) {
		foreach ($this->submittedFiles as $fileAction) {
			$src = $fileAction[0];
			$dst = $fileAction[1];
			if (($src != $dst) && $fileAction[2]) unlink($src);
		}

		// get a fresh profile
		$originalProfile = $this->retrieveProfile();
		foreach ($this->resetFields AS $field) {
			$valueArr[$field] = $originalProfile[$field];
		}
	}

	function _generalProcessGPvars($caller, $mode, $field, $value, &$valueArr) {
		if ($this->isRequiredField($mode, $field) && !$value) {
			$err = $this->pi_getLL($mode.'_'.$field.'_required');
			if (!$err) $err = $this->pi_getLL($mode.'_required');
			$this->fieldErrors[$field] = $err;
		}
		$valueArr[$field] = $value;
	}

	function isRequiredField($mode, $field) {
		$fields = explode(',', $this->config[$mode.'.']['requiredFields']);
		return in_array($field, $fields);
	}

	function processSubmitGPvar($caller, $mode, $value, &$valueArr) {
		$this->submitted = TRUE;
	}

	function processCancelGPvar($caller, $mode, $value, &$valueArr) {
		$this->submitted = FALSE;
	}

	function processPassword1GPvar($caller, $mode, $value, &$valueArr) {
		$this->password1 = $value;
		// Wait for second password
	}

	function processPassword2GPvar($caller, $mode, $value, &$valueArr) {
		$password1 = $this->password1;
		$password2 = $value;

		// No password given
		if (!$password1 && !$password2) return;

		// Both passwords given
		if ($password1 && $password2) {
			if ($password1 == $password2) {
				// Salt password if required
				if (t3lib_extMgm::isLoaded('saltedpasswords')) {
					if (tx_saltedpasswords_div::isUsageEnabled('FE')) {
						$objSalt = tx_saltedpasswords_salts_factory::getSaltingInstance(NULL);
						if (is_object($objSalt)) {
							$password1 = $objSalt->getHashedPassword($password1);
						}
					}
				}
				$valueArr['password'] = $password1;
				return;
			}
			$this->fieldErrors['password1'] = $this->pi_getLL($mode.'_password_mismatch');
			$this->fieldErrors['password2'] = $this->pi_getLL($mode.'_password_mismatch');
			return;
		}

		// Either one is not set
		if (!$password1) $this->fieldErrors['password1'] = $this->pi_getLL($mode.'_password1_missing');
		else $this->fieldErrors['password2'] = $this->pi_getLL($mode.'_password2_missing');
	}

	function processImageGPvar($caller, $mode, $value, &$valueArr) {
		$imgTypes = array(1 => 'gif', 2 => 'jpg', 3 => 'png');

		$picFolder = $this->config['userPicDir'];
		if (($value['name'] != '') && ($value['type'] != '') && ($value['tmp_name'] != '')) {
			// upload
			if ($value['error'] != 0) {
				$this->fieldErrors['image'] = $this->pi_getLL($mode.'_image_internal_error');
				return;
			}

			// Check the image type
			if (!preg_match('/^image\//', $value['type'])) {
				// Invalid type
				$this->fieldErrors['image'] = $this->pi_getLL($mode.'_image_invalid_image_type');
				return;
			}

			// Remember source location
			$sourceFile = $value['tmp_name'];
			$dim = getimagesize($sourceFile);
			$fileExt = $imgTypes[$dim[2]];

			// Final destination once the submit is confirmed
			$finalName = $this->tempnam($picFolder, 'userpic_'.$valueArr['uid'].'_', $fileExt);

			// Resize the image
			$allowedSize = $this->config['maxFileSize'] * 1024;
			$maxW = $this->config['maxW'];
			$maxH = $this->config['maxH'];
			if (($value['size'] > $allowedSize) || ($dim[0] > $max) || ($dim[1] > $maxH)) {
				if ((0 < $dim[2]) && ($dim[2] < 4)) {
					// Only specific types can be resized

					// Move file for resizing
					$uniqueName = $this->tempnam($picFolder, 'tmp_'.$valueArr['uid'].'_', $fileExt);
					t3lib_div::upload_copy_move($sourceFile, $uniqueName);

					// Resize
					$resizedImage = $this->resizeImage($dim, $uniqueName, $maxW, $maxH);

					// Get rid of temporary image
					if ($uniqueName != $resizedImage) {
						unlink($uniqueName);
					}

					// Remember for submit action
					$this->submittedFiles[] = array($resizedImage, $finalName, TRUE);
				} else {
					// Image too large, reject
					return FALSE;
				}
			} else {
				$this->submittedFiles[] = array($sourceFile, $finalName, FALSE);
			}

			if ($valueArr['image']) $this->submittedRemoveFiles[] = $valueArr['image'];
			$valueArr['image'] = $finalName;
			$this->resetFields[] = 'image';

		} else {
			$isRemove = $this->getGPvar($mode, 'removeImage');
			if ($isRemove) {
				$this->submittedRemoveFiles[] = $valueArr['image'];
				$this->resetFields[] = 'image';
				$valueArr['image'] = '';
			}
		}
	}

	function getListProfiles() {
		$template = $this->getSubTemplate('LIST_PROFILES');

		$dataArr = array();

		if ($this->getGPvar('list', 'search')) {
			$dataArr['type'] = 'SEARCH';
		}

		$rc = $this->fillTemplate($template, 'list', $dataArr);
		return $rc;
	}

	function getSpecialProfiles() {
		$template = $this->getSubTemplate('LIST_PROFILES');
		$dataArr = array(
			'type' => 'SPECIAL',
		);

		$rc = $this->fillTemplate($template, 'list', $dataArr);
		return $rc;
	}

	function getOnlineProfiles() {
		$template = $this->getSubTemplate('LIST_PROFILES');
		$dataArr = array(
			'type' => 'ONLINE',
		);
		$rc = $this->fillTemplate($template, 'online', $dataArr);
		return $rc;
	}

	function getDisabledProfiles() {
		$template = $this->getSubTemplate('LIST_PROFILES');
		$dataArr = array(
			'type' => 'DISABLED',
		);
		$rc = $this->fillTemplate($template, 'disabled', $dataArr);
		return $rc;
	}

	function getMembersMarkers($caller, $template, &$singleMarkers, &$subpartMarkers, &$wrapped, $mode, &$valueArr) {
		if ($valueArr['type'] == 'ONLINE') {
			$profiles = $this->db->getOnlineUsers();
		} else if ($valueArr['type'] == 'DISABLED') {
			$profiles = $this->db->getDisabledUsers($this->disabledGroups);
		} else if ($valueArr['type'] == 'SEARCH') {
			$profiles = $this->db->getSearchUsers($this->getGPvar('list', 'search'));
		} else if ($valueArr['type'] == 'SPECIAL') {
			$profiles = $this->db->getSpecialUsers($this->disabledGroups);
		} else {
			$profiles = $this->db->getUsers();
		}

		// Filter and Sort
		$profiles = $this->applyFilters($profiles);
		$this->applySort($profiles);

		// Render the navigation first
		$valueArr['member_count'] = count($profiles);
		$startIndex = $this->getGPvar($mode, 'start');
		$navtemplate = $this->getSubTemplate('LIST_NAVIGATION');
		$navigation = $this->fillTemplate($navtemplate, 'listnav', array('start' => $startIndex, 'count' => count($profiles), 'id' => $this->id));
		$content .= $navigation;

		$rc = '';
		$odd = 1;
		$idx = 0;
		$cnt = 0;
		if (!$startIndex) $startIndex = 0;
		foreach ($profiles AS $profile) {
			$idx++;
			if ($idx <= $startIndex) continue;

			// Render
			$tdc = $odd ? 'class="odd"' : 'class="even"';
			$localT = str_replace('###CLASS###', $tdc, $template);
			$profile['_is_online'] = $this->db->isUserOnline($profile);
			$content .= $this->fillTemplate($localT, $mode, $profile);
			$odd = $odd ? 0 : 1;

			// Render at most 30 items
			$cnt++;
			if ($cnt >= $this->config['maxListItems']) break;
		}

		// Render navigation again
		$content .= $navigation;

		if (count($profiles) == 0) {
			$content = $this->pi_getLL('l_no_members');
		}
		$subpartMarkers['###MEMBERS###'] = $content;
	}

	function getMd5SubmitMarker($caller, &$singleMarkers, &$subpartMarkers, &$wrapped, $mode, $valueArr) {
		$singleMarkers['###MD5_SUBMIT###'] = 'onSubmit="return enc_form(this);"';
	}

	function getPagenavMarkers($caller, $template, &$singleMarkers, &$subpartMarkers, &$wrapped, $mode, $valueArr) {
		$i = 0;
		$pageNo = 1;
		while ($i < $valueArr['count']) {
			$tArr = array(
				'start' 	=> $i, 
				'pageno' 	=> $pageNo, 
				'_setLink' 	=> $i == $valueArr['start'] ? 0 : 1, 
				'id' => $this->id,
			);

			$this->addFilterAndSortVars($tArr);

			$rc .= $this->fillTemplate($template, $mode, $tArr);

			$i += $this->config['maxListItems'];
			$pageNo++;
		}

		// Next
		if ($valueArr['start'] + $this->config['maxListItems'] < $valueArr['count']) {
			$data = array(
				'start' => $valueArr['start'] + $this->config['maxListItems'],
				'_setLink' => 1,
				'pageno' => $this->getLabel('l_next', $mode, array()),
				'id' => $this->id,
			);
			$this->addFilterAndSortVars($data);
			$rc .= $this->fillTemplate($template, $mode, $data);
		}

		// Previous
		if ($valueArr['start'] > 0) {
			$data = array(
				'start' => $valueArr['start'] - $this->config['maxListItems'],
				'_setLink' => 1,
				'pageno' => $this->getLabel('l_previous', $mode, array()),
				'id' => $this->id,
			);
			$this->addFilterAndSortVars($data);
			$rc = $this->fillTemplate($template, $mode, $data).$rc;
		}
		$subpartMarkers['###PAGENAV###'] = $rc;
	}

	function getNoticeMarkers($caller, $template, &$singleMarkers, &$subpartMarkers, &$wrapped, $mode, $valueArr) {
		$valueArr['notice'] = $this->pi_getLL($mode.'_saved');
		$rc = $this->fillTemplate($template, $mode, $valueArr);
		$subpartMarkers['###NOTICE###'] = $rc;
	}

	function isMemberFilterMarkerVisible($caller, $mode, $valueArr) {
		return $this->config['list.']['_FILTERS'];
	}

	function isMemberSortMarkerVisible($caller, $mode, $valueArr) {
		return 1;
	}

	function addFilterAndSortVars(&$arr) {
		$filters = $this->piVars['filter'];
		if (is_array($filters)) {
			foreach ($filters AS $key => $value) {
				$arr['filter'][$key] = $value;
			}
		}

		$sort = $this->piVars['sort'];
		if (isset($sort)) $arr['sort'] = $sort;
	}

	function getDefaultListSort() {
		$rc = $this->config['orderBy'];
		if (!$rc) $rc = 'username';
		return $rc;
	}

	function getListSort() {
		$rc = $this->piVars['sort'];
		if (!$rc) {
			$rc = $this->getDefaultListSort();
			$this->piVars['sort'] = $rc;
		}
		return $rc;
	}

	function getMemberFilterMarker($caller, &$singleMarkers, &$subpartMarkers, &$wrapped, $mode, $valueArr) {
		$content = '';

		// get all registered filters
		$filters = explode(',', $this->config['list.']['_FILTERS']);
		if ($filters) {
			foreach ($filters AS $filter) {
				$obj = $this->getCustomizedObject($mode, $filter);	
				$funcName = 'get'.str_replace(' ','',ucwords(str_replace('_',' ',$filter))).'FilterOptions';
				if (method_exists($obj, $funcName)) {
					$options = $obj->$funcName($this, $mode, $valueArr);
				} else if (method_exists($this, $funcName)) {
					$options = $this->$funcName($this, $mode, $valueArr);
				} else {
					$options = array();
				}

				$selValue = $this->piVars['filter'][$filter];
				if (!isset($selValue) || ($selValue == '')) $selValue = 'ALL';
				settype($selValue, 'string');

				if (count($options)) {
					$content .= '<select name="'.$this->prefixId.'[filter]['.$filter."]\">\n";
					foreach ($options AS $key => $value) {
						$selected = '';
						settype($key, 'string');
						if ($selValue === $key) $selected = ' selected="selected"';
						$content .= '   <option value="'.$key.'"'.$selected.'>'.$value."</option>\n";
					}
					$content .= "</select>&nbsp;\n";
				}
			}
		}
		$singleMarkers['###MEMBER_FILTER###'] = $content;
	}

	function getGenderFilterOptions($caller, $mode, $valueArr) {
		return array(
			'ALL' => $this->pi_getLL('l_both_genders'),
			'0' => $this->pi_getLL('l_male'),
			'1' => $this->pi_getLL('l_female'),
		);
	}


	function getMemberSortMarker($caller, &$singleMarkers, &$subpartMarkers, &$wrapped, $mode, $valueArr) {
		$content = '';

		// What is the default sort criteria?
		$selectedSort = $this->getListSort();

		// get all registered sorts
		$sorts = explode(',', $this->config['list.']['_SORT']);
		//print_r($sorts);
		if (count($sorts)) {
			$content .= '<select name="'.$this->prefixId.'[sort]">';
			foreach ($sorts AS $key) {
				$selected = '';
				if ($selectedSort === $key) $selected = ' selected="selected"';
				$content .= '<option value="'.$key.'"'.$selected.'>'.$this->pi_getLL('l_sort_'.$key).'</option>';
			}
			$content .= '</select>&nbsp;';
		}
		$singleMarkers['###MEMBER_SORT###'] = $content;
	}

	function applyFilters($users) {
		$filters = $this->piVars['filter'];
		if (is_array($filters)) {
			$rc = $users;
			foreach ($filters AS $filter => $value) {
				settype($value, 'string');
				if ($value !== 'ALL') {
					$obj = $this->getCustomizedObject('list', $filter);
					$funcName = 'apply'.str_replace(' ','',ucwords(str_replace('_',' ',$filter))).'Filter';
					if (method_exists($obj, $funcName)) {
						$rc = $obj->$funcName($this, 'list', $rc);
					} else if (method_exists($this, $funcName)) {
						$rc = $this->$funcName($this, 'list', $rc);
					} else {
						$rc = $this->applyFilter($filter, $value, $rc);
					}
				}
				$list = $rc;
			}
			return $rc;
		} else {
			return $users;
		}
	}

	/* General filter function. Filters $list by all record having property $field set to $value
	 */
	function applyFilter($field, $value, $list) {
		$rc = array();
		settype($value, 'string');
		foreach ($list AS $record) {
			$v = $record[$field];
			settype($v, 'string');
			if ($value === $v) $rc[] = $record;
		}
		return $rc;
	}

	function applySort(&$users) {
		$key = $this->getListSort();

		if (!$key) return;

		$obj = $this->getCustomizedObject('list', $key);
		$funcName = 'apply'.str_replace(' ','',ucwords(str_replace('_',' ',$key))).'Sort';
		if (method_exists($obj, $funcName)) {
			$obj->$funcName($this, 'list', $users);
		} else if (method_exists($this, $funcName)) {
			$this->$funcName($this, 'list', $users);
		} else {
			// What is the default sort order?
			$order = $this->config['list.']['_SORT.'][$key];
			if (!$order) $order = 'ASC';
			$this->applyGeneralSort($key, $users, $order);
		}
	}

	function applyGeneralSort($field, &$list, $order = 'ASC') {
		$GLOBALS['GENERAL_SORT_FIELD'] = $field;
		$GLOBALS['GENERAL_SORT_ORDER'] = $order;
		usort($list, 'feuserprofileGeneralSort');
	}
}

function feuserprofileGeneralSort($a, $b) {
	$field = $GLOBALS['GENERAL_SORT_FIELD'];
	$order = $GLOBALS['GENERAL_SORT_ORDER'];

	$rc = strcasecmp($a[$field], $b[$field]);
	if ($order === 'DESC') return -$rc;
	return $rc;
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/feuserprofile/pi1/class.tx_feuserprofile_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/feuserprofile/pi1/class.tx_feuserprofile_pi1.php']);
}

?>
