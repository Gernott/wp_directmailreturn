<?php
namespace WEBprofil\WpDirectmailreturn\Utility;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * j.velletti: this File gives some more features to the direct mail class : Status 554 - marked as spam
 *

 * @package  TYPO3
 * @version  $Id: class.readmail.php 6012 2007-07-23 12:54:25Z ivankartolo $
 *
 *
 */
class ReadMail extends \DirectMailTeam\DirectMail\Readmail {

    protected $reason_text = array(
        '550' => 'no mailbox|account does not exist|user unknown|Recipient unknown|recipient unknown|account that you tried to reach is disabled|User Unknown|User unknown|unknown in relay recipient table|user is unknown|unknown user|unknown local part|unrouteable address|does not have an account here|no such user|user not listed|account has been disabled or discontinued|user disabled|unknown recipient|invalid recipient|recipient problem|recipient name is not recognized|mailbox unavailable|550 5\.1\.1 recipient|status: 5\.1\.1|delivery failed 550|550 requested action not taken|receiver not found|unknown or illegal alias|is unknown at host|is not a valid mailbox|no mailbox here by that name|we do not relay|5\.7\.1 unable to relay|cuenta no activa|inactive user|user is inactive|mailaddress is administratively disabled|not found in directory|not listed in public name & address book|destination addresses were unknown|recipient address rejected|Recipient address rejected|Address rejected|rejected address|not listed in domino directory|domino directory entry does not|550-5\.1.1 The email account that you tried to reach does not exist|The email address you entered couldn',
        '551' => 'over quota|quota exceeded|mailbox full|mailbox is full|not enough space on the disk|mailfolder is over the allowed quota|recipient reached disk quota|temporalmente sobre utilizada|recipient storage full|mailbox lleno|user mailbox exceeds allowed size',
        '552' => 'connection refused|Connection refused|connection timed out|Connection timed out|timed out while|Host not found|host not found|Unable to connect to DNS|t find any host named|unrouteable mail domain|not reached for any host after a long failure period|domain invalid|host lookup did not complete: retry timeout exceeded|no es posible conectar correctamente',
        '554' => 'error in header|header error|invalid message|invalid structure|header line format error'
    );


	/**
	 * Returns email from input TO header
	 * (the return address of the sent mail from Dmailer)
	 *
	 * @param string  $to Email address, return address string
	 *
	 * @return bool|mixed|string
	 */
	public function find_EmailfromFinalRecipent($to)
	{
		$to2 = trim( str_replace('rfc822;', '' , $to)) ;
		if (GeneralUtility::validEmail($to2)) {
			return $to2 ;
		}
		$out = $this->extractNameEmail($to2) ;
		if (GeneralUtility::validEmail($out['email'])) {
			return ($out['email']);
		} else {
			return false ;
		}
	}



	/**
	 * Analyses the return-mail content for the Dmailer module
	 * used to find what reason there was for rejecting the mail
	 * Used by the Dmailer, but not exclusively.
	 *
	 * @param string  $c Message Body/text
	 *
	 * @return array  key/value pairs with analysis result.
	 *  Eg. "reason", "content", "reason_text", "mailserver" etc.
	 */
	public function analyseReturnError($c)
	{
		$cp = array();
		// QMAIL
		if (preg_match('/' . preg_quote('--- Below this line is a copy of the message.') . '|' . preg_quote('------ This is a copy of the message, including all the headers.') . '/i', $c)) {
			if (preg_match('/' . preg_quote('--- Below this line is a copy of the message.') . '/i', $c)) {
				// Splits by the QMAIL divider
				$parts = explode('-- Below this line is a copy of the message.', $c, 2);
			} else {
				// Splits by the QMAIL divider
				$parts = explode('------ This is a copy of the message, including all the headers.', $c, 2);
			}
			$cp['content'] = trim($parts[0]);
			$parts = explode('>:', $cp['content'], 2);
			$cp['reason_text'] = trim($parts[1])?trim($parts[1]):$cp['content'];
			$cp['mailserver'] = 'Qmail';
			$cp['reason'] = $this->extractReason($cp['reason_text']);
		} elseif (strstr($c, 'The Postfix program')) {
			// Postfix
			$cp['content'] = trim($c);
			$parts = explode('>:', $c, 2);
			$cp['reason_text'] = trim($parts[1]);
			$cp['mailserver'] = 'Postfix';
			if (stristr($cp['reason_text'], '550')) {
				// 550 Invalid recipient, User unknown
				$cp['reason'] = 550;
			} elseif (stristr($cp['reason_text'], '553')) {
				// No such user
				$cp['reason'] = 553;
			} elseif (stristr($cp['reason_text'], '551')) {
				// Mailbox full
				$cp['reason'] = 551;
			} elseif (stristr($cp['reason_text'], '554')) {
				// Our Mail was Blocked because of Spam (f.e. gmx sends this)
				$cp['reason'] = 554;
			} elseif (stristr($cp['reason_text'], 'recipient storage full')) {
				// Mailbox full
				$cp['reason'] = 551;
			} else {
				$cp['reason'] = -1;
			}
		} elseif (strstr($c, 'Your message cannot be delivered to the following recipients:')) {
			// whoever this is...
			$cp['content'] = trim($c);
			$cp['reason_text'] = trim(strstr($cp['content'], 'Your message cannot be delivered to the following recipients:'));
			$cp['reason_text']=trim(substr($cp['reason_text'], 0, 500));
			$cp['mailserver']='unknown';
			$cp['reason'] = $this->extractReason($cp['reason_text']);
		} elseif (strstr($c, 'Diagnostic-Code: X-Notes')) {
			// Lotus Notes
			$cp['content'] = trim($c);
			$cp['reason_text'] = trim(strstr($cp['content'], 'Diagnostic-Code: X-Notes'));
			$cp['reason_text'] = trim(substr($cp['reason_text'], 0, 200));
			$cp['mailserver']='Notes';
			$cp['reason'] = $this->extractReason($cp['reason_text']);
		} else {
			// No-named:
			$cp['content'] = trim($c);
			$cp['reason_text'] = trim(substr($c, 0, 1000));
			$cp['mailserver'] = 'unknown';
			$cp['reason'] = $this->extractReason($cp['reason_text']);
		}
		if ( $cp['reason']  > 1 ) {
			$cp['reason_name'] = $this->reason_text[ $cp['reason']] ;
		}

		return $cp;
	}



	/**
	 * Returns the data from the 'content-type' field.
	 * That is the boundary, charset and mime-type
	 *
	 * @param string $contentTypeStr Content-type-string
	 *
	 * @return array key/value pairs with the result.
	 */
	public function getContentTypeData($contentTypeStr)
	{
		$outValue = array();
		$cTypeParts = GeneralUtility::trimExplode(';', $contentTypeStr, 1);
		// Content type, first value is supposed to be the mime-type,
		// whatever after the first is something else.
		$outValue['_MIME_TYPE'] = $cTypeParts[0];
		reset($cTypeParts);
		next($cTypeParts);
		foreach ( $cTypeParts as $v) {
			$reg = '';
			preg_match('/([^=]*)="(.*)"/i', $v, $reg);
			if (trim($reg[1]) && trim($reg[2])) {
				$outValue[strtolower($reg[1])] = $reg[2];
			}
		}
		return $outValue;
	}

	/**
	 * Makes a UNIX-date based on the timestamp in the 'Date' header field.
	 *
	 * @param string $dateStr String with a timestamp according to email standards.
	 *
	 * @return int The timestamp converted to unix-time in seconds and compensated for GMT/CET ($this->serverGMToffsetMinutes);
	 */
	public function makeUnixDate($dateStr)
	{
		$dateParts = explode(',', $dateStr);
		$dateStr = count($dateParts) > 1 ? $dateParts[1] : $dateParts[0];
		$spaceParts = GeneralUtility::trimExplode(' ', $dateStr, 1);
		$spaceParts[1] = $this->dateAbbrevs[strtoupper($spaceParts[1])];
		$timeParts = explode(':', $spaceParts[3]);
		if( count($timeParts ) < 3 ) {
			$timeParts = array( 0 , 0 , 0 ) ;
		}
		$timeStamp = mktime(intval($timeParts[0] ) , intval($timeParts[1]) , intval($timeParts[2]) , intval($spaceParts[1]), intval($spaceParts[0]), intval($spaceParts[2]));
		$offset = $this->getGMToffset($spaceParts[4]);
		// Compensates for GMT by subtracting the number of seconds which the date is offset from serverTime
		$timeStamp -= $offset * 60;
		return $timeStamp;
	}



}
