<?php
declare(strict_types=1);

namespace Learnomancer\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Exception;
use Learnomancer;

class ListVpcsCommand extends BaseCommand
{
	/**
	 * Returns the description of the command
	 */
	public static function getDescription(): string
	{
		return 'List currently existing VPCs for a given region';
	}

	/**
	 * Configures arguments, and options for the command
	 */
	protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
	{
		$parser->addArguments([
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
		$region = $args->getArgument('region') ?: $io->abort('Missing region parameter');

		/** @var \Learnomancer\Command\Helper\TableHelper $tableHelper */
		$tableHelper = $io->helper('Learnomancer\Command\Helper\TableHelper');

		try
		{
			$ec2  = new Learnomancer\AwsClient($region);
			$vpcs = $ec2->listVpcs();

			$tableData = [['VpcId', 'Name']];

			$io->out("========== Listing VPCs in '{$region}' ==========");

			foreach ($vpcs as $vpc)
			{
				$name = '(No tags on VPC)';
				if(isset($vpc['Tags']))
				{
					foreach($vpc['Tags'] as $tag)
					{
						if ($tag['Key'] == 'Name')
						{
							$name = $tag['Value'];
						}
					}
				}

				$tableData[] = [$vpc['VpcId'], $name];
			}

			$tableHelper->output($tableData);
		}
		catch (Learnomancer\LearnomancerException | Exception $e)
		{
			$io->error("** Unable to list VPCs: {$e->getMessage()}**");

			return static::CODE_ERROR;
		}

		return static::CODE_SUCCESS;
	}
}
