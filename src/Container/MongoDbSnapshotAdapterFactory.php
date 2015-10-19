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
use Interop\Config\RequiresContainerId;
use Interop\Container\ContainerInterface;
use Prooph\EventStore\Exception\ConfigurationException;
use Prooph\EventStore\Snapshot\Adapter\MongoDb\MongoDbSnapshotAdapter;

/**
 * Class MongoDbSnapshotAdapterFactory
 * @package Prooph\EventStore\Snapshot\Adapter\MongoDb\Container
 */
final class MongoDbSnapshotAdapterFactory implements RequiresContainerId
{
    use ConfigurationTrait;

    /**
     * @param ContainerInterface $container
     * @return MongoDbSnapshotAdapter
     */
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get('config');

        $snapshotAdapterConfig = $this->optionsWithFallback($config);

        if (!isset($snapshotAdapterConfig['options'])) {
            throw ConfigurationException::configurationError(
                'Snapshot adapter options missing'
            );
        }

        if (!is_array($snapshotAdapterConfig['options'])
            && !$snapshotAdapterConfig['options'] instanceof \ArrayAccess
        ) {
            throw ConfigurationException::configurationError(
                'Snapshot adapter options must be an array or implement ArrayAccess'
            );
        }

        $adapterOptions = $snapshotAdapterConfig['options'];

        $mongoClient = isset($adapterOptions['mongo_connection_alias'])
            ? $container->get($adapterOptions['mongo_connection_alias'])
            : new \MongoClient;

        if (!isset($adapterOptions['db_name'])) {
            throw ConfigurationException::configurationError(
                'Mongo database name is missing'
            );
        }

        $dbName = $adapterOptions['db_name'];

        $writeConcern = isset($adapterOptions['write_concern']) ? $adapterOptions['write_concern'] : [];

        $snapshotGridFsMap = isset($adapterOptions['snapshot_grid_fs_map'])
            ? $adapterOptions['snapshot_grid_fs_map']
            : [];

        return new MongoDbSnapshotAdapter($mongoClient, $dbName, $writeConcern, $snapshotGridFsMap);
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
    public function containerId()
    {
        return 'snapshot_adapter';
    }
}
