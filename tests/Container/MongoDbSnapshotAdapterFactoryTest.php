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

namespace ProophTest\EventStore\Snapshot\Adapter\MongoDb\Container;

use MongoDB\Client;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Snapshot\Adapter\MongoDb\Container\MongoDbSnapshotAdapterFactory;
use Prooph\EventStore\Snapshot\Adapter\MongoDb\MongoDbSnapshotAdapter;
use Psr\Container\ContainerInterface;

/**
 * Class MongoDbSnapshotAdapterFactoryTest
 * @package ProophTest\EventStore\Snapshot\Adapter\MongoDb\Container
 */
final class MongoDbSnapshotAdapterFactoryTest extends TestCase
{
    /**
     * @test
     */
    public function it_creates_adapter_with_minimum_settings()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->get('config')->willReturn([
            'prooph' => [
                'snapshot_store' => [
                    'adapter' => [
                        'type' => MongoDbSnapshotAdapter::class,
                        'options' => [
                            'db_name' => 'test-db-name',
                        ],
                    ],
                ],
            ],
        ]);

        $factory = new MongoDbSnapshotAdapterFactory();
        $adapter = $factory($container->reveal());

        $this->assertInstanceOf(MongoDbSnapshotAdapter::class, $adapter);
    }

    /**
     * @test
     */
    public function it_creates_adapter_with_maximum_settings()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->get('config')->willReturn([
            'prooph' => [
                'snapshot_store' => [
                    'adapter' => [
                        'type' => MongoDbSnapshotAdapter::class,
                        'options' => [
                            'db_name' => 'test-db-name',
                            'mongo_connection_alias' => 'mongo-client',
                            'write_concern' => [
                                'w' => 'majority',
                                'j' => true,
                            ],
                            'snapshot_grid_fs_map' => [
                                'foo' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $container->get('mongo-client')->willReturn($this->prophesize(Client::class)->reveal());

        $factory = new MongoDbSnapshotAdapterFactory();
        $adapter = $factory($container->reveal());

        $this->assertInstanceOf(MongoDbSnapshotAdapter::class, $adapter);
    }
}
