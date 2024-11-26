<?php

namespace Glhd\Bits\Tests\Feature;

use Glhd\Bits\Database\HasSnowflakes;
use Glhd\Bits\Sonyflake;
use Glhd\Bits\Tests\ResolvesSequencesFromMemory;
use Glhd\Bits\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\DataProvider;

class SonyflakeCastTest extends TestCase
{
	use ResolvesSequencesFromMemory;

	protected function setUp(): void
	{
		parent::setUp();
		
		Schema::create('test_sonyflake_models', function(Blueprint $table) {
			$table->unsignedBigInteger('id')->primary();
			$table->string('label')->nullable();
			$table->timestamps();
		});

		Schema::create('test_sonyflake_casts_method_models', function(Blueprint $table) {
			$table->unsignedBigInteger('id')->primary();
			$table->string('label')->nullable();
			$table->timestamps();
		});

		Schema::create('test_sonyflake_automatic_casts_models', function(Blueprint $table) {
			$table->unsignedBigInteger('id')->primary();
			$table->string('label')->nullable();
			$table->timestamps();
		});
	}

	/** @param class-string<Model> $class */
	#[DataProvider('modelClassProvider')]
	#[DefineEnvironment('usesSonyflakes')]
	public function test_it_casts_attributes(string $class): void
	{
		$model1 = $class::create();
		$model2 = $class::create();
		
		$this->assertInstanceOf(Sonyflake::class, $model1->id);
		$this->assertInstanceOf(Sonyflake::class, $model2->id);
		
		$this->assertTrue($model2->id->id() > $model1->id->id());
	}

	/** @param class-string<Model> $class */
	#[DataProvider('modelClassProvider')]
	#[DefineEnvironment('usesSonyflakes')]
	public function test_you_can_set_id_manually(string $class): void
	{
		$model = $class::forceCreate(['id' => 123]);
		
		$this->assertEquals(123, $model->id->id());
		
		$model = $class::find(123);
		
		$this->assertEquals(123, $model->id->id());
		
		$this->assertEquals(1, $class::count());
	}

	public static function modelClassProvider(): array
	{
		$with_casts_method = (int) Application::VERSION >= 11;

		return [
			'casts property' => [TestSonyflakeModel::class],
			'casts method' => $with_casts_method ? [TestSonyflakeCastsMethodModel::class] : null,
			'automatic casting' => [TestSonyflakeAutomaticCastsModel::class],
		];
	}
}

class TestSonyflakeModel extends Model
{
	use HasSnowflakes;

	protected $casts = [
		'id' => Sonyflake::class,
	];
}

class TestSonyflakeCastsMethodModel extends Model
{
	use HasSnowflakes;

	protected function casts()
	{
		return [
			'id' => Sonyflake::class,
		];
	}
}

class TestSonyflakeAutomaticCastsModel extends Model
{
	use HasSnowflakes;
}
