<?php
defined('TYPO3_MODE') || die();

call_user_func(
    function($extKey)
    {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Reelworx\WpDirectmailreturn\Scheduler\FetchBouncesTask::class] = array(
            'extension' => $extKey,
            'title' => 'Fetch Bounces',
            'description' => 'Fetch Bounces for direct_mail with imap',
            'additionalFields' => ''
        );

    },
    $_EXTKEY
);
