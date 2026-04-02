<?php
declare(strict_types=1);

namespace Learnomancer\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Learnomancer\AwsClient;

class ListInstancesCommand extends BaseCommand
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->addArguments([
			'slug' => [
				'required' => true,
				'help' => 'The slug/suffix for the class',
			],
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
        $slug = $args->getArgument('slug') ?: $io->abort("Missing slug parameter");
        $region = $args->getArgument('region') ?: $io->abort("Missing region parameter");

		$ec2 = new AwsClient($region);
		$reservations = $ec2->getInstancesBySlug($slug);

		if (empty($reservations))
        {
            $io->warning("! No instances found for '{$slug}' in '{$region}'\n");
            return static::CODE_SUCCESS;
        }

		$frontLength = strlen("Percona-Training-{$slug}-");

        foreach ($reservations as $instance)
        {
            $fullName = $instance['Hostname'];
            $typeTeamName = substr($fullName, $frontLength);
            $io->out(sprintf("%-15s %-30s %-10s", $instance['PublicIpAddress'], $fullName, $typeTeamName));
        }

        return static::CODE_SUCCESS;
    }
}
