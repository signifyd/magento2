[Signifyd Extension for Magento 2](../README.md) > Manual download install (not recommended)

# Manual download install (not recommended)

Access the below link and download the latest version of the Signifyd extension.

https://github.com/signifyd/magento2/releases/latest

Uncompress the file contents into the folder [MAGENTO_ROOT]/app/code/Signifyd/Connect of your Magento instance.

On the downloaded files, not on GitHub repository, check the [MAGENTO_ROOT]/app/code/Signifyd/Connect/composer.json file and look into "require" section for the "signifyd/signifyd-php" line. Check the version and download the exact same version of the Signifyd PHP SDK from the below link.

https://github.com/signifyd/php/releases

Uncompress the file and move the contents of lib folder into the folder [MAGENTO_ROOT]/app/code/Signifyd of your Magento instance. On Unix like systems the coomand looks like:

```
cd [signifyd_php_uncompressed_directory]
mv lib/* [MAGENTO_ROOT]/app/code/Signifyd
```

At this point, when listing the [MAGENTO_ROOT]/app/code/Signifyd folder it's expected to see these directories: Connect, Core, Models, Tests.

Check if you are using production mode:

```
bin/magento deploy:mode:show
```

If yes, then put your application in maintenance, if not you can skip this:

```
bin/magento maintenance:enable
```

On the root of your Magento installation, run Magento setup upgrade:

```
bin/magento setup:upgrade
```

If you're on production mode, run below commands, if not, you can skip these:

```
bin/magento deploy:mode:set production
bin/magento maintenance:disable
```
