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

namespace Prooph\EventStore\Snapshot\Adapter\MongoDb\Container;

use Interop\Config\ConfigurationTrait;
use Interop\Config\ProvidesDefaultOptions;
use Interop\Config\RequiresConfig;
use Interop\Config\RequiresMandatoryOptions;
use MongoDB\Client;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\WriteConcern;
use Prooph\EventStore\Snapshot\Adapter\MongoDb\MongoDbSnapshotAdapter;
use Psr\Container\ContainerInterface;

/**
 * Class MongoDbSnapshotAdapterFactory
 * @package Prooph\EventStore\Snapshot\Adapter\MongoDb\Container
 */
final class MongoDbSnapshotAdapterFactory implements RequiresConfig, RequiresMandatoryOptions, ProvidesDefaultOptions
{
    use ConfigurationTrait;

    /**
     * @param ContainerInterface $container
     * @return MongoDbSnapshotAdapter
     */
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get('config');
        $config = $this->options($config)['adapter']['options'];

        $mongoClient = isset($config['mongo_connection_alias'])
            ? $container->get($config['mongo_connection_alias'])
            : new Client();

        $readConcern = new ReadConcern($config['read_concern']);
        $writeConcern = new WriteConcern(
            $config['write_concern']['w'],
            $config['write_concern']['wtimeout'],
            $config['write_concern']['journal']
        );

        return new MongoDbSnapshotAdapter(
            $mongoClient,
            $config['db_name'],
            $config['snapshot_grid_fs_map'],
            $config['default_snapshot_grid_fs_name'],
            $readConcern,
            $writeConcern
        );
    }

    /**
     * {@inheritdoc}
     */
    public function dimensions(): iterable
    {
        return ['prooph', 'snapshot_store'];
    }

    /**
     * {@inheritdoc}
     */
    public function mandatoryOptions(): iterable
    {
        return ['adapter' => ['options' => ['db_name']]];
    }

    /**
     * {@inheritdoc}
     */
    public function defaultOptions(): iterable
    {
        return [
            'adapter' => [
                'options' => [
                    'snapshot_grid_fs_map' => [],
                    'default_snapshot_grid_fs_name' => 'snapshots',
                    'read_concern' => 'local', // other value: majority
                    'write_concern' => [
                        'w' => 1,
                        'wtimeout' => 0, // How long to wait (in milliseconds) for secondaries before failing.
                        'journal' => false, // Wait until mongod has applied the write to the journal.
                    ],
                ],
            ],
        ];
    }
}
