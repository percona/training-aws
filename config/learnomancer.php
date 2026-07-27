<?php
declare(strict_types=1);

/**
 * die(var_dump()) no longer works in 8.4 like it did before. This
 * is a crude wrapper attempt.
 *
 * @param mixed $value
 */
function dvd(mixed $value): void
{
	var_dump($value);
	die;
}

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
