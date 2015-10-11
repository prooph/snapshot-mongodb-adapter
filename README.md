# snapshot-mongodb-adapter

MongoDB Adapter for the Snapshot Store

[![Build Status](https://travis-ci.org/prooph/snapshot-mongodb-adapter.svg?branch=master)](https://travis-ci.org/prooph/snapshot-mongodb-adapter)
[![Coverage Status](https://coveralls.io/repos/prooph/snapshot-mongodb-adapter/badge.svg?branch=master&service=github)](https://coveralls.io/github/prooph/snapshot-mongodb-adapter?branch=master)
[![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/prooph/improoph)

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
