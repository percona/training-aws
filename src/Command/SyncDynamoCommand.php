<?php
declare(strict_types=1);

namespace Learnomancer\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Exception;
use Learnomancer;

class SyncDynamoCommand extends BaseCommand
{
	/**
	 * Returns the description of the command
	 */
	public static function getDescription(): string
	{
		return 'Sync instance information to DynamoDB (rarely needed)';
	}

	/**
	 * Configures arguments, and options for the command
	 */
	protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
	{
		$parser->addArguments([
			'slug' => [
				'required' => true,
				'help' => 'The slug/suffix for the class',
			],
			'region' => [
				'required' => true,
				'help' => 'The region to list from',
			],
		]);

		$parser->removeOption('verbose')->removeOption('quiet');

		return $parser;
	}

	/**
	 * Executes the command
	 */
	public function execute(Arguments $args, ConsoleIo $io): ?int
	{
		// Get arguments
		$slug   = $args->getArgument('slug') ?: $io->abort('Missing slug parameter');
		$region = $args->getArgument('region') ?: $io->abort('Missing region parameter');

		try
		{
			$ec2          = new Learnomancer\AwsClient($region);
			$reservations = $ec2->getInstancesBySlug($slug, stateFilters: ['running']);

			if (empty($reservations))
			{
				$io->warning("! No instances found for '{$slug}' in '{$region}'");

				return static::CODE_SUCCESS;
			}

			$numReservations = count($reservations);
			$io->out("- Found {$numReservations} instances to sync");

			$frontLength = strlen("Percona-Training-{$slug}-");

			foreach ($reservations as $instance)
			{
				$teamName = substr($instance['Hostname'], $frontLength);
				if ($teamName === 'PMM')
				{
					continue;
				}

				[$machineType, $teamId] = explode('-', $teamName);

				// remove the 'T'
				$teamId = substr($teamId, 1);
				if ($teamId == 0)
				{
					continue;
				}

				$instanceInfo = [
					'teamId' => $teamId,
					'machineType' => $machineType,
					'PublicIp' => $instance['PublicIpAddress'],
					'PrivateIp' => $instance['PrivateIpAddress'],
				];

				if (Learnomancer\DynamoDbClient::saveInstanceInfoToDynamo($slug, $teamId, $instanceInfo))
				{
					$io->out("-- Added {$instanceInfo['machineType']} to team {$teamId}");
				}
				else
				{
					$io->error("! Unable to add {$instanceInfo['machineType']} to team {$teamId}");
				}
			}
		}
		catch (Learnomancer\LearnomancerException | Exception $e)
		{
			$io->error('** UNABLE TO SAVE TO DYNAMO **');
			$io->error("** Unknown Error: {$e->getMessage()} **");

			return static::CODE_ERROR;
		}

		return static::CODE_SUCCESS;
	}
}
