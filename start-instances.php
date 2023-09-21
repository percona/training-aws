#!/usr/bin/env php
<?php

include 'config.php';

$actions      = array('ADD', 'DROP', 'GENHOSTS', 'GENPUBHOSTS', 'GETANSIBLEHOSTS', 'GETCSV', 'GETSSHCONFIG', 'SYNCDYNAMO');
$machineTypes = array('db1', 'db2', 'scoreboard', 'app', 'pmm', 'mysql1', 'mysql2', 'mysql3', 'pxc', 'gr', 'node1', 'node2', 'node3', 'node4', 'mongodb');

const DEBUG = false;

$options = parseOptions();
$config = loadConfig();

/* This is the EC2 API Client object */
$ec2 = Aws\Ec2\Ec2Client::factory(array(
	'key' => $aws_key,
	'secret' => $aws_secret,
	'region' => $options['region'],
	'version'=> 'latest'));

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;

$dynamo = Aws\DynamoDb\DynamoDbClient::factory(array(
	'profile' => 'default',
    'region'  => 'us-east-1',
    'version' => 'latest'));

switch($options['action'])
{
	case 'ADD':
		addNewInstance();
		break;
	case 'GETSSHCONFIG':
		getSshConfig();
		break;
	case 'TAG':
		tagInstances();
		break;
	case 'GENHOSTS':
		getHostsFile($ip = "Private");
		break;
	case 'GENPUBHOSTS':
		getHostsFile($ip = "Public");
		break;
	case 'GETANSIBLEHOSTS':
		getAnsibleHosts();
		break;
	case 'DROP':
		removeInstances();
		break;
	case 'SYNCDYNAMO':
		syncDynamo();
		break;
	case 'LISTALL':
		listAllInstances();
		break;
	default:
		printf("!! Unknown action !!\n");
		printHelp();
}


// example ansible command:
//    $ ansible-playbook -i ansible-hosts hosts.yml [--limit mysql1]
function getAnsibleHosts()
{
	global $ec2, $options, $config;

	// search for all instances that match our name pattern
	$reservations = searchInstanceMetadataForTag($options['suffix']);

	$frontLength = strlen(sprintf("Percona-Training-%s-", $options['suffix']));

	$instances = array();
	foreach ($reservations as $instance)
	{
		$teamName = substr($instance["Hostname"], $frontLength);
		if($teamName == "PMM")
			continue;

		list($machineType, $teamCounter) = explode("-", $teamName);

		$machineName = $machineType . "-" . $teamCounter;

		$instances['machinetype'][$machineType][] = $machineName;
		$instances['team'][$teamCounter][] = $machineName;
		$instances['all'][] = $machineName;
		$instances['privateip'][$machineName] = $instance['PrivateIpAddress'];
		$instances['publicip'][$machineName] = $instance['PublicIpAddress'];
	}

	if (count($instances) == 0)
	{
		fwrite(STDERR, "No instances found. Nothing to show. Exiting.\n");
		return;
	}

	print "[all]\n";
	foreach ($instances['all'] as $machine)
	{
		$dash = strpos($machine, "-");
		$team = substr($machine, $dash);
		$mach = substr($machine, 0, $dash);

		print $machine . "\tprivateIp=" . $instances['privateip'][$machine]
			. "\tansible_ssh_host=" . $instances['publicip'][$machine];

		if ($mach == "mysql2" || $mach == "mysql3")
			print "\tmysql_master_host=mysql1" . $team;

		if ($mach == "node2" || $mach == "node3" || $mach == "node4")
			print "\tkube_master=node1" . $team;

		print "\n";
	}
	print "\n";
	print "[all:vars]\n";
	print "ansible_ssh_user=ec2-user\n";
	print "ansible_become=true\n";
	print "ansible_ssh_private_key_file=Percona-Training.key\n";
	print "\n";

	foreach ($instances['machinetype'] as $key => $machines)
	{
		print "[" . $key . "]\n";
		foreach ($machines as $machine)
		{
			print $machine . "\n";
		}
		print "\n";

		print "[" . $key . ":vars]\n";
		print "machinetype=" . $key . "\n";
		print "\n";
	}

	foreach ($instances['team'] as $key => $machines)
	{
		print "[" . $key . "]\n";
		foreach ($machines as $machine)
		{
			print $machine . "\n";
		}
		print "\n";

		print "[" . $key . ":vars]\n";
		print "team=" . $key . "\n";
		print "\n";
	}
}

function removeInstances()
{
	global $ec2, $options, $config;

	// search for all instances that match our name pattern
	$reservations = searchInstanceMetadataForTag($options['suffix']);

	$instanceIds = array();
	$instanceNames = array();
	$frontLength = strlen("Percona-Training-");

	if (count($reservations) < 1)
	{
		printf("No instances were found.\n");
		return;
	}

	printf("The following instances were found:\n\n");

	foreach($reservations as $instance)
	{
		$instanceIds[] = $instance['InstanceId'];

		list($teamTag, $machineType, $teamId) = explode("-", substr($instance['Hostname'], $frontLength));
		$instanceNames[] = sprintf("%s-%d", strtolower($teamTag), substr($teamId, 1));

		printf("-- %s - %s\n",
			$instance['InstanceId'], $instance['Hostname']);
	}

	printf("\n- Confirm to STOP AND TERMINATE/DROP:\n");
	printf("- (y/n) - ");

	if(getYesNoResponse())
	{
		try
		{
			printf("- Terminating...\n");

			$res = $ec2->terminateInstances(array(
				'InstanceIds' => $instanceIds
			));

			printf("- Waiting for termination confirmation...\n");

			$res = $ec2->waitUntil('InstanceTerminated', array(
				'InstanceIds' => $instanceIds
			));

			printf("- Instances have been terminated. They may still be visable for up to 1 hour in the console/API.\n");
			printf("- Removing instances from DynamoDB...\n");

			deleteInstancesFromDynamo($instanceNames);
		}
		catch(Exception $e)
		{
			printf("\n** Unknown Error: %s **\n", $e->getMessage());
		}
	}
	else
	{
		printf("-- ABORTED --\n");
	}
}

function syncDynamo()
{
	global $options, $dynamo;

	try
	{
		$reservations = searchInstanceMetadataForTag($options['suffix']);

		$frontLength = strlen(sprintf("Percona-Training-%s-", $options['suffix']));

		foreach ($reservations as $instance)
		{
			//var_dump($instance["Hostname"]);
			//var_dump($frontLength);

			$teamName = substr($instance["Hostname"], $frontLength);
			if($teamName == "PMM")
				continue;

			list($machineType, $teamId) = explode("-", $teamName);

			// remove the 'T'
			$teamId = substr($teamId, 1);
			if ($teamId == 0)
				continue;

			$instanceInfo = array(
				"teamId" => $teamId,
				"machineType" => $machineType,
				"PublicIp" => $instance['PublicIpAddress'],
				"PrivateIp" => $instance['PrivateIpAddress']);

			saveInstanceInfoToDynamo($teamId, $instanceInfo);
		}
	}
	catch(Exception $e)
	{
		printf("** UNABLE TO SAVE TO DYNAMO **\n");
		printf("** Unknown Error: %s **\n", $e->getMessage());
	}
}

function deleteInstancesFromDynamo($tagTeams)
{
	global $dynamo;

	// remove duplicate tag-team members
	$tagTeams = array_unique($tagTeams);

	// loop and delete
	foreach($tagTeams as $t)
	{
		list($tag, $tid) = explode("-", $t);

		$params = [
			"TableName" => "percona_training_servers",
			"Key" => array(
				"teamTag" => array("S" => $tag),
				"teamId" =>  array("N" => $tid)
			)
		];

		try
		{
			$result = $dynamo->deleteItem($params);
			printf("-- Deleted %s-T%d\n",
				$tag, $tid);
		}
		catch (DynamoDbException $e)
		{
			printf("-- !! Unable to delete %s-T%d: %s\n",
				$tag, $tid, $e->getMessage());
		}
	}

	printf("-- Deletes from DynamoDB completed\n");
}

function tagInstances()
{
	global $ec2, $options, $config;

	// in some weird cases, instances may not get tagged because of exception on creation
	// since they are un-tagged, they are assumed to be unused so we can simply get those
	// instances in this VPC that don't have the tag and assign new tags to them

	// search for all instances that match our name pattern
	$res = $ec2->describeInstances(array(
		'Filters' => array(
			array(
				'Name' => 'vpc-id',
				'Values' => array($config['Vpc']['VpcId'])
			)
		)
	));

	$teamCounter = 1;
	$reservations = $res->get('Reservations');

	// Instances can be part of multiple reservations (hosts)
	$numInstances = 0;
	foreach($reservations as $r)
	{
		$numInstances += count($r['Instances']);
	}

	// Construct an array of all the names we 'should' have. We will remove as
	// we find them and then assign the ones that remain.
	$instanceNames = array();
	for($i = 1; $i <= $numInstances; $i++)
	{
		$instanceNames[] = sprintf("Percona-Training-%s-%s-T%d",
				$options['suffix'], $options['machinetype'], $i);
	}

	// TODO: finish this. for adding tags after the fact.
	exit();

	// loop thru all instances looking for those without name tag
	foreach($reservations as $r)
	{
		foreach($r['Instances'] as $i)
		{
			if (isset($i['Tags'])
				&& $i['Tags'][0]['Key'] == 'Name'
				&& in_array($i['Tags'][0]['Value'], $instanceNames))
			{
				unset($instanceNames);
			}
		}
	}
}

function getHostsFile($ip = 'Private', $asArray = false)
{
	global $ec2, $options, $config;

	switch ($ip)
	{
		case "Private":
			$ip = "PrivateIpAddress";
			break;
		case "Public":
			$ip = "PublicIpAddress";
			break;
		default:
			die("Invalid IP type");
	}

	// get instances
	$reservations = searchInstanceMetadataForTag($options['suffix']);

	$frontLength = strlen(sprintf("Percona-Training-%s-", $options['suffix']));

	$hosts = array();
	foreach ($reservations as $instance)
	{
		$fullName = $instance['Hostname'];
		$typeTeamName = substr($fullName, $frontLength);

		if (!$asArray)
		{
			printf("%-15s %-30s %-10s\n",
				$instance[$ip], $fullName, $typeTeamName);
		}
		else
		{
			$hosts[] = array(
				'ip' => $instance[$ip],
				'fqdn' => $fullName,
				'team' => $typeTeamName);
		}
	}

	if ($asArray) return $hosts;
}

function getSshConfig()
{
	global $ec2, $options, $config;

	// search for all instances that match our name pattern
	$reservations = searchInstanceMetadataForTag($options['suffix']);

	$frontLength = strlen(sprintf("Percona-Training-%s-", $options['suffix']));
	$sshConfigFile = sprintf("ssh_%s.txt", $options['suffix']);

	try
	{

		if(!$fp = fopen($sshConfigFile, "a"))
		{
			throw new Exception("Cannot open file $sshConfigFile");
		}

		// Print ssh config lines to file
		foreach ($reservations as $instance)
		{
			$typeTeamName = substr($instance['Hostname'], $frontLength);

			fwrite($fp, sprintf("Host %s %s\n", $instance['Hostname'], $typeTeamName));
			fwrite($fp, sprintf("  HostName %s\n", $instance['PublicIpAddress']));
			fwrite($fp, sprintf("  User ec2-user\n"));
			fwrite($fp, sprintf("  IdentityFile %s.key\n", $config['KeyPair']['KeyName']));
			fwrite($fp, sprintf("  StrictHostKeyChecking no\n"));
			fwrite($fp, sprintf("  ForwardAgent yes\n"));
		}

		fclose($fp);

		printf("-- SSH config for all instances saved to '%s'\n", $sshConfigFile);

	}
	catch (Exception $e)
	{
		printf("Unable to get SSH config: %s", $e);
	}
}

function addNewInstance()
{
	global $ec2, $options, $config;

	$instanceIds = array();

	// Support launching multiple machine types at once
	$machines = $options['machinetype'];

	foreach ($machines as $machine)
	{
		$instanceType = "t3.large";
		switch ($machine)
		{
			case "app":
				$instanceType = "t3.xlarge";
				break;
			case "node1":
				$instanceType = "t3.2xlarge";
				break;
		}

		try
		{
			printf("-- Launching %d instances of type '%s'\n", $options['teamcount'], $machine);

			$res = $ec2->runInstances(array(
				'ImageId' => $options['ami'],
				'MinCount' => $options['teamcount'],
				'MaxCount' => $options['teamcount'],
				'InstanceType' => $instanceType,
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
							'VolumeSize' => 100,
							'DeleteOnTermination' => true,
							'VolumeType' => 'gp2'
						)
					)
				)
			));

			$ids = $res->search('Instances[*].InstanceId');
			$instanceIds = array_merge($instanceIds, $ids);
		}
		catch(Exception $e)
		{
			printf("\n** Unable to add instances: %s **\n\n", $e->getMessage());
			exit(1);
		}
	}

	// Get all the instance Ids of what we are launching
	printf("-- Created the following instances:\n");
	foreach ($instanceIds as $instance)
	{
		printf("--- %s\n", $instance);
	}

	printf("-- Waiting until instances are running (may take up to 30s+)...\n");

	// Tag instances with a Name
	try
	{
		$ec2->waitUntil('InstanceRunning', array(
			'InstanceIds' => $instanceIds,
			'@waiter' => [
				'initDelay' => 10,
				'before' => function (Aws\CommandInterface $c, $attempts) {
					printf("-- ... waiting ... (Attempt %d)\n", $attempts); }
			]
		));

		// loop over machine types so that teamIds get reset for each type
		$instanceList = $instanceIds;

		for ($teamCounter = $options['offset']+1; $teamCounter <= ($options['teamcount'] + $options['offset']); $teamCounter++)
		{
			foreach ($machines as $machine)
			{
				$instanceId = array_pop($instanceList);
				if ($instanceId === NULL)
				{
					throw new Exception("Could not pop instance from list.");
				}

				$tag = sprintf("Percona-Training-%s-%s-T%d", $options['suffix'], $machine, $teamCounter);

				// scoreboard is always team 0
				if ($machine == 'scoreboard')
					$tag = sprintf("Percona-Training-%s-%s-T%d", $options['suffix'], $machine, "0");

				tagEntity($instanceId, 'Name', $tag);
			}
		}

		printf("Instances are running.\n");
		getSshConfig();

		//
		// Now that the instances are running, we can get their IPs
		// and add them to dynamo
		//
		// Add instances to dynamo table
		printf("-- Adding cluster info to Dynamo...\n");

		syncDynamo();

		printf("-- Done Adding Instances\n");

	}
	catch(Exception $e)
	{
		printf("\n** Unknown Error: %s **\n", $e->getMessage());
		printf("\n '%s' \n", get_class($e));
	}
}

function saveInstanceInfoToDynamo($tid, $info)
{
	global $dynamo, $options;

	$marshaler = new Marshaler();
	$json = sprintf('{":m": { "publicIp": "%s", "privateIp": "%s" } }',
		$info['PublicIp'], $info['PrivateIp']);

	$row = $marshaler->marshalJson($json);

	$teamTag = strtolower($options['suffix']);

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
		printf("-- Added %s to team %d\n", $info['machineType'], $tid);
	}
	catch (DynamoDbException $e)
	{
		throw new Exception(sprintf("!! Unable to add %s to T%d: %s", $info['machineType'], $tid, $e->getMessage()));
	}
}

function searchInstanceMetadataForTag($tag)
{
	global $ec2;

	$fTag = sprintf("Percona-Training-%s-*", $tag);

	$res = $ec2->describeInstances([
		'Filters' => [
			[
				'Name' => 'tag:Name',
				'Values' => [$fTag]
			]
		],
	]);

	$reservations = $res->search('Reservations[*].Instances[*].{
		InstanceId: InstanceId,
		PublicIpAddress: PublicIpAddress,
		PrivateIpAddress: PrivateIpAddress,
		Tags: Tags}');

	// Flatten array;
	// Also, need to filter on Tags to get proper hostname because
	// Percona added 'PerconaCreatedBy' tag to all instances
	$instances = array();
	for ($r = 0; $r < count($reservations); $r++)
	{
		for ($i = 0; $i < count($reservations[$r]); $i++)
		{
			foreach ($reservations[$r][$i]["Tags"] as $tag)
			{
				if ($tag["Key"] == "Name")
				{
					$reservations[$r][$i]["Hostname"] = $tag["Value"];
					unset($reservations[$r][$i]["Tags"]);
				}
			}
			$instances[] = $reservations[$r][$i];
		}
	}

	if (DEBUG)
		fwrite(STDERR, "== DEBUG ==\n" . var_export($reservations, true) . "\n== END ==\n");

	return $instances;
}

function parseOptions()
{
	global $argv, $argc, $actions, $machineTypes;

	// defaults
	$_opt = array('teamcount' => 1, 'offset' => 0);

	$opts = getopt("h::a:c:m:o:p:r:i:", array("help::"));
	foreach($opts as $k => $v)
	{
		switch ($k)
		{
			case 'a': $_opt['action'] = strtoupper($v); break;
			case 'c': $_opt['teamcount'] = $v; break;
			case 'm': $_opt['machinetype'] = $v; break;
			case 'o': $_opt['offset'] = $v; break;
			case 'p': $_opt['suffix'] = strtoupper($v); break;
			case 'r': $_opt['region'] = $v; break;
			case 'i': $_opt['ami'] = $v; break;
			case 'help':
			case 'h':
				printHelp();
				exit();
		}
	}

	// Action is required
	if (!isset($_opt['action']) || !in_array($_opt['action'], $actions))
	{
		printf("\nError: -a is a required flag.\n");
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

	// Suffix is required
	if (!isset($_opt['suffix']) || strlen($_opt['suffix']) < 3)
	{
		printf("\nError: -p is required. 3 character minimum.\n");
		exit();
	}

	// The three parameters always required are: action, region and suffix
	// Some actions require other parameters and some only require the base three.
	$onlyNeedBaseThree = array('GETSSHCONFIG', 'GENHOSTS', 'GENPUBHOSTS', 'GETANSIBLEHOSTS', 'TAG', 'DROP', 'SYNCDYNAMO');

	// Check a few things if ADDing instances
	if ($_opt['action'] == "ADD")
	{
		// Validate machine types
		if (!isset($_opt['machinetype']))
		{
			printf("\nError: -m is a required option. Possible values are: %s\n",
				implode(', ', $machineTypes));
			exit();
		}

		$machines = explode(",", $_opt['machinetype']);
		$diff = array_diff($machines, $machineTypes);

		if (count($diff) > 0)
		{
			printf("\nError: Invalid machine option. Possible values are: %s\n",
				implode(', ', $machineTypes));
			exit();
		}

		// Sanity; scoreboard and clusters cannot be launched with other types
		if (count($machines) > 1 && (in_array("scoreboard", $machines) || in_array("pxc", $machines) || in_array("gr", $machines)))
		{
			printf("\nError: 'scoreboard', 'pxc', and 'gr' cannot be combined with other maching types.\n");
			exit();
		}

		// Support launching clusters; recreate the alias as all 4 machines
		if ($machines[0] == 'pxc' || $machines[0] == 'gr')
		{
			$machines = array('mysql1', 'mysql2', 'mysql3', 'app');
		}

		// AMI is required for adding instance. Search and display a list of all Percona-Training* AMIs
		if (!isset($_opt['ami']))
		{
			printf("You must set the AMI (-i) to use for the training instances.\n");
			printf("The following Percona-Training AMIs were found in the '%s' region:\n\n", $_opt['region']);

			printAmis($_opt['region']);
			exit();
		}

		// Reset option to possible array
		$_opt['machinetype'] = $machines;
	}

	return $_opt;
}

function printHelp()
{
	global $argv, $actions, $machineTypes;

	print "\n";
	printf("Usage: %s -a <action> -r <region> -p <suffix>\n", $argv[0]);
	print "\n";
	print "  -a    Action: " . implode(", ", $actions) . "\n";
	print "  -r    Region: us-west-1, us-west-2, us-east-1, eu-west-1\n";
	print "  -p    Suffix: Usually a 3-letter code of the city hosting training.\n";
	print "  -c    Team Count: Number of instances to launch.\n";
	print "  -m    Machine Type: " . implode(", ", $machineTypes) . "\n";
	print "        You can specify multiple machines at once: 'app,mysql1,mysql2'\n";
	print "  -o    Offset: Offsets team counter if you want to add more hosts.\n";
	print "  -i    Amazon Machine Image to use.\n";
	print "\n";
}

function loadConfig()
{
	global $config, $options;

	// Retrieve or Save settings to cache file
	$config = array();
	$config['configfile'] = getConfigFile($options['suffix'], $options['region']);

	if (file_exists($config['configfile']))
	{
		if($config = json_decode(file_get_contents($config['configfile']), true))
		{
			// printf("- Successfully loaded config '%s'\n", $config['configfile']);
		}
		else
		{
			printf("\n** Error loading config file: '%s' **\n", $config['configfile']);
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

function printAmis($region)
{
	global $aws_key, $aws_secret;

	$ec2 = Aws\Ec2\Ec2Client::factory(array(
		'key' => $aws_key,
		'secret' => $aws_secret,
		'region' => $region,
		'version'=> 'latest'));

	$res = $ec2->describeImages(array(
		'Owners' => array('self'),
		'Filters' => array(
			array(
				'Name' => 'name',
				'Values' => array('*Training*')
			)
		)
	));

	$images = $res->get('Images');
	usort($images, function($a, $b) { return strcasecmp($a['Name'], $b['Name']); });

	printf("%-30s - %s\n",
		"Name", "AMI");

	foreach($images as $image)
	{
		if (!isset($image['Name']))
			$name = '-- None --';
		else
			$name = $image['Name'];

		printf("%-30s - %s\n",
			$name, $image['ImageId']);
	}
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
