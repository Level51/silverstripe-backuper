# Backuper - A SilverStripe backup module
**Backuper** is a slick backup module for SilverStripe. It just gets a database dump and packs it with all files in the assets/ directory as .zip archive. Great for migration of pages through easy-to-use export functionality in the *Settings* section of the CMS.

## Maintainer
* Julian Scheuchenzuber <js@lvl51.de>

## Installation
```
composer require level51/silverstripe-backuper
```

## Features
* Generating (and soon also loading) a database dump. **Only MySQL at the moment!**
* Archiving (and soon also loading) assets 

# Dependencies
* ifsnop MysqlDump: https://github.com/ifsnop/mysqldump-php

# Setup
Just install it and do the classical dev/build?flush=all.