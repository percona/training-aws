#!/usr/bin/env php72
<?php

date_default_timezone_set('UTC');

require 'aws.phar';

$options = parseOptions();
$config = loadConfig();

$pxcAmis = array(
	"us-east-1" => "ami-01ced8cc8b0eb770e",
	"us-east-2" => "ami-06bf47e28485f9cba",
	"us-west-1" => "ami-0a84a00bebea0fbc6",
	"us-west-2" => "ami-06cb3d8efc59541e9");

use Aws\DynamoDb\Marshaler;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\Ec2\Ec2Client;
use Aws\Credentials\CredentialProvider;

$provider = CredentialProvider::instanceProfile();
$dynamo = new DynamoDbClient([
        'credentials' => $provider,
        'region'  => 'us-east-1',
        'version' => 'latest'
]);

$ec2 = new Ec2Client([
	'credentials' => $provider,
	'region' => $options['region'],
	'version'=> 'latest'
]);

switch($options['action'])
{
	case 'ADD':
		addNewCluster();
		break;
}


function getNextTeamNumber()
{
	global $dynamo;
	
	// Get next team number from Dynamo
	$tableName = 'pla19counter';
	$marshaler = new Marshaler();
	$key = $marshaler->marshalJson('{"counterId":1}');
	$eav = $marshaler->marshalJson('{":inc": 1}');
	$params = [
		'TableName' => $tableName,
		'Key' => $key,
		'ExpressionAttributeValues' => $eav,
		'UpdateExpression' => 'SET teamcounter = teamcounter + :inc',
		'ReturnValues' => 'UPDATED_NEW'
	];
	
	try
	{
		$result = $dynamo->updateItem($params);
		$attr = $result->get("Attributes");
		return $attr['teamcounter']['N'];
	}
	catch (DynamoDbException $e)
	{
		echo "Unable to increment team counter:\n";
		echo $e->getMessage() . "\n";
		exit(1);
	}
}

function addNewCluster()
{
	global $ec2, $options, $config, $pxcAmis;
	
	// Sanity check
	if (!isset($options['region']))
	{
		echo "Cannot determine region\n";
		exit(1);
	}
	else if (!isset($pxcAmis[$options['region']]))
	{
		echo "Cannot determine AMI\n";
		exit(1);
	}

	// get next team from dynamo
	$teamId = getNextTeamNumber();

	// Launch 4 instances
	printf("-- Launching new cluster... ");
	try
	{
		$ami = $pxcAmis[$options['region']];
		
		// launch 4 instances
		$res = $ec2->runInstances(array(
			'ImageId' => $ami,
			'MinCount' => 4,
			'MaxCount' => 4,
			'InstanceType' => 't2.large',
			'CreditSpecification' => array(
				'CpuCredits' => 'unlimited'
			),
			'KeyName' => 'Percona-Training',
			'NetworkInterfaces' => array(
				array(
					'DeviceIndex' => 0,
					'AssociatePublicIpAddress' => true,
					'SubnetId' => $config['Subnet']['SubnetId']
				)
			),
			'BlockDeviceMappings' => array(
				array(
					'DeviceName' => '/dev/sda1',
					'Ebs' => array(
						'VolumeSize' => 20,
						'DeleteOnTermination' => true,
						'VolumeType' => 'gp2'
					)
				)
			)
		));
	}
	catch(Exception $e)
	{
		printf("\n** Unable to add instances: %s **\n\n", $e->getMessage());
		exit(1);
	}

	printf("Done\n");

	// Get the instance Ids
	$instanceIds = getInstanceIds($res, 'Default');

	printf("-- Created the following instances:\n");
	foreach ($instanceIds as $instance)
	{
		printf("--- %s\n", $instance);
	}


	// Wait for instances, then tag them	
	try
	{
		$ec2->waitUntil('InstanceExists', array(
			'InstanceIds' => $instanceIds,
			'@waiter' => [
				'initDelay' => 5,
				'before' => function (Aws\CommandInterface $c, $attempts) {
					printf("-- Waiting for instances... (Attempt %d)\n", $attempts); }
			]
		));
		
		// Tag each instance with a name to see in AWS GUI
		printf("-- Tagging instances...\n");
		
		$types = array("mysql1", "mysql2", "mysql3", "app");
		
		foreach($instanceIds as $i => $instanceId)
		{
			tagEntity($instanceId, 'Name', sprintf("PLA19-PXC-T%d-%s", $teamId, $types[$i]));
		}
	}
	catch(Exception $e)
	{
		printf("\n** Unknown Error: %s **\n", $e->getMessage());
		printf("\n '%s' \n", get_class($e));
	}
	
	// Add instances to dynamo table
	printf("-- Adding cluster info to Dynamo...\n");

	// search for newly added instances to get public IPs
	$instanceInfo = array();
	try
	{
		$res = $ec2->describeInstances(array(
			'InstanceIds' => $instanceIds
		));
		
		$reservations = $res->search('Reservations[*].Instances[*].{
			InstanceId: InstanceId,
			PublicIpAddress: PublicIpAddress,
			PrivateIpAddress: PrivateIpAddress,
			Hostname: Tags[0].Value}');

		$frontLength = strlen(sprintf("PLA19-PXC-T%d-", $teamId));

		foreach ($reservations as $r)
		{
			foreach ($r as $instance)
			{
				$type = substr($instance['Hostname'], $frontLength);
				$instanceInfo[$type] = array(
					'PublicIp' => $instance['PublicIpAddress'],
					'PrivateIp' => $instance['PrivateIpAddress'],
					'Hostname' => $instance['Hostname']);
			}
		}
		
		saveInstanceInfoToDynamo($teamId, $instanceInfo);
	}
	catch(Exception $e)
	{
		printf("** UNABLE TO SAVE TO DYNAMO **\n");
		printf("** Unknown Error: %s **\n", $e->getMessage());
		printf("'%s' \n", get_class($e));
	}

	// save ansible config for team
	saveAnsibleTeamConfig($teamId, $instanceInfo);
	
	printf("-- Instances are running! -- TEAM: %d -- Don't forget to provision!\n", $teamId);
	printf("-- ALL DONE! --\n\n");
}

function saveInstanceInfoToDynamo($tid, $info)
{
	global $dynamo;
	
	$marshaler = new Marshaler();
	$json = sprintf('
		{
			"teamId": %d,
			"mysql1": { "publicIp": "%s", "privateIp": "%s" },
			"mysql2": { "publicIp": "%s", "privateIp": "%s" },
			"mysql3": { "publicIp": "%s", "privateIp": "%s" },
			"app": { "publicIp": "%s", "privateIp": "%s" }
		}
	',
		$tid,
		$info['mysql1']['PublicIp'], $info['mysql1']['PrivateIp'],
		$info['mysql2']['PublicIp'], $info['mysql2']['PrivateIp'],
		$info['mysql3']['PublicIp'], $info['mysql3']['PrivateIp'],
		$info['app']['PublicIp'], $info['app']['PrivateIp']
	);
	
	$row = $marshaler->marshalJson($json);
	
	$params = ['TableName' => 'pla19pxcteams', 'Item' => $row];
	
	$result = $dynamo->putItem($params);
	
	echo "-- Added info to Dynamo\n";
}

function parseOptions()
{
	global $argv, $argc;

	// defaults
	$_opt = array('teamcount' => 1, 'offset' => 0);

	$opts = getopt("h::a:r:", array("help::"));
	foreach($opts as $k => $v)
	{
		switch ($k)
		{
			case 'a': $_opt['action'] = $v; break;
			case 'r': $_opt['region'] = $v; break;
			case 'help':
			case 'h':
				printHelp();
				exit();
		}
	}

	// Action is required
	$actions = array('ADD', 'PROVISION');
	if (!isset($_opt['action']) || !in_array($_opt['action'], $actions))
	{
		printf("\nError: -a is a required option. Possible values are: %s\n", implode(', ', $actions));
		printHelp();
		exit();
	}

	// AWS region required
	if (!isset($_opt['region']))
	{
		printf("\nError: -r is a required option. Possible values are:\n\n\tus-east-1, us-west-1, us-west-2,\n\tap-south-1, ap-northeast-1, ap-northeast-2, ap-southeast-1, ap-southeast-2\n\teu-central-1, eu-west-1, sa-east-1\n");
		printHelp();
		exit();
	}
	
	$_opt['suffix'] = 'PLA19_PXC';

	return $_opt;
}

function printHelp()
{
	global $argv;

	print "\n";
	printf("Usage: %s -a <action> -r <region>\n", $argv[0]);
	print "\n";
	print "  -a    Action: ADD, PROVISION\n";
	print "  -r    Region: us-west-1, us-west-2, us-east-1, eu-west-1\n";
	print "\n";
}

function loadConfig()
{
	global $options;

	// Retrieve or Save settings to cache file
	$configFile = ".config-" . md5(sprintf("Percona-Training-%s-%s", $options['suffix'], $options['region'])) . ".cnf";
	$config = array();

	if (file_exists($configFile))
	{
		if($config = json_decode(file_get_contents($configFile), true))
		{
			// printf("- Successfully loaded config '%s'\n", $configFile);
		}
		else
		{
			printf("\n** Error loading config file: '%s' **\n", $configFile);
			exit();
		}
	}
	else
	{
		printf("\n** No config file found. Have you configured the VPC? **\n\n");
		exit();
	}

	return $config;
}


function getYesNoResponse()
{
	$stdin = fopen('php://stdin', 'r');
	$res = fgetc($stdin);
	fclose($stdin);

	return ($res == 'Y' || $res == 'y');
}

function getInstanceIds($res, $type = 'Default')
{
	$resArray = $res->toArray();
	$instanceIds = array();

	switch($type) {
		case "Default":
			$instances = $resArray['Instances'];
			break;
		case "Reservations":
			$instances = $resArray['Reservations'][0]['Instances'];
			break;
		default:
			die("ERROR< could not find instanceID type\n");
			break;
	}

	foreach ($instances as $instance)
	{
		$instanceIds[] = $instance['InstanceId'];
	}

	return $instanceIds;
}

function tagEntity($entity, $key, $value)
{
	global $ec2;

	try
	{
		$res = $ec2->createTags(array(
			'Resources' => array($entity),
			'Tags' => array(
				array('Key' => $key, 'Value' => $value)
			)
		));
	}
	catch(Exception $e)
	{
		printf("** Unable to tag entity ('%s') - '%s' => '%s' **\n",
			$entity, $key, $value);
	}
}

function saveAnsibleTeamConfig($teamId, $instanceInfo)
{
	$frontLength = strlen("PLA19-PXC-");
	$instances = array();

	foreach($instanceInfo as $instance)
	{
		list($teamCounter, $machineType) = explode("-", substr($instance['Hostname'], $frontLength));
		
		$machineName = $machineType . "-" . $teamCounter;
		
		$instances['machinetype'][$machineType][] = $machineName;
		$instances['team'][$teamCounter][] = $machineName;
		$instances['all'][] = $machineName;
		$instances['privateip'][$machineName] = $instance['PrivateIp'];
		$instances['publicip'][$machineName] = $instance['PublicIp'];
	}

	// save ansible config to temp string, then output to file

	$a = "[all]\n";
	foreach ($instances['all'] as $machine)
	{
		$dash = strpos($machine, "-");
		$team = substr($machine, $dash);
		$mach = substr($machine, 0, $dash);
		
		$a .= $machine . "\tprivateIp=" . $instances['privateip'][$machine]
			. "\tansible_ssh_host=" . $instances['publicip'][$machine];
		
		if ($mach == "mysql2" || $mach == "mysql3")
			$a .= "\tmysql_master_host=" . "mysql1" . $team;
		
		$a .= "\n";
	}
	$a .= "\n";
	$a .= "[all:vars]\n";
	$a .= "ansible_ssh_user=centos\n";
	$a .= "ansible_become=true\n";
	$a .= "ansible_ssh_private_key_file=Percona-Training.key\n";
	$a .= "\n";

	foreach ($instances['machinetype'] as $key => $machines)
	{
		$a .= "[" . $key . "]\n";
		foreach ($machines as $machine)
		{
			$a .= $machine . "\n";
		}
		$a .= "\n";

		$a .= "[" . $key . ":vars]\n";
		$a .= "machinetype=" . $key . "\n";
		$a .= "\n";
	}

	foreach ($instances['team'] as $key => $machines)
	{
		$a .= "[" . $key . "]\n";
		foreach ($machines as $machine)
		{
			$a .= $machine . "\n";
		}
		$a .= "\n";

		$a .= "[" . $key . ":vars]\n";
		$a .= "team=" . $key . "\n";
		$a .= "\n";
	}
	
	$file = sprintf("tutorial-T%d.hosts", $teamId);
	file_put_contents($file, $a) or print("-- Unable to save ansible inventory\n");
	printf("\n ** ansible-playbook -i %s ansible_playbooks/hosts.yml\n", $file);
}
