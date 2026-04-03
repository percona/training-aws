<?php
declare(strict_types=1);

return [

	/**
	 * The CIDR networks for certain regions in case VPCs need to be linked
	 */
	'VPC_Subnets' => [
		'DEFAULT'   => '10.11.0.0/16',
		'us-west-1' => '10.12.0.0/16',
		'us-east-1' => '10.13.0.0/16',
	],
];
