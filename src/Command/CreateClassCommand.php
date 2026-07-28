<?php
declare(strict_types=1);

namespace Learnomancer\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

class CreateClassCommand extends BaseCommand
{
	/**
	 * Returns the description of the command
	 */
	public static function getDescription(): string
	{
		return 'Create a new class, including the VPC, instances, and Ansible configuration';
	}

	/**
	 * Configures arguments, and options for the command
	 */
	protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
	{
		$parser->setDescription([
			'Use this command to create a new class.', '',
			'Available Classes:',
			'',
			'- MySQL:      mysql-ops, mysql-dev, pxc, gr, proxysql, mysql-k8s',
			'- MongoDB:    mongo-ops, mongo-dev, mongo-k8s',
			'- PostgreSQL: pg-ops, pg-dev, pg-k8s',
		]);

		$parser->setEpilog([
			'Examples:',
			'',
			' - MySQL Operations class for Acme Corp, with 9 students:',
			' create-class mysql-ops ACME us-west-1 9',
			'',
			' - XtraDB Cluster Tutorial class for Skynet Inc, with 4 students:',
			' create-class pg-dev SKY us-east-2 4',
		]);

		$parser->addArguments([
			'class' => [
				'required' => true,
				'help' => 'The name of the class. Use -h for full list.',
				'choices' => [
					'mysql-ops', 'pxc', 'gr', 'proxysql', 'mysql-k8s',
					'mongo-ops', 'mongo-dev', 'mongo-k8s',
					'pg-ops', 'pg-dev', 'pg-k8s',
				],
			],
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
			'ami' => [
				'required' => true,
				'help' => 'The AMI to use. Use list-amis to get a list',
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
		$class  = $args->getArgument('class') ?: $io->abort('Missing class parameter');

		// @var int $count
		$count = intval($args->getArgument('count')) ?: $io->error('Count must be an integer');

		// @var string $ami
		$ami = (string)$args->getArgument('ami') ?: $io->error('AMI is required');

		// Class->machine mapping
		$machineTypes = match ($class)
		{
			'mysql-ops' => 'db1,db2',
			'proxysql' => 'db1,db2',
			'mysql-k8s' => 'node1',
			'pxc', 'gr' => 'app,mysql1,mysql2,mysql3',
			'mongo-ops' => 'mongodb',
			'mongo-dev' => 'mongodb',
			'pg-ops' => 'pg1',
			'pg-dev' => 'pg1',
			default => $io->abort('!! Invalid option for class !!'),
		};

		// Create the VPC
		$res = $this->executeCommand(CreateVpcCommand::class, [$slug, $region], $io);
		if ($res !== static::CODE_SUCCESS)
		{
			$io->abort('!! Error creating VPC for class. See above error message !!');
		}

		// Call the create-instances command
		$res = $this->executeCommand(CreateInstancesCommand::class, [
			$slug,
			$region,
			$count,
			$machineTypes,
			"--ami={$ami}",
		], $io);
		if ($res !== static::CODE_SUCCESS)
		{
			$io->abort('!! Error creating class. See above error message !!');
		}

		$res = $this->executeCommand(ClassSummaryCommand::class, [$slug], $io);
		if ($res !== static::CODE_SUCCESS)
		{
			$io->abort('!! Error - See above error message !!');
		}

		$this->executeCommand(SshConfigCommand::class, [$slug, $region, '--output'], $io);
		$this->executeCommand(AnsibleConfigCommand::class, [$slug, $region, '--output'], $io);

		$io->success('!! Class Created !!');

		return static::CODE_SUCCESS;
	}
}
