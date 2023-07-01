<?php

namespace Glhd\Bits\Support;

use Glhd\Bits\CacheSequenceResolver;
use Glhd\Bits\Config\GenericConfiguration;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\Configuration;
use Glhd\Bits\Contracts\MakesBits;
use Glhd\Bits\Contracts\MakesSnowflakes;
use Glhd\Bits\Contracts\MakesSonyflakes;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\Factory;
use Glhd\Bits\Presets\Snowflakes;
use Glhd\Bits\Presets\Sonyflakes;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class BitsServiceProvider extends ServiceProvider
{
	public function register()
	{
		$this->mergeConfigFrom($this->packageConfigFile(), 'bits');
		
		$this->app->alias(CacheSequenceResolver::class, ResolvesSequences::class);
		$this->app->alias(Snowflakes::class, Configuration::class);
		
		$this->app->singleton(Snowflakes::class);
		$this->app->singleton(Sonyflakes::class);
		
		$this->app->singleton(MakesSnowflakes::class, function(Container $container) {
			$config = config('bits');
			$sequence = $container->make(ResolvesSequences::class);
			
			return $container->make(Snowflakes::class)->factory($config, $sequence);
		});
		
		$this->app->singleton(MakesSonyflakes::class, function(Container $container) {
			$config = config('bits');
			$sequence = $container->make(ResolvesSequences::class);
			
			return $container->make(Sonyflakes::class)->factory($config, $sequence);
		});
		
		$this->app->singleton(MakesBits::class, function(Container $container) {
			$format = config('bits.format', 'snowflake');
			
			return match($format) {
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
