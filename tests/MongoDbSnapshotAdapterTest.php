<?php

/**
 * /*
 * This file is part of the prooph/snapshot-mongodb-adapter.
 * (c) 2014-2018 - 2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStore\Snapshot\Adapter\MongoDb;

use MongoDB\Client;
use MongoDB\GridFS\Bucket;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Aggregate\AggregateType;
use Prooph\EventStore\Snapshot\Adapter\MongoDb\MongoDbSnapshotAdapter;
use Prooph\EventStore\Snapshot\Snapshot;

/**
 * Class MongoDbSnapshotAdapterTest
 * @package ProophTest\EventStore\Adapter\MongoDb
 */
final class MongoDbSnapshotAdapterTest extends TestCase
{
    /**
     * @var MongoDbSnapshotAdapter
     */
    private $adapter;

    /**
     * @var Client
     */
    private $client;

    protected function setUp()
    {
        $this->client = TestUtil::getConnection();

        $this->adapter = new MongoDbSnapshotAdapter(
            $this->client,
            TestUtil::getDatabaseName(),
            [],
            'bar'
        );
    }

    protected function tearDown()
    {
        TestUtil::tearDownDatabase();
    }

    /**
     * @test
     */
    public function it_saves_and_reads(): void
    {
        $aggregateType = AggregateType::fromString('stdClass');

        $aggregateRoot = new \stdClass();
        $aggregateRoot->foo = 'bar';

        $time = (string) \microtime(true);
        if (false === \strpos($time, '.')) {
            $time .= '.0000';
        }
        $now = \DateTimeImmutable::createFromFormat('U.u', $time, new \DateTimeZone('UTC'));

        $snapshot = new Snapshot($aggregateType, 'id', $aggregateRoot, 1, $now);

        $this->adapter->save($snapshot);

        $this->assertNull($this->adapter->get($aggregateType, 'invalid'));

        $readSnapshot = $this->adapter->get($aggregateType, 'id');

        $this->assertEquals($aggregateRoot, $readSnapshot->aggregateRoot());
        $this->assertEquals('stdClass', $readSnapshot->aggregateType());
        $this->assertEquals('id', $readSnapshot->aggregateId());
        $this->assertEquals(1, $readSnapshot->lastVersion());
        $this->assertEquals('bar', $readSnapshot->aggregateRoot()->foo);

        $gridFs = $this->getBucket(TestUtil::getDatabaseName(), 'bar');
        $files = $gridFs->find();
        $this->assertEquals(1, \count($files->toArray()));
    }

    /**
     * @test
     */
    public function it_saves_two_versions_gets_the_latest_back(): void
    {
        $aggregateType = AggregateType::fromString('stdClass');

        $aggregateRoot = new \stdClass();
        $aggregateRoot->foo = 'bar';

        $time = (string) \microtime(true);
        if (false === \strpos($time, '.')) {
            $time .= '.0000';
        }
        $now = \DateTimeImmutable::createFromFormat('U.u', $time, new \DateTimeZone('UTC'));

        $snapshot = new Snapshot($aggregateType, 'id', $aggregateRoot, 1, $now);

        $this->adapter->save($snapshot);

        $aggregateRoot = new \stdClass();
        $aggregateRoot->foo = 'baz';

        $time = (string) \microtime(true);
        if (false === \strpos($time, '.')) {
            $time .= '.0000';
        }
        $now = \DateTimeImmutable::createFromFormat('U.u', $time, new \DateTimeZone('UTC'));

        $snapshot = new Snapshot($aggregateType, 'id', $aggregateRoot, 2, $now);

        $this->adapter->save($snapshot);

        $readSnapshot = $this->adapter->get($aggregateType, 'id');

        $this->assertEquals($aggregateRoot, $readSnapshot->aggregateRoot());
        $this->assertEquals('stdClass', $readSnapshot->aggregateType());
        $this->assertEquals('id', $readSnapshot->aggregateId());
        $this->assertEquals(2, $readSnapshot->lastVersion());
        $this->assertEquals('baz', $readSnapshot->aggregateRoot()->foo);

        $gridFs = $this->getBucket(TestUtil::getDatabaseName(), 'bar');
        $files = $gridFs->find();
        $this->assertEquals(1, \count($files->toArray()));
    }

    /**
     * @test
     */
    public function it_saves_two_versions_gets_the_latest_back_when_older_written_last(): void
    {
        $aggregateType = AggregateType::fromString('stdClass');

        $aggregateRoot2 = new \stdClass();
        $aggregateRoot2->foo = 'baz';

        $time = (string) \microtime(true);
        if (false === \strpos($time, '.')) {
            $time .= '.0000';
        }
        $now = \DateTimeImmutable::createFromFormat('U.u', $time, new \DateTimeZone('UTC'));

        $snapshot2 = new Snapshot($aggregateType, 'id', $aggregateRoot2, 2, $now);

        $this->adapter->save($snapshot2);

        $aggregateRoot1 = new \stdClass();
        $aggregateRoot1->foo = 'bar';

        $time = (string) \microtime(true);
        if (false === \strpos($time, '.')) {
            $time .= '.0000';
        }
        $now = \DateTimeImmutable::createFromFormat('U.u', $time, new \DateTimeZone('UTC'));

        $snapshot1 = new Snapshot($aggregateType, 'id', $aggregateRoot1, 1, $now);

        $this->adapter->save($snapshot1);

        $readSnapshot = $this->adapter->get($aggregateType, 'id');

        $this->assertEquals($aggregateRoot2, $readSnapshot->aggregateRoot());
        $this->assertEquals('stdClass', $readSnapshot->aggregateType());
        $this->assertEquals('id', $readSnapshot->aggregateId());
        $this->assertEquals(2, $readSnapshot->lastVersion());
        $this->assertEquals('baz', $readSnapshot->aggregateRoot()->foo);

        $gridFs = $this->getBucket(TestUtil::getDatabaseName(), 'bar');
        $files = $gridFs->find();
        $this->assertEquals(1, \count($files->toArray()));
    }

    /**
     * @test
     */
    public function it_ignores_invalid_serialized_strings(): void
    {
        $time = (string) \microtime(true);
        if (false === \strpos($time, '.')) {
            $time .= '.0000';
        }
        $now = \DateTimeImmutable::createFromFormat('U.u', $time, new \DateTimeZone('UTC'));

        $createdAt = $now->format('Y-m-d\TH:i:s.u');

        $gridFs = $this->getBucket(TestUtil::getDatabaseName(), 'bar');
        $gridFs->uploadFromStream(
            'id',
            $this->createStream('invalid_serialize_string'),
            [
                '_id' => 'id',
                'metadata' => [
                    'aggregate_id' => 'id',
                    'aggregate_type' => 'stdClass',
                    'last_version' => 1,
                    'created_at' => $createdAt,
                ],
            ]
        );

        $aggregateType = AggregateType::fromString('stdClass');

        $this->assertNull($this->adapter->get($aggregateType, 'id'));
    }

    /**
     * @test
     */
    public function it_uses_custom_snapshot_grid_fs_map_and_write_concern(): void
    {
        $this->adapter = new MongoDbSnapshotAdapter(
            $this->client,
            TestUtil::getDatabaseName(),
            [
                'foo' => 'bar',
            ]
        );

        $aggregateType = AggregateType::fromString('foo');

        $aggregateRoot = new \stdClass();
        $aggregateRoot->foo = 'bar';

        $time = (string) \microtime(true);
        if (false === \strpos($time, '.')) {
            $time .= '.0000';
        }
        $now = \DateTimeImmutable::createFromFormat('U.u', $time);

        $snapshot = new Snapshot($aggregateType, 'id', $aggregateRoot, 1, $now);

        $this->adapter->save($snapshot);

        $snapshotFromStore = $this->adapter->get($aggregateType, 'id');

        $this->assertEquals($snapshot, $snapshotFromStore);
    }

    private function getBucket(string $dbName, string $aggregateType): Bucket
    {
        return $this->client->selectDatabase($dbName)->selectGridFSBucket([
            'bucketName' => $aggregateType,
        ]);
    }

    /**
     * Creates an in-memory stream with the given data.
     *
     * @return resource
     */
    private function createStream(string $data = '')
    {
        $stream = \fopen('php://temp', 'w+b');
        \fwrite($stream, $data);
        \rewind($stream);

        return $stream;
    }
}
