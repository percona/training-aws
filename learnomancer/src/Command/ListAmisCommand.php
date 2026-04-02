<?php
declare(strict_types=1);

namespace Learnomancer\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Learnomancer\AwsClient;

class ListAmisCommand extends BaseCommand
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->addArguments([
			'region' => [
				'required' => true,
				'help' => 'The region to list from'
			]
		]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        // Validate options
        $region = $args->getArgument('region') ?: $io->abort("Missing region parameter");

		$ec2 = new AwsClient($region);
		$amis = $ec2->getAmisInRegion($region);

		if (empty($amis))
        {
            $io->warning("! No training AMIs found in '{$region}'\n");
            return static::CODE_SUCCESS;
        }

		// Display
		$io->out();
		$io->out(str_repeat('-', 31) . "+" . str_repeat('-', 24));
		$io->out(sprintf("%-30s | %s", "Name", "AMI Id"));
		$io->out(str_repeat('-', 31) . "+" . str_repeat('-', 24));

        foreach ($amis as $ami)
        {
        	$name = $ami['Name'] ?: '-- None --';
			$io->out(sprintf("%-30s | %s", $name, $ami['ImageId']));
        }

		$io->out(str_repeat('-', 31) . "+" . str_repeat('-', 24));

        return static::CODE_SUCCESS;
    }
}
