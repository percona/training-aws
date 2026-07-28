<?php
declare(strict_types=1);

namespace Learnomancer\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Exception;
use Learnomancer;

class VpcConfigCommand extends BaseCommand
{
	/**
	 * Returns the description of the command
	 */
	public static function getDescription(): string
	{
		return 'Rebuild the local Learnomancer config for a given class (rarely needed)';
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
				'help' => 'The region to rebuild the config for',
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
		$slug	= $args->getArgument('slug') ?: $io->abort('Missing slug parameter');
		$region = $args->getArgument('region') ?: $io->abort('Missing region parameter');

		try
		{
			$baseName       = "Percona-Training-{$slug}";
			$vpcName        = "{$baseName}-VPC";
			$routeTableName = "{$baseName}-RT";
			$subnetName     = "{$baseName}-SN";
			$gatewayName    = "{$baseName}-GW";

			$ec2 = new Learnomancer\AwsClient($region);
			$vpc = $ec2->getVpcByName($vpcName);
			if (empty($vpc)) {
				$io->error("!! VPC '{$vpcName}' not found in '{$region}' !!");

				return static::CODE_ERROR;
			}

			$config = new Learnomancer\LearnomancerConfig($slug, $region);
			$config->load(missingOk: true);

			$config->vpcId        = $vpc['VpcId'];
			$config->routeTableId = $ec2->getRouteTableByName($routeTableName)['RouteTableId'];
			$config->subnetId     = $ec2->getSubnetByName($subnetName)['SubnetId'];
			$config->gatewayId    = $ec2->getGatewayByName($gatewayName)['GatewayId'];

			$config->save();

			$io->out("Config rebuilt for '{$slug}' in '{$region}' with VPC '{$vpcName}' ({$vpc['VpcId']})");
		}
		catch (Learnomancer\LearnomancerException | Exception $e)
		{
			$io->error("** Unable to rebuild config for '{$slug}' in '{$region}': {$e->getMessage()}**");

			return static::CODE_ERROR;
		}

		return static::CODE_SUCCESS;
	}
}
