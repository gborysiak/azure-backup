<?php
set_include_path(get_include_path() . PATH_SEPARATOR .'/usr/local/share/WindowsAzure');
require_once 'WindowsAzure.php';

use WindowsAzure\Common\ServicesBuilder;
use WindowsAzure\Blob\Models\PublicAccessType;
use WindowsAzure\Common\ServiceException;
use WindowsAzure\Blob\Models\Block;
use WindowsAzure\Blob\Models\CreateContainerOptions;
use WindowsAzure\Blob\Models\ListContainersOptions;

define("BLOCKSIZE", 4 * 1024 * 1024);    
define("PADLENGTH", 5); 

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

function sendFile($blobRestProxy, $containerName, $filename) {
    $handle = fopen($filename, "r");
    // Upload the blob using blocks.
    $counter = 1;
    $blockIds = array();
    // file name extracted from path
    $blobname = basename($filename);
    while (!feof($handle))
    {
        $blockId = str_pad($counter, PADLENGTH, "0", STR_PAD_LEFT);
        echo "Processing block $blockId.\n";
        
        $block = new Block();
        $block->setBlockId(base64_encode($blockId));
        $block->setType("Uncommitted");
        array_push($blockIds, $block);
        
        $data = fread($handle, BLOCKSIZE);
        
        // Upload the block.
        $blobRestProxy->createBlobBlock($containerName, $blobname, base64_encode($blockId), $data);
        $counter++;
    }
    // Done creating the blocks. Close the file and commit the blocks.
    fclose($handle);
    echo "Commiting the blocks.\n";    
    $blobRestProxy->commitBlobBlocks($containerName, $blobname, $blockIds);
}

function cleanUpOldFiles($blobRestProxy, $containerName, $nb_days) {
	try {
		// List blobs.
		$blob_list = $blobRestProxy->listBlobs($containerName);
		$blobs = $blob_list->getBlobs();

		foreach($blobs as $blob)
		{
			$name = $blob->getName();
			//echo $name . "\n";
			$arr = explode(".", $name);
			try {
				$d1 = new DateTime($arr[1]);
				//print_r($d1);
				$now = new DateTime();
				//print_r($now);
				$res = $now->diff($d1);
				if( $res->days > $nb_days) {
					echo $name . " a supprimer " . $res->days . "\n";
					$blobRestProxy->deleteBlob($containerName, $name);
				}
			}
			catch(Exception $e) {
			}
		}
	}
	catch(ServiceException $e){
		// Handle exception based on error codes and messages.
		// Error codes and messages are here:
		// http://msdn.microsoft.com/library/azure/dd179439.aspx
		$code = $e->getCode();
		$error_message = $e->getMessage();
		echo $code.": ".$error_message."<br />";
	}	
}

// check argument line
//print_r($argv);
if( count($argv) < 2 ) {
  echo "Usage:\n";
  echo "# php azure_backup.php <file>\n";
  exit("No file selected.\n");
}

// check if $argv[1] exists
$filename=$argv[1];

// check ig argv[1] is a folder
$folder=false;
if( is_dir($filename) ) {
  $folder=true;
} else if( ! file_exists($filename)) {
  exit($filename . " does not exists.\n");
}	

// read configuration ( local for test else /etc/azurebackup.conf
$config = null;
if( file_exists('/etc/azurebackup.conf')) {
   $config = parse_ini_file('/etc/azurebackup.conf');
} else if( file_exists('./azurebackup.conf')) {	
   $config = parse_ini_file('./azurebackup.conf');
} else {
   exit('No configuration file found.\n');
}
//print_r($config);

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
    $containerName="backups";
    createContainerIfNotExists($blobRestProxy, $containerName);
	
    if( ! $folder ) {
      sendfile($blobRestProxy, $containerName, $filename);
    } else {
      foreach(glob($filename.'/*.*') as $file) {
        echo $file . "\n";
        sendfile($blobRestProxy, $containerName, $file);
      } 
    }
		
	// delete expired backups
	$retention=60;
    if( array_key_exists("retention", $config ) == true ) {
        $retention=intval($config['retention']);
    }
	cleanUpOldFiles($blobRestProxy, $containerName, $retention);
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
