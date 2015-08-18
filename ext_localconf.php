<?php
if (!defined ("TYPO3_MODE")) die ("Access denied.");

$TYPO3_CONF_VARS[TYPO3_MODE]["XCLASS"]["ext/dr_wiki/pi1/class.tx_drwiki_pi1.php"] = 
	t3lib_extMgm::extPath($_EXTKEY) . '/hooks/class.ux_tx_drwiki_pi1.php';
	
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dr_wiki']['drwiki_SubmitAfterInsert'][] = 
	'EXT:' . $_EXTKEY . '/hooks/class.tx_intdrwikimods_hooks_afterinsert.php:tx_intdrwikimods_hooks_afterinsert';

?>