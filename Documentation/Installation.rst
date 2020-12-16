.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: Includes.txt

.. _start:


============
Installation
============

Configuration
"""""""""""""

First step is the configuration of the TYPO3 extension Direct Mail according to their installation and configuration manual.
Make sure to create a new Direct Mail container ready for email dispatch, otherwhise you will not be able to configure Returnmail properly.
Test your Direct Mail setup by sending out some emails.

In the next step you need to add a "Return Path" email address to your Direct Mail container. You can add such an address in the Direct Mail interface in the Configuration menu.
Emails in the "Return Path" email account will be deleted without any further notices or warnings, so make sure that you only use this account as "Return Path" email address.


.. figure:: Images/dmail-configuration.png

TYPO3 LTS 7 or 8:
"""""""""""""""""
Now we need to create a new Scheduler task for Returnmail.

The class of the scheduler task must be "Fetch Bounces". It must be a recurring task in an interval you like.
When ready safe the task.


.. figure:: Images/scheduler-configuration.png


TYPO3 LTS 9:
""""""""""""
You can create a scheduler task:
The class of the scheduler task must be "Console Command" -> "wpdirectmailreturn:analyzemail "
It must be a recurring task in an interval you like.
When ready safe the task.


Or can run this from commandline
     ./vendor/bin/typo3cms  wpdirectmailreturn:analyzemail

on commandline you can get debug output by adding -vvv option or overrule the option amount:
      ./vendor/bin/typo3cms  wpdirectmailreturn:analyzemail --amount=2 -vvv
the option -vvv will give hints about reason in case of problems accessing mailbox.

on commandline you can also add the argument "rundry".
this will do nothing with your inbox but test if config will work:
      ./vendor/bin/typo3cms  wpdirectmailreturn:analyzemail rundry --amount=2 -vvv


At last we have to enter the complete connection details of our previously set "Return Path" email account
at the extension settings in the extensionmanager.

Return mail supports the following mailing protocols:
IMAP, POP3 and IMAPS or, from Version 2.x also Microsoft EXCHANGE

Make sure to enter every detail, don't miss out anything. The required inputs are: port (if 0, then it uses the default port), name of the inbox, host of the mailserver, username of the mailbox, password of the mailbox and the amount of mails per cycle.

Additionally you can add your own "open" mailbox string if type is not supported. In this case options like host, port etc are ignored
"yourServerAddress:567/imap/ssl/validate-cert/an-other-option/user=UserId}INBOX"

.. figure:: Images/extensionmanager.png

TYPO3 LTS 9 - Advanced:
"""""""""""""""""""""""
If you want to move emails to a specific Folder instead of deleting them directly, you can add two different options.
One Folder for undeliverable emails sent by directmail : Suggestion is "Bounced"
this will create an IMAP inbox folder called INBOX.Bounced

One Folder for emails NOT sent by directmail (or no entries found in dmail log table): Suggestion is "Processed"
this will create an IMAP inbox folder called INBOX.Processed

.. figure:: Images/extensionmanager_advanced.png

With this step the configuration of Returnmail is complete. The scheduled task now fetches the emails on the "Retun Path" email account and analyses them.
You can see the number of the returned emails with their return reason in the Direct Mail interface at Statistics. There you can choose between the sent emails. The statistics will be displayed at the bottom of the detailed view of each email.


.. figure:: Images/mails-returned.png



FAQ
"""
**Gmail:**

If you use GMail as returnmail account, take a look on this website:
https://www.google.com/settings/security/lesssecureapps


**Errors:**

in case of error: Class 'WEBprofil\WpDirectmailreturn\Command\AnalyzeMailCommand' not found
please add this line:

     "WEBprofil\\WpDirectmailreturn\\": "http/typo3conf/ext/wp_directmailreturn/Classes/"

to your local composer.json

    "autoload": {
       "psr-4": {
           "WEBprofil\\WpDirectmailreturn\\": "http/typo3conf/ext/wp_directmailreturn/Classes/"
       }
    }

and run

   composer dumpautoload

