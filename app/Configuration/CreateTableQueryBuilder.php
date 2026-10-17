<?php declare(strict_types=1);
namespace R3T\Configuration;
use R3T\Configuration\TableConfiguration;
use R3T\Exception\R3TException;
use R3T\Database\OprDB;
use R3T\Util\DBQueryFormatter;
class CreateTableQueryBuilder{
    public string $query;
    public function __construct(public TableConfiguration $config)
    {
    }
    public function createQuery():void{
        $enc = DBQueryFormatter::getEncapsulation($this->config->driver);
        $this->query = 'CREATE TABLE IF NOT EXISTS '.$enc.$this->config->tablename.$enc.' (';
        $type = DBQueryFormatter::getTextType($this->config->driver);
        foreach($this->config->columns as $column){
            $this->query .= $enc.$column.$enc.(empty($type) ? '' : ' '.$type).',';
        }
        $this->query = rtrim($this->query,",");
        $this->query .= ")";
    }
}