<?php
declare(strict_types=1);

namespace Learnomancer;

final class LearnomancerConfig
{
	// Basic info
	private string $configFile;
	private string $region;
	private string $slug;
	private string $keypair;

	// VPC Info
	private string $vpcId;
	private string $vpcCidrBlock;

	// Subnet Info
	private string $subnetId;
	private string $subnetCidrBlock;
	private string $subnetAz;

	// Gateway Info
	private string $gatewayId;

	// Routing Info
	private string $routeTableId;

	// Security Group Info
	private string $securityGroupId;

	public function __construct(string $slug, string $region)
	{
		$this->slug = $slug;
		$this->region = $region;
	}

	public function loadConfig(): void
	{
		// Get config file name
		$configFile = self::getConfigFile($this->slug, $this->region);

		// Return empty LearnomancerConfig if config file does not exist
		if (!file_exists($configFile))
		{
			throw new LearnomancerException("Missing config file for '{$this->slug}'. Have you created the VPC?");
		}

		// Load config file contents, and unserialize
		if (!$contents = file_get_contents($configFile))
		{
			throw new LearnomancerException("Failed to read config file '{$configFile}' contents.");
		}

		$parsed = json_decode($contents);
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

	public function getSubnetId(): string
	{
		return $this->subnetId;
	}

	public static function getConfigFile(string $slug, string $region): string
	{
		return strtolower(".config-Percona-Training-{$slug}-{$region}.cnf");
	}

	public function saveConfig(): bool
	{
		return file_put_contents($this->configFile, json_encode($this)) !== false;
	}

	public function __set(string $property, string $value): void
	{
		if (property_exists($this, $property))
		{
			$this->$property = $value;
		}
	}
}
