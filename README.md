# Backuper - A SilverStripe backup module
**Backuper** is a slick backup module for SilverStripe. It gets a database dump and packs it with all files in the assets/ directory as compressed archive. Great for migration of pages through easy-to-use export and import functionality.

## Maintainer
* Julian Scheuchenzuber <js@lvl51.de>

## Installation
```
composer require level51/silverstripe-backuper
```

## Features
* Generating and loading a database dump
* Archiving and loading assets
* Downloading backup file to local disk
* Uploading backup to Google Drive
* Restoring backup from manual upload or Google Drive

## Dependencies
* silverstripe/sspak: https://github.com/silverstripe/sspak
* google/apiclient: @version 1.1.7

## Setup
Just install it and do the classical dev/build?flush=all.

## Google Drive Backups
* Get client credentials by following Step 1 at https://developers.google.com/drive/v3/web/quickstart/php
* Setup **client_id** and **client_secret** at the APIKeys tab of the plugin's CMS settings
* Save and click authenticate
* Allow access to your Google Drive at the shown authentication screen
* Create backups by ticking the 'Upload to Google Drive' option
