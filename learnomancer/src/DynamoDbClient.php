<?php
declare(strict_types=1);

namespace Learnomancer;

use Aws\DynamoDb;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use Aws\Result;


final class DynamoDbClient
{
	private static ?DynamoDb\DynamoDbClient $client = null;

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
		foreach($tagTeams as $t)
		{
			list($tag, $tid) = explode("-", $t);

			$params = [
				"TableName" => "percona_training_servers",
				"Key" => array(
					"teamTag" => array("S" => $tag),
					"teamId" =>  array("N" => $tid),
				)
			];

			try
			{
				self::getClient()->deleteItem($params);
				print("-- Deleted {$tag}-T{$tid}\n");
			}
			catch (DynamoDbException $e)
			{
				print("-- !! Unable to delete {$tag}-T{$tid}:\n");
				print($e->getMessage());
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
		$json = sprintf('{":m": { "publicIp": "%s", "privateIp": "%s" } }',
			$instanceInfo['PublicIp'], $instanceInfo['PrivateIp']);

		$row = $marshaler->marshalJson($json);

		$teamTag = strtolower($slug);

		$params = [
			'TableName' => "percona_training_servers",
			'Key' => array(
				'teamTag' => array('S' => $teamTag),
				'teamId' =>  array('N' => "$teamId")
			),
			'UpdateExpression' => sprintf("SET %s = :m", $instanceInfo['machineType']),
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
