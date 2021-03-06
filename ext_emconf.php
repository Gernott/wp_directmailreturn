<?php

/* * *************************************************************
 * Extension Manager/Repository config file for ext "wp_directmailreturn".
 *
 * Auto generated 12-07-2017 19:17
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 * ************************************************************* */

$EM_CONF[$_EXTKEY] = array(
    'title' => 'Returnmail analyse of direct_mail',
    'description' => 'direct_mail return mail analysis without fetchmail installed (uses php imap functions)',
    'category' => 'plugin',
    'version' => '2.3.0',
    'state' => 'stable',
    'uploadfolder' => false,
    'createDirs' => '',
    'clearcacheonload' => false,
    'author' => 'WEBprofil - Gernot Ploiner e.U.',
    'author_email' => 'office@webprofil.at',
    'author_company' => 'WEBprofil - Gernot Ploiner e.U.',
    'constraints' =>
    array(
        'depends' =>
        array(
            'typo3' => '7.6.0-10.4.99',
            'direct_mail' => '4.0.0-6.99.99',
        ),
        'conflicts' =>
        array(
        ),
        'suggests' =>
        array(
        ),
    ),
);
