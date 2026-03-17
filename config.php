<?php

define('DRY_RUN', false);

date_default_timezone_set('UTC');

require 'vendor/autoload.php';

// Helpers

function getConfigFile($suffix, $region) {

	$file = strtolower(sprintf(".config-Percona-Training-%s-%s.cnf", $suffix, $region));

	return $file;
}
