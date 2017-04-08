# snapshot-mongodb-adapter

MongoDB Adapter for the Snapshot Store

[![Build Status](https://travis-ci.org/prooph/snapshot-mongodb-adapter.svg?branch=master)](https://travis-ci.org/prooph/snapshot-mongodb-adapter)
[![Coverage Status](https://coveralls.io/repos/prooph/snapshot-mongodb-adapter/badge.svg?branch=master&service=github)](https://coveralls.io/github/prooph/snapshot-mongodb-adapter?branch=master)
[![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/prooph/improoph)

#CAUTION: Support for the adapter will end at 31 December 2017. Use https://github.com/prooph/mongodb-snapshot-store instead!

## Set Up

How to use the adapter is explained in the [prooph/event-store docs](https://github.com/prooph/event-store/blob/master/docs/snapshots.md).

## Interop Factory

Some general notes about how to use interop factories shipped with prooph components can be found in the [event store docs](https://github.com/prooph/event-store/blob/master/docs/interop_factories.md).
Use the [mongodb snapshot adapter factory](src/Container/MongoDbSnapshotAdapterFactory.php) to set up the adapter. If your IoC container supports callable factories
you can register the factory under a service id of your choice and configure this service id as `$config['prooph']['snapshot_store']['adpater']['type'] = <adapter_service_id>`.


## Indexing

For faster access to the snapshots, it's recommended to index the metadata.

For example:

    db.user_snapshot.files.createIndex({aggregate_type: 1, aggregate_id: 1});


Support
-------

- Ask questions on [prooph-users](https://groups.google.com/forum/?hl=de#!forum/prooph) google group.
- File issues at [https://github.com/prooph/snapshot-mongodb-adapter/issues](https://github.com/prooph/snapshot-mongodb-adapter/issues).
- Say hello in the [prooph gitter](https://gitter.im/prooph/improoph) chat.

Contribute
----------

Please feel free to fork and extend existing or add new features and send a pull request with your changes!
To establish a consistent code quality, please provide unit tests for all your changes and may adapt the documentation.

# Dependencies

Please refer to the project [composer.json](composer.json) for the list of dependencies.

License
-------

Released under the [New BSD License](LICENSE).
