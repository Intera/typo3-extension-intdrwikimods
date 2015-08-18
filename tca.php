<?php

if (!defined ('TYPO3_MODE')) 	die ('Access denied.');


$TCA['tx_intdrwikimods_subscriptions'] = Array (
	'ctrl' => $TCA['tx_intdrwikimods_subscriptions']['ctrl'],
	'interface' => Array (
		'showRecordFieldList' => 'keyword,fe_user'
	),
	'columns' => Array (
		'keyword' => Array (		
			'exclude' => 0,	
			'label' => 'LLL:EXT:dr_wiki/locallang_db.php:tx_drwiki_pages.keyword',
			'config' => Array (
				'type' => 'input',
				'max' => 255,
				'eval' => 'required,trim',
			)
		),
		'fe_user' => Array (
			'exclude' => 0,
			'label' => 'LLL:EXT:cms/locallang_tca.xml:fe_users',
			'config' => Array (
				'type' => 'select',
				'foreign_table' => 'fe_users',
			)
		),
	),
	'types' => Array (
		'0' => Array( 'showitem' => 'keyword,fe_user')
	)
);
		
?>