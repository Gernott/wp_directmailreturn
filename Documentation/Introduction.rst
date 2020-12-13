Introduction
============

What does it do?
----------------

The extension Returnmail has been developed by the company Webprofil and is supported by Reelworx.
LTS 9 Compatibilty Update to Version 2.0 was done by J. Velletti ( www.velletti.de )

This extension makes it possible to analyse the email dispatch of directmail.

Returnmail automatically downloads the returned mails of the failed dispatches and analyses them for their return code.
This return code will be written into the direct mail log table and is displayed in the statistics category of each mail dispatch.

Does not need mdule fetchmail installed.

What is new in Version 9?
-------------------------
To be compatible with TYPO3 LTS 9 using a separate,new developed Command Controller that can be started:
- via commandline or a NEW scheduler task

Extension Configuration offers New options in the tab advanced to move bounced emails to an IMAP inbox folder (f.e. INBOX.Bounced) instead of deleting them.
Or to move emails, not found in direct_mail send log table to an IMAP inbox folder (f.e. INBOX.Processed)

you can get setup a target email Address after each successful run (at least one Bounced email found)
you can get setup a different target email Address in case of errors

New Mailbox Type: EXCHANGE allows IMAP4 access to a microsoft exchange inbox.


Screenshots
-----------

This is the output after the proper configuration of the extension Returnmail.

.. figure:: Images/mails-returned.png
