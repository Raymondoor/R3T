

## Operations
### Join
### Extract
### Modify

needs orchestration

auto create list identifier.
need two identifiers for table config. One for the system, used also for tablenames, then another one used by users to reference the table.
users can define any string, where as id will be systematic, and restricted to usable string for a table name.

proccess interface class for basically defining. Then another query builder class that runs based on the proccess
interface class for building the tables with the correct columns sest.
    do I need data type? cuz blobs and stuff may be hard to handle, in terms of performance. no constraints nor references tho.
allow setting datatypes in settle??

rename beforehand to avoid duplication in join opr.

should be able to run even without the databases being present, modularization is needed. not just this, but the engine as well.

on error, if one goes wrong it will chain with no-initialized atm because it never set the configs on the previous opr. it has to chain, but the error message should not be php default's "not initialized", but a proper one. Dunno if putting `$error` inside the operation is good or not either.

Some database needs id counter reset. keep that in mind. Should we provide helper?

It would be better to implement an `analyze()` method to each operation rather than the manager trying to do everything?
    A consumable OperationAnalyzer helper class may be a good starting point, although the above method itlsef belongs to Operation.

Do I even have to implement my own schema builder? as in query formatter etc. I can just use doctorine/DBAL and get only the query, then just run that using my own PDO wrapper.