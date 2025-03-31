<?php

// Locations for AWS secrets when configured using the aws cli
$awsCredentials = $_SERVER['HOME'] . "/.aws/credentials";

// Check for existence and parse
if (file_exists($awsCredentials)) {
	
	$parsed = parse_ini_file($awsCredentials, process_sections: true);
	if (!isset($parsed['default'])) {
		die("Unable to determine AWS credentials. Ensure a 'default' profile exists in {$awsCredentials}");
	}

	$aws_key = $parsed['default']['aws_access_key_id'];
	$aws_secret = $parsed['default']['aws_secret_access_key'];

	// Validate
	if (!preg_match("/(?<![A-Z0-9])[A-Z0-9]{20}(?![A-Z0-9])/", $aws_key)) {
		die("AWS Key does not match known pattern.\nCheck credentials file.\n");
	}
	if (!preg_match("/(?<![A-Za-z0-9\/+=])[A-Za-z0-9\/+=]{40}(?![A-Za-z0-9\/+=])/", $aws_secret)) {
		die("AWS Secret does not match known pattern.\nCheck credentials file.\n");
	}
}
else
{
	die("Unable to locate AWS credentials file: {$awsCredentials}\nPlease configure credentials using `aws configure`.\n");
}

define('DRY_RUN', false);

date_default_timezone_set('UTC');

require 'vendor/autoload.php';

// Helpers

function getConfigFile($suffix, $region) {

	$file = strtolower(sprintf(".config-Percona-Training-%s-%s.cnf", $suffix, $region));

	return $file;
}
