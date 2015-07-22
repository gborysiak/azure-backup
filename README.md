# azure-backup
PHP Backup script to backup file on Azure Storage using blobs

1. Installation
You need to follow instruction on http://azure.microsoft.com/en-us/develop/php/ 

For Suse Linux Enterprise (11/12) you need the following packages
SLES 11
* php53, php53-openssl, php53-pear
SLES 12
* 

In case of manual installation, the autoloader is 'WindowsAzure/WindowsAzure.php'.

2. Configuration

Copy azurebackup.template to /etc/backup.conf

Define those 3 parameters
* endpoint connection

* storage name

* storage key


