<?php
declare(strict_types=1);

namespace Learnomancer\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

class ClassSummaryCommand extends BaseCommand
{
	/**
	 * Returns the description of the command
	 */
	public static function getDescription(): string
	{
		return 'Output summary of a given class, including the class dashboard, and SSH info';
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
		$slug = $args->getArgument('slug');

		$io->out([
			'=================================================================',
			'                 TRAINING ENVIRONMENT READY                      ',
			'=================================================================',
			"Server IP Dashboard: http://percona-training.s3-website-us-east-1.amazonaws.com/?tag={$slug}",
			"SSH Username: ec2-user (or 'rocky' depending on the class)",
			'SSH Key Download: See link at the bottom of the Server IP Dashboard.',
			'',
			'Note: If the dashboard does not load immediately, please wait a minute for DynamoDB to sync.',
			'=================================================================',
		]);

		return static::CODE_SUCCESS;
	}
}
