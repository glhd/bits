<?PHP

namespace Glhd\Bits\Support\Livewire;

use Glhd\Bits\Snowflake;
use Livewire\Mechanisms\HandleComponents\Synthesizers\Synth;

class SnowflakeSynth extends Synth
{
	public static string $key = 'snowflake';
	
	public static function match($target)
	{
		return $target instanceof Snowflake;
	}
	
	/** @var Snowflake $target */
	public function dehydrate($target)
	{
		return [$target->jsonSerialize(), []];
	}
	
	public function hydrate($value)
	{
		return Snowflake::fromId($value);
	}
}
