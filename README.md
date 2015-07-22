# azure-backup
PHP Backup script to backup file on Azure Storage using blobs

1. Installation
You need to follow instruction on http://azure.microsoft.com/en-us/develop/php/ 

In case of manual installation, the autoloader is 'WindowsAzure/WindowsAzure.php'.

2. Configuration

Copy azurebackup.template to /etc/backup.conf

Define those 3 parameters
* endpoint connection

* storage name

* storage key


