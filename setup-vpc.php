#!/usr/bin/env php
<?php

include 'config.php';

$options = parseOptions();
$config = loadConfig();

/**
 * Subnet mapping for those rare cases we need to link VPCs
*/
$subnet_map = array(
	"DEFAULT" => "10.11.0.0/16",
	"us-west-1" => "10.12.0.0/16",
	"us-east-1" => "10.13.0.0/16"
);

/* This is the EC2 API Client object */
$ec2 = Aws\Ec2\Ec2Client::factory(array(
	'key' => $aws_key,
	'secret' => $aws_secret,
	'region' => $config['Region'],
	'version'=> 'latest'));

switch($options['action'])
{
	case 'ADD':
		addNewVpc();
		break;
	case 'STOP':
	case 'DROP':
		removeVpc();
		break;
	case 'REBUILD':
		rebuildConfigFile();
		break;
}

function rebuildConfigFile()
{
	global $ec2, $config, $options;
	
	// We need to discover everything about the VPC and recreate the config file.
	// All we have is the intended VPC name. Percona-Training-SUFFIX
	
	$vpcName = sprintf("Percona-Training-%s", $options['suffix']);
	
	try
	{
		$vpcExists = false;
		
		printf("-- Attempting to discover if VPC ('%s') exists in AWS...\n", $vpcName);
		
		$res = $ec2->describeVpcs(array(
			'Filters' => array(
				array(
					'Name' => 'tag:Name',
					'Values' => array($vpcName)
				)
			)
		));
			
		$vpcs = $res->get('Vpcs');
		if (isset($vpcs[0]['VpcId']))
		{
			$config['Vpc'] = $vpcs[0];
			saveConfig();
			
			printf("-- VPC ('%s') exists in AWS!\n", $config['Vpc']['VpcId']);
		}
		else
			throw new Exception("-- Not found. Maybe under a different name/suffix?");
		
		// Find the subnet.
		$subnetName = sprintf("Percona-Training-%s-SN", $options['suffix']);
		$res = $ec2->describeSubnets(array(
			'Filters' => array(
				array(
					'Name' => 'tag:Name',
					'Values' => array($subnetName)
				)
			)
		));
		
		$subnets = $res->get('Subnets');
		if (isset($subnets[0]['SubnetId'])
		 && $subnets[0]['VpcId'] == $config['Vpc']['VpcId'])
		{
			$config['Subnet'] = $subnets[0];
			saveConfig();
			
			printf("-- Subnet ('%s') exists.\n", $config['Subnet']['SubnetId']);
		}
		else
			throw new Exception("-- Couldn't find subnet.");
		
		// The keypair/keyname
		$config['KeyPair'] = array('KeyName' => 'Percona-Training');
		saveConfig();
	}
	catch(Exception $e)
	{
		printf("\n** Unable to discover VPC: %s\n%s**\n", $e->getMessage(), $e->getTraceAsString());
		dry_exit();
	}
}

function removeVpc()
{
	global $ec2, $config, $options;
	
	// You must detach or delete all gateways and resources that are associated with 
	// the VPC before you can delete it. For example, you must terminate all instances
	// running in the VPC, delete all security groups associated with the VPC (except
	// the default one), delete all route tables associated with the VPC (except the
	// default one), and so on.
	
	printf("Not implemented - Remove VPC's manually in web gui console.\n");
}

function addNewVpc()
{
	global $config;
	
	// So much happens for creating a VPC. This is our wrapper function.
	createVpc();
	createSubnet();
	createGateway();
	createRouteTable();
	assignSubnet();
	attachGateway();
	updateNetworkAcl();
	createInboundRoute();
	createKeypair();
	createSecurityGroups();
	
	printf("\n- VPC Sucessfully created. Please use this VPC ('%s') when launching new instances.\n\n",
		$config['Vpc']['VpcId']);
}

function createVpc()
{
	global $ec2, $config, $options;
	
	/* Create new VPC to house all the instances.
	 * We do this so replication is kept within the private network
	*/
	
	try
	{
		$vpcExists = false;
		
		if (isset($config['Vpc']) && isset($config['Vpc']['VpcId']))
		{
			printf("- Found existing VPC ('%s') in cache config for '%s'.\n",
				$config['Vpc']['VpcId'], $options['region']);
			printf("-- Attempting to discover if VPC exists in AWS...\n");
			
			$res = $ec2->describeVpcs(array(
				'Filters' => array(
					array(
						'Name' => 'vpc-id',
						'Values' => array($config['Vpc']['VpcId'])
					)
				)
			));
			
			$vpcs = $res->get('Vpcs');
			if (isset($vpcs[0]['VpcId']) && $vpcs[0]['VpcId'] == $config['Vpc']['VpcId'])
			{
				printf("-- VPC exists in AWS!\n");
				$vpcExists = true;
			}
			else
				printf("-- Not found. Creating new VPC.\n");
		}
		
		/* VPC doesn't exist. Create it.
		*/
		if (!$vpcExists)
		{
			$res = $ec2->createVpc(array(
				'DryRun' => DRY_RUN,
				'CidrBlock' => getSubnetCidrBlock($options['region'])
			));
			
			$config['Vpc'] = $res->get('Vpc');
			$config['Region'] = $options['region'];
			saveConfig();
			
			printf("- VPC Created: %s\n", $config['Vpc']['VpcId']);
		}
		
		// Update the name in any case
		addName($config['Vpc']['VpcId'],
			sprintf("Percona-Training-%s", $options['suffix']));
		
	}
	catch(Exception $e)
	{
		printf("\n** Unable to create VPC: %s**\n", $e->getMessage());
		dry_exit();
	}
}

function createSubnet()
{
	global $ec2, $config, $options;
	
	// Create the subnet for our VPC.
	// Instances will get DHCP from this pool.
	try
	{
		$subnetExists = false;
		
		if (isset($config['Subnet']) && isset($config['Subnet']['SubnetId']))
		{
			printf("- Found existing subnet ('%s') in cache config.\n", $config['Subnet']['SubnetId']);
			printf("-- Attempting to discover if subnet exists in AWS...\n");
			
			$res = $ec2->describeSubnets(array(
				'Filters' => array(
					array(
						'Name' => 'subnet-id',
						'Values' => array($config['Subnet']['SubnetId'])
					)
				)
			));
			
			$subnets = $res->get('Subnets');
			if (count($subnets) == 1 
				&& isset($subnets[0]['SubnetId'])
				&& $subnets[0]['SubnetId'] == $config['Subnet']['SubnetId'])
			{
				printf("-- Subnet exists in AWS!\n");
				$subnetExists = true;
				
				/* Update config */
				$config['Subnet'] = $subnets[0];
				saveConfig();
			}
			
			if (!$subnetExists)
				printf("-- Couldn't find previously configured subnet. ");
		}
	
		if (!$subnetExists)
		{
			printf("- Creating new Subnet for VPC.\n");
			
			$res = $ec2->createSubnet(array(
				'DryRun' => DRY_RUN,
				'VpcId' => $config['Vpc']['VpcId'],
				'CidrBlock' => getSubnetCidrBlock($options['region']),
			));
			
			$config['Subnet'] = $res->get('Subnet');
			saveConfig();
			
			printf("- Created subnet ('%s') on VPC ('%s')\n", $config['Subnet']['SubnetId'], $config['Vpc']['VpcId']);
		}
		
		// Update the name in any case.
		addName($config['Subnet']['SubnetId'],
			sprintf("Percona-Training-%s-SN", $options['suffix']));
	}
	catch(Exception $e)
	{
		printf("\n** Unable to create subnet: %s **\n", $e->getMessage());
		dry_exit();
	}
}

function createGateway()
{
	global $ec2, $config, $options;
	
	/* Create an internet gateway. We will attach this gateway to the VPC so that
	 * students and instructors can access the instances within this VPC via each
	 * instances' public IP.
	*/
	
	try
	{
		$gatewayExists = false;
	
		if (isset($config['InternetGateway']) && isset($config['InternetGateway']['InternetGatewayId']))
		{
			printf("- Found existing gateway ('%s') in cache config.\n", $config['InternetGateway']['InternetGatewayId']);
			printf("-- Attempting to discover if gateway exists in AWS...\n");
		
			$res = $ec2->describeInternetGateways(array(
				'Filters' => array(
					array(
						'Name' => 'internet-gateway-id',
						'Values' => array($config['InternetGateway']['InternetGatewayId'])
					)
				)
			));
			
			$gateways = $res->get('InternetGateways');
			if (count($gateways) == 1
				&& isset($gateways[0]['InternetGatewayId']) 
				&& $gateways[0]['InternetGatewayId'] == $config['InternetGateway']['InternetGatewayId'])
			{
				printf("-- Gateway exists in AWS!\n");
				$gatewayExists = true;
			
				/* Update config */
				$config['InternetGateway'] = $gateways[0];
				saveConfig();
			}
			
			if (!$gatewayExists)
				printf("-- Could not find previously configured gateway.\n");
		}
	
		if (!$gatewayExists)
		{
			$res = $ec2->createInternetGateway(array(
				'DryRun' => DRY_RUN
			));
			
			$config['InternetGateway'] = $res->get('InternetGateway');
			saveConfig();
			
			printf("-- Created gateway ('%s') on VPC ('%s')\n",
				$config['InternetGateway']['InternetGatewayId'], $config['Vpc']['VpcId']);
			
			addName($config['InternetGateway']['InternetGatewayId'],
				sprintf("Percona-Training-%s-GW", $options['suffix']));
		}
	}
	catch(Exception $e)
	{
			printf("\n** Unable to create internet gateway: %s **\n", $e->getMessage());
			dry_exit();
	}
}

function createRouteTable()
{
	global $ec2, $config, $options;
	
	// Create route table for this VPC
	try
	{
		$routeTableExists = false;
	
		if (isset($config['RouteTable']) && isset($config['RouteTable']['RouteTableId']))
		{
			printf("- Found existing route table ('%s') in cache config.\n", $config['RouteTable']['RouteTableId']);
			printf("-- Attempting to discover if route table exists in AWS...\n");
		
			$res = $ec2->describeRouteTables(array(
				'Filters' => array(
					array(
						'Name' => 'route-table-id',
						'Values' => array($config['RouteTable']['RouteTableId'])
					)
				)
			));
			
			$routeTables = $res->get('RouteTables');
			if (count($routeTables) == 1
				&& isset($routeTables[0]['RouteTableId'])
				&& $routeTables[0]['RouteTableId'] == $config['RouteTable']['RouteTableId'])
			{
				printf("-- Route table exists in AWS!\n");
				$routeTableExists = true;
			
				/* Update config to match information retreived from AWS */
				$config['RouteTable'] = $routeTables[0];
				saveConfig();
			} 
			
			if (!$routeTableExists)
				printf("-- Could not find previously configured route table.\n");
		}
	
		if (!$routeTableExists)
		{
			$res = $ec2->createRouteTable(array(
				'DryRun' => DRY_RUN,
				'VpcId' => $config['Vpc']['VpcId']
			));
			
			$config['RouteTable'] = $res->get('RouteTable');
			saveConfig();
			
			printf("-- Created route table ('%s') on VPC ('%s')\n",
				$config['RouteTable']['RouteTableId'], $config['Vpc']['VpcId']);
			
			addName($config['RouteTable']['RouteTableId'],
				sprintf("Percona-Training-%s-RT", $options['suffix']));
		}
	}
	catch(Exception $e)
	{
			printf("\n** Unable to create route table: %s **\n", $e->getMessage());
			dry_exit();
	}
}

function assignSubnet()
{
	global $ec2, $config, $options;
	
	// Assign our subnet to the route table
	try
	{
		$subnetAssociated = false;
		
		printf("- Verifying subnet ('%s') is associated with route table ('%s')...\n",
			$config['Subnet']['SubnetId'], $config['RouteTable']['RouteTableId']);
		
		foreach($config['RouteTable']['Associations'] as $assoc)
		{
			if (isset($assoc['SubnetId']) && $assoc['SubnetId'] == $config['Subnet']['SubnetId'])
			{
				printf("-- Subnet properly associated with route table.\n");
				$subnetAssociated = true;
			}
		}
		
		if (!$subnetAssociated)
		{
			printf("-- Subnet not associated. Attempting association...\n");
			
			$res = $ec2->associateRouteTable(array(
				'DryRun' => DRY_RUN,
				'SubnetId' => $config['Subnet']['SubnetId'],
				'RouteTableId' => $config['RouteTable']['RouteTableId']
			));
			
			printf("-- Associated subnet with route table.\n");
		}
	}
	catch(Exception $e)
	{
			printf("\n** Unable to create or associate subnet for VPC: %s **\n", $e->getMessage());
			dry_exit();
	}
}

function attachGateway()
{
	global $ec2, $config, $options;
	
	// Attach our gateway to the VPC
	try
	{
		$gatewayAttached = false;
		
		printf("- Verifying gateway ('%s') is attached to VPC ('%s')...\n",
			$config['InternetGateway']['InternetGatewayId'], $config['Vpc']['VpcId']);
		
		foreach($config['InternetGateway']['Attachments'] as $gwattach)
		{
			if (isset($gwattach['VpcId']) && $gwattach['VpcId'] == $config['Vpc']['VpcId'])
			{
				printf("-- Gateway attached to VPC.\n");
				$gatewayAttached = true;
			}
		}
		
		if (!$gatewayAttached)
		{
			printf("-- Gateway not attached to VPC. Attempting to attach...\n");
			
			$res = $ec2->attachInternetGateway(array(
				'DryRun' => DRY_RUN,
				'InternetGatewayId' => $config['InternetGateway']['InternetGatewayId'],
				'VpcId' => $config['Vpc']['VpcId']
			));
			
			printf("-- Attached gateway to VPC.\n");
		}
	}
	catch(Exception $e)
	{
			printf("\n** Unable to attach gateway to VPC: %s **\n\n", $e->getMessage());
			dry_exit();
	}
}

function createInboundRoute()
{
	global $ec2, $config, $options;
	
	// Create route to allow inbound traffic from the gateway
	try
	{
		$publicRouteExists = false;
		
		printf("- Verifying public inbound route exists on route table ('%s') and gateway ('%s')...\n",
			$config['RouteTable']['RouteTableId'], $config['InternetGateway']['InternetGatewayId']);
		
		foreach($config['RouteTable']['Routes'] as $routes)
		{
			if ((isset($routes['DestinationCidrBlock']) && $routes['DestinationCidrBlock'] == "0.0.0.0/0")
				&& (isset($routes['GatewayId']) && $routes['GatewayId'] == $config['InternetGateway']['InternetGatewayId']))
			{
				printf("-- Public route exists. Route attached to gateway.\n");
				$publicRouteExists = true;
			}
		}
		
		if (!$publicRouteExists)
		{
			printf("-- Route not found. Adding inbound route to table and gateway.\n");
			
			$res = $ec2->createRoute(array(
				'DryRun' => DRY_RUN,
				'RouteTableId' => $config['RouteTable']['RouteTableId'],
				'DestinationCidrBlock' => '0.0.0.0/0',
				'GatewayId' => $config['InternetGateway']['InternetGatewayId']
			));
			
			printf("-- Created inboud route for route table on gateway.\n");
		}
	}
	catch(Exception $e)
	{
			printf("\n** Unable to create inbound route for gateway : %s **\n\n", $e->getMessage());
			dry_exit();
	}
}

function updateNetworkAcl()
{
	global $ec2, $config, $options;
	
	// A default network ACL is created allowing every in. This is just to
	// update the display name so if you look in AWS console.
	
	try
	{
		$res = $ec2->describeNetworkAcls(array(
			'Filters' => array(
				array(
					'Name' => 'vpc-id',
					'Values' => array($config['Vpc']['VpcId'])
				)
			)
		));
		
		$networkacl = $res->get('NetworkAcls');
		if (count($networkacl) != 1)
			throw new Exception("Got more than 1 Network ACL. Investigate.");
		
		addName($networkacl[0]['NetworkAclId'],
			sprintf("Percona-Training-%s-ACL", $options['suffix']));
	}
	catch(Exception $e)
	{
		printf("\n** Unable to get network ACL : %s **\n\n", $e->getMessage());
		dry_exit();
	}
}

function createKeypair()
{
	global $ec2, $config, $options;
	
	// KeyPair is already installed on AMI, thus no need to make one here.
	// In the future, might support re-creating of key but for now, just copy
	// the default key in to config.
	$config['KeyPair'] = array('KeyName' => 'Percona-Training');
	saveConfig();
	return;
	
	/* Create new keypair for instances. All instances will use the same keypair
	 * so that distribution to attendees is simple.
	*/
	try
	{
		$keyFilename = sprintf("Percona-Training-%s", $options['suffix']) . '.key';
		$useExisting = false;
		$foundKeypair = false;
		
		if (isset($config['KeyPair']) && isset($config['KeyPair']['KeyName']))
		{
			printf("- Found existing keypair ('%s') in config. Fingerprint: '%s'\n",
				$config['KeyPair']['KeyName'], $config['KeyPair']['KeyFingerprint']);
			printf("-- Verifying keypair exists in VPC...\n");
			
			$res = $ec2->describeKeyPairs(array(
				'Filters' => array(
					array(
						'Name' => 'fingerprint',
						'Values' => array($config['KeyPair']['KeyFingerprint'])
					)
				)
			));
			
			$keys = $res->get('KeyPairs');
			if (count($keys) == 1
				&& $keys[0]['KeyFingerprint'] == $config['KeyPair']['KeyFingerprint'])
			{
				printf("-- Found keypair in VPC.\n");
				$foundKeypair = true;
			}
			
			/* Even if the keypair exists in the VPC, user may no longer have keypair file. */
			if (file_exists($keyFilename))
			{
				printf("- Found existing private key file ('%s').\n", $keyFilename);
				printf("-- Do you wish to use this keypair?\n");
				printf("-- (NOTE: Selecting 'n' will delete and replace existing keypair file)\n");
			}
			else
			{
				printf("- Cannot find private key file ('%s').\n", $keyFilename);
				printf("-- Do you have this key somewhere else and/or still want to use it?\n");
				printf("-- (NOTE: Selecting 'y' without really having the key will make instances un-login-able)\n");
			}
			
			/* Behavior based on response above is the same for either choice. */
			printf("-- (y/n) - ");
			$useExisting = getYesNoResponse();
		}
		
		if (!$useExisting)
		{
			$trainingName = sprintf("Percona-Training-%s", $options['suffix']);
			
			/* Don't want to use the existing keypair. Delete old one from VPC if present */
			if ($foundKeypair)
			{
				$res = $ec2->deleteKeyPair(array(
					'DryRun' => DRY_RUN,
					'KeyName' => $trainingName
				));
				
				printf("-- Deleted old keypair from VPC.\n");
			}
			
			printf("- Creating new keypair ('%s')...\n", $trainingName);
			
			$res = $ec2->createKeyPair(array(
				'DryRun' => DRY_RUN,
				'KeyName' => $trainingName
			));
			
			$config['KeyPair'] = array(
				'KeyName' => $res->get('KeyName'),
				'KeyFingerprint' => $res->get('KeyFingerprint')
			);
			saveConfig();
			
			// save to file
			if (!DRY_RUN)
				file_put_contents($keyFilename, $res->get('KeyMaterial'));
			
			printf("-- Created new keypair:\n\n%s\n\n", file_get_contents($keyFilename));
			printf("-- Saved keypair to: '%s'\n", $keyFilename);
		}
	}
	catch(Exception $e)
	{
		printf("Unable to create new keypair: %s\n", $e->getMessage());
		dry_exit();
	}
}

function createSecurityGroups()
{
	global $ec2, $config, $options;
	
	// Have to have a security group when launching an instance
	try
	{
		/* get the default security group for our VPC and see if it already has the SSH
		 * ingress rule applied.
		*/
		
		printf("- Verifying security group has proper ingress rules...\n");
		
		$haveInboundSSHRule = false;
		$haveInboundHTTPRule = false;
		$haveInboundAltHTTPRule = false;
		$haveInboundHTTPSRule = false;
		
		$res = $ec2->describeSecurityGroups(array(
			'Filters' => array(
				array(
					'Name' => 'vpc-id',
					'Values' => array($config['Vpc']['VpcId'])
				)
			)
		));
		
		$sgs = $res->get('SecurityGroups');
		if (count($sgs) == 1)
		{
			printf("-- Found default security group ('%s')\n", $sgs[0]['GroupId']);
			
			$config['SecurityGroup'] = $sgs[0];
			saveConfig();
			
			/* Found the default security group. Check if has proper ingress rules. */
			foreach($config['SecurityGroup']['IpPermissions'] as $perm)
			{
				// Inbound SSH
				if ($perm['IpProtocol'] == 'tcp'
					&& $perm['FromPort'] == 22
					&& $perm['ToPort'] == 22
					&& count($perm['IpRanges']) == 1
					&& $perm['IpRanges'][0]['CidrIp'] == '0.0.0.0/0')
				{
					printf("-- Found ingress rule for SSH (22).\n");
					$haveInboundSSHRule = true;
				}
				
				// Inbound HTTP
				if ($perm['IpProtocol'] == 'tcp'
					&& $perm['FromPort'] == 80
					&& $perm['ToPort'] == 80
					&& count($perm['IpRanges']) == 1
					&& $perm['IpRanges'][0]['CidrIp'] == '0.0.0.0/0')
				{
					printf("-- Found ingress rule for HTTP (80).\n");
					$haveInboundHTTPRule = true;
				}
				
				// Inbound Alt-HTTP
				if ($perm['IpProtocol'] == 'tcp'
					&& $perm['FromPort'] == 8080
					&& $perm['ToPort'] == 8080
					&& count($perm['IpRanges']) == 1
					&& $perm['IpRanges'][0]['CidrIp'] == '0.0.0.0/0')
				{
					printf("-- Found ingress rule for Alt-HTTP (8080).\n");
					$haveInboundAltHTTPRule = true;
				}
				
				// Inbound HTTPS
				if ($perm['IpProtocol'] == 'tcp'
					&& $perm['FromPort'] == 443
					&& $perm['ToPort'] == 443
					&& count($perm['IpRanges']) == 1
					&& $perm['IpRanges'][0]['CidrIp'] == '0.0.0.0/0')
				{
					printf("-- Found ingress rule for HTTPS (443).\n");
					$haveInboundHTTPSRule = true;
				}
			}
		}
		else
		{
			throw new Exception("Found something other than 1 security group. Investigate.");
		}
		
		// Tag the Security Group
		addName($config['SecurityGroup']['GroupId'],
			sprintf("Percona-Training-%s-SG", $options['suffix']));
		
		if (!$haveInboundSSHRule)
		{
			printf("-- Did not find ingress rule for SSH. Adding rule...\n");
			addIngressRule(22, "0.0.0.0/0");
			printf("-- Added ingress rule for SSH.\n");
		}
		
		if (!$haveInboundHTTPRule)
		{
			printf("-- Did not find ingres rule for HTTP. Adding rule..\n");
			addIngressRule(80, "0.0.0.0/0");
			printf("-- Added ingress rule for HTTP.\n");
		}
		
		if (!$haveInboundHTTPSRule)
		{
			printf("-- Did not find ingres rule for HTTPS. Adding rule..\n");
			addIngressRule(443, "0.0.0.0/0");
			printf("-- Added ingress rule for HTTPS.\n");
		}
		
		if (!$haveInboundAltHTTPRule)
		{
			printf("-- Did not find ingres rule for Alt-HTTP. Adding rule..\n");
			addIngressRule(8080, "0.0.0.0/0");
			printf("-- Added ingress rule for Alt-HTTP.\n");
		}
	}
	catch(Exception $e)
	{
		printf("Unable to create security group: %s\n", $e->getMessage());
		dry_exit();
	}
}

/* Helper Functions
*/
function parseOptions()
{
	global $argv, $argc;
	
	$opts = getopt("a:p:r:");
	foreach($opts as $k => $v)
	{
		switch ($k)
		{
			case 'a': $_opt['action'] = strtoupper($v); break;
			case 'p': $_opt['suffix'] = strtoupper($v); break;
			case 'r': $_opt['region'] = $v; break;
		}
	}
	
	// Action is required
	$actions = array('ADD', 'DROP', 'STATUS', 'TAG', 'REBUILD');
	if (!isset($_opt['action']) || !in_array($_opt['action'], $actions))
	{
		printf("-a is a required option. Possible values are: %s\n",
			implode(', ', $actions));
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
		printf("-p is required. 3 character minimum.\n");
		printHelp();
		exit();
	}
		
	return $_opt;
}

function printHelp()
{
	global $argv;
	
	print "\n";
	printf("Usage: %s -a <action> -r <region> -p <suffix>\n", $argv[0]);
	print "\n";
	print "  -a    Action: ADD, DROP, STATUS, TAG, REBUILD\n";
	print "  -r    Region: us-west-1, us-west-2, us-east-1, eu-west-1\n";
	print "  -p    Suffix: Usually a 3-letter code of the city hosting training.\n";
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
		if ($options['action'] != 'ADD' && $options['action'] != 'REBUILD')
		{
			printf("\n** No config file found. Have you configured the VPC? **\n\n");
			exit();
		}
		
		$config['Region'] = $options['region'];
		$config['Suffix'] = $options['suffix'];
	}
	
	return $config;
}

function dry_exit()
{
// 	if (!DRY_RUN)
// 		exit(1);
}

function saveConfig()
{
	global $config;
	
	file_put_contents($config['configfile'], json_encode($config));
}

function getYesNoResponse()
{
	$stdin = fopen('php://stdin', 'r');
	$res = fgetc($stdin);
	fclose($stdin);
	
	return ($res == 'Y' || $res == 'y');
}

function tagEntity($entity, $key, $value)
{
	global $ec2;
	
	try
	{
		$res = $ec2->createTags(array(
			'DryRun' => DRY_RUN,
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

function addName($entity, $value)
{
	tagEntity($entity, 'Name', $value);
}

function addIngressRule($port, $cidr)
{
	global $ec2, $config;
	
	$res = $ec2->authorizeSecurityGroupIngress(array(
		'DryRun' => DRY_RUN,
		'GroupId' => $config['SecurityGroup']['GroupId'],
		'IpPermissions' => array(
			array(
				'IpProtocol' => 'tcp',
				'FromPort' => $port,
				'ToPort' => $port,
				'IpRanges' => array(array('CidrIp' => $cidr))
			)
		)		
	));
}

function getSubnetCidrBlock($r)
{
	global $subnet_map;

	if(!array_key_exists($r, $subnet_map))
	{
		return $subnet_map["DEFAULT"];
	}
	return $subnet_map[$r];
}
