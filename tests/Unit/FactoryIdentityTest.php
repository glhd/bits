<?php

namespace Glhd\Bits\Tests\Unit;

use Glhd\Bits\Config\GenericConfig;
use Glhd\Bits\Config\Segment;
use Glhd\Bits\Config\SnowflakesConfig;
use Glhd\Bits\Config\SonyflakesConfig;
use Glhd\Bits\Contracts\ResolvesIdentities;
use Glhd\Bits\Factories\GenericFactory;
use Glhd\Bits\Factories\SnowflakeFactory;
use Glhd\Bits\Factories\SonyflakeFactory;
use Glhd\Bits\IdentityResolvers\CacheIdentityResolver;
use Glhd\Bits\IdentityResolvers\FixedIdentityResolver;
use Glhd\Bits\IdentityResolvers\IdentityResolver;
use Glhd\Bits\IdentityResolvers\IdentitySpace;
use Glhd\Bits\SequenceResolvers\InMemorySequenceResolver;
use Glhd\Bits\Tests\CountingArrayStore;
use Glhd\Bits\Tests\TestCase;
use Illuminate\Cache\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

class FactoryIdentityTest extends TestCase
{
	protected CountingArrayStore $store;
	
	protected Repository $cache;
	
	protected function setUp(): void
	{
		parent::setUp();
		
		Date::setTestNow(now());
		
		$this->store = new CountingArrayStore();
		$this->cache = new Repository($this->store);
	}
	
	public function test_a_snowflake_factory_stamps_the_claimed_identity_into_its_ids(): void
	{
		$resolver = $this->cacheResolver();
		$factory = $this->snowflakeFactory($resolver);
		
		$ids = $resolver->identities();
		$snowflake = $factory->make();
		
		$this->assertEquals($ids->first(), $snowflake->datacenter_id);
		$this->assertEquals($ids->second(), $snowflake->worker_id);
		
		// And the property accessors still line up with what was generated
		$this->assertEquals($snowflake->datacenter_id, $factory->datacenter_id);
		$this->assertEquals($snowflake->worker_id, $factory->worker_id);
		$this->assertEquals($snowflake->datacenter_id, $factory->datacenterId());
		$this->assertEquals($snowflake->worker_id, $factory->workerId());
	}
	
	public function test_a_sonyflake_factory_claims_its_machine_id(): void
	{
		$resolver = new CacheIdentityResolver(
			cache: $this->cache,
			space: new IdentitySpace(new SonyflakesConfig()),
			scope: 'sonyflake',
		);
		
		$factory = SonyflakeFactory::withIdentities(
			epoch: now(),
			identities: $resolver,
			config: new SonyflakesConfig(),
			sequence: new InMemorySequenceResolver(),
		);
		
		$sonyflake = $factory->make();
		
		$this->assertEquals($resolver->identities()->first(), $sonyflake->machine_id);
		$this->assertEquals($sonyflake->machine_id, $factory->machine_id);
		$this->assertEquals($sonyflake->machine_id, $factory->machineId());
	}
	
	public function test_a_generic_factory_claims_every_id_segment(): void
	{
		$config = new GenericConfig(
			precision: 3,
			unit: 1,
			segments: new Collection([
				Segment::timestamp('timestamp', 41),
				Segment::id('region', 3),
				Segment::id('worker', 8),
				Segment::sequence('sequence', 12),
			]),
		);
		
		$resolver = new CacheIdentityResolver(
			cache: $this->cache,
			space: new IdentitySpace($config),
			scope: 'custom',
		);
		
		$factory = GenericFactory::withIdentities(
			epoch: now(),
			identities: $resolver,
			config: $config,
			sequence: new InMemorySequenceResolver(),
		);
		
		$bits = $factory->make();
		$ids = $resolver->identities();
		
		$this->assertEquals($ids->ids, [$bits->values[1], $bits->values[2]]);
		$this->assertEquals($ids->ids, $factory->ids()->ids);
		$this->assertEquals($ids->ids, $factory->ids->ids);
		
		// 3 + 8 bits of ID space
		$this->assertEquals(2_048, $resolver->space()->size());
	}
	
	public function test_generating_many_ids_costs_one_claim(): void
	{
		$resolver = $this->cacheResolver();
		$factory = $this->snowflakeFactory($resolver);
		
		$first = $factory->make();
		
		$this->store->resetCounts();
		
		for ($i = 0; $i < 5_000; $i++) {
			$snowflake = $factory->make();
			
			$this->assertEquals($first->datacenter_id, $snowflake->datacenter_id);
			$this->assertEquals($first->worker_id, $snowflake->worker_id);
		}
		
		// Nothing goes to the cache between renewals
		$this->assertEquals(0, $this->store->adds);
		$this->assertEquals(0, $this->store->gets);
		$this->assertEquals(0, $this->store->puts);
		
		// And the same WorkerIds instance comes back each time, so the config is not
		// re-validated on every ID
		$this->assertSame($resolver->identities(), $resolver->identities());
		$this->assertSame($factory->workerIds(), $factory->workerIds());
	}
	
	public function test_nothing_is_claimed_until_an_id_is_generated(): void
	{
		$factory = $this->snowflakeFactory($this->cacheResolver());
		
		$this->assertEquals(0, $this->store->adds);
		
		// None of these need to know who this process is
		$factory->fromId(1537200202186752);
		$factory->coerce('1537200202186752');
		$factory->firstForTimestamp(now()->addMinute());
		
		$this->assertEquals(0, $this->store->adds);
		
		$factory->make();
		
		$this->assertEquals(1, $this->store->adds);
	}
	
	public function test_a_factory_keeps_generating_after_its_claim_is_taken_over(): void
	{
		$time = 1_000.0;
		
		$resolver = $this->cacheResolver(ttl: 30)->clockUsing(function() use (&$time) {
			return $time;
		});
		
		$factory = $this->snowflakeFactory($resolver);
		
		$before = $factory->make();
		
		// Something else grabs our identity while we are idle
		$key = 'glhd-bits-id:snowflake:'.$before->datacenter_id.'-'.$before->worker_id;
		$this->cache->put($key, 'some-other-process', 300);
		
		$time += 11;
		
		$after = $factory->make();
		
		$this->assertNotEquals(
			[$before->datacenter_id, $before->worker_id],
			[$after->datacenter_id, $after->worker_id],
		);
	}
	
	public function test_fixed_ids_keep_working_through_the_old_constructor(): void
	{
		$factory = new SnowflakeFactory(
			epoch: now(),
			datacenter_id: 3,
			worker_id: 17,
			config: new SnowflakesConfig(),
			sequence: new InMemorySequenceResolver(),
		);
		
		$snowflake = $factory->make();
		
		$this->assertEquals(3, $snowflake->datacenter_id);
		$this->assertEquals(17, $snowflake->worker_id);
		$this->assertEquals(3, $factory->datacenter_id);
		$this->assertEquals(17, $factory->worker_id);
		$this->assertInstanceOf(FixedIdentityResolver::class, $factory->identityResolver());
		
		// Nothing was written to any cache
		$this->assertEquals(0, $this->store->adds);
	}
	
	public function test_a_fixed_datacenter_id_still_requires_a_worker_id(): void
	{
		$this->assertThrows(
			fn() => new SnowflakeFactory(now(), 3, config: new SnowflakesConfig()),
			\InvalidArgumentException::class
		);
	}
	
	public function test_a_factory_validates_what_its_resolver_returns(): void
	{
		$factory = $this->snowflakeFactory(FixedIdentityResolver::make(0, 32));
		
		$this->assertThrows(fn() => $factory->make(), \InvalidArgumentException::class);
	}
	
	public function test_a_custom_resolver_only_has_to_produce_an_index(): void
	{
		$resolver = new class(new IdentitySpace(new SnowflakesConfig())) extends IdentityResolver {
			protected function value(): int
			{
				return 33;
			}
		};
		
		$factory = $this->snowflakeFactory($resolver);
		$snowflake = $factory->make();
		
		$this->assertEquals(1, $snowflake->datacenter_id);
		$this->assertEquals(1, $snowflake->worker_id);
	}
	
	public function test_unknown_properties_still_report_as_undefined(): void
	{
		$factory = $this->snowflakeFactory(FixedIdentityResolver::make(1, 2));
		
		$this->assertTrue(isset($factory->datacenter_id));
		$this->assertTrue(isset($factory->worker_id));
		$this->assertFalse(isset($factory->nonsense));
		
		$this->assertThrows(fn() => $factory->nonsense, \OutOfBoundsException::class);
	}
	
	protected function cacheResolver(int $ttl = 300): CacheIdentityResolver
	{
		return new CacheIdentityResolver(
			cache: $this->cache,
			space: new IdentitySpace(new SnowflakesConfig()),
			scope: 'snowflake',
			ttl: $ttl,
		);
	}
	
	protected function snowflakeFactory(ResolvesIdentities $identities): SnowflakeFactory
	{
		return SnowflakeFactory::withIdentities(
			epoch: now(),
			identities: $identities,
			config: new SnowflakesConfig(),
			sequence: new InMemorySequenceResolver(),
		);
	}
}
