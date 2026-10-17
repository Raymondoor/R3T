# Project Overview

R3T is a PHP tool for describing database migrations as an ordered sequence of data operations. Its goal is to make migrations that reshape data easier to organize: instead of treating a migration as a single copy from one database to another, a user can describe intermediate datasets and how they lead to the destination.

This tool is currently a work in progress. This page describes the project's purpose and architecture, not a complete usage guide.

## Intended Workflow

A migration is intended to move through three database roles:

- **Source database**: holds the original data to be migrated.
- **Operational database**: holds intermediate tables used to stage and inspect data between steps.
- **Target database**: receives the resulting data.

The intended workflow is to configure these databases, register a sequence of operations, inspect the resulting plan, prepare its intermediate tables, and then transfer and settle the data into the target. Operations are linked to prior results, so later steps can build on earlier datasets.

## Main Components

- **R3T** provides the static entry point for configuring database roles, accessing the operation manager, analyzing the registered plan, and preparing intermediate tables.
- **OperationManager** keeps the registered sequence and initializes the table configuration associated with each operation.
- **Operations** describe the stages of a migration. A shared base class supplies common behavior for validating column relationships, defining intermediate output, and selecting input from a preceding stage.
- **Database adapters** provide PDO connections and query helpers for the source, operational, and target roles.
- **Intermediate-table helpers** build the SQL used to create staging tables and insert rows into them.

## Current Scope

The current code supports configuration of PDO connections and can analyze a registered operation sequence into generated SQL. It can also create intermediate tables in the operational database. These capabilities are primarily preparation and planning infrastructure; they do not yet amount to an end-to-end migration runner.

Database-driver behavior is not yet uniform. SQLite metadata discovery has implementation, while some MySQL and PostgreSQL metadata paths are marked unfinished. The SQL generation and schema handling should therefore be treated as early-stage and subject to change.

## Documentation Scope

This overview is the conceptual entry point. Detailed setup, API reference, and operation-specific guides belong in separate documentation and can be expanded as the implementation matures.