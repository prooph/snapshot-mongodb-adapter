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

        $snapshot = new Snapshot($aggregateType, 'id', $aggregateRoot, 1, $now);

        $this->adapter->save($snapshot);

        $this->assertNull($this->adapter->get($aggregateType, 'invalid'));

        $readSnapshot = $this->adapter->get($aggregateType, 'id');

        $this->assertEquals($snapshot, $readSnapshot);

        $gridFs = $this->client->selectDB('test')->getGridFS('bar');
        $files = $gridFs->find();
        $this->assertEquals(1, count($files));
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
