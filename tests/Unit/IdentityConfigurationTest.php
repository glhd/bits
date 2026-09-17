<?php

namespace Glhd\Bits\Tests\Unit;

use Glhd\Bits\Contracts\MakesSnowflakes;
use Glhd\Bits\Contracts\MakesSonyflakes;
use Glhd\Bits\Contracts\ResolvesIdentities;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\IdentityResolvers\CacheIdentityResolver;
use Glhd\Bits\IdentityResolvers\FixedIdentityResolver;
use Glhd\Bits\SequenceResolvers\CacheSequenceResolver;
use Glhd\Bits\SequenceResolvers\InMemorySequenceResolver;
use Glhd\Bits\Tests\TestCase;
use InvalidArgumentException;

class IdentityConfigurationTest extends TestCase
{
	public function test_it_does_not_claim_identities_while_running_tests(): void
	{
		config()->set('bits.identity.driver', 'auto');
		
		$this->assertInstanceOf(FixedIdentityResolver::class, app(ResolvesIdentities::class));
	}
	
	public function test_the_cache_driver_claims_identities(): void
	{
		config()->set('bits.identity.driver', 'cache');
		
		$resolver = app(ResolvesIdentities::class);
		
		$this->assertInstanceOf(CacheIdentityResolver::class, $resolver);
		$this->assertEquals('snowflake', $resolver->scope());
		$this->assertEquals(1024, $resolver->space()->size());
	}
	
	public function test_configuring_both_ids_pins_them_and_skips_the_cache(): void
	{
		config()->set('bits.identity.driver', 'cache');
		config()->set('bits.datacenter_id', 4);
		config()->set('bits.worker_id', 11);
		
		$resolver = app(ResolvesIdentities::class);
		
		$this->assertInstanceOf(FixedIdentityResolver::class, $resolver);
		$this->assertEquals([4, 11], $resolver->identities()->ids);
		
		$snowflake = app(MakesSnowflakes::class)->make();
		
		$this->assertEquals(4, $snowflake->datacenter_id);
		$this->assertEquals(11, $snowflake->worker_id);
	}
	
	public function test_configuring_one_id_pins_it_and_claims_the_rest(): void
	{
		config()->set('bits.identity.driver', 'cache');
		config()->set('bits.datacenter_id', 4);
		
		$resolver = app(ResolvesIdentities::class);
		
		$this->assertInstanceOf(CacheIdentityResolver::class, $resolver);
		
		// Only the 32 workers inside datacenter 4 are up for grabs
		$this->assertEquals(32, $resolver->space()->size());
		$this->assertEquals(4, $resolver->identities()->first());
	}
	
	public function test_sonyflakes_take_their_machine_id_from_the_worker_id(): void
	{
		config()->set('bits.identity.driver', 'cache');
		config()->set('bits.worker_id', 4_242);
		
		$sonyflake = app(MakesSonyflakes::class)->make();
		
		$this->assertEquals(4_242, $sonyflake->machine_id);
	}
	
	public function test_sonyflakes_claim_a_machine_id_when_none_is_configured(): void
	{
		config()->set('bits.identity.driver', 'cache');
		
		$factory = app(MakesSonyflakes::class);
		$resolver = $factory->identityResolver();
		
		$this->assertInstanceOf(CacheIdentityResolver::class, $resolver);
		$this->assertEquals('sonyflake', $resolver->scope());
		$this->assertEquals(65_536, $resolver->space()->size());
		
		$this->assertEquals($resolver->identities()->first(), $factory->make()->machine_id);
	}
	
	public function test_an_empty_configured_id_counts_as_unset(): void
	{
		config()->set('bits.identity.driver', 'cache');
		config()->set('bits.datacenter_id', '');
		config()->set('bits.worker_id', '');
		
		$this->assertInstanceOf(CacheIdentityResolver::class, app(ResolvesIdentities::class));
		
		// And the sequence resolver agrees that the identities are claimed
		$this->assertInstanceOf(InMemorySequenceResolver::class, app(ResolvesSequences::class));
	}
	
	public function test_a_configured_id_of_zero_is_honored(): void
	{
		config()->set('bits.identity.driver', 'cache');
		config()->set('bits.datacenter_id', 0);
		config()->set('bits.worker_id', 0);
		
		$resolver = app(ResolvesIdentities::class);
		
		$this->assertInstanceOf(FixedIdentityResolver::class, $resolver);
		$this->assertEquals([0, 0], $resolver->identities()->ids);
	}
	
	public function test_configured_ids_given_as_strings_are_honored(): void
	{
		config()->set('bits.identity.driver', 'cache');
		config()->set('bits.datacenter_id', '4');
		config()->set('bits.worker_id', '11');
		
		$this->assertEquals([4, 11], app(ResolvesIdentities::class)->identities()->ids);
	}
	
	public function test_the_fixed_driver_keeps_generating_ids_locally(): void
	{
		config()->set('bits.identity.driver', 'fixed');
		
		$resolver = app(ResolvesIdentities::class);
		
		$this->assertInstanceOf(FixedIdentityResolver::class, $resolver);
		$this->assertCount(2, $resolver->identities()->ids);
	}
	
	public function test_claimed_identities_get_an_in_memory_sequence(): void
	{
		config()->set('bits.identity.driver', 'cache');
		
		$this->assertInstanceOf(InMemorySequenceResolver::class, app(ResolvesSequences::class));
	}
	
	public function test_shared_identities_get_a_cached_sequence(): void
	{
		config()->set('bits.identity.driver', 'cache');
		config()->set('bits.worker_id', 7);
		
		$this->assertInstanceOf(CacheSequenceResolver::class, app(ResolvesSequences::class));
	}
	
	public function test_the_sequence_resolver_can_be_chosen_directly(): void
	{
		config()->set('bits.identity.driver', 'cache');
		config()->set('bits.sequence_resolver', 'cache');
		
		$this->assertInstanceOf(CacheSequenceResolver::class, app(ResolvesSequences::class));
		
		$this->refreshApplication();
		
		config()->set('bits.identity.driver', 'fixed');
		config()->set('bits.sequence_resolver', 'memory');
		
		$this->assertInstanceOf(InMemorySequenceResolver::class, app(ResolvesSequences::class));
	}
	
	public function test_an_unknown_driver_is_rejected(): void
	{
		config()->set('bits.identity.driver', 'nonsense');
		
		$this->assertThrows(fn() => app(ResolvesIdentities::class), InvalidArgumentException::class);
		
		$this->refreshApplication();
		
		config()->set('bits.sequence_resolver', 'nonsense');
		
		$this->assertThrows(fn() => app(ResolvesSequences::class), InvalidArgumentException::class);
	}
	
	public function test_identity_settings_are_passed_through(): void
	{
		config()->set('bits.identity.driver', 'cache');
		config()->set('bits.identity.ttl', 60);
		config()->set('bits.identity.prefix', 'custom-prefix');
		
		$resolver = app(ResolvesIdentities::class);
		
		$this->assertEquals(20, $resolver->renewalInterval());
		
		$ids = $resolver->identities();
		
		$this->assertNotNull(cache()->get('custom-prefix:snowflake:'.implode('-', $ids->ids)));
	}
}
