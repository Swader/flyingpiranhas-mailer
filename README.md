###FlyingPiranhas Mailer library

###This is not for any kind of use. It's just here for composer testing, and for development purposes. I'm still heavily working on this - it's actually a port of a library I already built before.

####Introduction
The FlyingPiranhas mailer library is part of the [FlyingPiranhas](http://www.flyingpiranhas.net) wireframework. It helps [me](http://www.bitfalls.com) develop websites quickly and safely, so I thought I'd share it with the world. For more information on it and its authors, head over to the home page and read up.

####Mailer
This is the _mailer_ library, meaning it focuses solely on advanced emailing functionality.

####Usage
Before sending, you must call `Mailer::setDeveloperRecipient()` on the current instance of the Mailer class.
This is a safety measure - it enables sending. If you pass in an email as the parameter, that email will
override all recipients and receive emails instead of the originally intended ones. This is useful for testing
bulk email functionality without quasi-spamming your users. To deactivate this measure, simply call the method
with no parameters.

Mailer supports "express email" functionality via the method expressMail.
This method requires an array containing "to" and "body", and optionally "subject" and "from".
If "from" is omitted, it should be provided via the static method `Mailer::setDefaultSender()`
The expressMail method works without a Repo by default, as long as you leave the second param
at false. As soon as the second param is true, it will try to archive the sent email and will fail unless
you provide a Repo class for it to work with. It is important to note that the email still gets sent,
only the archiving fails with an exception.

Regular email sending works via `Mailer::prepareEmail()`, but requires a Repo
Just pass in the required params and call either `sendPreparedEmails` or `queuePreparedEmails`.
Send will instantly send the email and archive it as sent. Queue will place it in the Repo queue
for later retrieval and sending. Once a queued email was sent, it is marked as such. For more info
and demos, please see the homepage and in-depth documentation (coming soon).

####Requirements
- Php 5.4+
- Swift Mailer (add "swiftmailer/swiftmailer":"v4.3.0" (or higher) to your composer.json file's require block)
- IMAP extension for checking when you've last sent an email to a specific person
- flyingpiranhas/common

####Installation
You can install the fpmailer library with composer. Just look for flyingpiranhas/mailer on packagist.org and add it to your composer.json file for a painless installation. You can also download a zip from github and just point a regular PSR autoloader at the folder, but composer is the preferred method since it allows us to easily update the library and fix bugs and loopholes people help us discover further down the road.

####License
See LICENSE.md

####Contributing
There's a lot of @todos in the code, so feel free to take a look and submit a pull request if you fix anything. Also, we desperately need tests written. There is only one rule: follow PSR-2 as much as possible. Use other classes as examples and keep the coding style consistent.

####Contact
We're on [Twitter](http://www.twitter.com/wireframework) and I am on [Google plus](http://www.gplus.to/Swader) or at [my website](http://www.bitfalls.com).
