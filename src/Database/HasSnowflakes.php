<?php

namespace Glhd\Bits\Database;

use Glhd\Bits\Snowflake;

trait HasSnowflakes
{
	protected function initializeHasSnowflakes(): void
	{
		if (property_exists($this, 'usesUniqueIds')) {
			$this->usesUniqueIds = true;
			return;
		}
		
		$this->setAttribute($this->getKeyName(), $this->newUniqueId());
	}
	
	public function uniqueIds()
	{
		return [$this->getKeyName()];
	}
	
	public function newUniqueId()
	{
		return Snowflake::make()->id();
	}
	
	public function getIncrementing()
	{
		if (in_array($this->getKeyName(), $this->uniqueIds())) {
			return false;
		}
		
		return $this->incrementing;
	}
}
