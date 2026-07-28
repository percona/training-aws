<?php
declare(strict_types=1);

namespace Learnomancer\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

class DropClassCommand extends BaseCommand
{
	/**
	 * Returns the description of the command
	 */
	public static function getDescription(): string
	{
		return 'Drop an existing class, including the VPC, instances, and related resources';
	}

	/**
	 * Configures arguments, and options for the command
	 */
	protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
	{
		$parser->setDescription([
			'Use this command to drop an existing class based on slug and region.', '',
		]);

		$parser->setEpilog([
			'Examples:',
			'',
			' - Drop the class for Acme Corp:',
			' drop-class ACME us-west-1',
			'',
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

		// Call the create-instances command
		$res = $this->executeCommand(DropInstancesCommand::class, [$slug, $region], $io);
		if ($res !== static::CODE_SUCCESS)
		{
			if ($res === CODE_ABORT)
			{
				// Don't need another error message if user answers 'N' to confirmation
				return static::CODE_ERROR;
			}
			$io->abort('!! Error dropping instances in class. See above error message !!');
		}

		$res = $this->executeCommand(DropVpcCommand::class, [$slug, $region], $io);
		if ($res !== static::CODE_SUCCESS)
		{
			$io->abort('!! Error dropping class VPC - See above error message !!');
		}

		$io->success('!! Instances, and VPC for class dropped !!');

		return static::CODE_SUCCESS;
	}
}
