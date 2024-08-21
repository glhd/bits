<?php

namespace Glhd\Bits\Support;

use Glhd\Bits\Config\SnowflakesConfig;
use Glhd\Bits\Config\SonyflakesConfig;
use Glhd\Bits\Contracts\Configuration;
use Glhd\Bits\Contracts\MakesBits;
use Glhd\Bits\Contracts\MakesSnowflakes;
use Glhd\Bits\Contracts\MakesSonyflakes;
use Glhd\Bits\Contracts\ResolvesWorkerIds;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\Factories\SnowflakeFactory;
use Glhd\Bits\Factories\SonyflakeFactory;
use Glhd\Bits\IdResolvers\CacheLockWorkerIdResolver;
use Glhd\Bits\IdResolvers\StaticWorkerIdResolver;
use Glhd\Bits\SequenceResolvers\CacheSequenceResolver;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\DateFactory;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class BitsServiceProvider extends ServiceProvider
{
	public function register()
	{
		$this->mergeConfigFrom($this->packageConfigFile(), 'bits');
		
		$this->app->alias(CacheSequenceResolver::class, ResolvesSequences::class);
		$this->app->alias(SnowflakesConfig::class, Configuration::class);
		$this->app->alias(SnowflakeFactory::class, MakesSnowflakes::class);
		$this->app->alias(SonyflakeFactory::class, MakesSonyflakes::class);
		
		$this->app->singleton(SnowflakesConfig::class);
		$this->app->singleton(SonyflakesConfig::class);
		
		$this->app->singleton(CacheLockWorkerIdResolver::class, function(Container $container) {
			$config = $container->make(Repository::class);
			$cache = $container->make(CacheManager::class)->store();
			$dates = $container->make(DateFactory::class);
			
			$format = $config->get('bits.format', 'snowflake');
			
			return match ($format) {
				'snowflake', 'snowflakes' => new CacheLockWorkerIdResolver($cache, $dates, 0b1111111111),
				'sonyflake', 'sonyflakes' => new CacheLockWorkerIdResolver($cache, $dates, 0b1111111111111111),
				default => value(fn() => throw new InvalidArgumentException("Unknown bits format: '{$format}'")),
			};
		});
		
		$this->app->singleton(StaticWorkerIdResolver::class, function(Container $container) {
			$config = $container->make(Repository::class);
			$format = $config->get('bits.format', 'snowflake');
			
			return match ($format) {
				'snowflake', 'snowflakes' => new StaticWorkerIdResolver(
					$config->get('bits.datacenter_id') ?? random_int(0, 0b11111),
					$config->get('bits.worker_id') ?? $this->generateWorkerId(0b11111)
				),
				'sonyflake', 'sonyflakes' => new StaticWorkerIdResolver(
					$config->get('bits.worker_id') ?? $this->generateWorkerId(0b1111111111111111)
				),
				default => value(fn() => throw new InvalidArgumentException("Unknown bits format: '{$format}'")),
			};
		});
		
		$this->app->singleton(ResolvesWorkerIds::class, function(Container $container) {
			$config = $container->make(Repository::class);
			
			$lambda = match ($config->get('bits.lambda', 'autodetect')) {
				true, 1, '1' => true,
				'autodetect' => ! empty(getenv('AWS_LAMBDA_FUNCTION_NAME')),
				default => false,
			};
			
			return $lambda
				? $container->make(CacheLockWorkerIdResolver::class)
				: $container->make(StaticWorkerIdResolver::class);
		});
		
		$this->app->singleton(MakesSnowflakes::class, function(Container $container) {
			$config = $container->make(Repository::class);
			$dates = $container->make(DateFactory::class);
			$ids = $container->make(ResolvesWorkerIds::class);
			
			return new SnowflakeFactory(
				epoch: $dates->parse($config->get('bits.epoch', '2023-01-01'), 'UTC')->startOfDay(),
				datacenter_id: $ids->get(5, 5)->first(),
				worker_id: $ids->get(5, 5)->second(),
				config: $container->make(SnowflakesConfig::class),
				sequence: $container->make(ResolvesSequences::class),
			);
		});
		
		$this->app->singleton(MakesSonyflakes::class, function(Container $container) {
			$config = $container->make(Repository::class);
			$dates = $container->make(DateFactory::class);
			$ids = $container->make(ResolvesWorkerIds::class);
			
			return new SonyflakeFactory(
				epoch: $dates->parse($config->get('bits.epoch', '2023-01-01'), 'UTC')->startOfDay(),
				machine_id: $ids->get(16)->first(),
				config: $container->make(SonyflakesConfig::class),
				sequence: $container->make(ResolvesSequences::class),
			);
		});
		
		$this->app->singleton(MakesBits::class, function(Container $container) {
			$format = $container->make(Repository::class)->get('bits.format', 'snowflake');
			
			return match ($format) {
				'snowflake', 'snowflakes' => $container->make(MakesSnowflakes::class),
				'sonyflake', 'sonyflakes' => $container->make(MakesSonyflakes::class),
				default => value(fn() => throw new InvalidArgumentException("Unknown bits format: '{$format}'")),
			};
		});
	}
	
	public function boot()
	{
		require_once __DIR__.'/helpers.php';
		
		$this->bootConfig();
		
		Blueprint::macro('snowflake', function(string $column) {
			return $this->unsignedBigInteger($column);
		});
		
		Blueprint::macro('snowflakeId', function(string $column = 'id') {
			return $this->unsignedBigInteger($column)->primary();
		});
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
	
	protected function generateWorkerId(int $max): int
	{
		$token = $this->app->runningUnitTests() ? ParallelTesting::token() : null;
		
		if (is_numeric($token) && (int) $token <= $max) {
			return (int) $token;
		}
		
		return random_int(0, $max);
	}
}
