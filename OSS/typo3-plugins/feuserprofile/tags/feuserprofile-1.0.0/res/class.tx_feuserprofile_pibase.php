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

require_once(t3lib_extMgm::extPath('rsextbase').'res/class.tx_rsextbase_pibase.php');
require_once(t3lib_extMgm::extPath('feuserprofile').'res/class.tx_feuserprofile_database.php');


/**
 * Plugin 'FE User Profile' for the 'feuserprofile' extension.
 *
 * @author	Ralph Schuster <typo3@ralph-schuster.eu>
 * @package	TYPO3
 * @subpackage	tx_feuserprofile
 */
class tx_feuserprofile_pibase extends tx_rsextbase_pibase {
	
	var $extKey        = 'feuserprofile';

	/**
	 * Always call this function before starting
	 * @param $conf configuration
	 */
	function init($config) {
		parent::init($config);
	
		// Configuration
		$this->setConfiguration('listProfilesPID');
		$this->setConfiguration('viewProfilePID');
		$this->setConfiguration('editProfilePID');
		$this->setConfiguration('userPicDir');
		$this->setConfiguration('maxListItems');
		
		$this->setConfiguration('showDisabledGroups');
		if ($this->config['showDisabledGroups']) $this->disabledGroups = explode(',', $this->config['showDisabledGroups']);
	}

	/**
	 * Creates the database object
	 */
	function createDatabaseObject() {
		$this->db = t3lib_div::makeInstance('tx_feuserprofile_database');
		$this->db->init($this);
	}
	
	function tempnam($dir, $prefix, $suffix) {
		$tmpName = tempnam($dir, $prefix);
		$finalName = $tmpName.".$suffix";
		rename($tmpName, $finalName);
		$finalName = substr($finalName, strlen(PATH_site));
		return $finalName;
	}

}


?>
