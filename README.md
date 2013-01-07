Migration Helper
================

Keeping track of Magento configuration changes and applying them appropriately across multiple installations can be a really time-consuming task.
Even though you can set default values for configuration settings through config.xml, this too takes a lot of time and is easily overwritten by someone messing with values in the backend.
Migration Helper helps you by automatically generating database migrations for configuration changes.


Sounds cool! How do I install?
------------------------------

Migration Helper is a [modman](https://github.com/colinmollenhour/modman) module.
After installing modman you can easily deploy it:

    ./modman clone Rubic_MigrationHelper https://github.com/danslo/MigrationHelper.git

Clear cache and you are ready to go! Try saving something in the backend!


Tips
----

Even though MigrationHelper can (and by default will) store migrations in itself, it is recommended you create a separate module for them.
This will allow you to properly put those migrations into version control.


Known Issues
------------

* Multiple configuration changes done at once will generate multiple migrations. This will be fixed in a later version.
* Make sure your bootstrap file is writable by the server and a writable data folder exists.
