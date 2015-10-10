# snapshot-mongodb-adapter
MongoDB Adapter for the Snapshot Store

## Indexing

For faster access to the snapshots, it's recommended to index the metadata.

For example:

    db.user_snapshot.files.createIndex({aggregate_type: 1, aggregate_id: 1});
