<?php
defined('TYPO3_MODE') || die();
if (\TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_branch) < 9000000) {
    // @todo remove once TYPO3 8.6.x support is dropped
    call_user_func(
        function ($extKey) {
            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\Reelworx\WpDirectmailreturn\Scheduler\FetchBouncesTask::class] = array(
                'extension' => $extKey,
                'title' => 'Fetch Bounces (< TYPO3 LTS 9)',
                'description' => 'Fetch Bounces for direct_mail with imap',
                'additionalFields' => ''
            );

        },
        $_EXTKEY
    );
}
