<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

t3lib_div::loadTCA('tt_content');


// Installing all plugins
for ($i=1; $i<2; $i++) {
	$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi'.$i]='layout,select_key';
	$TCA['tt_content']['types']['list']['subtypes_addlist'][$_EXTKEY.'_pi'.$i]='pi_flexform';
	t3lib_extMgm::addPlugin(array('LLL:EXT:'.$_EXTKEY.'/pi'.$i.'/locallang.xml:tt_content.list_type', $_EXTKEY.'_pi'.$i),'list_type');
	t3lib_extMgm::addPiFlexFormValue($_EXTKEY.'_pi'.$i, 'FILE:EXT:'.$_EXTKEY.'/pi'.$i.'/flexform.xml');
}


t3lib_extMgm::addStaticFile($_EXTKEY,'static/fe_user_profile/', 'FE User Profile');
?>