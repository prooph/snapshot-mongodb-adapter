<?php
/*
 * This file is part of the prooph/service-bus.
 * (c) 2014 - 2015 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Date: 10/10/15 - 13:57
 */

namespace Prooph\EventStore\Snapshot\Adapter\MongoDb;

use Assert\Assertion;
use Prooph\EventStore\Aggregate\AggregateType;
use Prooph\EventStore\Snapshot\Adapter\Adapter;
use Prooph\EventStore\Snapshot\Snapshot;

/**
 * Class MongoDbSnapshotAdapter
 * @package Prooph\EventStore\Snapshot\Adapter
 */
final class MongoDbSnapshotAdapter implements Adapter
{
    /**
     * @var \MongoClient
     */
    private $mongoClient;

    /**
     * @var string
     */
    private $dbName;

    /**
     * Mongo DB write concern
     * The default options can be overridden with the constructor
     *
     * @var array
     */
    private $writeConcern = [
        'w' => 1,
        'j' => true
    ];

    /**
     * Custom sourceType to snapshot mapping
     *
     * @var array
     */
    private $snapshotCollectionMap = [];

    /**
     * @param \MongoClient $mongoClient
     * @param string $dbName
     * @param array|null $writeConcern
     * @param array $snapshotCollectionMap
     */
    public function __construct(
        \MongoClient $mongoClient,
        $dbName,
        array $writeConcern = null,
        array $snapshotCollectionMap = []
    ) {
        Assertion::minLength($dbName, 1, 'Mongo database name is missing');

        $this->mongoClient      = $mongoClient;
        $this->dbName           = $dbName;
        $this->snapshotCollectionMap = $snapshotCollectionMap;
        if (null !== $writeConcern) {
            $this->writeConcern = $writeConcern;
        }
    }

    /**
     * Get the aggregate root if it exists otherwise null
     *
     * @param AggregateType $aggregateType
     * @param string $aggregateId
     * @return Snapshot
     */
    public function get(AggregateType $aggregateType, $aggregateId)
    {
        $collection = $this->getCollection($aggregateType->toString());

        $data = $collection->findOne(
            [
                '$query' => [
                    'aggregate_type' => $aggregateType->toString(),
                    'aggregate_id' => $aggregateId,
                ]
            ],
            [
                '$orderBy' => [
                    'last_version' => -1
                ]
            ]
        );

        if (!$data) {
            return;
        }

        return new Snapshot(
            $aggregateType,
            $aggregateId,
            unserialize($data['aggregate_root']),
            $data['last_version'],
            $data['created_at']
        );
    }

    /**
     * Add a snapshot
     *
     * @param Snapshot $snapshot
     * @return void
     */
    public function add(Snapshot $snapshot)
    {
        $collection = $this->getCollection($snapshot->aggregateType());

        $collection->insert(
            [
                'aggregate_type' => $snapshot->aggregateType()->toString(),
                'aggregate_id' => $snapshot->aggregateId(),
                'last_version' => $snapshot->lastVersion(),
                'created_at' => new \MongoDate($snapshot->createdAt()->getTimestamp(), $snapshot->createdAt()->format('u')),
                'aggregate_root' => serialize($snapshot->aggregateRoot()),
            ],
            $this->writeConcern
        );
    }

    /**
     * Get mongo db stream collection
     *
     * @param AggregateType $aggregateType
     * @return \MongoCollection
     */
    private function getCollection(AggregateType $aggregateType)
    {
        $collection = $this->mongoClient->selectCollection($this->dbName, $this->getCollectionName($aggregateType));
        return $collection;
    }

    /**
     * @param AggregateType $aggregateType
     * @return string
     */
    private function getCollectionName(AggregateType $aggregateType)
    {
        if (isset($this->snapshotCollectionMap[$aggregateType->toString()])) {
            $collectionName = $this->snapshotCollectionMap[$aggregateType->toString()];
        } else {
            $collectionName = strtolower($this->getShortAggregateTypeName($aggregateType));
            if (strpos($collectionName, "_snapshot") === false) {
                $collectionName.= "_snapshot";
            }
        }
        return $collectionName;
    }

    /**
     * @param AggregateType $aggregateType
     * @return string
     */
    private function getShortAggregateTypeName(AggregateType $aggregateType)
    {
        $aggregateTypeName = str_replace('-', '_', $aggregateType->toString());
        return implode('', array_slice(explode('\\', $aggregateTypeName), -1));
    }
}
