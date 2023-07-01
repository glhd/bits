<?php

namespace Glhd\Bits\Database;

use Glhd\Bits\Snowflake;

trait HasSnowflakeKey
{
	protected function initializeHasSnowflakeKey(): void
	{
		$this->attributes[$this->getKeyName()] = Snowflake::make()->id();
	}
}
