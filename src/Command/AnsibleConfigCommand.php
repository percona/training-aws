<?php
declare(strict_types=1);

namespace Learnomancer\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Exception;
use Learnomancer;

class AnsibleConfigCommand extends BaseCommand
{
	/**
	 * Returns the description of the command
	 */
	public static function getDescription(): string
	{
		return 'Generate an Ansible configuration file for a given class';
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

		$parser->addOption('output', [
			'short' => 'o',
			'help' => 'Save output to file',
			'boolean' => true,
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
			$reservations = $ec2->getInstancesBySlug($slug);

			if (empty($reservations))
			{
				$io->warning("!! No instances found for '{$slug}' in '{$region}' !!\n");

				return static::CODE_SUCCESS;
			}

			$frontLength = strlen("Percona-Training-{$slug}-");

			/**
			 * @var array{
			 *		machinetype?: array<string, array<string>>,
			 *		team?: array<string, array<string>>,
			 *		all?: array<string>,
			 *		privateip?: array<string, string>,
			 *		publicip?: array<string, string>,
			 *	} $instances
			 */
			$instances = [];
			foreach ($reservations as $instance)
			{
				$teamName = substr($instance['Hostname'], $frontLength);
				if ($teamName == 'PMM') {
					continue;
				}

				[$machineType, $teamCounter] = explode('-', $teamName);

				$machineName = $machineType . '-' . $teamCounter;

				$instances['machinetype'][$machineType][] = $machineName;
				$instances['team'][$teamCounter][]        = $machineName;
				$instances['all'][]                       = $machineName;
				$instances['privateip'][$machineName]     = $instance['PrivateIpAddress'];
				$instances['publicip'][$machineName]      = $instance['PublicIpAddress'];
			}

			// Build contents

			$contents = "[all]\n";

			// The 'all' group
			if (!isset($instances['all'])) {
				$io->abort('Empty instances list');
			}

			foreach ($instances['all'] as $machine)
			{
				$dash = strpos($machine, '-');
				if ($dash === false)
				{
					$io->abort("Failed to parse machine name '{$machine}'");
				}

				$team        = substr($machine, $dash);
				$machineType = substr($machine, 0, $dash);

				if (!isset($instances['privateip']) || !isset($instances['publicip']))
				{
					$io->abort('Failed to find private or public ip for instance');
				}

				$contents .= $machine . "\tprivateIp=" . $instances['privateip'][$machine]
					. "\tansible_ssh_host=" . $instances['publicip'][$machine];

				// Source-Replica / PXC / GR tutorials
				if ($machineType == 'mysql2' || $machineType == 'mysql3')
				{
					$contents .= "\tmysql_master_host=mysql1" . $team;
				}

				// Kubernetes tutorials
				if ($machineType == 'node2' || $machineType == 'node3' || $machineType == 'node4')
				{
					$contents .= "\tkube_master=node1" . $team;
				}

				$contents .= "\n";
			}

			$contents .= "\n";
			$contents .= "[all:vars]\n";
			$contents .= "ansible_ssh_user=rocky\n";
			$contents .= "ansible_become=true\n";
			$contents .= "ansible_ssh_private_key_file=Percona-Training.key\n";
			$contents .= "\n";

			// Machine groups
			if (!isset($instances['machinetype'])) {
				$io->abort('Empty machine types list');
			}

			foreach ($instances['machinetype'] as $key => $machines)
			{
				$contents .= '[' . $key . "]\n";
				foreach ($machines as $machine)
				{
					$contents .= $machine . "\n";
				}
				$contents .= "\n";

				$contents .= '[' . $key . ":vars]\n";
				$contents .= 'machinetype=' . $key . "\n";
				$contents .= "\n";
			}

			// Team groups
			if (!isset($instances['team'])) {
				$io->abort('Empty teams list');
			}

			foreach ($instances['team'] as $key => $machines)
			{
				$contents .= '[' . $key . "]\n";
				foreach ($machines as $machine)
				{
					$contents .= $machine . "\n";
				}
				$contents .= "\n";

				$contents .= '[' . $key . ":vars]\n";
				$contents .= 'team=' . $key . "\n";
			}

			// Output
			if ($args->getOption('output'))
			{
				$filename = "ansible_{$slug}";
				$io->createFile($filename, $contents, forceOverwrite: true);
			} else {
				$io->out($contents);
			}
		}
		catch(Learnomancer\LearnomancerException | Exception $e)
		{
			$io->error("** Unable to create VPC: {$e->getMessage()}**");

			return static::CODE_ERROR;
		}

		return static::CODE_SUCCESS;
	}
}
