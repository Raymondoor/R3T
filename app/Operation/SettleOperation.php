<?php declare(strict_types=1);
namespace R3T\Operation;
use R3T\Database\TrgtDB;
use R3T\Configuration\TableConfiguration;
use R3T\Exception\R3TException;
/**
 * Magic operation to treat target DB insert as a settlement operation. This operation will not generate any new columns, but will validate the existing columns and ensure that the data is settled correctly.
 */
class SettleOperation extends Operation{
    public bool $temporaryConfiguration = false;
    public string $table;
    public array $tableColumns;
    /**
     * @var string[]
     * @todo implement type conversions at the last moment?
     */
    public array $dataTypes = [];
    /**
     * Creates a new table configuration and sets to `$this->tableConfig`
     */
    public function setTableConfiguration():void{
        $this->tableConfig = new TableConfiguration($this->table, $this->validateThenGenerateColumns());
        $this->tableConfig->setDriver(TrgtDB::$driver);
        $this->tableConfig->setInsert();
    }
    public function settle(string $table):self{
        $this->table = $table;
        return $this;
    }
    public function settleColumns(string $table, array|string $columns):self{
        $this->temporaryConfiguration = true;
        $this->table = $table;
        $this->tableColumns = is_array($columns) ? $columns : [$columns];
        return $this;
    }
    /**
     * Set data type of values. tpes as in PHP primitive types, not SQL types
     */
    public function withTypes(array $types):self{
        $this->dataTypes = $types;
        return $this;
    }
    public static function from(Operation $previousOperation):self{
        $o = new self();
        $o->id = OperationManager::generateId();
        $o->previousOperation = $previousOperation;
        return $o;
    }
    public function setColumnsFromTable():void{
        $this->tableColumns = TrgtDB::selectColumns($this->table);
    }
    public function validateThenGenerateColumns():array{
        $columns = $this->previousOperation->tableConfig->columns;
        if($this->temporaryConfiguration === false){
            $this->setColumnsFromTable();
        }
        $sourceColumns = $columns;
        $targetColumns = $this->tableColumns;
        sort($sourceColumns, SORT_STRING);
        sort($targetColumns, SORT_STRING);
        if($sourceColumns !== $targetColumns){
            throw new \R3T\Exception\R3TValueException("Cannot settle to target table, column names seems to not match.");
        }
        return $this->tableColumns;
    }
    public function transform(iterable $data):iterable{
        // @todo if column name exist in $this->dataType, run typeval() to change, and keep structure
        foreach($data as $row){
            $adjustedRow = []; // du stuff
            yield $row;
        }
    }
}