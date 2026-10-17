# R3T
A relational data transformation and transfer tool.

## Overview
A PHP tool for describing and preparing database migrations as a sequence of data operations. It separates the source, an operational staging database, and the destination so that migration work can be organized and inspected before it is applied.

## Installation
```sh
composer require raymondoor/r3t
```

## Usage
The example illustrates the intended migration flow.

### Prerequisites
1. The source database has `users` table with columns [id, name, group, created_at].
2. The target database will have two tables `users` and `groups` table with [id, name, groups_id, created_at] and [id, name, created_at] respectively.
3. "I" want to extract the groups to a separate table and instead reference them in users table by id.

```php
use R3T\R3T;
use R3T\Operation\{AddColumnsOperation, CaptureOperation, DistinctOperation, ExtractOperation, JoinOperation, ModifyValuesOperation, RenameOperation, SettleOperation, UnionOperation};

R3T::boot();
R3T::setSourceDB('sqlite', '/path/to/db.sqlite'); // original
R3T::setOperationalDB('sqlite', '/path/to/temp/db.sqlite'); // transactional DB
R3T::setTargetDB('mysql','host=localhost;dbname=r3t;port=3306','user','pass'); // target database
R3T::connectDBs(); // establish PDO connection

$opr = R3T::operator(); // returns OperationManager
$usersOriginal = $opr::register(CaptureOperation::fromTable('users')); // eg. consists of [id, name, group, created_at]

$groupsExtracted = $opr::register(ExtractOperation::from($usersOriginal)->extract('group'));
$distinctGroups = $opr::register(DistinctOperation::from($groupsExtracted)->distinct('group'));
$renameAdjustGroups = $opr::register(RenameOperation::from($distinctGroups)->rename(['group' => 'group_name']));
$addIdToGroup = $opr::register(AddColumnsOperation::from($renameAdjustGroups)->add(['groups_id']));
$populateId = $opr::register(ModifyValuesOperation::from($addIdToGroup)->modify(function(iterable $records){ // callback
	$i = 1;
	foreach($records as $record){
		$record['groups_id'] = $i;
		$i++;
		yield $record;
		// return structure must not change. only values inside
	}
}));

$joinToSyncGroupId = $opr::register(JoinOperation::from($usersOriginal)->join($populateId)->on('group_name')->source('group')->direction('left'));
$usersFinal = $opr::register(ExtractOperation::from($joinToSyncGroupId)->extract(['id', 'name', 'groups_id', 'created_at'])); // re-extract

$groupsAdjust = $opr::register(RenameOperation::from($populateId)->rename(['groups_id'=>'id','group_name'=>'name'])); // change col name on groups
$groupsAddTimestamp = $opr::register(AddColumnsOperation::from($groupsAdjust)->add(['created_at'])); // add created_at to groups
$groupsFinal = $opr::register(ModifyValuesOperation::from($groupsAddTimestamp)->modify(function(iterable $records){
	foreach($records as $record){
		$record['created_at'] = date('Y-m-d H:i:s');
		yield $record;
	}
})); // add created_at value to groups

$settleGroup = $opr::register(SettleOperation::from($groupsFinal)->settle('groups')); // table and column name must match target
$settleUser = $opr::register(SettleOperation::from($usersFinal)->settle('users')); // settle after groups to respect foreign key constraints

var_dump(R3T::analyze()); // array of operations' query
R3T::createTables(true); // creates intermediate tables. does not touch actual data yet.
R3T::transform(); // execute the transfer apart from settling to new DB
R3T::settle(); // finalize the transfer and insert to the new DB.
```

## Why R3T?

### Make complicated migrations manageable

Database migrations can become difficult when the destination is not simply a newer version of the source.

A schema may need to be split apart, combined, reorganized, or have its data represented differently. What starts as a few schema changes can quickly become a complicated chain of data transformations.

R3T takes a different approach:

> **Break the complicated migration into simple transformations.**

Rather than trying to express the entire migration as one large operation, each transformation can be kept small and composed with the others.

A complicated migration can therefore be built progressively:

```text
source
  ↓
simple transformation
  ↓
simple transformation
  ↓
simple transformation
  ↓
target
```

The complexity is still there — but it is organized into steps that can be understood individually.

This makes it possible to handle substantial changes in how data is structured without turning the migration itself into an increasingly complicated piece of code.

### Keep migrations understandable

Complexity is only half of the problem.

A migration that works today can still become difficult to understand, inspect, or change later.

R3T is designed around **immutable operations**. A transformation does not silently alter the result of an earlier transformation. Instead, each operation produces a new result that can become the basis for subsequent work.

That gives the migration a history.

Each step can be examined independently, and the relationships between steps can be analyzed rather than hidden inside a sequence of destructive changes.

The migration can therefore be treated not just as something to execute, but as something that can be **understood, analyzed, and recorded**.

When a migration changes, you can reason about what changed and where it affects the process.

> **A migration should be more than something that runs. It should be something you can understand.**

## Docs
Not written yet...