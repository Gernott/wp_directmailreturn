<?php
defined('TYPO3_MODE') || die();

if (TYPO3_MODE === 'BE') {
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = \Reelworx\WpDirectmailreturn\Command\ReturnAnalysisCommandController::class;
}
