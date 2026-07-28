<?php
declare(strict_types=1);

namespace Learnomancer\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Exception;
use Learnomancer;

class DropVpcCommand extends BaseCommand
{
	/**
	 * Returns the description of the command
	 */
	public static function getDescription(): string
	{
		return 'Drop the VPC for an existing class';
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

		// Load VPC config
		try
		{
			$config = new Learnomancer\LearnomancerConfig($slug, $region);
			$config->load(missingOk: true);
		}
		catch (Learnomancer\LearnomancerException | Exception $e)
		{
			$io->error("!! Failed to load config: {$e->getMessage()} !!");

			return static::CODE_ERROR;
		}

		try
		{
			// EC2 Client
			$ec2 = new Learnomancer\AwsClient($region);

			$baseName = "Percona-Training-{$slug}";
			$vpcName  = "{$baseName}-VPC";
			$vpcId    = $config->vpcId;

			// Sanity
			if (empty($vpcId))
			{
				$io->abort('!! No VPC Id found in config file !!');
			}

			// Confirm drop
			$resp = $io->askChoice("Confirm to DROP/DELETE VPC '{$vpcName}':", ['Y', 'N'], 'N');
			if (strtolower($resp) !== 'y')
			{
				$io->abort('!! DROP VPC CANCELED !!');
			}

			/**
			 * In order to delete a VPC, you must first delete all the components of
			 * the VPC (eg: instances, subnet, route table), and detach the gateway.
			 */

			// Delete subnet
			$subnetId = $config->subnetId;
			if (empty($subnetId))
			{
				$io->abort('No Subnet Id found in config file. Please rebuild the config file.');
			}
			$ec2->deleteSubnet($subnetId);
			$io->out("-- Subnet ({$subnetId}) deleted");

			// Delete route table
			$routeTableId = $config->routeTableId;
			if (empty($routeTableId))
			{
				$io->abort('No Route Table Id found in config file. Please rebuild the config file.');
			}
			$ec2->deleteRouteTable($routeTableId);
			$io->out("-- Route table ({$routeTableId}) deleted");

			// Detach, and delete gateway
			$gatewayId = $config->gatewayId;
			if (empty($gatewayId))
			{
				$io->abort('No gateway Id found in config file. Please rebuild the config file.');
			}
			$ec2->deleteGateway($vpcId, $gatewayId);
			$io->out("-- Gateway ({$gatewayId}) detached, and deleted");

			// Delete VPC
			$ec2->deleteVpc($vpcId);
			$config->save(saveEmpty: true);

			$io->success("!! VPC '{$vpcName}' ({$vpcId}) deleted !!");
		}
		catch (Learnomancer\LearnomancerException | Exception $e)
		{
			$io->error("** Error: {$e->getMessage()} **");

			return static::CODE_ERROR;
		}

		return static::CODE_SUCCESS;
	}
}
