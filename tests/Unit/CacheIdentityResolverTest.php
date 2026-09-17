<?php

namespace Glhd\Bits\Tests\Unit;

use Glhd\Bits\Config\SnowflakesConfig;
use Glhd\Bits\Exceptions\IdentitySpaceExhausted;
use Glhd\Bits\IdentityResolvers\CacheIdentityResolver;
use Glhd\Bits\IdentityResolvers\IdentitySpace;
use Glhd\Bits\Tests\CountingArrayStore;
use Glhd\Bits\Tests\TestCase;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;

class CacheIdentityResolverTest extends TestCase
{
	protected CountingArrayStore $store;
	
	protected Repository $cache;
	
	protected function setUp(): void
	{
		parent::setUp();
		
		// The test suite fakes Sleep, which needs a test "now" to advance
		Date::setTestNow(now());
		
		$this->store = new CountingArrayStore();
		$this->cache = new Repository($this->store);
	}
	
	public function test_it_claims_an_identity_from_the_space(): void
	{
		$resolver = $this->resolver();
		
		$ids = $resolver->identities();
		
		$this->assertCount(2, $ids->ids);
		$this->assertGreaterThanOrEqual(0, $ids->first());
		$this->assertLessThanOrEqual(31, $ids->first());
		$this->assertGreaterThanOrEqual(0, $ids->second());
		$this->assertLessThanOrEqual(31, $ids->second());
	}
	
	public function test_it_holds_the_same_identity_for_the_life_of_the_process(): void
	{
		$resolver = $this->resolver();
		
		$first = $resolver->identities();
		
		for ($i = 0; $i < 25; $i++) {
			$this->assertEquals($first->ids, $resolver->identities()->ids);
		}
		
		// One probe to claim, and nothing after that
		$this->assertEquals(1, $this->store->adds);
	}
	
	public function test_it_writes_and_releases_a_readable_cache_key(): void
	{
		$resolver = $this->resolver();
		
		$ids = $resolver->identities();
		$key = 'glhd-bits-id:snowflake:'.implode('-', $ids->ids);
		
		$this->assertNotNull($this->cache->get($key));
		
		$resolver->release();
		
		$this->assertNull($this->cache->get($key));
		$this->assertNull($resolver->claimed());
	}
	
	public function test_it_does_not_release_an_identity_another_process_took_over(): void
	{
		$resolver = $this->resolver();
		
		$ids = $resolver->identities();
		$key = 'glhd-bits-id:snowflake:'.implode('-', $ids->ids);
		
		$this->cache->put($key, 'some-other-process', 300);
		
		$resolver->release();
		
		$this->assertEquals('some-other-process', $this->cache->get($key));
	}
	
	public function test_it_does_not_talk_to_the_cache_again_until_a_third_of_the_ttl_has_passed(): void
	{
		$time = 1_000.0;
		
		$resolver = $this->resolver(ttl: 300)->clockUsing(function() use (&$time) {
			return $time;
		});
		
		$this->assertEquals(100, $resolver->renewalInterval());
		
		$resolver->identities();
		
		$this->store->resetCounts();
		
		// Anything up to the renewal interval is answered from memory
		foreach ([1, 50, 99] as $elapsed) {
			$time = 1_000.0 + $elapsed;
			
			$resolver->identities();
		}
		
		$this->assertEquals(0, $this->store->adds);
		$this->assertEquals(0, $this->store->gets);
		$this->assertEquals(0, $this->store->puts);
	}
	
	public function test_it_renews_its_claim_once_a_third_of_the_ttl_has_passed(): void
	{
		$time = 1_000.0;
		
		$resolver = $this->resolver(ttl: 30)->clockUsing(function() use (&$time) {
			return $time;
		});
		
		$first = $resolver->identities();
		$key = $this->keyFor($resolver);
		$owner = $this->cache->get($key);
		
		$this->store->resetCounts();
		
		$time += 11;
		
		$this->assertEquals($first->ids, $resolver->identities()->ids);
		
		// Same identity, same owner: the claim was renewed rather than re-taken
		$this->assertEquals($owner, $this->cache->get($key));
		$this->assertEquals(0, $this->store->adds);
		$this->assertEquals(1, $this->store->puts);
	}
	
	public function test_it_claims_a_new_identity_when_it_loses_the_one_it_had(): void
	{
		$time = 1_000.0;
		
		$resolver = $this->resolver(ttl: 30)->clockUsing(function() use (&$time) {
			return $time;
		});
		
		$first = $resolver->identities();
		
		// Another process takes it over while we were idle
		$this->cache->put($this->keyFor($resolver), 'some-other-process', 300);
		
		$time += 11;
		
		$second = $resolver->identities();
		
		$this->assertNotEquals($first->ids, $second->ids);
		$this->assertEquals('some-other-process', $this->cache->get('glhd-bits-id:snowflake:'.implode('-', $first->ids)));
	}
	
	public function test_it_only_claims_within_a_pinned_datacenter(): void
	{
		for ($i = 0; $i < 32; $i++) {
			CacheIdentityResolver::forgetClaims();
			
			$ids = $this->resolver(pins: ['datacenter' => 17])->identities();
			
			$this->assertEquals(17, $ids->first());
			$claimed[$ids->second()] = true;
		}
		
		// All 32 workers in datacenter 17, and nothing outside of it
		$this->assertCount(32, $claimed);
		
		CacheIdentityResolver::forgetClaims();
		
		$this->assertThrows(
			fn() => $this->resolver(pins: ['datacenter' => 17])->identities(),
			IdentitySpaceExhausted::class
		);
		
		// A different datacenter is untouched
		CacheIdentityResolver::forgetClaims();
		$this->assertEquals(18, $this->resolver(pins: ['datacenter' => 18])->identities()->first());
	}
	
	public function test_it_throws_once_every_identity_is_taken(): void
	{
		$resolver = $this->resolver(pins: ['datacenter' => 1, 'worker' => 1]);
		
		$this->assertEquals([1, 1], $resolver->identities()->ids);
		
		CacheIdentityResolver::forgetClaims();
		
		$this->assertThrows(
			fn() => $this->resolver(pins: ['datacenter' => 1, 'worker' => 1])->identities(),
			IdentitySpaceExhausted::class
		);
	}
	
	public function test_it_reports_the_space_and_probe_count_when_it_gives_up(): void
	{
		$resolver = $this->resolver(pins: ['datacenter' => 1, 'worker' => 1], attempts: 2);
		$resolver->identities();
		
		CacheIdentityResolver::forgetClaims();
		
		try {
			$this->resolver(pins: ['datacenter' => 1, 'worker' => 1], attempts: 2)->identities();
			$this->fail('Expected the resolver to give up.');
		} catch (IdentitySpaceExhausted $exception) {
			$this->assertStringContainsString('snowflake', $exception->getMessage());
			$this->assertStringContainsString('Probed 2 of 1 available', $exception->getMessage());
		}
	}
	
	public function test_it_stops_probing_at_the_configured_limit(): void
	{
		// Fill the whole space so that every probe fails
		for ($i = 0; $i < 1024; $i++) {
			CacheIdentityResolver::forgetClaims();
			$this->resolver()->identities();
		}
		
		CacheIdentityResolver::forgetClaims();
		$this->store->resetCounts();
		
		$this->assertThrows(
			fn() => $this->resolver(max_probes: 10, attempts: 3)->identities(),
			IdentitySpaceExhausted::class
		);
		
		$this->assertEquals(30, $this->store->adds);
	}
	
	public function test_it_requires_an_expiration(): void
	{
		$this->assertThrows(fn() => $this->resolver(ttl: 0), InvalidArgumentException::class);
		$this->assertThrows(fn() => $this->resolver(max_probes: 0), InvalidArgumentException::class);
		$this->assertThrows(fn() => $this->resolver(attempts: 0), InvalidArgumentException::class);
	}
	
	public function test_it_names_its_scope_after_the_space_when_none_is_given(): void
	{
		$resolver = new CacheIdentityResolver(
			cache: $this->cache,
			space: new IdentitySpace(new SnowflakesConfig()),
		);
		
		$this->assertEquals('datacenter:5,worker:5', $resolver->scope());
	}
	
	protected function resolver(
		array $pins = [],
		int $ttl = 300,
		int $max_probes = 1024,
		int $attempts = 3,
	): CacheIdentityResolver {
		return new CacheIdentityResolver(
			cache: $this->cache,
			space: new IdentitySpace(new SnowflakesConfig(), $pins),
			scope: 'snowflake',
			ttl: $ttl,
			max_probes: $max_probes,
			attempts: $attempts,
		);
	}
	
	protected function keyFor(CacheIdentityResolver $resolver): string
	{
		return 'glhd-bits-id:snowflake:'.implode('-', $resolver->claimed()->ids);
	}
}
