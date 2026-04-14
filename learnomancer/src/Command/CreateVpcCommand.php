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
	private Learnomancer\AwsClient $ec2;

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
	 * Executes the steps to create a VPC
	 *
	 * 0. Check for prior existence of same-named VPC
	 * 1. Create VPC, with subnet, if not exists
	 * 2.
	 * 3. Create/modify default security group
	 */
	public function execute(Arguments $args, ConsoleIo $io): ?int
	{
		// Validate options
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
			$io->error($e->getMessage());

			return static::CODE_ERROR;
		}

		try
		{
			// EC2 Client
			$this->ec2 = new Learnomancer\AwsClient($region);
			$this->ec2->loadUser();

			$baseName = "Percona-Training-{$slug}";
			$vpcName  = "{$baseName}-VPC";

			$io->out("- Checking for VPC '{$vpcName}' ({$config->vpcId}) in AWS...");

			// Check by Name, and exit on duplicate name
			$vpc = $this->ec2->getVpcByName($vpcName);
			if (!empty($vpc))
			{
				$io->error("!! A VPC with that name already exists: {$vpc['Name']} ({$vpc['VpcId']})");
				$io->error('!! Please use a different region, different slug, or execute rebuild-config');

				return static::CODE_ERROR;
			}

			$io->out("- VPC '{$vpcName}' not found. Creating new VPC...");

			$vpcCidr = $this->getSubnetCidrBlock($region);
			$vpcId   = $this->ec2->createVpc($vpcCidr, $vpcName);

			$config->vpcId  = $vpcId;
			$config->region = $region;
			$config->save();

			$io->out("- VPC Created: {$vpcName} ({$vpcId})");

			/**
			 * Add/Check subnet
			 */
			$subnetId = $config->subnetId;
			if (!empty($subnetId))
			{
				$io->out("- Checking for subnet ({$subnetId})");
				$subnet = $this->ec2->getSubnetWithId($subnetId);
				if ($subnet['VpcId'] !== $vpcId)
				{
					$io->error("!! Subnet ({$subnetId}) does not belong to VPC ({$vpcId}) !!");
					$io->error('!! Please execute rebuild-config if you believe this to be in error.');

					return static::CODE_ERROR;
				}
			}
			else
			{
				$io->out('- No subnet found for VPC in config. Creating new subnet...');
				$subnetName = "{$baseName}-SN";
				$subnetId   = $this->ec2->createSubnetOnVpc($vpcId, $subnetName, $vpcCidr);
				$io->out("- Subnet Created: {$subnetName} ({$subnetId})");

				$config->subnetId = $subnetId;
				$config->save();
			}

			/**
			 * Add/Check internet gateway
			 */
			$gatewayId = $config->gatewayId;
			if (!empty($gatewayId))
			{
				$io->out("- Checking for internet gateway ({$gatewayId})");
				$gateway = $this->ec2->getGatewayWithId($gatewayId);
				if ($gateway['VpcId'] !== $vpcId)
				{
					$io->error("!! Gateway ({$gatewayId}) does not belong to VPC ({$vpcId}) !!");
					$io->error('!! Please execute rebuild-config if you believe this to be in error.');

					return static::CODE_ERROR;
				}
			}
			else
			{
				$io->out('- No internet gateway found for VPC in config. Creating new gateway...');
				$gatewayName = "{$baseName}-GW";
				$gatewayId   = $this->ec2->createGatewayOnVpc($vpcId, $gatewayName);
				$io->out("- Gateway Created: {$gatewayName} ({$gatewayId})");

				$config->gatewayId = $gatewayId;
				$config->save();
			}

			/**
			 * Add/Check security group
			 */

			/** @var array{
			 *		GroupId: string,
			 *		IpPermissions: array<int, array{
			 * 			IpProtocol: string,
			 *			FromPort: int,
			 *			ToPort: int,
			 *			IpRanges: array<int, array{
			 *				CidrIp: string,
			 *			}>,
			 *		}>
			 *	} $securityGroup
			 *
			 * Check for Security group
			 */
			$securityGroup   = $this->ec2->getSecurityGroupForVpc($vpcId);
			$securityGroupId = $securityGroup['GroupId'];

			$this->ensureSecurityGroupPermissions($securityGroup, $io);
			$this->ec2->tagEntity($securityGroupId, 'Name', "{$vpcName}-SG");
		}
		catch(Learnomancer\LearnomancerException | Exception $e)
		{
			$io->error("** Unable to create VPC: {$e->getMessage()}**");

			return static::CODE_ERROR;
		}

		return static::CODE_SUCCESS;
	}

	/** @param array{
	 *		GroupId: string,
	 *		IpPermissions: array<int, array{
	 * 			IpProtocol: string,
	 *			FromPort: int,
	 *			ToPort: int,
	 *			IpRanges: array<int, array{
	 *				CidrIp: string,
	 *			}>,
	 *		}>
	 *	} $securityGroup
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
