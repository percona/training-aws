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
        $slug = $args->getArgument('slug');
        $region = $args->getArgument('region');

		$ec2 = new AwsClient($region);
		$instances = $ec2->getInstancesBySlug($slug);

		if (empty($instances))
        {
            print("! No instances found for '{$slug}'\n");
            return static::CODE_SUCCESS;
        }

		$frontLength = strlen("Percona-Training-{$slug}-");

        foreach ($instances as $instance)
        {
            $fullName = $instance['Hostname'];
            $typeTeamName = substr($fullName, $frontLength);
            printf("%-15s %-30s %-10s\n",
                $instance['PublicIpAddress'], $fullName, $typeTeamName);
        }

        return static::CODE_SUCCESS;
    }
}
