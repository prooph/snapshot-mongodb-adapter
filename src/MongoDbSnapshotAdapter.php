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

namespace Prooph\EventStore\Snapshot\Adapter\MongoDb;

use DateTimeImmutable;
use DateTimeZone;
use MongoDB\BSON\ObjectId;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\WriteConcern;
use MongoDB\GridFS\Bucket;
use MongoDB\GridFS\Exception\FileNotFoundException;
use Prooph\EventStore\Aggregate\AggregateType;
use Prooph\EventStore\Snapshot\Adapter\Adapter;
use Prooph\EventStore\Snapshot\Snapshot;

/**
 * Class MongoDbSnapshotAdapter
 * @package Prooph\EventStore\Snapshot\Adapter\MongoDb
 */
final class MongoDbSnapshotAdapter implements Adapter
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $dbName;

    /**
     * Custom sourceType to snapshot mapping
     *
     * @var array
     */
    private $snapshotGridFsMap;

    /**
     * @var string
     */
    private $defaultSnapshotGridFsName;

    /**
     * @var ReadConcern
     */
    private $readConcern;
    /**
     * @var WriteConcern
     */
    private $writeConcern;

    public function __construct(
        Client $client,
        string $dbName,
        array $snapshotGridFsMap = [],
        string $defaultSnapshotGridFsName = 'snapshots',
        ReadConcern $readConcern = null,
        WriteConcern $writeConcern = null
    ) {
        $this->client = $client;
        $this->dbName = $dbName;
        $this->snapshotGridFsMap = $snapshotGridFsMap;
        $this->defaultSnapshotGridFsName = $defaultSnapshotGridFsName;
        $this->readConcern = $readConcern;
        $this->writeConcern = $writeConcern;
    }

    public function get(AggregateType $aggregateType, $aggregateId)
    {
        $bucket = $this->getGridFs($aggregateType->toString());

        $snapshot = $bucket->findOne(
            [
                'metadata.aggregate_id' => $aggregateId,
                'metadata.aggregate_type' => $aggregateType->toString(),
            ],
            [
                'projection' => ['_id' => 1, 'metadata' => 1],
                'sort' => ['metadata.last_version' => -1],
            ]
        );

        if (empty($snapshot['_id'])) {
            return null;
        }

        try {
            $stream = $bucket->openDownloadStream($snapshot['_id']);
        } catch (FileNotFoundException $e) {
            return null;
        }
        $aggregateRoot = false;

        try {
            $destination = $this->createStream();
            \stream_copy_to_stream($stream, $destination);
            $aggregateRoot = \unserialize(\stream_get_contents($destination, -1, 0));
        } catch (\Throwable $e) {
            // nothing to do
        } finally {
            // delete in case of error, so new snapshot can be created
            if ($aggregateRoot === false) {
                $this->deleteByAggregateId($aggregateId, $aggregateType);

                return null;
            }
        }
        $createdAt = $snapshot['metadata']['created_at'] ?? '';
        $lastVersion = $snapshot['metadata']['last_version'] ?? 0;

        return new Snapshot(
            $aggregateType,
            $aggregateId,
            $aggregateRoot,
            $lastVersion,
            DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', $createdAt, new DateTimeZone('UTC'))
        );
    }

    public function save(Snapshot $snapshot)
    {
        $aggregateId = $snapshot->aggregateId();
        $aggregateType = $snapshot->aggregateType()->toString();

        $bucket = $this->getGridFs($aggregateType);

        $fileId = new ObjectId();

        $bucket->uploadFromStream(
            $fileId,
            $this->createStream(\serialize($snapshot->aggregateRoot())),
            [
                '_id' => $fileId,
                'metadata' => [
                    'aggregate_id' => $aggregateId,
                    'aggregate_type' => $aggregateType,
                    'last_version' => $snapshot->lastVersion(),
                    'created_at' => $snapshot->createdAt()->format('Y-m-d\TH:i:s.u'),
                ],
            ]
        );

        $this->removeOldSnapshots($aggregateType, $aggregateId);
    }

    public function removeOldSnapshots(string $aggregateType, string $aggregateId): void
    {
        $bucket = $this->getGridFs($aggregateType);

        $cursor = $bucket->find(
            [
                'metadata.aggregate_id' => $aggregateId,
                'metadata.aggregate_type' => $aggregateType,
            ],
            [
                'projection' => ['_id' => 1],
                'sort' => ['metadata.last_version' => -1],
                'skip' => 1,
            ]
        );

        foreach ($cursor as $oldSnapshot) {
            try {
                $bucket->delete($oldSnapshot['_id']);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    public function deleteByAggregateType(AggregateType $aggregateType): void
    {
        $aggregateType = $aggregateType->toString();

        $bucket = $this->getGridFs($aggregateType);

        $cursor = $bucket->find(
            [
                'metadata.aggregate_type' => $aggregateType,
            ],
            ['projection' => ['_id' => 1]]
        );

        foreach ($cursor as $doc) {
            try {
                $bucket->delete($doc['_id']);
            } catch (\Throwable $e) {
                continue;
            }
        }
    }

    public function deleteByAggregateId(string $aggregateId, AggregateType $aggregateType): void
    {
        $bucket = $this->getGridFs($aggregateType->toString());

        $snapshot = $bucket->findOne(
            [
                'metadata.aggregate_id' => $aggregateId,
                'metadata.aggregate_type' => $aggregateType->toString(),
            ],
            [
                'projection' => ['_id' => 1],
            ]
        );

        try {
            $bucket->delete($snapshot['_id']);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function getGridFs(string $aggregateType): Bucket
    {
        return $this->client->selectDatabase($this->dbName)->selectGridFSBucket([
            'bucketName' => $this->getGridFsName($aggregateType),
            'readConcern' => $this->readConcern,
            'writeConcern' => $this->writeConcern,
        ]);
    }

    private function getGridFsName(string $aggregateType): string
    {
        if (isset($this->snapshotGridFsMap[$aggregateType])) {
            $gridFsName = $this->snapshotGridFsMap[$aggregateType];
        } else {
            $gridFsName = $this->defaultSnapshotGridFsName;
        }

        return $gridFsName;
    }

    /**
     * Creates an in-memory stream with the given data.
     *
     * @param string $data
     * @return resource
     */
    private function createStream(string $data = '')
    {
        $stream = \fopen('php://temp', 'w+b');
        \fwrite($stream, $data);
        \rewind($stream);

        return $stream;
    }

    public static function createIndexes(Collection $collection): void
    {
        $collection->createIndex(
            [
                'metadata.aggregate_type' => 1,
                'metadata.aggregate_id' => 1,
                'metadata.last_version' => -1,
            ],
            [
                'background' => true,
            ]
        );
    }
}
