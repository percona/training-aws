<?php
declare(strict_types=1);

namespace Learnomancer;

use Aws\Ec2;

final class AwsClient
{
	private Ec2\Ec2Client $client;

	public function __construct(string $region)
	{
		// Just about every function needs this client
		$this->client = new Ec2\Ec2Client([
			'region' => $region,
			'version'=> 'latest',
		]);
	}

	/**
	 * @return array<string>
	 */
	public function createInstances(int $count, string $instanceType, string $subnetId, string $ami): array
	{
		$res = $this->client->runInstances(array(
			'ImageId' => $ami,
			'MinCount' => $count,
			'MaxCount' => $count,
			'InstanceType' => $instanceType,
			'CreditSpecification' => array(
				'CpuCredits' => 'unlimited'
			),
			'KeyName' => 'Percona-Training',
			'NetworkInterfaces' => array(
				array(
					'DeviceIndex' => 0,
					'AssociatePublicIpAddress' => true,
					'SubnetId' => $subnetId,
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

		/**
		 * @var array<string> $ids
		 */
		$ids = $res->search('Instances[*].InstanceId');

		return $ids;
	}

	/**
	 * @return array<int, array{InstanceId: string, PublicIpAddress: string, PrivateIpAddress: string, Hostname: string}>
	 */
	public function getInstancesBySlug(string $slug): array
	{
		$fTag = "Percona-Training-{$slug}-*";

		$res = $this->client->describeInstances([
			'Filters' => [
				[
					'Name' => 'tag:Name',
					'Values' => [$fTag]
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

		//var_dump($reservations); die();

		// Flatten array;
		// Also, need to filter on Tags to get proper hostname because
		// Percona added 'PerconaCreatedBy' tag to all instances
		$instances = array();
		for ($r = 0; $r < count($reservations); $r++)
		{
			for ($i = 0; $i < count($reservations[$r]); $i++)
			{
				$hostname = "";

				// Look for the 'Name' (ie: hostname) in the tags
				foreach ($reservations[$r][$i]["Tags"] as $tag)
				{
					if ($tag["Key"] == "Name")
					{
 						$hostname = $tag["Value"];
 						unset($reservations[$r][$i]["Tags"]);
					}
				}

				// Add Hostname as top-level array key
				$instances[] = array_merge($reservations[$r][$i], array('Hostname' => $hostname));
			}
		}

		return $instances;
	}

	/**
	 * @param array<string> $instanceIds
	 */
	public function terminateInstancesById(array $instanceIds): void
	{
		$this->client->terminateInstances(array(
			'InstanceIds' => $instanceIds,
		));
	}

	/**
	 * @param array<string> $instanceIds
	 */
	public function waitForTerminatedInstances(array $instanceIds): void
	{
		$this->client->waitUntil('InstanceTerminated', array(
			'InstanceIds' => $instanceIds,
		));
	}

	/**
	 * @param array<string> $instanceIds
	 * @param callable $cb Aws client will execute the callback on each iteration of waiting
	 */
	public function waitForRunningInstances(array $instanceIds, callable $cb): void
	{
		$this->client->waitUntil('InstanceRunning', array(
			'InstanceIds' => $instanceIds,
			'@waiter' => [
				'initDelay' => 10,
				'before' => $cb,
			]
		));
	}

	/**
	 * @return array<array{Name: string, ImageId: string}>
	 */
	public function getAmisInRegion(string $region): array
	{
		$res = $this->client->describeImages(array(
			'Owners' => array('self'),
			'Filters' => array(
				array(
					'Name' => 'name',
					'Values' => array('*Training*')
				)
			)
		));

		// We only care about the image name, and the id

		/**
		 * @var array<array{Name: string, ImageId: string}> $images
		 */
		$images = $res->search('Images[*].{Name: Name, ImageId: ImageId}');
		usort($images, function($a, $b) {
			return strcasecmp($a['Name'], $b['Name']);
		});

		return $images;
	}

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
			]
		]);
	}
}
