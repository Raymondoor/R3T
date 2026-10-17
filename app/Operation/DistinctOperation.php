<?php declare(strict_types=1);
namespace R3T\Operation;

use R3T\Database\OprDB;
use R3T\Util\DBQueryFormatter;
class DistinctOperation extends Operation{
    public string $column;
    /**
     * distinct column of choise. can only be one column
     */
    public function distinct(string $column):self{
        $this->column = $column;
        return $this;
    }
    public static function from(Operation $previousOperation):self{
        $o = new self();
        $o->id = OperationManager::generateId();
        $o->previousOperation = $previousOperation;
        return $o;
    }
    public function validateThenGenerateColumns():array{
        $columns = $this->previousOperation->tableConfig->columns;
        if(self::columnExists($columns, [$this->column]) === false){
            throw new \R3T\Exception\R3TValueException("Column '$this->column' does not exist in the table.");
        }
        return [$this->column];
    }
    public function selectQueryFromPrevious():string{
        $encapsulation = DBQueryFormatter::getEncapsulation(OprDB::$driver);
        return 'SELECT DISTINCT '.$encapsulation.$this->column.$encapsulation.' FROM '.$encapsulation.$this->previousOperation->tableConfig->tablename.$encapsulation;
    }
    public function transform(iterable $data):iterable{
        foreach($data as $row){
            yield $row;
        }
    }
}