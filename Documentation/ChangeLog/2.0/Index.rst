.. include:: ../../Includes.txt

.. contents::
        :local:
        :depth: 1

=============================
WP directmailreturn Version 2
=============================

- adding command line interface (run with:  ./vendor/bin/typo3cms  wpdirectmailreturn:analyzemail )
- new Protocol type EXCHANGE to have access to a Microsoft Exchange inbox, (using IMAP4 protocol)
- You need to setup a new scheduler task if you use TYPO3 LTS 9.x or higher (installation)
- Options to move emails instead of deleting them directly
- Options to get notified by email