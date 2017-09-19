<?php

namespace Convenia\Timeliner\Tests;

use Aws\DynamoDb\Marshaler;
use BaoPham\DynamoDb\DynamoDbClientService;
use BaoPham\DynamoDb\DynamoDbModel;
use BaoPham\DynamoDb\EmptyAttributeFilter;
use Convenia\Timeliner\Services\TimelineService;
use Convenia\Timeliner\TimelinerServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Mockery\Exception;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * TestCase
 */
class TestCase extends Orchestra
{
    protected $dynamoDb;

    protected $dynamoDbClient;

    /**
     * @var \Convenia\Timeliner\Tests\TestModel
     */
    protected $testModel;

    protected $testModelData;

    protected $timelineService;

    protected $table = [
        'TableName' => 'TestTable',
        'KeySchema' => [
            [
                'AttributeName' => 'id',
                'KeyType' => 'HASH'  //Partition key
            ],
        ],
        'AttributeDefinitions' => [
            [
                'AttributeName' => 'id',
                'AttributeType' => 'S',
            ],

        ],
        'ProvisionedThroughput' => [
            'ReadCapacityUnits' => 1,
            'WriteCapacityUnits' => 1,
        ],
    ];

    public function setUp()
    {
        $this->timelineService = new TimelineService();

        parent::setUp();

        $this->setUpClient();
        $this->setUpDatabase($this->app);
        $this->createTable();
        config(['mirrorable.table' => 'TestTable']);
        $this->createTestModel();
    }

    private function setUpClient()
    {
        $marshalerOptions = [
            'nullify_invalid' => true,
        ];

        $config = [
            'credentials' => [
                'key' => 'key',
                'secret' => 'secret',
            ],
            'region' => 'test',
            'version' => '2012-08-10',
            'endpoint' => 'http://localhost:8000',
        ];

        $this->dynamoDb = new DynamoDbClientService($config, new Marshaler($marshalerOptions), new EmptyAttributeFilter);
        DynamoDbModel::setDynamoDbClientService($this->dynamoDb);

        $this->dynamoDbClient = $this->dynamoDb->getClient();
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function setUpDatabase($app)
    {
        $app['db']->connection()->getSchemaBuilder()->create('test_models', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createTable()
    {
        try {
            $this->dynamoDbClient->createTable($this->table);
        } catch (Exception $e) {
            $this->dynamoDbClient->deleteTable([
                'TableName' => $this->table['TableName'],
            ]);
        }
    }

    private function createTestModel()
    {
        $this->testModel = new TestModel();
    }

    public function tearDown()
    {
        $this->dynamoDbClient->deleteTable([
            'TableName' => $this->table['TableName'],
        ]);

        parent::tearDown();
    }

    public function addItem()
    {
        $this->testModel->id = time();
        $model = $this->timelineService->mirrorModel($this->testModel, 'event');
        $this->testModel->id = null;

        return $model;
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            TimelinerServiceProvider::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('app.key', '6rE9Nz59bGRbeMATftriyQjrpF7DcOQm');
    }
}
