<?php
declare(strict_types=1);

namespace Learnomancer\Command;

use Cake\Console\BaseCommand as CakeBaseCommand;
use Cake\Console\ConsoleOptionParser;
use Learnomancer\ConsoleOptionParser as LearnomancerConsoleOptionParser;

/**
 * Base command that keeps dashed command names in generated help output.
 */
abstract class AbstractCommand extends CakeBaseCommand
{
	/**
	 * Build the option parser using Learnomancer's command-name formatting.
	 */
	public function getOptionParser(): ConsoleOptionParser
	{
		[$root, $name] = explode(' ', $this->name, 2);
		$parser        = new LearnomancerConsoleOptionParser($name);
		$parser->setRootName($root);
		$parser->setDescription(static::getDescription());

		return $this->buildOptionParser($parser);
	}
}
