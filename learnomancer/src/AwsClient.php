<?php
declare(strict_types=1);

namespace Learnomancer;

use Aws\Ec2;

final class AwsClient
{
	private Ec2\Ec2Client $client;

	public function __construct(string $region)
	{
		$this->client = new Ec2\Ec2Client([
			'region' => $region,
			'version'=> 'latest',
		]);
	}

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

		return $instances;
	}
}
