<div align="center">
<a href="https://gitlab.com/Serenata/Serenata"><img src="https://assets.gitlab-static.net/uploads/-/system/project/avatar/2815601/PHP_Integrator.png" alt="Serenata" title="Serenata" width="258"></a>

<h1>Serenata</h1>
<h4>Gratis, libre and open source server providing code assistance for PHP</h4>

<a href="https://gitlab.com/Serenata/Serenata/commits/master">
    <img src="https://gitlab.com/Serenata/Serenata/badges/master/pipeline.svg">
</a>

<a href="https://gitlab.com/Serenata/Serenata/commits/master">
    <img src="https://gitlab.com/Serenata/Serenata/badges/master/coverage.svg">
</a>

<a href="https://serenata.gitlab.io/#support">
    <img src="https://img.shields.io/badge/€-Support-blue.svg?&amp;style=flat">
</a>
</div>

Serenata (previously "PHP Integrator") is a gratis, libre and open source server that indexes PHP code and performs static analysis. It stores its information in a database and retrieves information about your code to clients by communicating over sockets. Clients can use this information to provide code assistance, such as autocompletion, linting, code navigation and tooltips.

More information for users, both developers looking to implement clients for other editors as well as programmers using the server via editors and IDE's, can be found [on the wiki](https://gitlab.com/Serenata/Serenata/wikis/home) as well as [the website](https://serenata.gitlab.io/).

## What Features Are Supported?
* Autocompletion
* Goto Definition (Code Navigation)
* Signature Help (Call Tips)
* Tooltips
* Symbols
* Linting

There are also other requests clients can send to extract information about a code base. However, we are in the process of slowly migrating to become a [language server](https://microsoft.github.io/language-server-protocol/) for PHP, so these may be replaced by compliant requests in the future.

## Where Is It Used?
The [php-ide-serenata](https://github.com/Gert-dev/php-ide-serenata/) package integrates Serenata into the Atom editor via Atom-IDE.

## Installation
### Runtime
If you want to use the server directly, i.e. just to be able to fire it up and communicate with it over a socket, such as when you want to integrate it into an editor:

```sh
composer create-project "serenata/serenata" serenata --prefer-dist --no-dev
```

You can then run it with:

```sh
php -d memory_limit=1024M bin/console --uri=tcp://127.0.0.1:11111
```

Using `0.0.0.0` as host allows the server to be reachable when located on a different machine, such as across the network or inside a Docker container.

You can select any port you desire, as long as it is not in use on your system.

The memory limit can also be freely set. The memory needed very much depends on the size of the project, the PHP version as well as the operating system. To give you some idea, at the time of writing, when running the server on itself, it sits at around 150 MB on a 64-bit Linux system with PHP 7.1.

### Development
If you want to make the server part of your (existing) project and use the classes contained inside it for your own purposes:

```sh
composer require "serenata/serenata"
```

Note that the server was designed primarily as an application and not as a library. However, it is still very much possible to instantiate the classes you need yourself.

You may also be interested in [other libraries that are part of the Serenata suite](https://gitlab.com/Serenata). In the future, more code may be split from the server into proper, separate libraries.

## Contributing
As this project is inherently large in scope, there is a lot of potential and a lot of areas to work in, so contributions are most welcome! Take a look at [our contribution guide](https://gitlab.com/Serenata/Serenata/blob/master/CONTRIBUTING.md).

![AGPLv3 Logo](https://www.gnu.org/graphics/agplv3-with-text-162x68.png)
