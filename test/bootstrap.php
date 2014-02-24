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


$host = getenv("SELENIUM_SERVER_HOST"); 

$noizulabs_phpconform_container = new \Pimple(); 
$noizulabs_phpconform_container['SeleniumHost'] = $host;
$noizulabs_phpconform_container['WebDriver'] =  function() { 
	$c = function($host) { return new \WebDriver\WebDriver($host) ;}; 
        return $c; 
};
 
