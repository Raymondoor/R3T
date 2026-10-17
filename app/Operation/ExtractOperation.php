<?php declare(strict_types=1);
namespace R3T\Operation;
use R3T\Database\OprDB;
use R3T\Util\DBQueryFormatter;
class ExtractOperation extends Operation{
    public array $columns;
    /**
     * column to extract
     */
    public function extract(array|string $columns):self{
        $this->columns = is_string($columns) ? [$columns] : $columns;
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
        foreach($this->columns as $column){
            if(self::columnExists($columns, [$column]) === false){
                throw new \R3T\Exception\R3TValueException("Column '$column' does not exist in the table.");
            }
        }
        return $this->columns;
    }
    public function selectQueryFromPrevious():string{
        $columns = DBQueryFormatter::wrapWithEncapsulationOnDriver($this->columns, OprDB::$driver);
        return 'SELECT '.implode(',', $columns).' FROM '.DBQueryFormatter::getEncapsulation(OprDB::$driver).$this->previousOperation->tableConfig->tablename.DBQueryFormatter::getEncapsulation(OprDB::$driver);
    }
    public function transform(iterable $data):iterable{
        foreach($data as $row){
            yield $row;
        }
    }
}