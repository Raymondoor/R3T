<?php declare(strict_types=1);
namespace R3T\Operation;

use R3T\Database\OprDB;
use R3T\Exception\R3TException;
use R3T\Util\DBQueryFormatter;
/**
 * @todo not tested yet
 */
class UnionOperation extends Operation{
    public Operation $unionOperation;
    public bool $unionAll = false;
    public array $fromColumns = [];
    public array $unionColumns = [];
    /**
     * @param Operation $unionOperation second operation to union with
     */
    public function union(Operation $unionOperation):self{
        $this->unionOperation = $unionOperation;
        return $this;
    }
    public function all(bool $all = true):self{
        $this->unionAll = $all;
        return $this;
    }
    /**
     * Says `from`, but is just an convention from other operations. there is no traditional order in union
     * @param Operation $previousOperation first operation to union with
     */
    public static function from(Operation $previousOperation):self{
        $o = new self();
        $o->id = OperationManager::generateId();
        $o->previousOperation = $previousOperation;
        return $o;
    }
    public function fromColumns(array|string $columns):self{
        $this->fromColumns = is_array($columns) ? $columns : [$columns];
        return $this;
    }
    public function unionColumns(array|string $columns):self{
        $this->unionColumns = is_array($columns) ? $columns : [$columns];
        return $this;
    }
    public function validateThenGenerateColumns():array{
        // on both operations, check if the columns exist, and if not, throw an exception
        if(self::columnExists($this->previousOperation->tableConfig->columns, $this->fromColumns) === false){
            throw new R3TException("Some columns in the first operation do not exist in the table.");
        }
        if(self::columnExists($this->unionOperation->tableConfig->columns, $this->unionColumns) === false){
            throw new R3TException("Some columns in the second operation do not exist in the table.");
        }
        // check if the number of columns is the same
        if(count($this->fromColumns) !== count($this->unionColumns)){
            throw new R3TException("The number of columns in the first operation does not match the number of columns in the second operation.");
        }
        return $this->fromColumns;
    }
    public function selectQueryFromPrevious():string{
        $encapsulation = DBQueryFormatter::getEncapsulation(OprDB::$driver);
        $fromColumns = DBQueryFormatter::wrapWithEncapsulationOnDriver($this->fromColumns, OprDB::$driver);
        $unionColumns = DBQueryFormatter::wrapWithEncapsulationOnDriver($this->unionColumns, OprDB::$driver);
        return 'SELECT '.implode(', ', $fromColumns).' FROM '.$encapsulation.$this->previousOperation->tableConfig->tablename.$encapsulation.' UNION'.($this->unionAll ? ' ALL' : '').' SELECT '.implode(', ', $unionColumns).' FROM '.$encapsulation.$this->unionOperation->tableConfig->tablename.$encapsulation;
    }
    public function transform(iterable $data):iterable{
        foreach($data as $row){
            yield $row;
        }
    }
}