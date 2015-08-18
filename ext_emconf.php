<?php

########################################################################
# Extension Manager/Repository config file for ext "intdrwikimods".
#
# Auto generated 17-06-2010 16:12
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'DR Wiki - Modifications',
	'description' => 'Modifies the dr_wiki extension',
	'category' => 'plugin',
	'author' => 'Alexander Stehlik',
	'author_email' => 'astehlik@intera.de',
	'author_company' => 'Intera Gesellschaft für Software-Entwicklung mbH',
	'shy' => '',
	'dependencies' => 'dr_wiki',
	'conflicts' => '',
	'priority' => '',
	'module' => '',
	'state' => 'stable',
	'internal' => '',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'version' => '1.0.0',
	'constraints' => array(
		'depends' => array(
			'dr_wiki' => '',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'suggests' => array(
	),
	'_md5_values_when_last_written' => 'a:3:{s:12:"ext_icon.gif";s:4:"4625";s:17:"ext_localconf.php";s:4:"a55f";s:32:"hooks/class.ux_tx_drwiki_pi1.php";s:4:"7479";}',
);

?>