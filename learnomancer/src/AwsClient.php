<?php
declare(strict_types=1);

namespace Learnomancer;

use Aws\Ec2;

final class AwsClient
{
	private Ec2\Ec2Client $client;

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
	 * Create a number of instances using an AMI image in a VPC (ie: subnet)
	 *
	 * @return array<string>
	 */
	public function createInstances(int $count, string $instanceType, string $subnetId, string $ami): array
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

		/**
		 * @var array<string> $ids
		 */
		$ids = $res->search('Instances[*].InstanceId');

		return $ids;
	}

	/**
	 * Searches for, and returns any instances matching the slug pattern
	 *
	 * @return array<int, array{InstanceId: string, PublicIpAddress: string, PrivateIpAddress: string, Hostname: string}>
	 */
	public function getInstancesBySlug(string $slug): array
	{
		$fTag = "Percona-Training-{$slug}-*";

		$res = $this->client->describeInstances([
			'Filters' => [
				[
					'Name' => 'tag:Name',
					'Values' => [$fTag],
				],
			],
		]);

		/**
		 * @var array<int, array<int, array{
		 *		InstanceId: string,
		 *		PublicIpAddress: string,
		 *		PrivateIpAddress: string,
		 *		Tags: array<int, array{
		 *			Key: string,
		 *			Value: string
		 *		}>
		 *	}>> $reservations
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
	public function waitForTerminatedInstances(array $instanceIds): void
	{
		$this->client->waitUntil('InstanceTerminated', [
			'InstanceIds' => $instanceIds,
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
				'initDelay' => 10,
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

		/**
		 * @var array<array{Name: string, ImageId: string}> $images
		 */
		$images = $res->search('Images[*].{Name: Name, ImageId: ImageId}');
		usort($images, function ($a, $b) {
			return strcasecmp($a['Name'], $b['Name']);
		});

		return $images;
	}

	/**
	 * Apply tags to the entity
	 */
	public function tagEntity(string $entity, string $key, string $value): void
	{
		$this->client->createTags([
			'Resources' => [
				$entity,
			],
			'Tags' => [
				[
					'Key' => $key,
					'Value' => $value,
				],
			],
		]);
	}
}
