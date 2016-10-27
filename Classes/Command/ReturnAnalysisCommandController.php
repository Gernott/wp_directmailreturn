<?php
namespace Reelworx\WpDirectmailreturn\Command;

use DirectMailTeam\DirectMail\Readmail;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Locking\LockFactory;
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

class ReturnAnalysisCommandController extends CommandController
{
    /** @var string IMAP, POP3, IMAPS */
    private $type = 'IMAP';

    /** @var string host of the Mailserver */
    private $host = 'localhost';

    /** @var int port to your Mailserver (default: POP=110, IMAP=143, IMAPS=993) 0 = default port */
    private $port = 0;

    /** @var string Username of the mailbox */
    private $user = '';

    /** @var string Password of the mailbox */
    private $password = '';

    /** @var string name of the Inbox */
    private $inbox = 'INBOX';

    /** @var int Amount of Mails per cycle */
    private $amount = 300;

    private function fetchConfiguration()
    {
        $settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['wp_directmailreturn']);
        if (empty($settings)) {
            $this->outputLine('Configuration incomplete. Check EM. Aborting.');
            $this->sendAndExit(1);
        }
        $this->type = $settings['type'];
        if (!in_array($this->type, ['IMAP', 'POP', 'POP3', 'IMAPS'], true)) {
            $this->outputLine('Configuration incomplete. "type" is invalid. Check EM. Aborting.');
            $this->sendAndExit(2);
        }
        $this->host = $settings['host'];
        if (empty($this->host)) {
            $this->outputLine('Configuration incomplete. "host" is empty. Check EM. Aborting.');
            $this->sendAndExit(2);
        }
        $this->user = $settings['user'];
        if (empty($this->user)) {
            $this->outputLine('Configuration incomplete. "user" is empty. Check EM. Aborting.');
            $this->sendAndExit(2);
        }
        $this->password = $settings['password'];
        if (empty($this->password)) {
            $this->outputLine('Configuration incomplete. "password" is empty. Check EM. Aborting.');
            $this->sendAndExit(2);
        }
        $this->inbox = $settings['inbox'];
        if (empty($this->inbox)) {
            $this->outputLine('Configuration incomplete. "inbox" is empty. Check EM. Aborting.');
            $this->sendAndExit(2);
        }
        $this->port = (int)$settings['port'];
        $this->amount = (int)$settings['amount'];
    }

    public function analyzeCommand()
    {
        $this->fetchConfiguration();

        /** @var LockFactory $lockFactory */
        $lockFactory = GeneralUtility::makeInstance(LockFactory::class);
        $locker = $lockFactory->createLocker('dmail_bouncer', LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE | LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK);

        /** @var $logger \TYPO3\CMS\Core\Log\Logger */
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        // Check if cronjob is already running:
        if (!$locker->acquire($locker::LOCK_CAPABILITY_EXCLUSIVE | $locker::LOCK_CAPABILITY_NOBLOCK)) {
            return false;
        }

        // create the connection string
        switch (strtoupper($this->type)) {
            case "IMAP":
                $this->port = $this->port ?: 143;
                $mb = "{" . $this->host . ":" . $this->port . "}" . $this->inbox;
                break;
            case "IMAPS":
                $this->port = $this->port ?: 993;
                $mb = "{" . $this->host . ":" . $this->port . "/imap/ssl/novalidate-cert" . "}" . $this->inbox;
                break;
            case "POP":
            case "POP3":
                $this->port = $this->port ?: 110;
                $mb = "{" . $this->host . ":" . $this->port . "/pop3" . "}" . $this->inbox;
                break;
            default:
                $mb = $this->type;
                $logger->warning('TYPO3 wp_directmailreturn Cron: WARNING: Unrecognized protocol type: ' . $this->type);
                break;
        }

        $mbox = imap_open($mb, $this->user, $this->password);
        if ($mbox === false) {
            $locker->release();
            $logger->error('TYPO3 wp_directmailreturn Cron: ERROR: Cannot open connection! (' . $mb . ' - ' . $this->user . ')');
            $this->output('TYPO3 wp_directmailreturn Cron: ERROR: Cannot open connection! (' . $mb . ' - ' . $this->user . ')');
            return false;
        }

        /** @var Readmail $readMail */
        $readMail = GeneralUtility::makeInstance(Readmail::class);
        $mails = imap_sort($mbox, SORTDATE, 0);
        $cnt = 0;
        /** @var DatabaseConnection $db */
        $db = $GLOBALS['TYPO3_DB'];
        foreach ($mails as $mail) {
            $content = imap_fetchheader($mbox, $mail) . imap_body($mbox, $mail);
            if (trim($content)) {
                // Split mail into head and content
                $mailParts = $readMail->extractMailHeader($content);
                // Find id
                $midArr = $readMail->find_XTypo3MID($content);
                if (!is_array($midArr)) {
                    $midArr = $readMail->find_MIDfromReturnPath($mailParts['to']);
                }
                // Extract text content
                $c = trim($readMail->getMessage($mailParts));

                $cp = $readMail->analyseReturnError($c);
                $where = 'rid=' . (int)$midArr['rid'] . ' AND rtbl=' . $db->fullQuoteStr($midArr['rtbl'], 'sys_dmail_maillog') . ' AND mid=' . (int)$midArr['mid'] . ' AND response_type=0';
                $res = $db->exec_SELECTquery('uid,email', 'sys_dmail_maillog', $where);
                if (!$res || !$db->sql_num_rows($res)) {
                    $midArr = array();
                    $cp = $mailParts;
                    if (!$db->sql_num_rows($res)) {
                        $db->sql_free_result($res);
                    }
                } else {
                    $row = $db->sql_fetch_assoc($res);
                    $midArr['email'] = $row['email'];
                    $db->sql_free_result($res);
                }

                $insertFields = array(
                    'tstamp' => time(),
                    'response_type' => -127,
                    'mid' => intval($midArr['mid']),
                    'rid' => intval($midArr['rid']),
                    'email' => $midArr['email'],
                    'rtbl' => $midArr['rtbl'],
                    'return_content' => serialize($cp),
                    'return_code' => intval($cp['reason']),
                );
                $insResult = $db->exec_INSERTquery('sys_dmail_maillog', $insertFields);
                if (!$insResult) {
                    $logger->error('sys_dmail_maillog failed, reason: ' . $db->sql_error());
                }
            }

            imap_delete($mbox, $mail);
            $cnt++;
            if ($cnt >= $this->amount) {
                break;
            }
        }
        imap_close($mbox, CL_EXPUNGE);

        return true;
    }

}