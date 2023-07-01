<?php

namespace Glhd\Bits\Support;

use Glhd\Bits\CacheSequenceResolver;
use Glhd\Bits\Contracts\Configuration;
use Glhd\Bits\Contracts\MakesBits;
use Glhd\Bits\Contracts\MakesSnowflakes;
use Glhd\Bits\Contracts\MakesSonyflakes;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\Factories\SnowflakeFactory;
use Glhd\Bits\Factories\SonyflakeFactory;
use Glhd\Bits\Presets\Snowflakes as SnowflakesPreset;
use Glhd\Bits\Presets\Sonyflakes as SonyflakesPreset;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\DateFactory;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class BitsServiceProvider extends ServiceProvider
{
	public function register()
	{
		$this->mergeConfigFrom($this->packageConfigFile(), 'bits');
		
		$this->app->alias(CacheSequenceResolver::class, ResolvesSequences::class);
		$this->app->alias(SnowflakesPreset::class, Configuration::class);
		
		$this->app->singleton(SnowflakesPreset::class);
		$this->app->singleton(SonyflakesPreset::class);
		
		$this->app->singleton(MakesSnowflakes::class, function(Container $container) {
			$config = $container->make(Repository::class);
			$dates = $container->make(DateFactory::class);
			
			new SnowflakeFactory(
				epoch: $dates->parse($config->get('bits.epoch', '2023-01-01'), 'UTC')->startOfDay(),
				datacenter_id: $config->get('bits.datacenter_id') ?? random_int(0, 31),
				worker_id: $config->get('bits.worker_id') ?? random_int(0, 31),
				config: $container->make(SnowflakesPreset::class),
				sequence: $config->make(ResolvesSequences::class),
			);
		});
		
		$this->app->singleton(MakesSonyflakes::class, function(Container $container) {
			$config = $container->make(Repository::class);
			$dates = $container->make(DateFactory::class);
			
			new SonyflakeFactory(
				epoch: $dates->parse($config->get('bits.epoch', '2023-01-01'), 'UTC')->startOfDay(),
				machine_id: $config->get('bits.worker_id') ?? random_int(0, 65535),
				config: $container->make(SonyflakesPreset::class),
				sequence: $config->make(ResolvesSequences::class),
			);
		});
		
		$this->app->singleton(MakesBits::class, function(Container $container) {
			$format = $container->make(Repository::class)
				->get('bits.format', 'snowflake');
			
			return match ($format) {
				'snowflake', 'snowflakes' => $container->make(MakesSnowflakes::class),
				'sonyflake', 'sonyflakes' => $container->make(MakesSonyflakes::class),
				default => throw new InvalidArgumentException("Unknown bits format: '{$format}'"),
			};
		});
	}
	
	public function boot()
	{
		require_once __DIR__.'/helpers.php';
		
		$this->bootConfig();
	}
	
	protected function bootConfig(): self
	{
		$this->publishes([
			$this->packageConfigFile() => $this->app->configPath('bits.php'),
		], 'bits-config');
		
		return $this;
	}
	
	protected function packageConfigFile(): string
	{
		return dirname(__DIR__, 2).'/config/bits.php';
	}
}
