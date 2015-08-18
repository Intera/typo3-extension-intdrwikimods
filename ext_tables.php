<?php
if (!defined ('TYPO3_MODE')) die ('Access denied.');

$TCA['tx_intdrwikimods_subscriptions'] = array (
	'ctrl' => array (
		'title'             => 'LLL:EXT:intdrwikimods/locallang_db.xml:tx_intdrwikimods_subscriptions',
		'label' 			=> 'keyword',
		'tstamp' 			=> 'tstamp',
		'crdate' 			=> 'crdate',
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY) . 'tca.php',
		'iconfile' 			=> t3lib_extMgm::extRelPath($_EXTKEY) . 'tx_intdrwikimods_subscriptions.gif'
	)
);
t3lib_extMgm::allowTableOnStandardPages('tx_intdrwikimods_subscriptions');
t3lib_extMgm::addToInsertRecords('tx_intdrwikimods_subscriptions');

t3lib_extMgm::addStaticFile($_EXTKEY, 'static', 'DR Wiki Modifications');

?>