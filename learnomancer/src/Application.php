<?php
declare(strict_types=1);

namespace Learnomancer;

use Cake\Core\ConsoleApplicationInterface;
use Cake\Console\CommandCollection;
use Learnomancer\Command;

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
    }

	/**
     * Define the console commands for an application.
     *
     * @param \Cake\Console\CommandCollection $commands The CommandCollection to add commands into.
     * @return \Cake\Console\CommandCollection The updated collection.
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands->add('list-instances', Command\ListInstancesCommand::class);
        $commands->add('ansible-config', Command\AnsibleConfigCommand::class);
        return $commands;
    }
}
