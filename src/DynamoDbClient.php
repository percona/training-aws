<?php
declare(strict_types=1);

namespace Learnomancer;

use Aws\DynamoDb;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;

final class DynamoDbClient
{
	private static ?DynamoDb\DynamoDbClient $client = null;

	/**
	 * Singleton-style function to get a DynamoDb API client
	 */
	public static function getClient(): DynamoDb\DynamoDbClient
	{
		if (self::$client == null)
		{
			self::$client = new DynamoDb\DynamoDbClient([
				'region'  => 'us-east-1',
				'version' => 'latest',
			]);
		}

		return self::$client;
	}

	/**
	 * @param array<string> $tagTeams
	 */
	public static function deleteFromDynamo(array $tagTeams): void
	{
		// remove duplicate tag-team members
		$tagTeams = array_unique($tagTeams);

		// loop and delete
		foreach ($tagTeams as $t)
		{
			[$tag, $tid] = explode('-', $t);

			$params = [
				'TableName' => 'percona_training_servers',
				'Key' => [
					'teamTag' => ['S' => $tag],
					'teamId'  => ['N' => $tid],
				],
			];

			try {
				self::getClient()->deleteItem($params);
				print "-- Deleted {$tag}-T{$tid}\n";
			} catch (DynamoDbException $e)
			{
				print "-- !! Unable to delete {$tag}-T{$tid}:\n";
				print $e->getMessage();

				return;
			}
		}

		printf("-- Deletes from DynamoDB completed\n");
	}

	/**
	 * Remove individual machine entries without deleting the rest of the team.
	 *
	 * @param array<array{string, string, string}> $instances Arrays containing the team tag, team ID, and machine type
	 */
	public static function deleteInstancesFromDynamo(array $instances): void
	{
		foreach (array_unique($instances, SORT_REGULAR) as [$teamTag, $teamId, $machineType])
		{
			// Team zero contains shared infrastructure that is not stored in DynamoDB.
			if ($teamId === '0')
			{
				continue;
			}

			$params = [
				'TableName' => 'percona_training_servers',
				'Key' => [
					'teamTag' => ['S' => $teamTag],
					'teamId'  => ['N' => $teamId],
				],
				'UpdateExpression' => 'REMOVE #machineType',
				'ExpressionAttributeNames' => [
					'#machineType' => $machineType,
				],
				'ConditionExpression' => 'attribute_exists(teamTag) AND attribute_exists(teamId)',
			];

			try {
				self::getClient()->updateItem($params);
				print "-- Deleted {$machineType} from {$teamTag}-T{$teamId}\n";
			} catch (DynamoDbException $e)
			{
				print "-- !! Unable to delete {$machineType} from {$teamTag}-T{$teamId}:\n";
				print $e->getMessage();

				return;
			}
		}

		printf("-- Deletes from DynamoDB completed\n");
	}

	/**
	 * @param array<string> $instanceInfo
	 */
	public static function saveInstanceInfoToDynamo(string $slug, string $teamId, array $instanceInfo): bool
	{
		$marshaler = new Marshaler();
		$json      = sprintf(
			'{":m": { "publicIp": "%s", "privateIp": "%s" } }',
			$instanceInfo['PublicIp'],
			$instanceInfo['PrivateIp'],
		);

		$row = $marshaler->marshalJson($json);

		$teamTag = strtolower($slug);

		$params = [
			'TableName' => 'percona_training_servers',
			'Key' => [
				'teamTag' => ['S' => $teamTag],
				'teamId'  => ['N' => "$teamId"],
			],
			'UpdateExpression' => sprintf('SET %s = :m', $instanceInfo['machineType']),
			'ExpressionAttributeValues' => $row,
		];

		$result = self::getClient()->updateItem($params);

		// @var array{statusCode: int} $meta
		$meta = $result->get('@metadata');
		if (!is_array($meta))
		{
			throw new LearnomancerException('Failed to get response from DynamoDB.');
		}

		return $meta['statusCode'] === 200;
	}
}
