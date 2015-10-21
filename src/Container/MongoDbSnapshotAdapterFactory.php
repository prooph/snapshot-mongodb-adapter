<?php
/*
 * This file is part of the prooph/snapshot-mongodb-adapter.
 * (c) 2014 - 2015 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Date: 10/18/15 - 19:40
 */

namespace Prooph\EventStore\Snapshot\Adapter\MongoDb\Container;

use Interop\Config\ConfigurationTrait;
use Interop\Config\ProvidesDefaultOptions;
use Interop\Config\RequiresConfig;
use Interop\Config\RequiresMandatoryOptions;
use Interop\Container\ContainerInterface;
use Prooph\EventStore\Snapshot\Adapter\MongoDb\MongoDbSnapshotAdapter;

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
        $config = $this->options($config)['snapshot_adapter']['options'];

        $mongoClient = isset($config['mongo_connection_alias'])
            ? $container->get($config['mongo_connection_alias'])
            : new \MongoClient;

        return new MongoDbSnapshotAdapter(
            $mongoClient,
            $config['db_name'],
            $config['write_concern'],
            $config['snapshot_grid_fs_map']
        );
    }

    /**
     * @inheritdoc
     */
    public function vendorName()
    {
        return 'prooph';
    }

    /**
     * @inheritdoc
     */
    public function packageName()
    {
        return 'event_store';
    }

    /**
     * @inheritdoc
     */
    public function mandatoryOptions()
    {
        return ['snapshot_adapter' => ['options' => ['db_name']]];
    }

    /**
     * @inheritdoc
     */
    public function defaultOptions()
    {
        return [
            'snapshot_adapter' => [
                'options' => [
                    'snapshot_grid_fs_map' => [],
                    'write_concern' => [
                        'w' => 1,
                        'j' => true
                    ]
                ]
            ]
        ];
    }
}
