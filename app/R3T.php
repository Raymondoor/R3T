<?php declare(strict_types=1);
namespace R3T;

use R3T\Database\SrcDB;
use R3T\Database\OprDB;
use R3T\Database\TrgtDB;
use R3T\Exception\R3TException;
use R3T\Operation\CaptureOperation;
use R3T\Operation\SettleOperation;
use R3T\Operation\OperationManager;
use R3T\Util\DBQueryFormatter;
class R3T{
    public static array $config = [
        'cli' => true,
        'nodata' => false,
        'debug' => false,
        'log' => false,
    ];
    public static bool $isSrcDBSet = false;
    public static bool $isOprDBSet = false;
    public static bool $isTrgtDBSet = false;
    public static function boot(array $config = []):void{
        self::$config = array_merge(self::$config, $config);
    }
    public static function setSourceDB(string $driver, string $dsn, string $user = '', string $pass = '', array $options = []):void{
        SrcDB::$driver = $driver;
        SrcDB::$dsn = $dsn;
        SrcDB::$user = $user;
        SrcDB::$pass = $pass;
        SrcDB::$options = SrcDB::$options + $options;
        self::$isSrcDBSet = true;
    }
    public static function setOperationalDB(string $driver, string $dsn, string $user = '', string $pass = '', array $options = []):void{
        OprDB::$driver = $driver;
        OprDB::$dsn = $dsn;
        OprDB::$user = $user;
        OprDB::$pass = $pass;
        OprDB::$options = OprDB::$options + $options;
        self::$isOprDBSet = true;
    }
    public static function setTargetDB(string $driver, string $dsn, string $user = '', string $pass = '', array $options = []):void{
        TrgtDB::$driver = $driver;
        TrgtDB::$dsn = $dsn;
        TrgtDB::$user = $user;
        TrgtDB::$pass = $pass;
        TrgtDB::$options = TrgtDB::$options + $options;
        self::$isTrgtDBSet = true;
    }
    public static function connectDBs(bool $src = true, bool $opr = true, bool $trgt = true):void{
        if($src && !self::$isSrcDBSet){
            throw new R3TException("Source DB is not set.");
        }
        if($opr && !self::$isOprDBSet){
            throw new R3TException("Operational DB is not set.");
        }
        if($trgt && !self::$isTrgtDBSet){
            throw new R3TException("Target DB is not set.");
        }
        if($src)SrcDB::connect();
        if($opr)OprDB::connect();
        if($trgt)TrgtDB::connect();
    }
    /**
     * 
     * @return OperationManager Instance of OperationManager to register operations and manage the transfer process.
     */
    public static function operator():OperationManager{
        return OperationManager::boot();
    }
    /**
     * Checks config validity and reports errors and detailed relations of all operations.
     * This has slight impact on Database as it may select the columns from the source and target DBs to validate the operations. Although it does not write any data.
     * @todo implement warning feat on src & target column if not used. catch exception, and report where gone wrong. make relations diagram or smth Nd style a bit
     */
    public static function analyze():mixed{
        OperationManager::setAllTableConfiguration();
        $maps = [];
        foreach(OperationManager::$operationList as $operation){
            $set = [];
            $reflection = new \ReflectionClass($operation::class);
            $set['operation'] = $reflection->getShortName();
            $set['id'] = $operation->id;
            $set['label'] = $operation->label;
            if(is_null($operation->error)){
                if($operation instanceof SettleOperation){
                    $set['create'] = null;
                }else{
                    $set['create'] = $operation->tableConfig->creator->query;
                }
                $set['select'] = $operation->selectQueryFromPrevious();
                $set['insert'] = $operation->tableConfig->inserter->query;
            }else{
                $set['create'] = null;
                $set['select'] = null;
                $set['insert'] = null;
            }
            $set['error'] = $operation->error;
            $maps[] = $set;
        }
        return $maps;
    }
    /**
     * Create all intermediate tables registered in operation. Is created in database set in `R3T::setOperationalDB()`.
     * @param bool $reset `true` deletes all existing tables inside operational DB. Useful when re-running many times to test the configuration.
     */
    public static function createTables(bool $reset = false):bool{
        OperationManager::setAllTableConfiguration();
        if($reset)OprDB::dropAllTables();
        foreach(OperationManager::$operationList as $operation){
            if($operation instanceof SettleOperation){
                continue;
            }
            if($operation->error !== null){
                throw new R3TException("Cannot create table for operation: `".$operation->id.'` due to error: "'.$operation->error['message'].'". Please check the configuration and fix the error before proceeding.');
            }
            if(OprDB::exec($operation->tableConfig->creator->query) === false){
                throw new R3TException("Failed to create intermediate table: ".$operation->tableConfig->tablename);
            }
        }
        return true;
    }
    /**
     * Execute the transfer apart from settling to new DB. This will create all new intermediate tables, and transfer the data from source to operational DB.
     */
    public static function transfer():bool{
        self::createTables(true);
        foreach(OperationManager::$operationList as $operation){
            if(self::$config['cli']){
                echo 'Operation: '.$operation->id.' started... ';
            }
            if($operation instanceof SettleOperation){
                if(self::$config['cli']){
                    echo 'skipped for later.'.PHP_EOL;
                }
            }else{
                if($operation instanceof CaptureOperation){
                    $data = SrcDB::selectUnbuffered($operation->selectQueryFromPrevious());
                }else{
                    $data = OprDB::selectUnbuffered($operation->selectQueryFromPrevious());
                }
                $newData = $operation->transform($data);
                foreach($newData as $newRecord){
                    OprDB::run($operation->tableConfig->inserter->query, DBQueryFormatter::prependColon($newRecord));
                }
                if(self::$config['cli']){
                    echo 'transfer completed.'.PHP_EOL;
                }
            }
        }
        return true;
    }
    /**
     * Alias for `self::transfer()`. 
     * @todo [2026/10/09 from:torhc17311@gmail.com to:@all] The terminology is not quite set yet, decide which naming is better.
     */
    public static function transform():bool{
        return self::transfer();
    }
    /**
     * Finalize the transfer and insert to the new DB. Cannot run if there is no prior operation/transfer.
     * @todo implement warning if data already exists in target before running
     */
    public static function settle():bool{
        foreach(OperationManager::$operationList as $operation){
            if($operation instanceof SettleOperation){
                if(self::$config['cli']){
                    echo 'Operation: '.$operation->id.' started... ';
                }
                $data = OprDB::selectUnbuffered($operation->selectQueryFromPrevious());
                $newData = $operation->transform($data);
                foreach($newData as $newRecord){
                    TrgtDB::run($operation->tableConfig->inserter->query, DBQueryFormatter::prependColon($newRecord));
                }
                if(self::$config['cli']){
                    echo 'settle completed.'.PHP_EOL;
                }
            }
        }
        return true;
    }
    /**
     * Transfer and settle to new DB. Does all methods for transfering the data and settling to the new DB.
     * Order is `self::createTables()`, `self::transfer()`, `self::settle()`.
     * @todo not implemented yet correctly
     */
    public static function execute():bool{
        self::transform(); // includes `self::createTables()`
        self::settle();
        return true;
    }
}