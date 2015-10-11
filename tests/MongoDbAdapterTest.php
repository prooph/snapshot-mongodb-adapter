<?php
/*
 * This file is part of the prooph/service-bus.
 * (c) 2014 - 2015 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Date: 10/10/15 - 15:37
 */

namespace ProophTest\EventStore\Adpater\MongoDb;

use PHPUnit_Framework_TestCase as TestCase;
use Prooph\EventStore\Aggregate\AggregateType;
use Prooph\EventStore\Snapshot\Adapter\MongoDb\MongoDbAdapter;
use Prooph\EventStore\Snapshot\Snapshot;

/**
 * Class MongoDbAdapterTest
 * @package ProophTest\EventStore\Adpater\MongoDb
 */
final class MongoDbAdapterTest extends TestCase
{
    /**
     * @var MongoDbAdapter
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

        $this->adapter = new MongoDbAdapter($this->client, 'test');
    }

    protected function tearDown()
    {
        $this->client->selectDB('test')->drop();
    }

    /**
     * @test
     */
    public function it_adds_and_reads()
    {
        $aggregateType = AggregateType::fromString('foo');

        $aggregateRoot = new \stdClass();
        $aggregateRoot->foo = 'bar';

        $time = microtime(true);
        if (false === strpos($time, '.')) {
            $time .= '.0000';
        }
        $now = \DateTimeImmutable::createFromFormat('U.u', $time);

        $snapshot = new Snapshot($aggregateType, 'id', $aggregateRoot, 1, $now);

        $this->adapter->add($snapshot);

        $this->assertNull($this->adapter->get($aggregateType, 'invalid'));

        $readSnapshot = $this->adapter->get($aggregateType, 'id');

        $this->assertEquals($snapshot, $readSnapshot);
    }

    /**
     * @test
     */
    public function it_uses_custom_snapshot_grid_fs_map_and_write_concern()
    {
        $this->adapter = new MongoDbAdapter(
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

        $this->adapter->add($snapshot);

        $gridFs = $this->client->selectDB('test')->getGridFS('bar');
        $file = $gridFs->findOne();

        $this->assertNotNull($file);
    }
}
