<?php
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Connection;

use PHPUnit\Framework\TestCase;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;

class DoctrineDbalInstrumentationTest extends TestCase
{
    private ScopeInterface $scope;
    /** @var ArrayObject<int, ImmutableSpan> */
    private ArrayObject $storage;


    public function setUp(): void
    {
        $this->storage = new ArrayObject();
        $tracerProvider = new TracerProvider(
            new SimpleSpanProcessor(
                new InMemoryExporter($this->storage)
            )
        );

        $this->scope = Configurator::create()
            ->withTracerProvider($tracerProvider)
            ->activate();
    }

    public function tearDown(): void
    {
        $this->scope->detach();
    }

    public function testHelloWorld()
    {
        $expectedAttributes = [
            TraceAttributes::DB_SYSTEM => 'sqlite',
            TraceAttributes::DB_NAMESPACE => 'unknown',
            TraceAttributes::SERVER_ADDRESS => 'undefined',
        ];

        $connection = $this->createConnection();

        $connection->executeStatement($this->fillDB());
        $span = $this->storage->offsetGet(0);
        self::assertSame(Connection::class. ':executeStatement', $span->getName());
        self::assertArrayHasKey(TraceAttributes::DB_QUERY_TEXT, $span->getAttributes()->toArray());
        self::assertArrayContains($expectedAttributes, $span->getAttributes()->toArray());

        $result = $connection->executeQuery('SELECT * FROM technology');
        $span = $this->storage->offsetGet(1);
        self::assertSame(Connection::class. ':executeQuery', $span->getName());
        self::assertArrayHasKey(TraceAttributes::DB_QUERY_TEXT, $span->getAttributes()->toArray());
        self::assertArrayContains($expectedAttributes, $span->getAttributes()->toArray());

        $connection->prepare('SELECT * FROM technology');
        $span = $this->storage->offsetGet(2);
        self::assertSame(Connection::class. ':prepare', $span->getName());
        self::assertArrayHasKey(TraceAttributes::DB_QUERY_TEXT, $span->getAttributes()->toArray());
        self::assertArrayContains($expectedAttributes, $span->getAttributes()->toArray());

        $connection->beginTransaction();
        $span = $this->storage->offsetGet(3);
        self::assertSame(Connection::class. ':beginTransaction', $span->getName());
        self::assertArrayContains($expectedAttributes, $span->getAttributes()->toArray());

        $connection->rollBack();
        $span = $this->storage->offsetGet(4);
        self::assertSame(Connection::class. ':rollBack', $span->getName());
        self::assertArrayContains($expectedAttributes, $span->getAttributes()->toArray());

        $connection->beginTransaction();
        $connection->commit();
        $span = $this->storage->offsetGet(6);
        self::assertSame(Connection::class. ':commit', $span->getName());
        self::assertArrayContains($expectedAttributes, $span->getAttributes()->toArray());

        $result->fetchNumeric();
        $span = $this->storage->offsetGet(7);
        self::assertSame(Result::class. ':fetchNumeric', $span->getName());
        self::assertArrayContains($expectedAttributes, $span->getAttributes()->toArray());

        $result->fetchAssociative();
        $span = $this->storage->offsetGet(8);
        self::assertSame(Result::class. ':fetchAssociative', $span->getName());
        self::assertArrayContains($expectedAttributes, $span->getAttributes()->toArray());

        $result->fetchOne();
        $span = $this->storage->offsetGet(9);
        self::assertSame(Result::class. ':fetchOne', $span->getName());
        self::assertArrayContains($expectedAttributes, $span->getAttributes()->toArray());

        $result->fetchAllNumeric();
        $span = $this->storage->offsetGet(10);
        self::assertSame(Result::class. ':fetchAllNumeric', $span->getName());
        self::assertArrayContains($expectedAttributes, $span->getAttributes()->toArray());

        $result->fetchAllAssociative();
        $span = $this->storage->offsetGet(11);
        self::assertSame(Result::class. ':fetchAllAssociative', $span->getName());
        self::assertArrayContains($expectedAttributes, $span->getAttributes()->toArray());

        $result->fetchFirstColumn();
        $span = $this->storage->offsetGet(12);
        self::assertSame(Result::class. ':fetchFirstColumn', $span->getName());
        self::assertArrayContains($expectedAttributes, $span->getAttributes()->toArray());

        $result->rowCount();
        $span = $this->storage->offsetGet(13);
        self::assertSame(Result::class. ':rowCount', $span->getName());
        self::assertArrayContains($expectedAttributes, $span->getAttributes()->toArray());

        $result->columnCount();
        $span = $this->storage->offsetGet(14);
        self::assertSame(Result::class. ':columnCount', $span->getName());
        self::assertArrayContains($expectedAttributes, $span->getAttributes()->toArray());

        $result->free();
        $span = $this->storage->offsetGet(15);
        self::assertSame(Result::class. ':free', $span->getName());
        self::assertArrayContains($expectedAttributes, $span->getAttributes()->toArray());
    }

    private function fillDB():string
    {
        return <<<SQL
        CREATE TABLE `technology` (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(25) NOT NULL,
            date DATE NOT NULL
        );

        INSERT INTO technology(`name`, `date`)
        VALUES
            ('PHP', '1993-04-05'),
            ('CPP', '1979-05-06');

        SQL;
    }

    private function createConnection(): Connection
    {
        $connectionParams = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];
        $config = new Configuration();
        return DriverManager::getConnection($connectionParams, $config);
    }

    private static function assertArrayContains(array $expected, array $actual)
    {
        foreach ($expected as $key => $value) {
            self::assertArrayHasKey($key, $actual, "Missing key: $key");
            self::assertSame($value, $actual[$key], "Value for key $key does not match");
        }
    }
}