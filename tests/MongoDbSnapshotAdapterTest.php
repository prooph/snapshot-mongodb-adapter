<?php
/*
 * This file is part of the prooph/snapshot-mongodb-adapter.
 * (c) 2014 - 2015 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Date: 10/10/15 - 15:37
 */

namespace ProophTest\EventStore\Snapshot\Adpater\MongoDb;

use PHPUnit_Framework_TestCase as TestCase;
use Prooph\EventStore\Aggregate\AggregateType;
use Prooph\EventStore\Snapshot\Adapter\MongoDb\MongoDbSnapshotAdapter;
use Prooph\EventStore\Snapshot\Snapshot;

/**
 * Class MongoDbSnapshotAdapterTest
 * @package ProophTest\EventStore\Adpater\MongoDb
 */
final class MongoDbSnapshotAdapterTest extends TestCase
{
    /**
     * @var MongoDbSnapshotAdapter
     */
    private $adapter;

    /**
     * @var \MongoClient
     */
    private $client;

    protected function setUp()
    {
        $this->client = new \MongoClient();
        $this->client->selectDB('test')->drop();

        $this->adapter = new MongoDbSnapshotAdapter($this->client, 'test');
    }

    protected function tearDown()
    {
        $this->client->selectDB('test')->drop();
    }

    /**
     * @test
     */
    public function it_saves_and_reads()
    {
        $aggregateType = AggregateType::fromString('stdClass');

        $aggregateRoot = new \stdClass();
        $aggregateRoot->foo = 'bar';

        $time = microtime(true);
        if (false === strpos($time, '.')) {
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

        $gridFs = $this->client->selectDB('test')->getGridFS('bar');
        $files = $gridFs->find();
        $this->assertEquals(1, count($files));
    }

    /**
     * @test
     */
    public function it_saves_two_versions_gets_the_latest_back()
    {
        $aggregateType = AggregateType::fromString('stdClass');

        $aggregateRoot = new \stdClass();
        $aggregateRoot->foo = 'bar';

        $time = microtime(true);
        if (false === strpos($time, '.')) {
            $time .= '.0000';
        }
        $now = \DateTimeImmutable::createFromFormat('U.u', $time, new \DateTimeZone('UTC'));

        $snapshot = new Snapshot($aggregateType, 'id', $aggregateRoot, 1, $now);

        $this->adapter->save($snapshot);

        $aggregateRoot = new \stdClass();
        $aggregateRoot->foo = 'baz';

        $time = microtime(true);
        if (false === strpos($time, '.')) {
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

        $gridFs = $this->client->selectDB('test')->getGridFS('bar');
        $files = $gridFs->find();
        $this->assertEquals(1, count($files));
    }

    /**
     * @test
     */
    public function it_saves_two_versions_gets_the_latest_back_when_older_written_last()
    {
        $aggregateType = AggregateType::fromString('stdClass');

        $aggregateRoot2 = new \stdClass();
        $aggregateRoot2->foo = 'baz';

        $time = microtime(true);
        if (false === strpos($time, '.')) {
            $time .= '.0000';
        }
        $now = \DateTimeImmutable::createFromFormat('U.u', $time, new \DateTimeZone('UTC'));

        $snapshot2 = new Snapshot($aggregateType, 'id', $aggregateRoot2, 2, $now);

        $this->adapter->save($snapshot2);

        $aggregateRoot1 = new \stdClass();
        $aggregateRoot1->foo = 'bar';

        $time = microtime(true);
        if (false === strpos($time, '.')) {
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

        $gridFs = $this->client->selectDB('test')->getGridFS('bar');
        $files = $gridFs->find();
        $this->assertEquals(1, count($files));
    }

    /**
     * @test
     */
    public function it_ignores_invalid_serialized_strings()
    {
        $time = microtime(true);
        if (false === strpos($time, '.')) {
            $time .= '.0000';
        }
        $now = \DateTimeImmutable::createFromFormat('U.u', $time, new \DateTimeZone('UTC'));

        $createdAt = new \MongoDate(
            $now->getTimestamp(),
            $now->format('u')
        );

        $gridFs = $this->client->selectDB('test')->getGridFS('stdclass_snapshot');
        $gridFs->storeBytes(
            'invalid_serialize_string',
            [
                'aggregate_type' => 'stdClass',
                'aggregate_id' => 'id',
                'last_version' => 1,
                'created_at' => $createdAt,
            ],
            [
                'w' => 1,
            ]
        );

        $aggregateType = AggregateType::fromString('stdClass');

        $this->assertNull($this->adapter->get($aggregateType, 'id'));
    }

    /**
     * @test
     */
    public function it_ignores_wrong_returns()
    {
        $aggregateType = AggregateType::fromString('foo');

        $aggregateRoot = new \stdClass();
        $aggregateRoot->foo = 'bar';

        $time = microtime(true);
        if (false === strpos($time, '.')) {
            $time .= '.0000';
        }
        $now = \DateTimeImmutable::createFromFormat('U.u', $time, new \DateTimeZone('UTC'));

        $snapshot = new Snapshot($aggregateType, 'id', $aggregateRoot, 1, $now);

        $this->adapter->save($snapshot);

        $this->assertNull($this->adapter->get($aggregateType, 'id'));
    }

    /**
     * @test
     */
    public function it_uses_custom_snapshot_grid_fs_map_and_write_concern()
    {
        $this->adapter = new MongoDbSnapshotAdapter(
            $this->client,
            'test',
            [
                'w' => 'majority',
                'j' => true
            ],
            [
                'foo' => 'bar'
            ]
        );

        $aggregateType = AggregateType::fromString('foo');

        $aggregateRoot = new \stdClass();
        $aggregateRoot->foo = 'bar';

        $time = microtime(true);
        if (false === strpos($time, '.')) {
            $time .= '.0000';
        }
        $now = \DateTimeImmutable::createFromFormat('U.u', $time);

        $snapshot = new Snapshot($aggregateType, 'id', $aggregateRoot, 1, $now);

        $this->adapter->save($snapshot);

        $gridFs = $this->client->selectDB('test')->getGridFS('bar');
        $file = $gridFs->findOne();

        $this->assertNotNull($file);
    }
}
