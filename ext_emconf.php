<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "wp_directmailreturn".
 *
 * Auto generated 27-10-2014 10:57
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array (
	'title' => 'Returnmail analyse of direct_mail',
	'description' => 'direct_mail return mail analysis without fetchmail installed (uses php imap functions)',
	'category' => 'plugin',
	'version' => '1.0.5',
	'state' => 'stable',
	'uploadfolder' => false,
	'createDirs' => '',
	'clearcacheonload' => false,
	'author' => 'Gernot Ploiner',
	'author_email' => 'office@webprofil.at',
	'author_company' => NULL,
	'constraints' =>
	array (
		'depends' =>
		array (
			'typo3' => '6.2.0-6.2.99',
			'direct_mail' => '4.0.0-4.99.99',
		),
		'conflicts' =>
		array (
		),
		'suggests' =>
		array (
		),
	),
);

