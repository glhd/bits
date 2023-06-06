<?php

namespace Glhd\Bits\Support;

use Glhd\Bits\Bits;
use Glhd\Bits\CacheSequenceResolver;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\Factory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

class BitsServiceProvider extends ServiceProvider
{
	public function register()
	{
		$this->mergeConfigFrom($this->packageConfigFile(), 'bits');
		
		$this->app->alias(CacheSequenceResolver::class, ResolvesSequences::class);
		
		$this->app->singleton(Factory::class, function(Container $container) {
			return new Factory(
				epoch: Date::parse(config('bits.epoch')),
				datacenter_id: (int) (config('bits.datacenter_id') ?? random_int(0, 31)),
				worker_id: (int) (config('bits.worker_id') ?? random_int(0, 31)),
				precision: (int) (config('bits.precision') ?? 3),
				sequence: $container->make(ResolvesSequences::class),
				bits: $container->make(Bits::class),
			);
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
