###FlyingPiranhas Common library

###This is not for any kind of use. It's just here for composer testing, and for development purposes. I'm still heavily working on this - it's actually a port of a library I already built before.

####Introduction
The FlyingPiranhas common library is part of the [FlyingPiranhas](http://www.flyingpiranhas.net) wireframework. It helps [me](http://www.bitfalls.com) develop websites quickly and safely, so I thought I'd share it with the world. For more information on it and its authors, head over to the home page and read up.

####Mailer
This is the _mailer_ library, meaning it focuses solely on advanced emailing functionality. Usually, FP subcomponents require the fpcommon package, but the Mailer sublibrary is standalone enough to function with any PDO adapter.

####Requirements
- Php 5.4+
- Swift Mailer (add "swiftmailer/swiftmailer":"v4.3.0" (or higher) to your composer.json file's require block)

####Installation
You can install the fpmailer library with composer. Just look for flyingpiranhas/mailer on packagist.org and add it to your composer.json file for a painless installation. You can also download a zip from github and just point a regular PSR autoloader at the folder, but composer is the preferred method since it allows us to easily update the library and fix bugs and loopholes people help us discover further down the road.

####License
See LICENSE.md

####Contributing
There's a lot of @todos in the code, so feel free to take a look and submit a pull request if you fix anything. Also, we desperately need tests written. There is only one rule: follow PSR-2 as much as possible. Use other classes as examples and keep the coding style consistent.

####Contact
We're on [Twitter](http://www.twitter.com/wireframework) and I am on [Google plus](http://www.gplus.to/Swader) or at [my website](http://www.bitfalls.com).
