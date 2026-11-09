<?php declare(strict_types=1);
namespace R3T\Configuration;
/**
 * This manages the intermediate tables in sql, not the configuration itself, so it won't have relations
 */
class TableConfiguration{
    public CreateTableQueryBuilder $creator;
    public InsertQueryBuilder $inserter;
    public string $driver;
    public function __construct(
        /**
         * not set by user, but by the system, to identify the table
         */
        public string $tablename,
        public array $columns
    ){}
    /**
     * Set driver for the program to manage encapsulation
     * @param string|null $driver `null` will resolve to sqlite
     */
    public function setDriver(?string $driver = null):void{
        if(is_null($driver))$driver = 'sqlite';
        $this->driver = $driver;
    }
    public function setCreate():void{
        $this->creator = new CreateTableQueryBuilder($this);
        $this->creator->createQuery();
    }
    public function setInsert():void{
        $this->inserter = new InsertQueryBuilder($this);
        $this->inserter->createQuery();
    }
}
