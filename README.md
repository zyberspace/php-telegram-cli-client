zyberspace/telegram-cli-client
==============================
php-client for [telegram-cli](https://github.com/vysheng/tg/)

Requirements
------------
 - a running [telegram-cli](https://github.com/vysheng/tg/) listening on a unix-socket (`-S`) or a port (`-P`). Needs to be configured already (phone-number, etc.).
 - php >= 5.4.0

Usage
-----

###Setup telegram-cli
[telegram-cli](https://github.com/vysheng/tg/) needs to run on a unix-socket (`-S`) or a port (`-P`), so *telegram-cli-client* can connect to it.  
You should also start it with `-W` so the contact-list gets loaded on startup.  
For this example we will take the following command (execute it from the dir, where you installed telegram-cli, not the php-client), `-d` lets it run as daemon.:

```shell
./bin/telegram-cli -dWS /tmp/tg.sck &
```

If you never started telegram-cli before, you need to start it first in normal mode, so you can type in your telegram-phone-number and register it, if needed (`./bin/telegram-cli`).

To stop the daemon use `killall telegram-cli` or `kill -TERM [telegram-pid]`.

###Install telegram-cli-client with composer
In your project-root:

```shell
composer require zyberspace/telegram-cli-client
```

Composer will then automatically add the package to your project requirements and install it (also creates the `composer.json` if you don't have one already).

###Use it

```php
require('vendor/autoload.php');
$telegram = new \Zyberspace\Telegram\Cli\Client('unix:///tmp/tg.sck');

$contactList = $telegram->getContactList();
$telegram->msg($contactList[0], 'Hey man, what\'s up? :D');
```

Documentation
-------------
To create the docs, just run `phpdoc` in the the project-root.  
An online-version is available at [phpdoc.zyberware.org/zyberspace/telegram-cli-client](http://phpdoc.zyberware.org/zyberspace/telegram-cli-client/).

Supported Commands
------------------
You can execute every command with the `exec()`-method, but there are also several command-wrappers available in the `AbstractClientCommands`-Class ([doc-link](http://phpdoc.zyberware.org/zyberspace/telegram-cli-client/classes/Zyberspace.Telegram.Cli.AbstractClientCommands.html)), which the main class extends (see the `example.php`).

If you use `exec()` directly, please don't forget to escape the peer (contacts, chat-names, etc.) with `escapePeer()` and all string-arguments with `escapeStringArgument()` to avoid errors.

API-Stability
-------------
The api of the commands wrappers will definitely change in the future, for example `getHistory()` only returns the raw answer of the telegram-cli right now and is not really useful. Therefore you should not use this in production yet.

**If you still want to use this in your project, make sure you stay at the same [minor](http://semver.org/spec/v2.0.0.html)-version (`~0.1.0` or `0.1.*` for example)**.

License
-------
This software is licensed under the [Mozilla Public License v. 2.0](http://mozilla.org/MPL/2.0/). For more information, read the file `LICENSE`.
