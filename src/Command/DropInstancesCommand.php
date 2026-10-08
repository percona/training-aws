<?php
declare(strict_types=1);

namespace Learnomancer\Command;

use Aws\CommandInterface;
use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Exception;
use Learnomancer;

class DropInstancesCommand extends BaseCommand
{
	/**
	 * Returns the description of the command
	 */
	public static function getDescription(): string
	{
		return 'Drop instances from an existing class';
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

		$parser->addOption('instance-id', [
			'short' => 'i',
			'help' => 'Terminate only these instance IDs, supplied as a comma-separated list.',
			'multiple' => true,
			'separator' => ',',
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
		$slug                 = $args->getArgument('slug') ?: $io->abort('Missing slug parameter');
		$region               = $args->getArgument('region') ?: $io->abort('Missing region parameter');
		$requestedInstanceIds = array_values(array_unique(array_map(
			'trim',
			$args->getArrayOption('instance-id') ?? [],
		)));

		/** @var \Learnomancer\Command\Helper\TableHelper $tableHelper */
		$tableHelper = $io->helper('Learnomancer\Command\Helper\TableHelper');

		/** @var \Learnomancer\Command\Helper\ProgressHelper $progressHelper */
		$progressHelper = $io->helper('Learnomancer\Command\Helper\ProgressHelper');

		try
		{
			$ec2          = new Learnomancer\AwsClient($region);
			$reservations = $ec2->getInstancesBySlug($slug);

			$instanceIds    = [];
			$instanceNames  = [];
			$instanceTypes  = [];
			$tableData      = [['InstanceID', 'Hostname']];
			$hostnamePrefix = "Percona-Training-{$slug}-";

			if (empty($reservations))
			{
				if ($requestedInstanceIds !== [])
				{
					$io->error(sprintf(
						"These instance IDs were not found for '%s' in '%s': %s",
						$slug,
						$region,
						implode(', ', $requestedInstanceIds),
					));

					return static::CODE_ERROR;
				}

				$io->warning("! No instances found for '{$slug}' in '{$region}'");

				return static::CODE_SUCCESS;
			}

			if ($requestedInstanceIds !== [])
			{
				$reservations = array_values(array_filter(
					$reservations,
					fn(array $instance): bool => in_array($instance['InstanceId'], $requestedInstanceIds, true),
				));

				$foundInstanceIds   = array_column($reservations, 'InstanceId');
				$missingInstanceIds = array_diff($requestedInstanceIds, $foundInstanceIds);
				if ($missingInstanceIds !== [])
				{
					$io->error(sprintf(
						"These instance IDs were not found for '%s' in '%s': %s",
						$slug,
						$region,
						implode(', ', $missingInstanceIds),
					));

					return static::CODE_ERROR;
				}
			}

			usort(
				$reservations,
				fn(array $left, array $right): int => strnatcasecmp($left['Hostname'], $right['Hostname']),
			);

			foreach ($reservations as $instance)
			{
				// For confirmation output
				$tableData[] = [$instance['InstanceId'], $instance['Hostname']];

				// For terminating instances
				$instanceIds[] = $instance['InstanceId'];

				// Removing from DynamoDB
				[$machineType, $teamId] = explode('-', substr($instance['Hostname'], strlen($hostnamePrefix)));
				$instanceNames[]        = sprintf('%s-%d', strtolower($slug), substr($teamId, 1));
				$instanceTypes[]        = [strtolower($slug), substr($teamId, 1), $machineType];
			}

			$io->out("The following instances were found for {$slug} in '{$region}':");
			$tableHelper->output($tableData);

			// Make a choice
			$resp = $io->askChoice('Confirm to STOP AND TERMINATE/DROP:', ['Y', 'N'], 'N');
			if (strtolower($resp) !== 'y')
			{
				$io->error('-- DROP INSTANCES CANCELED --');

				return CODE_ABORT;
			}

			$io->out('- Terminating instances...');
			$ec2->terminateInstancesById($instanceIds);

			$io->out('- Waiting for termination confirmation...');

			$progressHelper->init([
				'total' => 60,
				'width' => 40,
			]);

			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			$ec2->waitForTerminatedInstances($instanceIds, function (CommandInterface $c, int $attempts) use ($progressHelper): void {
				$progressHelper->increment($attempts * 2);
				$progressHelper->draw();
			});

			// Set progress 100%
			$progressHelper->increment(60);
			$progressHelper->draw();
			$io->out('');

			$io->out('- Instances have been terminated. They may still be visable for up to 1 hour in the console/API.');

			$io->out('- Removing instances from DynamoDB...');
			if ($requestedInstanceIds === [])
			{
				Learnomancer\DynamoDbClient::deleteFromDynamo($instanceNames);
			}
			else
			{
				Learnomancer\DynamoDbClient::deleteInstancesFromDynamo($instanceTypes);
			}
		}
		catch (Learnomancer\LearnomancerException | Exception $e)
		{
			$io->error("** Unable to drop instances: {$e->getMessage()}**");

			return static::CODE_ERROR;
		}

		return static::CODE_SUCCESS;
	}
}
