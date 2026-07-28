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

class CreateInstancesCommand extends BaseCommand
{
	/**
	 * Returns the description of the command
	 */
	public static function getDescription(): string
	{
		return 'Create, or add, new instances to a existing class';
	}

	/**
	 * Configures arguments, and options for the command
	 */
	protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
	{
		$parser->setDescription([
			'Use this command to create/launch new instances for a class.',
			'There are several machine types to select from. You can select a single type, or combine several types separated by comma.',
			'', 'Types:',
			'- db1: A regular PS 8.4 MySQL server with sample data',
			'- db2: A blank instance. Typically used as a replica of db1.',
			'- app: An instance with sysbench, and helper scripts for generating load',
			'- mysql1, mysql2, mysql3: Regular PS 8.4 with sample data in S->R/R configuration. Used in the PXC, and GR classes',
			'- mongodb: Regular PS MongoDb server',
			'',
			'Aliases:',
			'- pxc: This is an alias for 4 types: "app,mysql1,mysql2,mysql3"',
			'- gr: Also an alias for 4 types: "app,mysql1,mysql2,mysql3"',
		]);

		$parser->setEpilog([
			'Examples:',
			'',
			' - MySQL Operations class for Acme Corp, with 9 students:',
			' create-instances ACME us-west-1 9 db1,db2 i-kjksdjf3k23232',
			'',
			' - XtraDB Cluster Tutorial class for Skynet Inc, with 4 students:',
			' create-instances SKY us-east-2 4 pxc i-kjksdjf3k23232',
		]);

		$parser->addArguments([
			'slug' => [
				'required' => true,
				'help' => 'The slug/suffix for the class',
			],
			'region' => [
				'required' => true,
				'help' => 'The region to create into',
			],
			'count' => [
				'required' => true,
				'help' => 'The number of teams to create',
			],
			'machinetype' => [
				'required' => true,
				'help' => 'The machine type to create, related to the class',
				'choices' => [
					'db1', 'db2',
					'app', 'mysql1', 'mysql2', 'mysql3',
					'pxc', 'gr',
					'node1', 'node2', 'node3', 'node4',
					'mongodb',
				],
				'separator' => ',',
			],
		]);

		$parser->addOptions([
			'offset' => [
				'short' => 'o',
				'help' => 'Offset the team counter by this amount. Useful when adding more instances to an existing class.',
				'default' => '0',
			],
			'ami' => [
				'short' => 'i',
				'help' => 'The AMI to use. Use list-instances to get a list',
				'default' => 'ami-xxxxxx',
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

		// @var int $count
		$count = intval($args->getArgument('count'));

		// @var int $offset
		$offset = intval($args->getOption('offset'));

		// @var string $ami
		$ami = (string)$args->getOption('ami');
		$this->validateOptionAmi($ami, $region, $io);

		// Load config
		try
		{
			$config = new Learnomancer\LearnomancerConfig($slug, $region);
			$config->load();

			/** @var \Learnomancer\Command\Helper\TableHelper $tableHelper */
			$tableHelper = $io->helper('Learnomancer\Command\Helper\TableHelper');

			/** @var \Learnomancer\Command\Helper\ProgressHelper $progressHelper */
			$progressHelper = $io->helper('Learnomancer\Command\Helper\ProgressHelper');
		}
		catch (Learnomancer\LearnomancerException | Exception $e)
		{
			$io->error("!! Failed to load config: {$e->getMessage()} !!");

			return static::CODE_ERROR;
		}

		$ec2 = new Learnomancer\AwsClient($region);
		$ec2->loadUser();

		// @var array<string> $instanceIds
		$instanceIds = [];

		// @var array $machines
		$machines = $args->getArrayArgument('machinetype');
		if (!is_array($machines))
		{
			$io->abort('Could not parse machine type argument.');
		}

		// Loop over each machine type, and launch them as a group
		foreach ($machines as $machine)
		{
			$instanceType = match ($machine) {
				'app' => 't3.xlarge',
				'node1' => 't3.2xlarge',
				default => 't3.large',
			};

			try
			{
				$io->out("-- Launching {$count} instances of type '{$machine}'");

				// @var array<string> $ids
				$ids         = $ec2->createInstances($instanceType, $config->subnetId, $count, $ami);
				$instanceIds = array_merge($instanceIds, $ids);
			}
			catch (Learnomancer\LearnomancerException | Exception $e)
			{
				$io->abort("** Unable to create instances: {$e->getMessage()} **");
			}
		}

		$io->out('-- Created the following instances:');

		/**
		 * Output of instance ids. The table helper needs an array-of-arrays.
		 * The reduce function turns our 1-dimension array of ids into a
		 * 2-dimensional array-of-arrays.
		 *
		 * @var array<array<string>> $tableData
		 */
		$tableData = array_reduce(
			$instanceIds,
			function (array $carry, string $item) {
				$carry[] = [$item];

				return $carry;
			},
			[['InstanceIDs']],
		);
		$tableHelper->output($tableData);

		// Wait for the instances to be in ready state
		$io->out('-- Waiting until instances are running (may take up to 30s+)...');

		$progressHelper->init([
			'total' => 60,
			'width' => 40,
		]);

		try
		{
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			$ec2->waitForRunningInstances($instanceIds, function (CommandInterface $c, int $attempts) use ($progressHelper): void {
				$progressHelper->increment($attempts * 2);
				$progressHelper->draw();
			});

			// Set progress 100%
			$progressHelper->increment(60);
			$progressHelper->draw();
			$io->out('');

			// loop over machine types so that teamIds get reset for each type
			$instanceList = $instanceIds;

			for ($teamCounter = $offset + 1; $teamCounter <= $count + $offset; $teamCounter++)
			{
				foreach ($machines as $machine)
				{
					// @var string $instanceId
					$instanceId = array_pop($instanceList);
					if ($instanceId === null)
					{
						$io->abort('Could not pop instance from list.');
					}

					$name = "Percona-Training-{$slug}-{$machine}-T{$teamCounter}";

					// scoreboard is always team 0
					if ($machine == 'scoreboard')
					{
						$name = "Percona-Training-{$slug}-{$machine}-T0";
					}

					$ec2->addTagsToEntity($instanceId, $ec2->getStandardTags($name));
				}
			}

			$io->success('Instances are running.');

			// Now that the instances are running, we can get their IPs
			// and add them to dynamo
			//
			// Add instances to dynamo table
			$io->out('-- Adding cluster info to Dynamo...');

			$this->executeCommand(SyncDynamoCommand::class, [$slug, $region]);

			$io->out('-- Done Adding Instances');
		}
		catch (Learnomancer\LearnomancerException | Exception $e)
		{
			$io->error("** Error waiting on instances: {$e->getMessage()}**");

			return static::CODE_ERROR;
		}

		return static::CODE_SUCCESS;
	}

	/**
	 * Validate that an AMI was chosen. Display list of AMIs if not specified.
	 */
	private function validateOptionAmi(string $ami, string $region, ConsoleIo $io): void
	{
		// AMI is required
		if ($ami === 'ami-xxxxxx' || empty($ami))
		{
			$io->error("Available AMIs in {$region}:");
			$this->executeCommand(ListAmisCommand::class, [$region]);

			$io->abort('Error: Missing AMI');
		}
	}
}
