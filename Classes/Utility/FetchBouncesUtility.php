<?php
namespace WEBprofil\WpDirectmailreturn\Utility;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Locking\LockFactory;
use TYPO3\CMS\Core\Locking\LockingStrategyInterface;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FetchBouncesUtility
{

    /** @var string IMAP, POP3, IMAPS */
    public $type = 'IMAP';

    /** @var string host of the Mailserver */
    public $host = 'localhost';

    /** @var int port to your Mailserver (default: POP=110, IMAP=143, IMAPS=993) 0 = default port */
    public $port = 0;

    /** @var string Username of the mailbox */
    public $user = '';

    /** @var string Password of the mailbox */
    public $password = '';

    /** @var string name of the Inbox */
    public $inbox = 'INBOX';

    /** @var int Amount of Mails per cycle */
    public $amount = 300;

    // response: skipCertValidation hinzugefügt
    /** @var int Skip certificate validation */
    public $skipCertValidation = false;

    /** @var  Logger */
    public $logger;

    /** @var  LockingStrategyInterface */
    public $locker;

    /** @var string  */
    public $errorMsg ;

    /** @var mixed  */
    public $bounced;

    /** @var mixed  */
    public $processed;

    /** @var string  */
    public $errorEmail ;

    /** @var string  */
    public $successEmail ;

    /** @var boolean  */
    public $rundry = false ;

    public function init( InputInterface $input=null)
    {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        if (class_exists(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)) {
            $settings = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class) ->get('wp_directmailreturn');
        } else {
            $settings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['wp_directmailreturn']);

        }

        if (empty($settings)) {
            $this->errorMsg = 'Configuration incomplete. Check EM. Aborting.';
            return false;
        }

        if( $input ) {
            // overwrite some settings from command line
            if ($input->getOption('amount')) {
                $settings['amount'] = $input->getOption('amount');
            }
        }
        $this->type = $settings['type'];
        if (!in_array($this->type, ['IMAP', 'POP', 'POP3', 'IMAPS'], true)) {
            $this->errorMsg = 'Configuration incomplete. "type" is invalid. Check EM. Aborting.' ;
            return false;
        }
        $this->host = $settings['host'];
        if (empty($this->host)) {
            $this->errorMsg = 'Configuration incomplete. "host" is empty. Check EM. Aborting.' ;
            return false;
        }
        $this->user = $settings['user'];
        if (empty($this->user)) {
            $this->errorMsg = 'Configuration incomplete. "user" is empty. Check EM. Aborting.' ;
            return false;
        }
        $this->password = $settings['password'];
        if (empty($this->password)) {
            $this->errorMsg = 'Configuration incomplete. "password" is empty. Check EM. Aborting.' ;
            return false;
        }
        $this->inbox = $settings['inbox'];
        if (empty($this->inbox)) {
            $this->errorMsg = 'Configuration incomplete. "inbox" is empty. Check EM. Aborting.' ;
            return false;
        }

        $this->processed = $settings['processed'];
        if (empty($this->processed)) {
            // if $this->processed is not set in EM Conf, mails will be deleted, even if not sent by directmail
            $this->processed = false;
        }
        $this->bounced = $settings['bounced'];
        if (empty($this->bounced)) {
            // if $this->bounced is not set in EM Conf, mails will be deleted, if sent by directmail
            $this->bounced = false;
        }
        $this->errorEmail = $settings['errorEmail'];
        $this->successEmail = $settings['successEmail'];
        $this->port = (int)$settings['port'];
        $this->amount = (int)$settings['amount'];
        // response: skipCertValidation hinzugefügt
        $this->skipCertValidation = (bool)$settings['skipCertValidation'];

        return true;
    }

    public function initLocker() {
        /** @var LockFactory $lockFactory */
        $lockFactory = GeneralUtility::makeInstance(LockFactory::class);
        $this->locker = $lockFactory->createLocker('dmail_bouncer', LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE | LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK);

        // Check if cronjob is already running:
        if (!$this->locker->acquire($this->locker::LOCK_CAPABILITY_EXCLUSIVE | $this->locker::LOCK_CAPABILITY_NOBLOCK)) {
            $this->errorMsg = 'This cronjob is already running' ;
            return false;
        }
        return true ;
    }

    public function getMbox( SymfonyStyle $io = null)
    {

        // create the connection string
        switch (strtoupper($this->type)) {
            case "IMAP":
                $this->port = $this->port ?: 143;
                // response: Zeichenkette '/novalidate-cert' ergänzt
                $suffix = "";
                if ($this->skipCertValidation)
                    $suffix .= "/novalidate-cert";
                $mb = "{" . $this->host . ":" . $this->port . $suffix . "}" . $this->inbox;
                break;
            case "IMAPS":
                $this->port = $this->port ?: 993;
                $mb = "{" . $this->host . ":" . $this->port . "/imap/ssl/novalidate-cert" . "}" . $this->inbox;
                break;
            case "EXCHANGE":
                $this->port = $this->port ?: 993;
                $mb = "{" . $this->host . ":" . $this->port . "/imap/ssl/authuser" . $this->user . "/user=" . $this->user . "}" . $this->inbox;
                break;

            case "POP":
            case "POP3":
                $this->port = $this->port ?: 110;
                $mb = "{" . $this->host . ":" . $this->port . "/pop3" . "}" . $this->inbox;
                break;
            default:
                $mb = $this->type;
                break;
        }
        if ( $io->getVerbosity() > 128 ) {
            $io->writeln( "Try to open Mailbox now with  " .  $mb . " and User (+ your pwd) : " . $this->user);
        }

        $mbox = imap_open($mb, $this->user, $this->password);
        if ($mbox==false) {
            $this->errorMsg =  'TYPO3 wp_directmailreturn: ERROR: Cannot open connection! (' . $mb . " - " . $this->user . " - " . substr($this->password, 0, 3) . '....)' ;
            return false ;
        }
        if( $this->bounced || $this->processed ) {
            $folders = imap_list($mbox, $mb, "*");

            if( $this->bounced && !$this->rundry ) {
                if( !$this->createSubfolder($mbox , $mb , $folders, $this->bounced )) {
                    return false ;
                }
            }
            if( $this->processed && !$this->rundry ) {
                if( !$this->createSubfolder($mbox , $mb , $folders, $this->processed )) {
                    return false ;
                }
            }
        }

        return $mbox ;


    }
    private function createSubfolder($mbox , $mb , $folders, $folder ) {
        if (!in_array($mb . "." . $folder, $folders)) {
            if ( !imap_createmailbox( $mbox , $mb . "." . imap_utf7_encode(  $folder ))) {
                $this->errorMsg =  'could not create IMAP Folder Bounced: ' . imap_utf7_encode($folder)  . " -> " . imap_last_error() . " Existing Folders: " . var_export($folders , true );
                return false ;
            } else {
                $this->errorMsg =  'Please Check Access to new created  IMAP Folder Bounced: ' . $this->inbox   . ".". imap_utf7_encode($folder)  . " -> " . imap_last_error() . " Existing Folders: " . var_export($folders , true );
                return false ;
            }
        }
        return true ;
    }

    /**
     * @param \WEBprofil\WpDirectmailreturn\Utility\Readmail $readMail
     * @param $mbox
     * @param $msgId
     * @return string
     */
    public function analyze(\WEBprofil\WpDirectmailreturn\Utility\Readmail $readMail ,  $mbox , $msgId )
    {
        $content = imap_fetchheader($mbox, $msgId , FT_UID ) . imap_body($mbox, $msgId , FT_UID );
        $report = '' ;
        if (trim($content))	{

            // Split mail into head and content
            $mailParts = $readMail->fullParse($content);
            $cForSentTo = false ;
            $cForReason = false ;
            $reason = false ;

            // Find id
            // Extract text content
            if( is_array( $mailParts['CONTENT'] )) {
                foreach ($mailParts['CONTENT'] as $key2 => $contentPart ) {
                    // echo "<br>Key:" . $key2 . " - " . $contentPart['content-description'] ;
                    if( $contentPart['content-description'] == "Delivery report" ) {
                        $cForReason = trim($readMail->getMessage($contentPart));
                        $cForSentTo = $readMail->extractMailHeader($cForReason);
                    }
                    if( !$cForReason) {
                        if( $contentPart['content-description'] == "Undelivered Message" ) {
                            $cForReason = trim($readMail->getMessage($contentPart));
                            $cForSentTo = $readMail->extractMailHeader($cForReason);
                        }
                    }
                }
            }

            if ( $cForSentTo  ) {
                $reason = $readMail->analyseReturnError($cForReason);
                $emailName = $readMail->find_EmailfromFinalRecipent($cForSentTo["final-recipient"]);
                $report .= " To Email : " . $emailName ;
            }


            // Split mail into head and content
            $mailParts = $readMail->extractMailHeader($content);
            // Find id
            $midArr = $readMail->find_XTypo3MID($content);
            if (!is_array($midArr))	{
                $midArr = $readMail->find_MIDfromReturnPath($mailParts['to']);
            }
            // Extract text content
            $c = trim($readMail->getMessage($mailParts));

            $cp = $readMail->analyseReturnError($c);

            /** @var ConnectionPool $connectionPool */
            $connectionPool = GeneralUtility::makeInstance( "TYPO3\\CMS\\Core\\Database\\ConnectionPool");
            $queryBuilder = $connectionPool->getConnectionForTable('sys_dmail_maillog')->createQueryBuilder();

            $queryBuilder->select('uid','email') ->from('sys_dmail_maillog') ;
            $expr = $queryBuilder->expr();
            $queryBuilder->where(
                $expr->eq('rid', $queryBuilder->createNamedParameter($midArr['rid'], \TYPO3\CMS\Core\Database\Connection::PARAM_INT))
            )->andWhere(
                $expr->eq('rtbl', $queryBuilder->createNamedParameter( $midArr['rtbl'], \TYPO3\CMS\Core\Database\Connection::PARAM_STR))
            )->andWhere(
                $expr->eq('mid', $queryBuilder->createNamedParameter( $midArr['mid'], \TYPO3\CMS\Core\Database\Connection::PARAM_INT))
            )->andWhere(
                $expr->eq('response_type', $queryBuilder->createNamedParameter( 0, \TYPO3\CMS\Core\Database\Connection::PARAM_INT))
            ) ;
            $rows = $queryBuilder->execute() ;


            if ( ! $rows->rowCount() > 0 ) {
                if( $this->processed ) {
                    imap_mail_move( $mbox , $msgId, $this->inbox . "." .$this->processed , CP_UID );
                    $report .= " Email : " . $msgId . " moved to " . $this->inbox . "." .$this->processed ;
                } else {
                    imap_delete($mbox, $msgId);
                    $report .= " Email : " . $msgId . " deleted " ;
                }


            } else {
                $row = $rows->fetch() ;
                $midArr['email'] = $row['email'];
                if ( !$midArr['email'] ) {
                    $midArr['email'] = "msg found, but empty email" ;
                };
                $midArr['response_type'] = -127;

                $insertFields = array(
                    'tstamp' => time(),
                    'response_type' => $midArr['response_type'],
                    'mid' => intval($midArr['mid']),
                    'rid' => intval($midArr['rid']),
                    'email' => $midArr['email'],
                    'rtbl' => $midArr['rtbl'],
                    'return_content' => serialize($cp),
                    'return_code' => intval($cp['reason'])
                );
                $queryBuilderInsert = $connectionPool->getConnectionForTable('sys_dmail_maillog')->createQueryBuilder();
                $queryBuilderInsert->insert('sys_dmail_maillog')->values( $insertFields  )->execute() ;

                if( $this->bounced ) {
                    imap_mail_move( $mbox , $msgId, $this->inbox . "." . $this->bounced , CP_UID );
                    $report .= " Email : " . $msgId .  " directmail ID " . intval($midArr['mid']) . " sent to : " . $midArr['email'] .  " moved to "  . $this->inbox . "." . $this->bounced ;

                } else {
                    imap_delete($mbox, $msgId);
                    $report .= " Email : " . $msgId .  " directmail ID " . intval($midArr['mid']) . " sent to : " . $midArr['email'] . " was deleted " ;
                }
            }
        }
        return $report  ;

    }

}
