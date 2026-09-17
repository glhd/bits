<?php

namespace Glhd\Bits\Support;

use Carbon\CarbonInterface;
use Glhd\Bits\Config\SnowflakesConfig;
use Glhd\Bits\Config\SonyflakesConfig;
use Glhd\Bits\Config\WorkerIds;
use Glhd\Bits\Contracts\Configuration;
use Glhd\Bits\Contracts\MakesBits;
use Glhd\Bits\Contracts\MakesSnowflakes;
use Glhd\Bits\Contracts\MakesSonyflakes;
use Glhd\Bits\Contracts\ResolvesIdentities;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\Factories\SnowflakeFactory;
use Glhd\Bits\Factories\SonyflakeFactory;
use Glhd\Bits\IdentityResolvers\CacheIdentityResolver;
use Glhd\Bits\IdentityResolvers\FixedIdentityResolver;
use Glhd\Bits\IdentityResolvers\IdentitySpace;
use Glhd\Bits\SequenceResolvers\CacheSequenceResolver;
use Glhd\Bits\SequenceResolvers\InMemorySequenceResolver;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
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
		
		$this->app->alias(SnowflakesConfig::class, Configuration::class);
		$this->app->alias(SnowflakeFactory::class, MakesSnowflakes::class);
		$this->app->alias(SonyflakeFactory::class, MakesSonyflakes::class);
		
		$this->app->singleton(SnowflakesConfig::class);
		$this->app->singleton(SonyflakesConfig::class);
		
		$this->app->singleton(ResolvesSequences::class, function(Container $container) {
			return $this->sequenceResolver($container);
		});
		
		$this->app->singleton(ResolvesIdentities::class, function(Container $container) {
			return $this->identityResolver($container, $container->make(SnowflakesConfig::class), 'snowflake');
		});
		
		$this->app->singleton(MakesSnowflakes::class, function(Container $container) {
			$config = $container->make(SnowflakesConfig::class);
			
			return SnowflakeFactory::withIdentities(
				epoch: $this->epoch($container),
				identities: $this->identityResolver($container, $config, 'snowflake'),
				config: $config,
				sequence: $container->make(ResolvesSequences::class),
			);
		});
		
		$this->app->singleton(MakesSonyflakes::class, function(Container $container) {
			$config = $container->make(SonyflakesConfig::class);
			
			return SonyflakeFactory::withIdentities(
				epoch: $this->epoch($container),
				identities: $this->identityResolver($container, $config, 'sonyflake'),
				config: $config,
				sequence: $container->make(ResolvesSequences::class),
			);
		});
		
		$this->app->singleton(MakesBits::class, function(Container $container) {
			$format = $container->make(Repository::class)
				->get('bits.format', 'snowflake');
			
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
	
	protected function epoch(Container $container): CarbonInterface
	{
		$settings = $container->make(Repository::class);
		
		return $container->make(DateFactory::class)
			->parse($settings->get('bits.epoch', '2023-01-01'), 'UTC')
			->startOfDay();
	}
	
	/**
	 * Build the identity resolver for a format. Every ID segment that has been given a
	 * value in config is pinned to it; anything left over is either claimed from the
	 * cache or generated locally, depending on the driver.
	 */
	protected function identityResolver(Container $container, Configuration $config, string $scope): ResolvesIdentities
	{
		$settings = $container->make(Repository::class);
		$pins = $this->pins($settings, $config);
		
		if ('cache' !== $this->identityDriver($settings) || ! in_array(null, $pins, true)) {
			return new FixedIdentityResolver($this->fixedIds($config, $pins));
		}
		
		return new CacheIdentityResolver(
			cache: $this->identityStore($container, $settings),
			space: new IdentitySpace($config, $pins),
			scope: $scope,
			ttl: (int) $settings->get('bits.identity.ttl', 300),
			prefix: (string) $settings->get('bits.identity.prefix', 'glhd-bits-id'),
			max_probes: (int) $settings->get('bits.identity.max_probes', 1024),
			attempts: (int) $settings->get('bits.identity.attempts', 3),
		);
	}
	
	/**
	 * Fixed values for each ID segment, keyed by position.
	 *
	 * Snowflakes have two ID segments, configured with `bits.datacenter_id` and
	 * `bits.worker_id`. Sonyflakes have one (the machine ID), which has always been
	 * configured with `bits.worker_id`. A configuration with any other shape has
	 * nothing these two settings could describe, so nothing is pinned.
	 *
	 * @return array<int, int|null>
	 */
	protected function pins(Repository $settings, Configuration $config): array
	{
		$segments = $config->idSegments()->count();
		
		return match ($segments) {
			1 => [$this->configuredId($settings, 'worker_id')],
			2 => [$this->configuredId($settings, 'datacenter_id'), $this->configuredId($settings, 'worker_id')],
			default => array_fill(0, $segments, null),
		};
	}
	
	/** A configured ID, treating an unset or empty env value as "not configured" */
	protected function configuredId(Repository $settings, string $key): ?int
	{
		$id = $settings->get("bits.{$key}");
		
		return null === $id || '' === $id ? null : (int) $id;
	}
	
	/** @param array<int, int|null> $pins */
	protected function fixedIds(Configuration $config, array $pins): WorkerIds
	{
		$ids = $config->idSegments()
			->map(fn($segment, $index) => $pins[$index] ?? $this->generateWorkerId($segment->maxValue()))
			->all();
		
		return new WorkerIds(...$ids);
	}
	
	/**
	 * 'auto' claims identities from the cache, except while running tests, where the
	 * cache is usually a per-process array store and every worker would be handed the
	 * same identity. Tests keep the pre-existing behavior instead.
	 */
	protected function identityDriver(Repository $settings): string
	{
		$driver = (string) ($settings->get('bits.identity.driver') ?: 'auto');
		
		if ('auto' === $driver) {
			return $this->app->runningUnitTests() ? 'fixed' : 'cache';
		}
		
		if (! in_array($driver, ['cache', 'fixed'], true)) {
			throw new InvalidArgumentException("Unknown bits identity driver: '{$driver}'");
		}
		
		return $driver;
	}
	
	protected function identityStore(Container $container, Repository $settings): CacheRepository
	{
		$store = $settings->get('bits.identity.store');
		
		return $store
			? $container->make(CacheFactory::class)->store($store)
			: $container->make(CacheRepository::class);
	}
	
	/**
	 * A process that claimed its own identity is the only one using it, so its sequence
	 * only needs to be unique within the process. Anything else falls back to a sequence
	 * that is shared across processes.
	 */
	protected function sequenceResolver(Container $container): ResolvesSequences
	{
		$settings = $container->make(Repository::class);
		$driver = (string) ($settings->get('bits.sequence_resolver') ?: 'auto');
		
		return match ($driver) {
			'memory' => new InMemorySequenceResolver(),
			'cache' => $container->make(CacheSequenceResolver::class),
			'auto' => $this->identitiesAreClaimed($settings)
				? new InMemorySequenceResolver()
				: $container->make(CacheSequenceResolver::class),
			default => throw new InvalidArgumentException("Unknown bits sequence resolver: '{$driver}'"),
		};
	}
	
	/**
	 * True only when every ID segment of every format is claimed, which is the one case
	 * where an in-memory sequence is safe no matter which format is in use. Pin one
	 * segment and Sonyflakes become fully fixed, so we stay on the shared sequence.
	 */
	protected function identitiesAreClaimed(Repository $settings): bool
	{
		return 'cache' === $this->identityDriver($settings)
			&& null === $this->configuredId($settings, 'datacenter_id')
			&& null === $this->configuredId($settings, 'worker_id');
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
