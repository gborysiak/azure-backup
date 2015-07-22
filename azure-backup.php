<?php
require_once 'WindowsAzure/WindowsAzure.php';

use WindowsAzure\Common\ServicesBuilder;
use WindowsAzure\Blob\Models\PublicAccessType;
use WindowsAzure\Common\ServiceException;
use WindowsAzure\Blob\Models\Block;
use WindowsAzure\Blob\Models\CreateContainerOptions;
use WindowsAzure\Blob\Models\ListContainersOptions;

function createContainerIfNotExists($blobRestProxy, $containername)
{
    // See if the container already exists.
    $listContainersOptions = new ListContainersOptions;
    $listContainersOptions->setPrefix($containername);
    $listContainersResult = $blobRestProxy->listContainers($listContainersOptions);
    $containerExists = false;
    foreach ($listContainersResult->getContainers() as $container)
    {
        echo $container->getName() . "\n";
        if ($container->getName() == $containername)
        {
            // The container exists.
            $containerExists = true;
            // No need to keep checking.
            break;
        }
    }
    if (!$containerExists)
    {
        echo "Creating container.\n";
        $blobRestProxy->createContainer($containername);
        echo "Container '" . $containername . "' successfully created.\n";
    }
}

// read configuration ( local for test else /etc/azurebackup.conf
$config = parse_ini_file('./azurebackup.conf');
print_r($config);

// Create blob REST proxy.
//$connectionString = "UseDevelopmentStorage=true";
$connectionString = "DefaultEndpointsProtocol=" . $config['endpoint']. ";AccountName=" . $config['accountname'] . ";AccountKey=" . $config['accountkey'];
$blobRestProxy = ServicesBuilder::getInstance()->createBlobService($connectionString);


// OPTIONAL: Set public access policy and metadata.
// Create container options object.
$createContainerOptions = new CreateContainerOptions(); 

// Set public access policy. Possible values are 
// PublicAccessType::CONTAINER_AND_BLOBS and PublicAccessType::BLOBS_ONLY.
// CONTAINER_AND_BLOBS:     
// Specifies full public read access for container and blob data.
// proxys can enumerate blobs within the container via anonymous 
// request, but cannot enumerate containers within the storage account.
//
// BLOBS_ONLY:
// Specifies public read access for blobs. Blob data within this 
// container can be read via anonymous request, but container data is not 
// available. proxys cannot enumerate blobs within the container via 
// anonymous request.
// If this value is not specified in the request, container data is 
// private to the account owner.
$createContainerOptions->setPublicAccess(PublicAccessType::CONTAINER_AND_BLOBS);

// Set container metadata
$createContainerOptions->addMetaData("key1", "value1");
$createContainerOptions->addMetaData("key2", "value2");

try {
    // Create container.
    //$blobRestProxy->createContainer("backups", $createContainerOptions);
    createContainerIfNotExists($blobRestProxy, "backups");
}
catch(ServiceException $e){
    // Handle exception based on error codes and messages.
    // Error codes and messages are here: 
    // http://msdn.microsoft.com/library/azure/dd179439.aspx
    $code = $e->getCode();
    $error_message = $e->getMessage();
    echo $code.": ".$error_message."<br />";
}
?>
