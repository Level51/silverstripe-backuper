# Backuper - A SilverStripe backup module
**Backuper** is a slick backup module for SilverStripe. It just gets a database dump and packs it with all files in the assets/ directory as .zip archive. Great for migration of pages through easy-to-use import/export functionality in the *Settings* section of the CMS.

---
# Features
* Generating and loading a database dump. **Only MySQL at the moment!**
* Archiving and loading assets 

---
# Setup
1. Be sure that the module is in a folder **backuper/** on the root of the project.
2. <code>sake dev/build "flush=all"</code>, depending on your config you might have to do this via URI in the browser.
3. Determine the full path of the binaries **mysqldump(.exe)** and **mysql(.exe)** and specify the full path in the settings/site config section.
4. If the binary paths are there you will see an action for backup creation and an upload field for loading backups.

---
# Maintainers
- Julian Scheuchenzuber <js@lvl51.de>

---
# License
Copyright 2015 Julian Scheuchenzuber

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.