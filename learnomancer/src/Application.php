<?php
declare(strict_types=1);

namespace Learnomancer;

use Cake\Console\CommandCollection;
use Cake\Core\Configure;
use Cake\Core\Configure\Engine\PhpConfig;
use Cake\Core\ConsoleApplicationInterface;
use Exception;

/*
 * The full path to the directory which holds "src", WITHOUT a trailing DS.
 */
define('ROOT', dirname(__DIR__));

/*
 * Path to the config directory.
 */
define('CONFIG', ROOT . DS . 'config' . DS);

final class Application implements ConsoleApplicationInterface
{
	/**
	 * Load all the application configuration and bootstrap logic.
	 *
	 * @return void
	 */
	public function bootstrap(): void
	{
		// Load configuration here. This is the first
		// method Cake\Console\CommandRunner will call on your application.
		try
		{
			Configure::config('default', new PhpConfig());
			Configure::load('learnomancer', 'default', false);
		}
		catch (Exception $e)
		{
			exit($e->getMessage() . "\n");
		}
	}

	/**
	 * Define the console commands for an application.
	 *
	 * @param  \Cake\Console\CommandCollection $commands The CommandCollection to add commands into.
	 * @return \Cake\Console\CommandCollection The updated collection.
	 */
	public function console(CommandCollection $commands): CommandCollection
	{
		$commands->add('list-instances', Command\ListInstancesCommand::class);
		$commands->add('list-amis', Command\ListAmisCommand::class);

		$commands->add('ansible-config', Command\AnsibleConfigCommand::class);
		$commands->add('ssh-config', Command\SshConfigCommand::class);
		$commands->add('sync-dynamo', Command\SyncDynamoCommand::class);

		$commands->add('create-instances', Command\CreateInstancesCommand::class);
		$commands->add('drop-instances', Command\DropInstancesCommand::class);

		$commands->add('create-vpc', Command\CreateVpcCommand::class);
		$commands->add('drop-vpc', Command\DropVpcCommand::class);

		return $commands;
	}
}
