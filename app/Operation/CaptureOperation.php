<?php declare(strict_types=1);
namespace R3T\Operation;
use R3T\Database\SrcDB;
use R3T\Util\DBQueryFormatter;
/**
 * Magic operation to treat source DB as a previous operation. This operation will replicate the source DB table to a new stage ensuring the modification does not happen in source.
 */
class CaptureOperation extends Operation{
    public ?Operation $previousOperation = null;
    public bool $temporaryConfiguration = false;
    public string $table;
    public array $tableColumns;
    public function capture(string $table):self{
        $this->table = $table;
        return $this;
    }
    /**
     * Cannot be used if is a temporary configuration
     */
    public static function fromTable(string $tablename):self{
        $o = new self();
        $o->id = OperationManager::generateId();
        $o->capture($tablename);
        return $o;
    }
    public static function fromColumns(string $tablename, array $columns):self{
        $o = new self();
        $o->id = OperationManager::generateId();
        $o->capture($tablename);
        $o->tableColumns = $columns;
        $o->temporaryConfiguration = true;
        return $o;
    }
    public function validateThenGenerateColumns():array{
        if(!$this->temporaryConfiguration){
            try{
                $this->tableColumns = SrcDB::selectColumns($this->table);
            }catch(\Exception $e){
                throw new \R3T\Exception\R3TValueException('Cannot capture from source table, table "'.$this->table.'" not exist.',$e->getCode(),$e);
            }
        }
        return $this->tableColumns;
    }
    public function selectQueryFromPrevious():string{
        if(!$this->temporaryConfiguration){
            return 'SELECT * FROM '.DBQueryFormatter::getEncapsulation(SrcDB::$driver).$this->table.DBQueryFormatter::getEncapsulation(SrcDB::$driver);
        }
        return 'SELECT * FROM '.$this->table;
    }
    public function transform(iterable $data):iterable{
        foreach($data as $row){
            yield $row;
        }
    }
}