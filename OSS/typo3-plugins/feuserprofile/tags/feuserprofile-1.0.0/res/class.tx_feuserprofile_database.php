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

require_once(t3lib_extMgm::extPath('rsextbase').'res/class.tx_rsextbase_database.php');


class tx_feuserprofile_database extends tx_rsextbase_database {

	function getSpecialUsers() {
		$pid = $this->pi->config['userFolder'];

		// Order and Filter
		$where = $this->pi->config['specialWhereClause'];

		return $this->filterGroups($this->getUsersWhere($where), $this->pi->disabledGroups);
	}

	function getSearchUsers() {
		$pid = $this->pi->config['userFolder'];

		// Order and Filter
		$search = "";
		$keywords = strtoupper($this->pi->getGPvar('list', 'search'));
		$search = "(UPPER(name) like '%$keywords%') OR (UPPER(username) like '%$keywords%')";

		// Return non-deleted, enabled records
		if (!$this->isAdminUser()) {
			$where =  "deleted=0 AND disable=0 AND ($search)";
		} else {
			$where =  "deleted=0 AND ($search)";
		}
		return $this->getUsersWhere($where);
	}

	function getDisabledUsers() {
		$pid = $this->pi->config['userFolder'];

		// Order and Filter params

		// Return non-deleted records
		$rc = array();
		$where =  "deleted=0 AND pid=$pid AND disable > 0";
		return $this->filterGroups($this->getUsersWhere($where), $this->pi->disabledGroups);
	}
	
	function filterGroups($users, $filteredGroups) {
		foreach ($users AS $row) {
			$addRow = 0;
			if ($filteredGroups) {
				$groups = explode(',', $row['usergroup']);
				foreach ($groups AS $group) {
					if (in_array($group, $filteredGroups)) {
						$addRow = 1;
					}
				}
			} else {
				$addRow = 1;
			}

			if ($addRow) {
				if (!isset($row['_is_online'])) {
					$row['_is_online'] = 0;
				}
				$rc[] = $row;
			}
		}
		return $rc;
	}

	
}

?>
