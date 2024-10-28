Log (module for Omeka S)
========================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Log] is a module for [Omeka S] that allows to monitor all logging messages and
background jobs directly in the admin board, in syslog, or in cloud services via
third parties and make them easily checkable.

Furthermore, additionnal logging destinations (alternative monitor, custom
logging…) and behaviors can be set just by providing their config, for example
to send an email when a critical error occurs.

The logs are [PSR-3] compliant: they can managed by any other tool that respects
this standard (see below). They can be translated too.

The error monitoring service [Sentry] is now available as a separate module [Log Sentry].
It allows to log end user errors and to profile and to trace exceptions,
allowing to find issues hard to reproduce quicker.


Installation
------------

The module uses an external library, [webui-popover], so use the release zip
to install it, or use and init the source.

This module requires the module [Common], that should be installed first.

See general end user documentation for [installing a module].

* From the zip

Download the last release [Log.zip] from the list of releases (the master does
not contain the dependency), and uncompress it in the `modules` directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `Log`, go to the root module, and run:

```sh
composer install --no-dev
```

If an issue appears after upgrade of Omeka, don’t forget to update the packages
of Omeka: `rm -rf vendor && composer install --no-dev`.

* Server with php 8.2 or greater

To support php 8.2 or greate, run this command first:

```sh
composer update
```


Config
------

The config is a pure Laminas log config: see the [Laminas Framework Log]
documentation for the format. Only common settings are explained here.

To enable or disable an option or a writer, it is recommended to copy the wanted
keys inside your own `config/local.config.php`, so the maintenance will be
simpler.

The default config keeps the existing log mechanism inside the file `logs/application.log`,
but removes the logs of the jobs in the table `job` inside the Omeka database.
This second logs are useless with this module. Furthermore, the default log of
jobs is a big text field (4GB), so it may prevent to restore a database if there
is a row is bigger than the param "max_allowed_packet" in the config of
mariadb/mysql.

The logger allows to define one or more of writers (a file, a database, a cloud
service, syslog, etc.). All the writers are listed in `config['logger']['writers']`.
When enable, a writer take its own config in the `config['logger']['options']['writers']`.
See the example in the [config of the module].

Note: External logs (db, sentry, etc.) are not fully checked for performance
reasons, and may fail silently, so their config should be checked separately.

### Default logs

After testing the module, if you want to disable double logging (stream for
direct logging and Omeka database for background jobs), add these keys in your
own `config/local.config.php`:

```php
    'logger' => [
        'log' => true,
        'writers' => [
            'stream' => false,
            'job' => false,
        ],
    ],
```

Instead, you can set a different severity level for database logging and file
logging (this example shows the default levels):

```php
    'logger' => [
        'log' => true,
        'writers' => [
            'stream' => false,
            'job' => false,
            'syslog' => true,
        ],
        'options' => [
            'writers' => [
                'db' => [
                    'options' => [
                        'filters' => \Laminas\Log\Logger::INFO,
                    ],
                ],
                'stream' => [
                    'options' => [
                        'filters' => \Laminas\Log\Logger::NOTICE,
                    ],
                ],
                'syslog' => [
                    'options' => [
                        'filters' => \Laminas\Log\Logger::ERR,
                    ],
                ],
            ],
        ],
    ],
```

### Background job logs

The job logs are automatically saved in the database and manageable in the admin
interface.

The default job logging is still enabled by default in config. To disable it,
set the key `['logger']['writers']['job']` to false in your own `config/local.config.php`:

```php
    'logger' => [
        'writers' => [
            'job' => false,
        ],
    ],
```

### Php errors and exceptions

By default, exceptions that are not managed by Omeka and php errors are logged
only in the file `php_errors.log` of the server. To enable them inside the
logger, add the options, at your choice:

```php
    'logger' => [
        'options' => [
            'exceptionhandler' => true,
            'errorhandler' => true,
            'fatal_error_shutdownfunction' => true,
        ],
    ],
```

Note that **this will disable the default error logging of php and debug tools**,
so if you want to keep it, add a writer for it.

Furthermore, they are managed automatically for background jobs.

### External database

The logs can be saved in an external database. To config it, add a file
`database-log.ini` beside the main `database.ini` of Omeka S, with its params,
and the params of the table inside `config['logger']['options']['writers']['db']['options']`.
Warning: for technical reasons, Omeka use `dbname` and `user`, but Laminas uses
`database` and `username`:

```ini
username = ""
password = ""
database = ""
host     = ""
;port     =
;unix_socket =
;driver   =
```

Extended options are supported via keys `driverOptions[xxx]`, in particular
when the ssl option is on:

```ini
driverOptions[1014] = 0
driverOptions[1009] = "/path/to/domain.certificate.cer"
```

Here, `1014` is `PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT` and `1009` is `PDO::MYSQL_ATTR_SSL_CA`.

Note that when the logs are logged externally, the admin interface cannot be
used for now.

### Additionnal logging

Other logging can be added. Just add their config in your `['logger']['options']`
and enable them under the key `['logger']['writers']`. See the [Laminas Framework Log]
documentation for the format of the config.

### Sentry

Install module [Log Sentry] and update the config.

**Warning**: the free Sentry subscription plan is limited to 5000 errors or
exceptions by month.

### Delete old logs

When the table is growing too much, it's time to clear them. It can be done with
a task of the module [Easy Admin] or via these SQL queries:

```sql
# To delete all messages lower or equal to info:
DELETE FROM `log`
WHERE `severity` <= 6;

# To delete only duplicate messages:
DELETE FROM `log`
WHERE `message` IN (select message from (SELECT message FROM log GROUP BY message ORDER BY COUNT(id) DESC limit 12) as s);
```

PSR-3 and logging
-----------------

With [PSR-3], the message uses placeholders that are not in C-style of the
function `sprintf` (`%s`, `%d`, etc.), but in moustache-style, identified with
`{` and `}`, without spaces.

These features depend on the module [Common]. Only specific features from the
current module are presented here.

### Logging extra data

The module adds three extra data to improve management of logs inside Omeka: the
current user, the job and a reference. The user and the job are automatically
added via the extra keys `userId` and `jobId`, that replace manually set keys.
The reference can be added as additional key `referenceId`. These keys are added
automatically in most of the cases, so you don't need to add them. If the
context uses these keys as placeholders, they are mapped in the message, else
they are removed from the context.

```php
// PSR-3 logging with extra data.
$this->logger()->info(
    'The {resource} #{id} has been updated by user #{userId}.', // @translate
    ['resource' => 'item', 'id' => 43, 'referenceId' => 'curation']
);
// output in stream: The item #43 has been updated by user #1. {"referenceId":"curation"}
// output in database: The item #43 has been updated by user #1.
```

In this implementation, like the default Laminas stream logger, extra data that
are not mappable are json encoded and appended to the end of the message via the
key `{extra}`. So this key should not be used in the context when there are
non-mapped keys.

```php
// PSR-3 logging with non-mappable extra data.
$this->logger()->info(
    'The {resource} #{id} has been updated by user #{userId}.', // @translate
    ['resource' => 'item', 'id' => 43, 'referenceId' => 'curation', 'pid' => 1234]
);
// output in stream: The item #43 has been updated by user #1. {"pid":1234,"referenceId"="curation"}
// output in database: The item #43 has been updated by user #1. {"pid":1234}
```

The reference can be any short string. It may be a category or a unique
identifier. If there is a job, it may repeat or not the values available in the
job settings and metadata.

It can be added at the beginning of the process to avoid to set it for each log.
This is the normal way to log messages:

```php
// PSR-3 logging with reference id (a random number if not set).
$referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
$referenceIdProcessor->setReferenceId('bulk/import/27');
$this->logger()->addProcessor($referenceIdProcessor);
$this->logger()->info(
    'The {resource} #{id} has been updated by user #{userId}.', // @translate
    ['resource' => 'item', 'id' => 43]
);
// output in stream: The item #43 has been updated by user #1. {"referenceId":"MyModule: my-process"}
// output in database: The item #43 has been updated by user #1.
```


TODO
----

- [ ] Use key "psr_log" instead of "log" (see https://docs.laminas.dev/laminas-log/service-manager/#psrloggerabstractadapterfactory).
- [ ] Use the second entity manager in all cases.
- [ ] Add an option to copy logs inside jobs when the module is uninstalled.
- [ ] Fix incompatibility between authentication modules (Ldap, Cas, Shibboleth). The user id is currently disabled in such a case.
- [ ] Replace laminas-db by doctrine and a second entity manager.
- [x] Separate Sentry into another module? It will be cleaner, but heavier in fact because only two small checks are needed, not a full module process.
- [ ] Improve display for messages like "item #xxx", "media #yyy", "resources #zz1, #zz2" with or without context.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.

* The library [webui-popover] is published under the license [MIT].


Copyright
---------

* Copyright Daniel Berthereau, 2017-2023 [Daniel-KM] on GitLab)

* Library [webui-popover]: Sandy Walker


[Log]: https://gitlab.com/Daniel-KM/Omeka-S-module-Log
[Log Sentry]: https://gitlab.com/Daniel-KM/Omeka-S-module-LogSentry
[Omeka S]: https://omeka.org/s
[PSR-3]: http://www.php-fig.org/psr/psr-3
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[webui-popover]: https://github.com/sandywalker/webui-popover
[installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[Log.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-Log/-/releases
[Laminas Framework Log]: https://docs.laminas.dev/laminas-log
[config of the module]: https://gitlab.com/Daniel-KM/Omeka-S-module-Log/-/blob/master/config/module.config.php#L6-126
[Sentry]: https://sentry.io
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Log/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[MIT]: https://github.com/sandywalker/webui-popover/blob/master/LICENSE.txt
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
