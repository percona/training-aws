<?php
declare(strict_types=1);

namespace Learnomancer\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Exception;
use Learnomancer;

class DropInstancesCommand extends BaseCommand
{
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

		return $parser;
	}

	/**
	 * Executes the command
	 */
	public function execute(Arguments $args, ConsoleIo $io): ?int
	{
		// Validate options
		$slug   = $args->getArgument('slug') ?: $io->abort('Missing slug parameter');
		$region = $args->getArgument('region') ?: $io->abort('Missing region parameter');

		$ec2          = new Learnomancer\AwsClient($region);
		$reservations = $ec2->getInstancesBySlug($slug);

		$instanceIds   = [];
		$instanceNames = [];
		$frontLength   = strlen('Percona-Training-');

		if (empty($reservations))
		{
			$io->warning("! No instances found for '{$slug}' in '{$region}'");

			return static::CODE_SUCCESS;
		}

		$io->out('The following instances were found:');

		foreach ($reservations as $instance)
		{
			$instanceIds[] = $instance['InstanceId'];

			[$teamTag, , $teamId] = explode('-', substr($instance['Hostname'], $frontLength));
			$instanceNames[]      = sprintf('%s-%d', strtolower($teamTag), substr($teamId, 1));

			$io->out(sprintf('-- %s - %s', $instance['InstanceId'], $instance['Hostname']));
		}

		// Make a choice
		$resp = $io->askChoice('Confirm to STOP AND TERMINATE/DROP:', ['Y', 'N'], 'N');
		if (strtolower($resp) !== 'y')
		{
			$io->error('-- DROP INSTANCES CANCELED --');
			$this->abort();
		}

		try {
			$io->out('- Terminating...');
			$ec2->terminateInstancesById($instanceIds);

			$io->out('- Waiting for termination confirmation...');
			$ec2->waitForTerminatedInstances($instanceIds);

			$io->out('- Instances have been terminated. They may still be visable for up to 1 hour in the console/API.');

			$io->out('- Removing instances from DynamoDB...');
			Learnomancer\DynamoDbClient::deleteFromDynamo($instanceNames);
		}
		catch (Exception $e)
		{
			$io->error("** Unknown Error: {$e->getMessage()} **");

			return static::CODE_ERROR;
		}

		return static::CODE_SUCCESS;
	}
}
