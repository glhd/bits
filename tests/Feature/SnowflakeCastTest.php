<?php

namespace Glhd\Bits\Tests\Feature;

use Glhd\Bits\Database\HasSnowflakes;
use Glhd\Bits\Snowflake;
use Glhd\Bits\Tests\ResolvesSequencesFromMemory;
use Glhd\Bits\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
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
	
	public function test_you_can_set_id_manually(): void
	{
		$model = TestModel::forceCreate(['id' => 123]);
		
		$this->assertEquals(123, $model->id->id());
		
		$model = TestModel::find(123);
		
		$this->assertEquals(123, $model->id->id());
		
		$this->assertEquals(1, TestModel::count());
	}
}

class TestModel extends Model
{
	use HasSnowflakes;
	
	protected $casts = [
		'id' => Snowflake::class,
	];
}
