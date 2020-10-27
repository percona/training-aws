#!/usr/bin/env php
<?php

require 'aws.phar';

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;

$dynamo = Aws\DynamoDb\DynamoDbClient::factory(array(
	'profile' => 'default',
    'region'  => 'us-east-1',
    'version' => 'latest'));

$i = array("machineType" => "mysql1", "PublicIp" => "33.33.33.33", "PrivateIp" => "1.2.3.4");

saveInstanceInfoToDynamo(4, $i);

function saveInstanceInfoToDynamo($tid, $info)
{
	global $dynamo;
	
	// this may be first instance to add to this team
	// search for teamId
	$marshaler = new Marshaler();
	$json = sprintf('{":m": { "publicIp": "%s", "privateIp": "%s" } }',
		$info['PublicIp'], $info['PrivateIp']);
	
	$row = $marshaler->marshalJson($json);
	
	$teamTag = "trek";

	$params = [
		'TableName' => "percona_training_servers",
		'Key' => array(
			'teamTag' => array('S' => $teamTag),
			'teamId' =>  array('N' => "$tid")
		),
		'UpdateExpression' => sprintf("SET %s = :m", $info['machineType']),
		'ExpressionAttributeValues' => $row
	];
	
	try {
		$result = $dynamo->updateItem($params);
		printf("-- Added %s to Dynamo\n", $info['machineType']);
	}
	catch (DynamoDbException $e)
	{
		printf("!! Unable to add %s to T%d: %s", $info['machineType'], $tid, $e->getMessage());
	}
}