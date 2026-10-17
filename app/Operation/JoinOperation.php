<?php declare(strict_types=1);
namespace R3T\Operation;

use R3T\Database\OprDB;
use R3T\Util\DBQueryFormatter;
use R3T\Exception\R3TException;
class JoinOperation extends Operation{
    public Operation $jointOperation;
    public string $jointColumn;
    public string $sourceColumn;
    public string $direction = 'left';
    public function join(Operation $jointOperation):self{
        $this->jointOperation = $jointOperation;
        return $this;
    }
    public static function from(Operation $previousOperation):self{
        $o = new self();
        $o->id = OperationManager::generateId();
        $o->previousOperation = $previousOperation;
        return $o;
    }
    public function source(string $column):self{
        $this->sourceColumn = $column;
        return $this;
    }
    public function on(string $column):self{
        $this->jointColumn = $column;
        return $this;
    }
    /**
     * Joining direction
     * @param 'left'|'inner'|'right'|'full'|'self' $direction
     */
    public function direction(string $direction = 'LEFT'):self{
        // make sure direction is valid
        $validDirections = ['LEFT', 'INNER', 'RIGHT', 'FULL', 'SELF'];
        if(!in_array(strtoupper($direction), $validDirections, true)){
            throw new R3TException("Invalid join direction '$direction'. Valid directions are: ".implode(', ', $validDirections));
        }
        $this->direction = strtoupper($direction);
        return $this;
    }
    public function validateThenGenerateColumns():array{
        if(!$this->previousOperation::columnExists($this->previousOperation->tableConfig->columns, [$this->sourceColumn])){
            throw new \R3T\Exception\R3TValueException("Join source column does not exist");
        }
        if(!$this->jointOperation::columnExists($this->jointOperation->tableConfig->columns, [$this->jointColumn])){
            throw new \R3T\Exception\R3TValueException("Join target column does not exist");
        }
        return array_merge($this->previousOperation->tableConfig->columns, $this->jointOperation->tableConfig->columns);
    }
    public function selectQueryFromPrevious():string{
        $encapsulation = DBQueryFormatter::getEncapsulation(OprDB::$driver);
        return 'SELECT * FROM '.$encapsulation.$this->previousOperation->tableConfig->tablename.$encapsulation.' '.$this->direction.' JOIN '.$encapsulation.$this->jointOperation->tableConfig->tablename.$encapsulation.' ON '.$encapsulation.$this->previousOperation->tableConfig->tablename.$encapsulation.'.'.$encapsulation.$this->sourceColumn.$encapsulation.' = '.$encapsulation.$this->jointOperation->tableConfig->tablename.$encapsulation.'.'.$encapsulation.$this->jointColumn.$encapsulation;
    }
    public function transform(iterable $data):iterable{
        foreach($data as $row){
            yield $row;
        }
    }
}