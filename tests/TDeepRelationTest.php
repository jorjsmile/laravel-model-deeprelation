<?php

/**
 * Created by PhpStorm.
 * User: george
 * Date: 10/25/17
 * Time: 9:10 PM
 *
 *
 */
class TDeepRelationTest extends \Orchestra\Testbench\TestCase
{

    /**
     * It will also cover tests for deepSetRelation
     */
    public function testDeepFill()
    {
        //fill usually without relations at all
        $testOrm = new TestORM();
        $data = [
            "name" => "Test",
            "date" => "Date",
            "belongs_id" => 1
        ];
        $testOrm->deepFill($data);
        $this->assertEquals($data, $testOrm->getAttributes());

        //fill with belongsTo
        \Illuminate\Database\Eloquent\Model::$snakeAttributes = false;
        $testOrm = new TestORM();
        $data = [
            "name" => "Test",
            "date" => "Date",
            "relationBelongsTo" => [
                "name" => "Belongs To Name"
            ]
        ];

        $testOrm->deepFill($data);
        $this->assertEquals($data, $testOrm->toArray());

        //fill with hasOne
        $testOrm = new TestORM();
        $data = [
            "name" => "Test",
            "date" => "Date",
            "relationHasOne" => [
                "name" => "Belongs To Name"
            ]
        ];

        $testOrm->deepFill($data);
        $this->assertEquals($data, $testOrm->toArray());

        //fill with hasMany
        $testOrm = new TestORM();
        $data = [
            "name" => "Test",
            "date" => "Date",
            "relationHasMany" => [
                [
                    "name" => "Has Many 1"
                ],
                [
                    "name" => "Has Many 2"
                ],
            ]
        ];

        $testOrm->deepFill($data);
        $this->assertEquals($data, $testOrm->toArray());


        //fill with hasMany - short assign
        $testOrm = new TestORM();
        $data = [
            "name" => "Test",
            "date" => "Date",
            "relationHasMany" => [
                1,2 //only ids
            ]
        ];

        $testOrm->deepFill($data);
        $this->assertArraySubset([ "relationHasMany" => [ ["name" => "TestHasOneMany::`1`"] ] ], $testOrm->toArray());

        //fill with BelongsToMany
        $testOrm = new TestORM();
        $data = [
            "name" => "Test",
            "date" => "Date",
            "relationManyMany" => [
                [
                    "name" => "Many Many 1"
                ],
                [
                    "name" => "Many Many 2"
                ],
            ]
        ];

        $testOrm->deepFill($data);
        $this->assertEquals($data, $testOrm->toArray());


        //fill with hasMany - short assign
        $testOrm = new TestORM();
        $data = [
            "name" => "Test",
            "date" => "Date",
            "relationManyMany" => [
                1,2 //only ids
            ]
        ];

        $testOrm->deepFill($data);
        $this->assertArraySubset([ "relationManyMany" => [ ["name" => "TestManyMany::`1`"] ] ], $testOrm->toArray());

        //check also deepFillable
        $testOrm = new TestORM();

        $testOrm->deepFillable=["relationHasOne"=>true, "relationHasMany"=>true];
        $data = [
            "name" => "Test",
            "date" => "Date",
            "relationBelongsTo" => [
                "name" => "Belongs To Name"
            ],
            "relationHasOne" => [
                "name" => "Belongs To Name"
            ],
            "relationHasMany" => [
                [
                    "name" => "Has Many 1"
                ],
                [
                    "name" => "Has Many 2"
                ],
            ]
        ];

        $testOrm->deepFill($data);
        unset($data["relationBelongsTo"]); //it should not be populated according to deepFillable
        $this->assertEquals($data, $testOrm->toArray());

    }

    public function testSaveBelongsTo()
    {
        //fill with belongsTo
        \Illuminate\Database\Eloquent\Model::$snakeAttributes = false;
        $testOrm = new TestORM();
        $data = [
            "name" => "Test",
            "date" => "Date",
            "relationBelongsTo" => [
                "name" => "Belongs To Name"
            ]
        ];

        $testOrm->deepFill($data);
        $testOrm->deepSave();

        //should create relationBelongsTo
        $this->assertTrue($testOrm->relationLoaded("relationBelongsTo"));
        $this->assertNotEmpty($testOrm->relationBelongsTo->id);
        //should fill belongs_id of original model
        $this->assertNotEmpty($testOrm->belongs_id);
        $this->assertEquals($testOrm->belongs_id, $testOrm->relationBelongsTo->id);

    }

    public function testSaveHasOne()
    {
        \Illuminate\Database\Eloquent\Model::$snakeAttributes = false;
        $testOrm = new TestORM();
        $data = [
            "name" => "Test",
            "date" => "Date",
            "relationHasOne" => [
                "name" => "Has One Name"
            ]
        ];

        $testOrm->deepFill($data);
        $testOrm->deepSave();

        //should create relationBelongsTo
        $this->assertTrue($testOrm->relationLoaded("relationHasOne"));
        $this->assertNotEmpty($testOrm->relationHasOne->id);
        //should fill belongs_id of original model
        $this->assertNotEmpty($testOrm->relationHasOne->testmodel_id);
        $this->assertEquals($testOrm->id, $testOrm->relationHasOne->testmodel_id);
    }

    public function testSaveHasMany()
    {
        \Illuminate\Database\Eloquent\Model::$snakeAttributes = false;
        $testOrm = new TestORM();
        $data = [
            "name" => "Test",
            "date" => "Date",
            "relationHasMany" => [
                [
                    "name" => "Has Many 1"
                ],
                [
                    "name" => "Has Many 2"
                ],
            ]
        ];

        $testOrm->deepFill($data);
        $testOrm->deepSave();

        //should create relationBelongsTo
        $this->assertTrue($testOrm->relationLoaded("relationHasMany"));
        $this->assertCount(2, $testOrm->relationHasMany->all());
        //should fill belongs_id of original model
        $hasTestModelId = true;
        $hasSelfId = true;

        foreach($testOrm->relationHasMany as $model){
            $hasTestModelId = $hasTestModelId && ($model->testmodel_id != 0);
            $hasSelfId = $hasSelfId && ($model->id != 0);
        }

        $this->assertTrue($hasTestModelId);
        $this->assertTrue($hasSelfId);
    }

    public function testSaveManyMany()
    {
        \Illuminate\Database\Eloquent\Model::$snakeAttributes = false;

        app()->singleton("db", function($app){
            return new TestDatabaseManager($app, $app["db.factory"]);
        } );
        \DB::clearResolvedInstance("db");

        $testOrm = new TestORM();
        $data = [
            "name" => "Test",
            "date" => "Date",
            "relationManyMany" => [
                [
                    "name" => "Many Many 1"
                ],
                [
                    "name" => "Many Many 2"
                ],
            ]
        ];

        $testOrm->deepFill($data);
        $testOrm->deepSave();

        //should create relationBelongsTo
        $this->assertTrue($testOrm->relationLoaded("relationManyMany"));
        $this->assertCount(2, $testOrm->relationManyMany->all());
        //should fill belongs_id of original model
        $hasSelfId = true;

        foreach($testOrm->relationManyMany as $model){
            $hasSelfId = $hasSelfId && ($model->id != 0);
        }

        $this->assertTrue($hasSelfId);
    }
}


abstract class TestModel extends \Illuminate\Database\Eloquent\Model {

    /**
     * Override save so it just display, what it's doing.
     * Without any database insert/update
     *
     * @param array $options
     * @return bool
     */
    public function save(array $options=[]){
        $this->fireModelEvent("saving");
        echo "\n";
        echo (new \ReflectionClass($this))->getShortName();
        echo "\n************************";
        echo "\n";
        print_r($this->getAttributes());
        echo "\n************************";
        echo "\n";
        $this->fireModelEvent("saved");
        return true;
    }


    public function newQuery()
    {
        $builder = new \Illuminate\Database\Query\Builder(
            TestDatabaseManager::provideTestConnection()
        );
        $builder = new MockBuilder( $builder );
        $builder->setModel($this);
        return $builder;
    }

}

/**
 * Class TestORM
 *
 * @property string name
 * @property integer date
 * @property integer belongs_id
 *
 *
 * @method setModelAttributes (array $data)
 * @package Tests\Unit
 */
class TestORM extends TestModel{
    use  \Jorjsmile\LaravelModelDeepRelation\TDeepRelation;

    protected $fillable = ["name", "date", "belongs_id"];
    protected $primaryKey = "id";


    protected function initListeners()
    {
        $listeners = parent::initListeners();
        $listeners["saving"][] = "deepBeforeSave";
        $listeners["saved"][] = "deepAfterSave";

        return $listeners;
    }


    protected function getValidationRules()
    {
        return [
            "name" => [["required"]],
            "belongs_id" => [["required"]]
        ];
    }

    public function save(array $options=[]){
        $this->id = 213;
        return parent::save($options);
    }

    public function relationBelongsTo(){
        return $this->belongsTo(TestBelongsTo::class, "belongs_id");
    }

    public function relationHasOne(){
        return $this->hasOne(TestHasOneMany::class, "testmodel_id");
    }

    public function relationHasMany(){
        return $this->hasMany(TestHasOneMany::class, "testmodel_id");
    }

    public function relationManyMany(){
        return $this->belongsToMany(TestManyMany::class, "testorm_many", "testmodel_id", "mamymany_id");
    }
}

/**
 * Class TestBelongsTo
 *
 * @property string name
 *
 * @package Tests\Unit
 */
class TestBelongsTo extends TestModel{
    use \Jorjsmile\LaravelModelDeepRelation\TDeepRelation;

    protected $fillable = ["name"];

    public function save(array $options = []){
        $this->id = 123;
        return parent::save($options);
    }
}
/**
 * Class TestHasOne
 *
 * @property string name
 *
 * @package Tests\Unit
 */
class TestHasOneMany extends TestModel{
    use \Jorjsmile\LaravelModelDeepRelation\TDeepRelation;

    protected $fillable = ["name", "testmodel_id"];

    public function save(array $options = []){
        $this->id = 102;
        return parent::save($options);
    }
}
/**
 * Class TestHasOne
 *
 * @property string name
 *
 * @package Tests\Unit
 */
class TestManyMany extends TestModel{
    use \Jorjsmile\LaravelModelDeepRelation\TDeepRelation;

    protected $fillable = ["id", "name"];
    protected static $_id = 100;

    public function save(array $options = []){
        $this->id = self::$_id ++;
        return parent::save($options);
    }

}

class TestDatabaseManager extends \Illuminate\Database\DatabaseManager {

    public function connection($name = null)
    {
        return self::provideTestConnection();
    }

    public static function provideTestConnection()
    {
        $factory = app()["db.factory"];
        $databasePath = 'tests/resources/testdatabase';
        $config = [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
        ];

        $pdo = $factory->createConnector($config)->connect($config);

        return new MySqlConnection($pdo, $config["database"]);
    }

    public static function testCall()
    {
        return 1;
    }


}

class MySqlConnection extends \Illuminate\Database\Connection {

    protected $_table = "";

    public function table($table){
        $this->_table = $table;

        return $this->query()->from($this->_table);
    }

    public function where($condition){
        echo "\nQuery Condition: \n";
        echo "**********************\n";
        print_r($condition);
        echo "\n**********************\n";
        return $this;
    }

    public function insert($query, $bindings = [])
    {
        echo "\nInsert: \n";
        echo "**********************\n";
        print_r($query."\n");
        print_r($bindings);
        echo "\n**********************\n";

        return true;
    }

    public function delete($q="", $p=[])
    {
        echo "\nDeletion: \n";
        echo "**********************\n";
        echo "Delete from ".$this->_table;
        echo "\n**********************\n";

    }


}

class MockBuilder  extends \Illuminate\Database\Eloquent\Builder {

    protected $_data;

    public function __construct(\Illuminate\Database\Query\Builder $query)
    {
        parent::__construct($query);
    }


    public function get($columns=[])
    {
        return new \Illuminate\Support\Collection($this->_data);
    }


    public function find($id, $columns=["*"]){
        $class  = get_class($this->getModel());
        return  new $class(
            [
                "id" =>  $id,
                "name" => "$class::`$id`"
            ]
        );
    }

    public function whereIn($column, $ids, $boolean = 'and', $not = false)
    {

        foreach($ids as $id)
            $this->_data[] = $this->find($id);

        return $this;
    }

}