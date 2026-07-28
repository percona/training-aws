<?php
declare(strict_types=1);

namespace Learnomancer;

use Aws\Ec2;
use Aws\Iam;

/**
 * @phpstan-type SecurityGroup array{
 *		GroupId: string,
 *		IpPermissions: array<int, array{
 * 			IpProtocol: string,
 *			FromPort: int,
 *			ToPort: int,
 *			IpRanges: array<int, array{
 *				CidrIp: string
 *			}>
 *		}
 *	>}
 *
 * @phpstan-type InstanceReservation array<array{
 *		InstanceId: string,
 *		PublicIpAddress: string,
 *		PrivateIpAddress: string,
 *		Tags: array<array{
 *			Key: string,
 *			Value: string
 *		}>
 *	}>
 */
final class AwsClient
{
	private Ec2\Ec2Client $client;

	private string $userArn;
	private string $username;

	/**
	 * Create an AwsClient for the region
	 */
	public function __construct(string $region)
	{
		// Just about every function needs this client
		$this->client = new Ec2\Ec2Client([
			'region' => $region,
			'version' => 'latest',
		]);
	}

	/**
	 * Get IAM user
	 */
	public function loadUser(): void
	{
		$iamClient = new Iam\IamClient([
			'region' => 'us-east-1',
			'version' => 'latest',
		]);

		/**
		 * Get the current user
		 *
		 * @var array{U: string, A: string} $user
		 */
		$user = $iamClient->getUser()->search('User.{U: UserName, A: Arn}');

		$this->userArn  = $user['A'];
		$this->username = $user['U'];
	}

	/**
	 * Searches for, and returns any instances matching the slug pattern
	 *
	 * @param  array<string> $stateFilters
	 * @return array<int, array{InstanceId: string, PublicIpAddress: string, PrivateIpAddress: string, Hostname: string}>
	 */
	public function getInstancesBySlug(string $slug, ?array $stateFilters = []): array
	{
		$fTag = "Percona-Training-{$slug}-*";

		$filters = [
			[
				'Name' => 'tag:Name',
				'Values' => [$fTag],
			],
		];

		if (!empty($stateFilters))
		{
			$filters[] = [
				'Name' => 'instance-state-name',
				'Values' => $stateFilters,
			];
		}

		$res = $this->client->describeInstances([
			'Filters' => $filters,
		]);

		/**
		 * @var array<InstanceReservation> $reservations
		 */
		$reservations = $res->search('Reservations[*].Instances[*].{
			InstanceId: InstanceId,
			PublicIpAddress: PublicIpAddress,
			PrivateIpAddress: PrivateIpAddress,
			Tags: Tags}');

		/**
		 * Flatten array;
		 * Also, need to filter on Tags to get proper hostname because
		 * Percona IT adds 'PerconaCreatedBy' tag to all instances
		 */
		$instances       = [];
		$numReservations = count($reservations);

		for ($r = 0; $r < $numReservations; $r++)
		{
			$numInstances = count($reservations[$r]);
			for ($i = 0; $i < $numInstances; $i++)
			{
				$hostname = '';

				// Look for the 'Name' (ie: hostname) in the tags
				foreach ($reservations[$r][$i]['Tags'] as $tag)
				{
					if ($tag['Key'] == 'Name')
					{
						$hostname = $tag['Value'];
						unset($reservations[$r][$i]['Tags']);
					}
				}

				// Add Hostname as top-level array key
				$instances[] = array_merge($reservations[$r][$i], ['Hostname' => $hostname]);
			}
		}

		return $instances;
	}

	/**
	 * @return array{}|array{VpcId: string, Name: string}
	 */
	public function getVpcById(string $vpcId): array
	{
		return $this->searchVpc('vpc-id', $vpcId);
	}

	/**
	 * @return array{}|array{VpcId: string, Name: string}
	 */
	public function getVpcByName(string $vpcName): array
	{
		return $this->searchVpc('tag:Name', $vpcName);
	}

	/**
	 * Search for an internet gateway by internet gateway ID
	 *
	 * @return array{GatewayId: string, VpcId: string, State: string}
	 */
	public function getGatewayById(string $gatewayId): array
	{
		return $this->searchGatewayByFilter('internet-gateway-id', $gatewayId);
	}

	/**
	 * Search for an internet gateway by internet gateway name
	 *
	 * @return array{GatewayId: string, VpcId: string, State: string}
	 */
	public function getGatewayByName(string $gatewayName): array
	{
		return $this->searchGatewayByFilter('tag:Name', $gatewayName);
	}

	/**
	 * Search for an internet gateway by filter name and value
	 *
	 * @return array{GatewayId: string, VpcId: string, State: string}
	 */
	public function searchGatewayByFilter(string $filterName, string $filterValue): array
	{
		$res = $this->client->describeInternetGateways([
			'Filters' => [
				[
					'Name' => $filterName,
					'Values' => [$filterValue],
				],
			],
		]);

		/** @var array<array{InternetGatewayId: string, Attachments: array<array{State: string, VpcId: string}>}> $gateways */
		$gateways = $res->get('InternetGateways');
		if (count($gateways) !== 1)
		{
			throw new LearnomancerException('Got more than 1 internet gateway. Investigate.');
		}

		$gateway     = $gateways[0];
		$attachments = $gateway['Attachments'];

		if (count($attachments) !== 1)
		{
			throw new LearnomancerException('Internet gateway has more than 1 attachment. Investigate.');
		}

		return [
			'GatewayId' => $gateway['InternetGatewayId'],
			'VpcId' => $attachments[0]['VpcId'],
			'State' => $attachments[0]['State'],
		];
	}

	/**
	 * Search for route table by route table name
	 *
	 * @return array{RouteTableId: string, VpcId: string}
	 */
	public function getRouteTableByName(string $routeTableName): array
	{
		return $this->searchRouteTable('tag:Name', $routeTableName);
	}

	/**
	 * Search for route table by route table ID
	 *
	 * @return array{RouteTableId: string, VpcId: string}
	 */
	public function getRouteTableById(string $routeTableId): array
	{
		return $this->searchRouteTable('route-table-id', $routeTableId);
	}

	/**
	 * Search for route table by filter name and value
	 *
	 * @return array{RouteTableId: string, VpcId: string}
	 */
	private function searchRouteTable(string $filterName, string $filterValue): array
	{
		$res = $this->client->describeRouteTables([
			'Filters' => [
				[
					'Name' => $filterName,
					'Values' => [$filterValue],
				],
			],
		]);

		/** @var array<array{RouteTableId: string, VpcId: string}> $routeTables */
		$routeTables = $res->get('RouteTables');
		if (count($routeTables) !== 1)
		{
			throw new LearnomancerException('Got more than 1 route table.');
		}

		return [
			'RouteTableId' => $routeTables[0]['RouteTableId'],
			'VpcId' => $routeTables[0]['VpcId'],
		];
	}

	/**
	 * Get network ACL Id
	 */
	public function getNetworkAclById(string $vpcId): string
	{
		$res = $this->client->describeNetworkAcls([
			'Filters' => [
				[
					'Name' => 'vpc-id',
					'Values' => [$vpcId],
				],
			],
		]);

		/** @var array{NetworkAclId?: string} $networkacl */
		$networkacl = $res->get('NetworkAcls');
		if (count($networkacl) !== 1)
		{
			throw new LearnomancerException('Got more than 1 Network ACL. Investigate.');
		}

		return $networkacl['NetworkAclId'];
	}

	/**
	 * Search for subnet by subnet name
	 *
	 * @return array{SubnetId: string, VpcId: string}
	 */
	public function getSubnetByName(string $subnetName): array
	{
		return $this->searchSubnet('tag:Name', $subnetName);
	}

	/**
	 * Search for subnet by subnet ID
	 *
	 * @return array{SubnetId: string, VpcId: string}
	 */
	public function getSubnetById(string $subnetId): array
	{
		return $this->searchSubnet('subnet-id', $subnetId);
	}

	/**
	 * Search for subnet by filter name and value
	 *
	 * @return array{SubnetId: string, VpcId: string}
	 */
	private function searchSubnet(string $filterName, string $filterValue): array
	{
		$res = $this->client->describeSubnets([
			'Filters' => [
				[
					'Name' => $filterName,
					'Values' => [$filterValue],
				],
			],
		]);

		/** @var array<array{SubnetId: string, VpcId: string}> $subnets */
		$subnets = $res->get('Subnets');
		if (count($subnets) !== 1)
		{
			throw new LearnomancerException('Found something other than 1 subnet. Investigate');
		}

		return $subnets[0];
	}

	/**
	 * Validate, and/or create security group rules. Get the default security group
	 * for our VPC, and check if it has inbound SSH, http, alt-http, and https
	 *
	 * @return SecurityGroup Security group information
	 */
	public function getSecurityGroupForVpc(string $vpcId): array
	{
		$res = $this->client->describeSecurityGroups([
			'Filters' => [
				[
					'Name' => 'vpc-id',
					'Values' => [$vpcId],
				],
			],
		]);

		/** @var array<SecurityGroup> $securityGroups */
		$securityGroups = $res->get('SecurityGroups');
		if (count($securityGroups) !== 1)
		{
			throw new LearnomancerException('Found something other than 1 security group. Investigate.');
		}

		return $securityGroups[0];
	}

	/**
	 * Terminate instances
	 *
	 * @param array<string> $instanceIds
	 */
	public function terminateInstancesById(array $instanceIds): void
	{
		$this->client->terminateInstances([
			'InstanceIds' => $instanceIds,
		]);
	}

	/**
	 * Wait for instances to be terminated
	 *
	 * @param array<string> $instanceIds
	 */
	public function waitForTerminatedInstances(array $instanceIds, callable $cb): void
	{
		$this->client->waitUntil('InstanceTerminated', [
			'InstanceIds' => $instanceIds,
			'@waiter' => [
				'initDelay' => 5,
				'delay' => 5,
				'maxAttempts' => 30,
				'before' => $cb,
			],
		]);
	}

	/**
	 * Wait for instances to be in running state. Executes the callback function on each iteration.
	 *
	 * @param array<string> $instanceIds
	 * @param callable $cb Callback function
	 */
	public function waitForRunningInstances(array $instanceIds, callable $cb): void
	{
		$this->client->waitUntil('InstanceRunning', [
			'InstanceIds' => $instanceIds,
			'@waiter' => [
				'initDelay' => 5,
				'delay' => 5,
				'maxAttempts' => 30,
				'before' => $cb,
			],
		]);
	}

	/**
	 * Search for, and return all the AMIs in this region matching the filter pattern of '*Training*'
	 *
	 * @return array<array{Name: string, ImageId: string}>
	 */
	public function listAmis(): array
	{
		$res = $this->client->describeImages([
			'Owners' => ['self'],
			'Filters' => [
				[
					'Name' => 'name',
					'Values' => ['*Training*'],
				],
			],
		]);

		// We only care about the image name, and the id

		/** @var array<array{Name: string, ImageId: string}> $images */
		$images = $res->search('Images[*].{Name: Name, ImageId: ImageId}');
		usort($images, function ($a, $b) {
			return strcasecmp($a['Name'], $b['Name']);
		});

		return $images;
	}

	/**
	 * Delete a subnet
	 */
	public function deleteSubnet(string $subnetId): void
	{
		$this->client->deleteSubnet([
			'SubnetId' => $subnetId,
		]);
	}

	/**
	 * Delete a route table
	 */
	public function deleteRouteTable(string $routeTableId): void
	{
		$this->client->deleteRouteTable([
			'RouteTableId' => $routeTableId,
		]);
	}

	/**
	 * Detach, and delete gateway
	 */
	public function deleteGateway(string $vpcId, string $gatewayId): void
	{
		$this->client->detachInternetGateway([
			'VpcId' => $vpcId,
			'InternetGatewayId' => $gatewayId,
		]);

		$this->client->deleteInternetGateway([
			'InternetGatewayId' => $gatewayId,
		]);
	}

	/**
	 * Delete VPC
	 */
	public function deleteVpc(string $vpcId): void
	{
		$this->client->deleteVpc([
			'VpcId' => $vpcId,
		]);
	}

	/**
	 * Search for VPC
	 *
	 * @return array{}|array{VpcId: string, Name: string}
	 */
	private function searchVpc(string $filterName, string $filterValue): array
	{
		// Search
		$res = $this->client->describeVpcs([
			'Filters' => [
				[
					'Name' => $filterName,
					'Values' => [$filterValue],
				],
			],
		]);

		/**
		 * The search path should return the VpcId, and the Name of the VPC, if found
		 *
		 * @var array{}|array<array{VpcId: string, Name: string}> $vpcs
		 */
		$vpcs = $res->search("Vpcs[].{
			VpcId: VpcId,
			Name: Tags[?Key=='Name'].Value | [0]
		}");

		$numVpcs = count($vpcs);
		if ($numVpcs > 1)
		{
			throw new LearnomancerException("More than 1 VPC returned for {$filterValue}");
		}

		// Return first element if only 1
		if ($numVpcs === 1)
		{
			return $vpcs[0];
		}

		// Otherwise, return empty
		return [];
	}

	/**
	 * Create a VPC with CIDR range, and name
	 */
	public function createVpc(string $cidrMask, string $vpcName): string
	{
		$res = $this->client->createVpc([
			'CidrBlock' => $cidrMask,
			'TagSpecifications' => [
				[
					'ResourceType' => 'vpc',
					'Tags' => $this->getStandardTags($vpcName),
				],
			],
		]);

		/** @var array{VpcId: string} $vpc */
		$vpc   = $res->get('Vpc');
		$vpcId = $vpc['VpcId'];

		return $vpcId;
	}

	/**
	 * List VPCs
	 *
	 * @return array<array{VpcId: string, Name: string, Tags?: array<array{Key: string, Value: string}>}>
	 */
	public function listVpcs(): array
	{
		$res = $this->client->describeVpcs();

		/** @var array<array{VpcId: string, Name: string, Tags: array<array{Key: string, Value: string}>}> $vpcs */
		$vpcs = $res->get('Vpcs');

		return $vpcs;
	}

	/**
	 * Create a number of instances using an AMI image in a VPC (ie: subnet)
	 *
	 * @return array<string>
	 */
	public function createInstances(string $instanceType, string $subnetId, int $count, string $ami): array
	{
		$res = $this->client->runInstances([
			'ImageId' => $ami,
			'MinCount' => $count,
			'MaxCount' => $count,
			'InstanceType' => $instanceType,
			'CreditSpecification' => [
				'CpuCredits' => 'unlimited',
			],
			'KeyName' => 'Percona-Training',
			'NetworkInterfaces' => [
				[
					'DeviceIndex' => 0,
					'AssociatePublicIpAddress' => true,
					'SubnetId' => $subnetId,
				],
			],
			'BlockDeviceMappings' => [
				[
					'DeviceName' => '/dev/sda1',
					'Ebs' => [
						'VolumeSize' => 100,
						'DeleteOnTermination' => true,
						'VolumeType' => 'gp2',
					],
				],
			],
		]);

		/** @var array<string> $ids */
		$ids = $res->search('Instances[*].InstanceId');

		return $ids;
	}

	/**
	 * Create a subnet
	 */
	public function createSubnetOnVpc(string $vpcId, string $name, string $cidrMask): string
	{
		$res = $this->client->createSubnet([
			'VpcId' => $vpcId,
			'CidrBlock' => $cidrMask,
			'TagSpecifications' => [
				[
					'ResourceType' => 'subnet',
					'Tags' => $this->getStandardTags($name),
				],
			],
		]);

		/** @var array{SubnetId?: string} $subnet */
		$subnet = $res->get('Subnet');
		if (!isset($subnet['SubnetId']))
		{
			throw new LearnomancerException('No id was returned when creating subnet on vpc');
		}

		return $subnet['SubnetId'];
	}

	/**
	 * Create a gateway, and attach to VPC
	 *
	 * @return string Gateway id
	 */
	public function createGatewayOnVpc(string $vpcId, string $name): string
	{
		$gatewayId = $this->createGateway($name);
		$this->attachGatewayToVpc($vpcId, $gatewayId);

		return $gatewayId;
	}

	/**
	 * Creates a gateway
	 *
	 * @return string Gateway id
	 */
	public function createGateway(string $name): string
	{
		$res = $this->client->createInternetGateway([
			'TagSpecifications' => [
				[
					'ResourceType' => 'internet-gateway',
					'Tags' => $this->getStandardTags($name),
				],
			],
		]);

		/** @var array{InternetGatewayId?: string} $gateway */
		$gateway = $res->get('InternetGateway');
		if (!isset($gateway['InternetGatewayId']))
		{
			throw new LearnomancerException('No id was returned when creating new internet gateway');
		}

		return $gateway['InternetGatewayId'];
	}

	/**
	 * Create a routing table
	 */
	public function createRouteTableOnVpc(string $vpcId, string $name): string
	{
		$res = $this->client->createRouteTable([
			'VpcId' => $vpcId,
			'TagSpecifications' => [
				[
					'ResourceType' => 'route-table',
					'Tags' => $this->getStandardTags($name),
				],
			],
		]);

		/** @var array{RouteTableId?: string} $routetable */
		$routetable = $res->get('RouteTable');
		if (!isset($routetable['RouteTableId']))
		{
			throw new LearnomancerException('No route table id was returned');
		}

		return $routetable['RouteTableId'];
	}

	/**
	 * Creates inbound route
	 */
	public function createInboundRoute(string $routeTableId, string $gatewayId): bool
	{
		$res = $this->client->createRoute([
			'RouteTableId' => $routeTableId,
			'DestinationCidrBlock' => '0.0.0.0/0',
			'GatewayId' => $gatewayId,
		]);

		if (!(bool)$res->get('Return'))
		{
			throw new LearnomancerException('Failed to create inbound route. Investigate.');
		}

		return true;
	}

	/**
	 * Assign a subnet to a route table
	 */
	public function assignSubnetToRouteTable(string $subnetId, string $routeTableId): string
	{
		$res = $this->client->associateRouteTable([
			'RouteTableId' => $routeTableId,
			'SubnetId' => $subnetId,
		]);

		/** @var string $associationId */
		$associationId = $res->get('AssociationId');

		return $associationId;
	}

	/**
	 * Attach gateway to VPC
	 */
	public function attachGatewayToVpc(string $vpcId, string $gatewayId): void
	{
		$this->client->attachInternetGateway([
			'InternetGatewayId' => $gatewayId,
			'VpcId' => $vpcId,
		]);
	}

	/**
	 * Creates/adds a new rule to a security group
	 */
	public function addIngressRule(string $securityGroupId, int $port, string $cidr): void
	{
		$this->client->authorizeSecurityGroupIngress([
			'GroupId' => $securityGroupId,
			'IpPermissions' => [
				[
					'IpProtocol' => 'tcp',
					'FromPort' => $port,
					'ToPort' => $port,
					'IpRanges' => [
						[
							'CidrIp' => $cidr,
						],
					],
				],
			],
		]);
	}

	/**
	 * Returns array of standard tags
	 *
	 * @return array<array{Key: string, Value: string}>
	 */
	public function getStandardTags(string $name): array
	{
		return [
			[
				'Key' => 'Name',
				'Value' => $name,
			],
			[
				'Key' => 'CreatedBy',
				'Value' => $this->username,
			],
			[
				'Key' => 'CreatedByArn',
				'Value' => $this->userArn,
			],
			[
				'Key' => 'TrainingEndDate',
				'Value' => date('Y-m-d', strtotime('+7 days')),
			],
		];
	}

	/**
	 * Apply a single tag to an entity
	 */
	public function addTagToEntity(string $entity, string $key, string $value): void
	{
		$this->client->createTags([
			'Resources' => [$entity],
			'Tags' => [
				[
					'Key' => $key,
					'Value' => $value,
				],
			],
		]);
	}

	/**
	 * Apply multiple tags to an entity
	 *
	 * @param array<array{Key: string, Value: string}> $tags
	 */
	public function addTagsToEntity(string $entity, array $tags): void
	{
		$this->client->createTags([
			'Resources' => [$entity],
			'Tags' => $tags,
		]);
	}
}
