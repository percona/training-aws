<?php
declare(strict_types=1);

namespace Learnomancer\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Exception;
use Learnomancer;

class CreateVpcCommand extends BaseCommand
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

		// Load config
		try
		{
			$config = new Learnomancer\LearnomancerConfig($slug, $region);
			$config->loadConfig(missingOk: true);
		}
		catch (Learnomancer\LearnomancerException | Exception $e)
		{
			$io->error($e->getMessage());

			return static::CODE_ERROR;
		}

		try
		{
			// Client
			$ec2 = new Learnomancer\AwsClient($region);
			$ec2->loadUser();

			$vpcName = "Percona-Training-{$slug}-VPC";

			$io->out("- Checking for VPC '{$vpcName}' ({$config->vpcId}) in AWS...");

			// Check by Name, and exit on duplicate name
			$vpc = $ec2->getVpcByName($vpcName);
			if (!empty($vpc))
			{
				$io->error("!! A VPC with that name already exists: {$vpc['Name']} ({$vpc['VpcId']})");
				$io->error('!! Please use a different region, different slug, or execute rebuild-config');

				return static::CODE_ERROR;
			}

			$io->out("- VPC '{$vpcName}' not found. Creating new VPC...");

			$vpcCidr = $this->getSubnetCidrBlock($region);
			$vpcId   = $ec2->createVpc($vpcCidr, $vpcName);

			$config->vpcId  = $vpcId;
			$config->region = $region;
			$config->saveConfig();

			$io->out("- VPC Created: {$vpcName} ({$vpcId})");

			// Update tag
			$ec2->tagEntity($vpcId, 'TrainingEndDate', date('Y-m-d', strtotime('+7 days')));
		}
		catch(Learnomancer\LearnomancerException | Exception $e)
		{
			$io->error("** Unable to create VPC: {$e->getMessage()}**");

			return static::CODE_ERROR;
		}

		return static::CODE_SUCCESS;
	}

	/**
	 * Returns a subnet for a given region
	 */
	private function getSubnetCidrBlock(string $region): string
	{
		/**
		 * @var array<string, string> $subnetBlocks
		 */
		$subnetBlocks = Configure::read('VPC_Subnets');
		if(!array_key_exists($region, $subnetBlocks))
		{
			return $subnetBlocks['DEFAULT'];
		}

		return $subnetBlocks[$region];
	}
}
