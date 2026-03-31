<?php
declare(strict_types=1);

namespace Learnomancer\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Learnomancer\AwsClient;

class AnsibleConfigCommand extends BaseCommand
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

		$parser->addOption('output', [
			'short' => 'o',
			'help' => 'Save output to file',
			'boolean' => true,
		]);

        return $parser;
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $slug = $args->getArgument('slug');
        $region = $args->getArgument('region');

		$ec2 = new AwsClient($region);
		$reservations = $ec2->getInstancesBySlug($slug);

		if (empty($reservations))
        {
            print("! No instances found for '{$slug}'\n");
            return static::CODE_SUCCESS;
        }

		$frontLength = strlen("Percona-Training-{$slug}-");

		$instances = array();
		foreach ($reservations as $instance)
		{
			$teamName = substr($instance["Hostname"], $frontLength);
			if($teamName == "PMM")
				continue;

			list($machineType, $teamCounter) = explode("-", $teamName);

			$machineName = $machineType . "-" . $teamCounter;

			$instances['machinetype'][$machineType][] = $machineName;
			$instances['team'][$teamCounter][] = $machineName;
			$instances['all'][] = $machineName;
			$instances['privateip'][$machineName] = $instance['PrivateIpAddress'];
			$instances['publicip'][$machineName] = $instance['PublicIpAddress'];
		}

		// Build contents

		$contents = "[all]\n";
		foreach ($instances['all'] as $machine)
		{
			$dash = strpos($machine, "-");
			$team = substr($machine, $dash);
			$mach = substr($machine, 0, $dash);

			$contents .= $machine . "\tprivateIp=" . $instances['privateip'][$machine]
				. "\tansible_ssh_host=" . $instances['publicip'][$machine];

			if ($mach == "mysql2" || $mach == "mysql3")
				$contents .= "\tmysql_master_host=mysql1" . $team;

			if ($mach == "node2" || $mach == "node3" || $mach == "node4")
				$contents .= "\tkube_master=node1" . $team;

			$contents .= "\n";
		}
		$contents .= "\n";
		$contents .= "[all:vars]\n";
		$contents .= "ansible_ssh_user=rocky\n";
		$contents .= "ansible_become=true\n";
		$contents .= "ansible_ssh_private_key_file=Percona-Training.key\n";
		$contents .= "\n";

		foreach ($instances['machinetype'] as $key => $machines)
		{
			$contents .= "[" . $key . "]\n";
			foreach ($machines as $machine)
			{
				$contents .= $machine . "\n";
			}
			$contents .= "\n";

			$contents .= "[" . $key . ":vars]\n";
			$contents .= "machinetype=" . $key . "\n";
			$contents .= "\n";
		}

		foreach ($instances['team'] as $key => $machines)
		{
			$contents .= "[" . $key . "]\n";
			foreach ($machines as $machine)
			{
				$contents .= $machine . "\n";
			}
			$contents .= "\n";

			$contents .= "[" . $key . ":vars]\n";
			$contents .= "team=" . $key . "\n";
			$contents .= "\n";
		}

		// Output
		if ($args->getOption('output')) {
			$filename = "ansible_{$slug}";
			file_put_contents($filename, $contents);
		} else {
			print($contents);
		}

        return static::CODE_SUCCESS;
    }
}
