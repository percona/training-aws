#!/usr/bin/env php
<?php

include 'config.php';

$actions      = array('ADD', 'DROP', 'LISTINSTANCES', 'GETANSIBLEHOSTS', 'GETCSV', 'GETSSHCONFIG', 'SYNCDYNAMO', 'LISTAMIS', 'TAG');
$machineTypes = array('db1', 'db2', 'scoreboard', 'app', 'pmm', 'mysql1', 'mysql2', 'mysql3', 'pxc', 'gr', 'node1', 'node2', 'node3', 'node4', 'mongodb');

const DEBUG = false;

$options = parseOptions();

// LISTAMIS only needs a region: list the Percona-Training AMIs and exit before
// we try to load any per-client VPC config (which requires a suffix).
if ($options['action'] == 'LISTAMIS')
{
	printAmis($options['region']);
	exit();
}

$config = loadConfig();

/* This is the EC2 API Client object */
$ec2 = Aws\Ec2\Ec2Client::factory(array(
	'region' => $options['region'],
	'version'=> 'latest',
	'retries'=> ['mode' => 'adaptive', 'max_attempts' => 10]));

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;

// The DynamoDB table that backs the student dashboard lives in a single
// region (us-east-1 by default). Override with PERCONA_TRAINING_DYNAMODB_REGION
// if the table is hosted elsewhere. This is independent of the EC2 training
// region passed via -r.
$dynamo = Aws\DynamoDb\DynamoDbClient::factory(array(
    'region'  => getenv('PERCONA_TRAINING_DYNAMODB_REGION') ?: 'us-east-1',
    'version' => 'latest',
    'retries' => ['mode' => 'adaptive', 'max_attempts' => 10]));

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
	case 'LISTINSTANCES':
		listInstances();
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
	print "ansible_ssh_user=rocky\n";
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

	// Named instances for this client (also used to derive DynamoDB keys).
	$reservations = searchInstanceMetadataForTag($options['suffix']);

	$frontLength = strlen("Percona-Training-");
	$namedIds = array();
	$instanceNames = array();
	$nameById = array();

	foreach($reservations as $instance)
	{
		$namedIds[] = $instance['InstanceId'];
		$nameById[$instance['InstanceId']] = $instance['Hostname'];

		list($teamTag, $machineType, $teamId) = explode("-", substr($instance['Hostname'], $frontLength));
		$instanceNames[] = sprintf("%s-%d", strtolower($teamTag), substr($teamId, 1));
	}

	// Also sweep the client's VPC directly so instances that lost their Name tag
	// (e.g. a failed tag at creation) are not missed and left running/billing.
	$vpcIds = getAllInstanceIdsInVpc();
	$untaggedIds = array_values(array_diff($vpcIds, $namedIds));
	$allIds = array_values(array_unique(array_merge($namedIds, $untaggedIds)));

	if (count($allIds) < 1)
	{
		printf("No instances were found.\n");
		return;
	}

	printf("The following instances were found:\n\n");

	foreach($namedIds as $id)
		printf("-- %s - %s\n", $id, $nameById[$id]);

	foreach($untaggedIds as $id)
		printf("-- %s - (UNTAGGED, found in VPC %s)\n", $id,
			isset($config['Vpc']['VpcId']) ? $config['Vpc']['VpcId'] : '?');

	printf("\n- Confirm to STOP AND TERMINATE/DROP %d instance(s):\n", count($allIds));
	printf("- (y/n) - ");

	if(getYesNoResponse())
	{
		try
		{
			printf("- Terminating...\n");

			$res = $ec2->terminateInstances(array(
				'InstanceIds' => $allIds
			));

			printf("- Waiting for termination confirmation...\n");

			$res = $ec2->waitUntil('InstanceTerminated', array(
				'InstanceIds' => $allIds
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

// Returns the IDs of all non-terminated instances in this client's VPC.
// Used by teardown so untagged instances are not missed. Returns an empty
// array if the VPC id is not known (no saved config).
function getAllInstanceIdsInVpc()
{
	global $ec2, $config;

	if (!isset($config['Vpc']['VpcId']))
		return array();

	$res = $ec2->describeInstances([
		'Filters' => [
			['Name' => 'vpc-id', 'Values' => [$config['Vpc']['VpcId']]],
			['Name' => 'instance-state-name', 'Values' => ['pending', 'running', 'stopping', 'stopped']],
		],
	]);

	$ids = array();
	foreach ($res->get('Reservations') as $r)
		foreach ($r['Instances'] as $i)
			$ids[] = $i['InstanceId'];

	return $ids;
}

function syncDynamo()
{
	global $options, $dynamo;

	try
	{
		// Then search for instances, and add
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

	// In rare cases an instance fails to get tagged at creation (an exception
	// during the tag call), leaving it without a Name tag. This re-tags those
	// instances by working out which expected names are still missing and
	// assigning them to the untagged instances.
	//
	// To rebuild the expected names we need the same -m (machine type) and
	// -c (team count) used at launch. An untagged instance carries no hint of
	// which machine type it was meant to be, so this is only safe for a single
	// machine type per run; for multi-type launches, re-run ADD for the type.
	if (!isset($options['machinetype']) || !isset($options['teamcount']))
	{
		printf("\nError: TAG requires -m (machine type) and -c (team count) so the expected names can be rebuilt.\n");
		exit();
	}

	$machines = explode(",", $options['machinetype']);
	$offset   = isset($options['offset']) ? $options['offset'] : 0;

	// Construct the full set of names this launch should have produced
	// (mirrors the naming used in addNewInstance()).
	$expectedNames = array();
	for ($team = $offset + 1; $team <= ($options['teamcount'] + $offset); $team++)
	{
		foreach ($machines as $machine)
		{
			$t = ($machine == 'scoreboard') ? 0 : $team;
			$expectedNames[] = sprintf("Percona-Training-%s-%s-T%d",
				$options['suffix'], $machine, $t);
		}
	}

	// Find running/stopped instances in this VPC; split into already-named
	// and untagged.
	$res = $ec2->describeInstances(array(
		'Filters' => array(
			array(
				'Name' => 'vpc-id',
				'Values' => array($config['Vpc']['VpcId'])
			),
			array(
				'Name' => 'instance-state-name',
				'Values' => array('pending', 'running', 'stopping', 'stopped')
			)
		)
	));

	$takenNames = array();
	$untaggedInstanceIds = array();
	foreach ($res->get('Reservations') as $r)
	{
		foreach ($r['Instances'] as $inst)
		{
			$name = null;
			if (isset($inst['Tags']))
			{
				foreach ($inst['Tags'] as $tag)
				{
					if ($tag['Key'] == 'Name')
					{
						$name = $tag['Value'];
						break;
					}
				}
			}

			if ($name === null || $name === '')
				$untaggedInstanceIds[] = $inst['InstanceId'];
			else
				$takenNames[] = $name;
		}
	}

	if (count($untaggedInstanceIds) == 0)
	{
		printf("- No untagged instances found in this VPC. Nothing to do.\n");
		return;
	}

	// Names we expected but don't see on any instance yet.
	$missingNames = array_values(array_diff($expectedNames, $takenNames));

	// Refuse to guess if the counts don't line up (e.g. multiple machine
	// types involved); mis-tagging would be worse than doing nothing.
	if (count($untaggedInstanceIds) > count($missingNames))
	{
		printf("!! Found %d untagged instance(s) but only %d expected name(s) are missing.\n",
			count($untaggedInstanceIds), count($missingNames));
		printf("   Re-run ADD for the affected machine type instead of using TAG.\n");
		return;
	}

	// Assign each missing name to an untagged instance.
	foreach ($untaggedInstanceIds as $idx => $instanceId)
	{
		$name = $missingNames[$idx];
		printf("- Tagging untagged instance %s as '%s'\n", $instanceId, $name);
		tagEntity($instanceId, 'Name', $name);
		tagEntity($instanceId, 'TrainingEndDate', date('Y-m-d', strtotime('+7 days')));
	}

	printf("- Tagged %d instance(s).\n", count($untaggedInstanceIds));
}

function listInstances()
{
	global $ec2, $options, $config;

	// get instances
	$reservations = searchInstanceMetadataForTag($options['suffix']);

	$frontLength = strlen(sprintf("Percona-Training-%s-", $options['suffix']));

	if(empty($reservations))
	{
		print("! No instances found for '{$options['suffix']}' !\n");
		return;
	}

	foreach ($reservations as $instance)
	{
		$fullName = $instance['Hostname'];
		$typeTeamName = substr($fullName, $frontLength);

		printf("%-15s %-30s %-10s\n",
				$instance['PublicIpAddress'], $fullName, $typeTeamName);
	}
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

		if(!$fp = fopen($sshConfigFile, "w"))
		{
			throw new Exception("Cannot open file $sshConfigFile");
		}

		// Print ssh config lines to file
		foreach ($reservations as $instance)
		{
			$typeTeamName = substr($instance['Hostname'], $frontLength);

			fwrite($fp, sprintf("Host %s %s\n", $instance['Hostname'], $typeTeamName));
			fwrite($fp, sprintf("  HostName %s\n", $instance['PublicIpAddress']));
			fwrite($fp, sprintf("  User rocky\n"));
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
				tagEntity($instanceId, 'TrainingEndDate', date('Y-m-d', strtotime('+7 days')));
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
			],
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

	// Suffix is required (except LISTAMIS, which only needs a region)
	if ($_opt['action'] != 'LISTAMIS' && (!isset($_opt['suffix']) || strlen($_opt['suffix']) < 3))
	{
		printf("\nError: -p is required. 3 character minimum.\n");
		exit();
	}

	// The three parameters always required are: action, region and suffix
	// Some actions require other parameters and some only require the base three.
	$onlyNeedBaseThree = array('GETSSHCONFIG', 'GETANSIBLEHOSTS', 'TAG', 'DROP', 'SYNCDYNAMO', 'LISTINSTANCES');

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
	$ec2 = Aws\Ec2\Ec2Client::factory(array(
		'region' => $region,
		'version'=> 'latest',
		'retries'=> ['mode' => 'adaptive', 'max_attempts' => 10]));

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
