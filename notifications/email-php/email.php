#!/usr/bin/php
<?php
// Sanity check
if($argc!=4)
{
	error_log("This process should only be called as an evQueue plugin\n");
	die(5);
}

// Read configuration
$stdin = fopen('php://stdin','r');

$config_str = stream_get_contents($stdin);
if($config_str==false)
{
	error_log("No configuration could be read on stdin\n");
	die(1);
}

// Decode configuration
$config = json_decode($config_str);
if($config===null)
{
	error_log("Unable to decode json data\n");
	die(2);
}

if(!isset($config->notificationconf->subject) || !isset($config->notificationconf->when) || !isset($config->notificationconf->to) || !isset($config->notificationconf->body))
{
	error_log("Invalid configuration\n");
	die(3);
}

// Read workflow instance informations from evQueue engine
$vars = array('#ID#'=>$argv[1]);
$xml = simplexml_load_string($config->instance);
$workflow_attributes = $xml->attributes();
$vars['#NAME#'] = (string)$workflow_attributes['name'];
$vars['#START_TIME#'] = (string)$workflow_attributes['start_time'];
$vars['#END_TIME#'] = (string)$workflow_attributes['end_time'];

// Extract mail informations from config
$to = $config->notificationconf->to;
$subject = $config->notificationconf->subject;
$body = $config->notificationconf->body;
$when = $config->notificationconf->when;
$cc = isset($config->notificationconf->cc)?$config->notificationconf->cc:false;

if($when!='ON_SUCCESS' && $when!='ON_ERROR' && $when!='ON_BOTH')
{
	error_log("Invalid value for 'when' parameter\n");
	die(6);
}

// When should be trigger alert
if($when=='ON_SUCCESS' && $argv[2]!=0)
	die();

if($when=='ON_ERROR' && $argv[2]==0)
	die();

// Do variables substitution
$values = array_values($vars);
$vars = array_keys($vars);

$subject = str_replace($vars,$values,$subject);
$body = str_replace($vars,$values,$body);

// Send email
$cmdline = '/usr/bin/mail';
$cmdline .= " -s '".addslashes($subject)."'";
if($cc)
	$cmdline .= " -c '".addslashes($cc)."'";
$cmdline .= " -a '".addslashes('From: '.$config->pluginconf->from)."'";
$cmdline .= ' '.addslashes($to);

$fd = array(0 => array('pipe', 'r'));
$proc = proc_open($cmdline, $fd, $pipes);

if(!is_resource($proc))
	die(4);

fwrite($pipes[0],$body);
fclose($pipes[0]);
$return_value = proc_close($proc);

die($return_value);
?>