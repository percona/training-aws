<?php
declare(strict_types=1);

namespace Learnomancer;

final class LearnomancerConfig
{
	// Basic info
	public string $region = '';
	public string $slug   = '';

	// VPC Info
	public string $vpcId        = '';
	public string $vpcCidrBlock = '';

	// Subnet Info
	public string $subnetId = '';

	// Gateway Info
	public string $gatewayId = '';

	// Routing Info
	public string $routeTableId  = '';
	public string $associationId = '';

	// Security Group Info
	public string $securityGroupId = '';

	/**
	 * Create a config object
	 */
	public function __construct(string $slug, string $region)
	{
		$this->slug   = $slug;
		$this->region = $region;
	}

	/**
	 * Load the config file, and parse the contents
	 *
	 * @throws \Learnomancer\LearnomancerException
	 */
	public function load(bool $missingOk = false): void
	{
		// Get config file name
		$configFile = self::getConfigFile($this->slug, $this->region);

		// Return empty LearnomancerConfig if config file does not exist
		if (!file_exists($configFile))
		{
			// When adding a new VPC, or rebuilding a config, there is nothing to parse
			if ($missingOk)
			{
				return;
			}

			throw new LearnomancerException("Missing config file for '{$this->slug}'. Have you created the VPC?");
		}

		// Load config file contents, and unserialize
		if (!$contents = file_get_contents($configFile))
		{
			throw new LearnomancerException("Failed to read config file '{$configFile}' contents.");
		}

		$parsed = json_decode($contents, associative: true);
		if (!is_array($parsed))
		{
			throw new LearnomancerException("Failed to parse config file '{$configFile}' contents.");
		}

		// Set member variables based on JSON object
		foreach ($parsed as $key => $val)
		{
			$this->$key = $val;
		}
	}

	/**
	 * Construct the config filename
	 */
	private static function getConfigFile(string $slug, string $region): string
	{
		return strtolower(".config-Percona-Training-{$slug}-{$region}.cnf");
	}

	/**
	 * Encode, and persist the config to disk
	 */
	public function save(bool $saveEmpty = false): bool
	{
		$configFile = self::getConfigFile($this->slug, $this->region);

		$encoded = $saveEmpty ? '{}' : json_encode($this);

		return file_put_contents($configFile, $encoded) !== false;
	}

	/**
	 * Magic function to set private members
	 */
	public function __set(string $property, string $value): void
	{
		if (property_exists($this, $property))
		{
			$this->$property = $value;
		}
	}
}
