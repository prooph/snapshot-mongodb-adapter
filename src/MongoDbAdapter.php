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
 * Class MongoDbAdapter
 * @package Prooph\EventStore\Snapshot\Adapter
 */
final class MongoDbAdapter implements Adapter
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
    private $snapshotGridFsMap = [];

    /**
     * @param \MongoClient $mongoClient
     * @param string $dbName
     * @param array|null $writeConcern
     * @param array $snapshotGridFsMap
     */
    public function __construct(
        \MongoClient $mongoClient,
        $dbName,
        array $writeConcern = null,
        array $snapshotGridFsMap = []
    ) {
        Assertion::minLength($dbName, 1, 'Mongo database name is missing');

        $this->mongoClient      = $mongoClient;
        $this->dbName           = $dbName;
        $this->snapshotGridFsMap = $snapshotGridFsMap;

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
        $gridFs = $this->getGridFs($aggregateType );

        $gridFsfile = $gridFs->findOne(
            [
                '$query' => [
                    'aggregate_type' => $aggregateType->toString(),
                    'aggregate_id' => $aggregateId,
                ],
                '$orderBy' => [
                    'last_version' => -1,
                ],
            ]
        );

        if (!$gridFsfile) {
            return;
        }

        return new Snapshot(
            $aggregateType,
            $aggregateId,
            unserialize($gridFsfile->getBytes()),
            $gridFsfile->file['last_version'],
            \DateTimeImmutable::createFromMutable($gridFsfile->file['created_at']->toDateTime())
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
        $gridFs = $this->getGridFs($snapshot->aggregateType());

        $gridFs->storeBytes(
            serialize($snapshot->aggregateRoot()),
            [
                'aggregate_type' => $snapshot->aggregateType()->toString(),
                'aggregate_id' => $snapshot->aggregateId(),
                'last_version' => $snapshot->lastVersion(),
                'created_at' => new \MongoDate($snapshot->createdAt()->getTimestamp(), $snapshot->createdAt()->format('u')),
            ],
            $this->writeConcern
        );
    }

    /**
     * Get mongo db stream collection
     *
     * @param AggregateType $aggregateType
     * @return \MongoGridFs
     */
    private function getGridFs(AggregateType $aggregateType)
    {
        return $this->mongoClient->selectDB($this->dbName)->getGridFS($this->getGridFsName($aggregateType));
    }

    /**
     * @param AggregateType $aggregateType
     * @return string
     */
    private function getGridFsName(AggregateType $aggregateType)
    {
        if (isset($this->snapshotGridFsMap[$aggregateType->toString()])) {
            $gridFsName = $this->snapshotGridFsMap[$aggregateType->toString()];
        } else {
            $gridFsName = strtolower($this->getShortAggregateTypeName($aggregateType));
            if (strpos($gridFsName, "_snapshot") === false) {
                $gridFsName.= "_snapshot";
            }
        }
        return $gridFsName;
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
