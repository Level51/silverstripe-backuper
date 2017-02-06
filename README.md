# Backuper - A SilverStripe backup module
**Backuper** is a slick backup module for SilverStripe. It gets a database dump and packs it with all files in the assets/ directory as compressed archive. Great for migration of pages through easy-to-use export and import functionality.

## Maintainer
* Julian Scheuchenzuber <js@lvl51.de>
* Fabian Göttl <fabian@goettl.de>

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
* Just install it and do the classical dev/build?flush=all.
* In order to upload large backup files, you have to increase the maximum upload filesize of your webserver configuration. 
For nginx have a look at: https://easyengine.io/tutorials/php/increase-file-upload-size-limit/
* For Google Drive Backup functionality: Make sure that the plugin can store and delete an accesstoken in a textfile located in the **_config** folder. Hence, user www-data requires **full** file permissions for the plugin's _config folder. Run: ```chown www-data _config && chmod 700 _config```


## Setup Google Drive Backups
Setup Google Drive Backups by following these steps:
1) Goto https://console.developers.google.com/start/api?id=drive 
2) Click **Continue**, then **Go to credentials**.
3) On the **Add credentials to your project page**, click the **Cancel** button.
4) At the top of the page, select the **OAuth consent screen** tab. Select an **Email address**, enter a **Product name** if not already set, and click the **Save** button.
5) Select the **Credentials** tab, click the **Create credentials** button and select **OAuth client ID**.
6) Select the application type **Web-Application**, enter a name e.g. "Drive API Silverstripe" and set **Authorized JavaScript-Source** to your project root URL e.g. https://example.com. Further, enter the **Authorized Re-direct URL** e.g. https://example.com/backuper/authenticate-gdrive and finally click the **Create** button.
7) A dialog will show the client credentials.
8) Copy and paste the **client_id** and **client_secret** from the dialog to the **API Keys** tab of the Backup plugin's CMS settings page.
9) Save and click authenticate.
10) Allow access to your Google Drive at the shown authentication screen.
11) Create backups by ticking the 'Upload to Google Drive' option.

## Backup
Create a backup by ticking the desired options in the Backup tab. Click **Save** and then **Create backup**. Make sure the you **save first** after you change backup options. 

## Restore by upload
Upload a backup file by the upload form in the plugin's **Restore** tab. Click **Save** and then **Restore backup**. Make sure the you **save first** after you uploaded a file. 

## Restore by Google Drive
Make sure that your are authorized to your Google Drive and backup files are stored on it. Goto the plugin's **Restore** tab and click on the backup file you want to restore.
