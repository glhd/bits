<?php

namespace Glhd\Bits\Tests\Feature;

use Glhd\Bits\Config\SnowflakesConfig;
use Glhd\Bits\Contracts\MakesSnowflakes;
use Glhd\Bits\Contracts\ResolvesSequences;
use Glhd\Bits\Database\HasSnowflakePrimaryKey;
use Glhd\Bits\Factories\SnowflakeFactory;
use Glhd\Bits\Snowflake;
use Glhd\Bits\Tests\ResolvesSequencesFromMemory;
use Glhd\Bits\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Schema;

class SnowflakeCastTest extends TestCase
{
	use ResolvesSequencesFromMemory;
	
	protected function setUp(): void
	{
		parent::setUp();
		
		Schema::create('test_models', function(Blueprint $table) {
			$table->unsignedBigInteger('id')->primary();
			$table->string('label')->nullable();
			$table->timestamps();
		});
	}
	
	public function test_it_casts_attributes(): void
	{
		$model1 = TestModel::create();
		$model2 = TestModel::create();
		
		$this->assertInstanceOf(Snowflake::class, $model1->id);
		$this->assertInstanceOf(Snowflake::class, $model2->id);
		
		$this->assertTrue($model2->id->id() > $model1->id->id());
	}
}

class TestModel extends Model
{
	use HasSnowflakePrimaryKey;
	
	protected $casts = [
		'id' => Snowflake::class,
	];
}
