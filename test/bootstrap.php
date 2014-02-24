<?php
global $noizulabs_phpconform_container;

error_reporting(E_ERROR | E_WARNING); 
ini_set('display_errors', 'on'); 

if(file_exists(__DIR__ . "/../vendor/autoload.php")) {
   require_once(__DIR__ . "/../vendor/autoload.php"); 
} else if (file_exists(__DIR__ . "/../../../autoload.php")) {
   require_once( __DIR__ . "/../../../vendor/autoload.php");
} else {
   trigger_error("Unable To Locate Required Autoloader for the NoizuLabs/FragmentedKeys Libraries"); 
}

$noizulabs_phpconform_container = new \Pimple(); 
$noizulabs_phpconform_container['SelniumHost'] = 'http://127.0.0.1:4444/wd/hub';
$noizulabs_phpconform_container['WebDriver'] =  function() { 
	return new \WebDriver\WebDriver($noizulabs_phpconform_container['SeleniumHost']); 
};
 