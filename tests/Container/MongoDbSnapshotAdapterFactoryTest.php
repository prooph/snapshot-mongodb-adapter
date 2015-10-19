<?php
/*
 * This file is part of the prooph/snapshot-mongodb-adapter.
 * (c) 2014 - 2015 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Date: 10/18/15 - 19:58
 */

namespace ProophTest\EventStore\Snapshot\Adapter\MongoDb\Container;

use Interop\Container\ContainerInterface;
use PHPUnit_Framework_TestCase as TestCase;
use Prooph\EventStore\Snapshot\Adapter\MongoDb\Container\MongoDbSnapshotAdapterFactory;
use Prooph\EventStore\Snapshot\Adapter\MongoDb\MongoDbSnapshotAdapter;

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
                'event_store' => [
                    'snapshot_adapter' => [
                        'type' => MongoDbSnapshotAdapter::class,
                        'options' => [
                            'db_name' => 'test-db-name'
                        ]
                    ]
                ]
            ]
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
                'event_store' => [
                    'snapshot_adapter' => [
                        'type' => MongoDbSnapshotAdapter::class,
                        'options' => [
                            'db_name' => 'test-db-name',
                            'mongo_connection_alias' => 'mongo-client',
                            'write_concern' => [
                                'w' => 'majority',
                                'j' => true
                            ],
                            'snapshot_grid_fs_map' => [
                                'foo' => 'bar'
                            ]
                        ]
                    ]
                ]
            ]
        ]);
        $container->get('mongo-client')->willReturn(new \MongoClient());

        $factory = new MongoDbSnapshotAdapterFactory();
        $adapter = $factory($container->reveal());

        $this->assertInstanceOf(MongoDbSnapshotAdapter::class, $adapter);
    }

    /**
     * @test
     * @expectedException \Prooph\EventStore\Exception\ConfigurationException
     * @expectedExceptionMessage [Configuration Error] Snapshot adapter options missing
     */
    public function it_throws_exception_when_config_options_missing()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->get('config')->willReturn([
            'prooph' => [
                'event_store' => [
                    'snapshot_adapter' => [
                        'type' => MongoDbSnapshotAdapter::class
                    ]
                ]
            ]
        ]);

        $factory = new MongoDbSnapshotAdapterFactory();
        $factory($container->reveal());
    }

    /**
     * @test
     * @expectedException \Prooph\EventStore\Exception\ConfigurationException
     * @expectedExceptionMessage [Configuration Error] Mongo database name is missing
     */
    public function it_throws_exception_when_db_name_missing()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->get('config')->willReturn([
            'prooph' => [
                'event_store' => [
                    'snapshot_adapter' => [
                        'type' => MongoDbSnapshotAdapter::class,
                        'options' => [
                        ]
                    ]
                ]
            ]
        ]);

        $factory = new MongoDbSnapshotAdapterFactory();
        $factory($container->reveal());
    }

    /**
     * @test
     * @expectedException \Prooph\EventStore\Exception\ConfigurationException
     * @expectedExceptionMessage [Configuration Error] Snapshot adapter options must be an array or implement ArrayAccess
     */
    public function it_throws_exception_when_config_options_is_not_array_or_array_access()
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->get('config')->willReturn([
            'prooph' => [
                'event_store' => [
                    'snapshot_adapter' => [
                        'type' => MongoDbSnapshotAdapter::class,
                        'options' => new \stdClass(),
                    ]
                ]
            ]
        ]);

        $factory = new MongoDbSnapshotAdapterFactory();
        $factory($container->reveal());
    }
}
