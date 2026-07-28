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

/**
 * @phpstan-import-type SecurityGroup from \Learnomancer\AwsClient
 */

class CreateVpcCommand extends BaseCommand
{
	private Learnomancer\AwsClient $ec2;

	/**
	 * Returns the description of the command
	 */
	public static function getDescription(): string
	{
		return 'Create a VPC for a new class';
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
	 * Executes the steps to create a VPC
	 *
	 * 0. Check for prior existence of same-named VPC
	 * 1. Create VPC, with subnet, if not exists
	 * 2.
	 * 3. Create/modify default security group
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
			$this->ec2 = new Learnomancer\AwsClient($region);
			$this->ec2->loadUser();

			$baseName = "Percona-Training-{$slug}";
			$vpcName  = "{$baseName}-VPC";
			$vpcCidr  = $config->vpcCidrBlock = $this->getSubnetCidrBlock($region);

			/**
			 * If no VPC Id in config file, search for VPC based off name.
			 */
			$vpcId = $config->vpcId;
			if (empty($vpcId))
			{
				$io->out("- No VPC Id in config file. Searching by name '{$vpcName}'...");

				$vpc = $this->ec2->getVpcByName($vpcName);
				if (!empty($vpc))
				{
					$io->error("!! A VPC with that name already exists: {$vpc['Name']} ({$vpc['VpcId']})");
					$io->error("!! Please use a different slug, or use 'vpc-config' to rebuild the config.");

					return static::CODE_ERROR;
				}
				else
				{
					// Nothing found via matching name. This is a brand new vpc creation.
					$io->out("- VPC '{$vpcName}' not found. Creating new VPC...");
					$vpcId = $this->ec2->createVpc($vpcCidr, $vpcName);

					$io->out("-- VPC {$vpcName} ({$vpcId}) created in {$region}");
				}

				$config->vpcId  = $vpcId;
				$config->region = $region;
				$config->save();
			}

			$io->out("- Checking VPC ({$vpcId}) status...");
			$vpc = $this->ec2->getVpcById($vpcId);
			if (empty($vpc) || $vpc['Name'] !== $vpcName)
			{
				$io->error("!! VPC '{$vpcId}' found, but does not match '{$vpcName}'. Please investigate.");

				return static::CODE_ERROR;
			}

			$io->out("-- VPC '{$vpcName}' is OK");

			/**
			 * Add/Check subnet
			 */
			$subnetId = $config->subnetId;
			if (empty($subnetId))
			{
				$io->out('- No subnet found for VPC in config. Creating new subnet...');
				$subnetName = "{$baseName}-SN";
				$subnetId   = $this->ec2->createSubnetOnVpc($vpcId, $subnetName, $vpcCidr);
				$io->out("-- Subnet Created: {$subnetName} ({$subnetId})");

				$config->subnetId = $subnetId;
				$config->save();
			}

			$io->out("- Checking subnet ({$subnetId}) status...");
			$subnet = $this->ec2->getSubnetById($subnetId);
			if ($subnet['VpcId'] !== $vpcId)
			{
				$io->error("!! Subnet ({$subnetId}) does not belong to VPC ({$vpcId}) !!");
				$io->error('!! Please rebuild the VPC config using "vpc-config" if you believe this to be in error.');

				return static::CODE_ERROR;
			}

			$io->out("-- Subnet ({$subnetId}) is attached to VPC");

			/**
			 * Add/Check internet gateway
			 */
			$gatewayId = $config->gatewayId;
			if (empty($gatewayId))
			{
				$io->out('- No internet gateway found for VPC in config. Creating new gateway...');
				$gatewayName = "{$baseName}-GW";
				$gatewayId   = $this->ec2->createGatewayOnVpc($vpcId, $gatewayName);
				$io->out("-- Gateway Created: {$gatewayName} ({$gatewayId})");

				$config->gatewayId = $gatewayId;
				$config->save();
			}

			$io->out("- Checking internet gateway ({$gatewayId}) status...");
			$gateway = $this->ec2->getGatewayById($gatewayId);
			if ($gateway['VpcId'] !== $vpcId)
			{
				$io->error("!! Gateway ({$gatewayId}) does not belong to VPC ({$vpcId}) !!");
				$io->error('!! Please rebuild the VPC config using "vpc-config" if you believe this to be in error.');

				return static::CODE_ERROR;
			}

			// Check attachment status of gateway. If not attached, then attach it
			if (!in_array($gateway['State'], ['available', 'attaching', 'attached']))
			{
				$this->ec2->attachGatewayToVpc($vpcId, $gatewayId);
			}

			$io->out("-- Gateway ({$gatewayId}) is attached to VPC");

			/**
			 * Add/Check route table
			 */
			$routeTableId = $config->routeTableId;
			if (empty($routeTableId))
			{
				$io->out('- No route table found for VPC in config. Creating new route table...');
				$routeTableName = "{$baseName}-RT";
				$routeTableId   = $this->ec2->createRouteTableOnVpc($vpcId, $routeTableName);
				$io->out("-- Route table created: {$routeTableName} ({$routeTableId})");

				// Add the 0.0.0.0 inbound route to the table
				$this->ec2->createInboundRoute($routeTableId, $gatewayId);

				$config->routeTableId = $routeTableId;
				$config->save();
			}

			$io->out("- Checking routing table ({$routeTableId}) status...");
			$routeTable = $this->ec2->getRouteTableById($routeTableId);
			if ($routeTable['VpcId'] !== $vpcId)
			{
				$io->error("!! Route table ({$routeTableId}) does not belong to VPC ({$vpcId}) !!");
				$io->error('!! Please rebuild the VPC config using "vpc-config" if you believe this to be in error.');

				return static::CODE_ERROR;
			}

			$io->out("-- Route table ({$routeTableId}) is attached to VPC");

			/**
			 * Associate subnet to route table
			 */
			$config->associationId = $this->ec2->assignSubnetToRouteTable($subnetId, $routeTableId);
			$config->save();
			$io->out('- Associated subnet with route table');

			/**
			 * Get the security group created automatically on VPC creation
			 */
			$securityGroup   = $this->ec2->getSecurityGroupForVpc($vpcId);
			$securityGroupId = $securityGroup['GroupId'];

			$config->securityGroupId = $securityGroupId;
			$config->save();

			/**
			 * Check security group has correct inbound permissions
			 */
			$io->out('- Checking security group ingress rules...');
			$this->ensureSecurityGroupPermissions($securityGroup, $io);

			$io->success('!! All done. VPC created. !!');
		}
		catch (Learnomancer\LearnomancerException | Exception $e)
		{
			$io->error("** Unable to create VPC: {$e->getMessage()}**");

			return static::CODE_ERROR;
		}

		return static::CODE_SUCCESS;
	}

	/**
	 * @param SecurityGroup $securityGroup
	 */
	private function ensureSecurityGroupPermissions(array $securityGroup, ConsoleIo $io): void
	{
		// Check if default security group has proper ingress rules.
		$securityGroupId = $securityGroup['GroupId'];

		$haveInboundSSHRule     = false;
		$haveInboundHTTPRule    = false;
		$haveInboundAltHTTPRule = false;
		$haveInboundHTTPSRule   = false;

		foreach($securityGroup['IpPermissions'] as $permission)
		{
			// Inbound SSH
			if ($permission['IpProtocol'] == 'tcp'
				&& $permission['FromPort'] == 22
				&& $permission['ToPort'] == 22
				&& count($permission['IpRanges']) == 1
				&& $permission['IpRanges'][0]['CidrIp'] == '0.0.0.0/0')
			{
				$io->out('-- Found ingress rule for SSH (22).');
				$haveInboundSSHRule = true;
			}

			// Inbound HTTP
			if ($permission['IpProtocol'] == 'tcp'
				&& $permission['FromPort'] == 80
				&& $permission['ToPort'] == 80
				&& count($permission['IpRanges']) == 1
				&& $permission['IpRanges'][0]['CidrIp'] == '0.0.0.0/0')
			{
				$io->out('-- Found ingress rule for HTTP (80).');
				$haveInboundHTTPRule = true;
			}

			// Inbound Alt-HTTP
			if ($permission['IpProtocol'] == 'tcp'
				&& $permission['FromPort'] == 8080
				&& $permission['ToPort'] == 8080
				&& count($permission['IpRanges']) == 1
				&& $permission['IpRanges'][0]['CidrIp'] == '0.0.0.0/0')
			{
				$io->out('-- Found ingress rule for Alt-HTTP (8080).');
				$haveInboundAltHTTPRule = true;
			}

			// Inbound HTTPS
			if ($permission['IpProtocol'] == 'tcp'
				&& $permission['FromPort'] == 443
				&& $permission['ToPort'] == 443
				&& count($permission['IpRanges']) == 1
				&& $permission['IpRanges'][0]['CidrIp'] == '0.0.0.0/0')
			{
				$io->out('-- Found ingress rule for HTTPS (443).');
				$haveInboundHTTPSRule = true;
			}
		}

		if (!$haveInboundSSHRule)
		{
			$io->out('-- Did not find ingress rule for SSH. Adding rule...');
			$this->ec2->addIngressRule($securityGroupId, 22, '0.0.0.0/0');
			$io->out('-- Added ingress rule for SSH.');
		}

		if (!$haveInboundHTTPRule)
		{
			$io->out('-- Did not find ingres rule for HTTP. Adding rule..');
			$this->ec2->addIngressRule($securityGroupId, 80, '0.0.0.0/0');
			$io->out('-- Added ingress rule for HTTP.');
		}

		if (!$haveInboundHTTPSRule)
		{
			$io->out('-- Did not find ingres rule for HTTPS. Adding rule..');
			$this->ec2->addIngressRule($securityGroupId, 443, '0.0.0.0/0');
			$io->out('-- Added ingress rule for HTTPS.');
		}

		if (!$haveInboundAltHTTPRule)
		{
			$io->out('-- Did not find ingres rule for Alt-HTTP. Adding rule..');
			$this->ec2->addIngressRule($securityGroupId, 8080, '0.0.0.0/0');
			$io->out('-- Added ingress rule for Alt-HTTP.');
		}
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
