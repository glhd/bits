<?php

namespace Glhd\Bits\Tests\Unit;

use Glhd\Bits\IdResolvers\CacheResolver;
use Glhd\Bits\Tests\TestCase;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\DateFactory;
use Illuminate\Support\Facades\Date;
use RuntimeException;

class CacheIdResolverTest extends TestCase
{
	public function test_it_increments_sequences(): void
	{
		Date::setTestNow(now());
		
		$store = new ArrayStore();
		
		// Should get 0b00
		$resolver = new CacheResolver($store, app(DateFactory::class), 0b11);
		$this->assertEquals(0b00, $resolver->get(1, 1)->first());
		$this->assertEquals(0b00, $resolver->get(1, 1)->second());
		
		Date::setTestNow(now()->addMinutes(10));
		
		// Should get 0b01
		$resolver = new CacheResolver($store, app(DateFactory::class), 0b11);
		$this->assertEquals(0b00, $resolver->get(1, 1)->first());
		$this->assertEquals(0b01, $resolver->get(1, 1)->second());
		
		Date::setTestNow(now()->addMinutes(10));
		
		// Should get 0b10
		$resolver = new CacheResolver($store, app(DateFactory::class), 0b11);
		$this->assertEquals(0b01, $resolver->get(1, 1)->first());
		$this->assertEquals(0b00, $resolver->get(1, 1)->second());
		
		Date::setTestNow(now()->addMinutes(10));
		
		// Should get 0b11
		$resolver = new CacheResolver($store, app(DateFactory::class), 0b11);
		$this->assertEquals(0b01, $resolver->get(1, 1)->first());
		$this->assertEquals(0b01, $resolver->get(1, 1)->second());
		
		// Now we've run out of IDs… should throw
		$this->assertThrows(function() use ($store) {
			$resolver = new CacheResolver($store, app(DateFactory::class), 0b11);
			$resolver->get(1, 1);
		}, RuntimeException::class);
		
		Date::setTestNow(now()->addMinutes(30));
		
		// 0b00 has not expired, so we should be able to acquire it
		$resolver = new CacheResolver($store, app(DateFactory::class), 0b11);
		$this->assertEquals(0b00, $resolver->get(1, 1)->first());
		$this->assertEquals(0b00, $resolver->get(1, 1)->second());
		
		// But everything else is locked, so we should throw again
		$this->assertThrows(function() use ($store) {
			$resolver = new CacheResolver($store, app(DateFactory::class), 0b11);
			$resolver->get(1, 1);
		}, RuntimeException::class);
	}
}
