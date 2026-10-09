<?php
declare(strict_types=1);

namespace Learnomancer;

use Cake\Console\ConsoleOptionParser as CakeConsoleOptionParser;

/**
 * Preserve registered command names in help output.
 *
 * CakePHP normally converts command names to underscores for display, but
 * Learnomancer registers and accepts commands with dashes.
 */
final class ConsoleOptionParser extends CakeConsoleOptionParser
{
	/**
	 * Set the command name without changing its separators.
	 */
	public function setCommand(string $text): static
	{
		$this->_command = $text;

		return $this;
	}
}
