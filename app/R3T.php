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
use R3T\Util\TerminalHelper;
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
     * @return OperationManager Instance of OperationManager to register operations and manage the transfer process.
     */
    public static function operator():OperationManager{
        return OperationManager::boot();
    }
    /**
     * Checks config validity and reports exceptions and detailed relations of all operations in registry.
     * This has slight impact on Database as it may select the columns from the source and target DBs to validate the operations. Although it does not write any data.
     * @todo implement warning feat on src & target column if not used. catch exception, and report where gone wrong. make relations diagram or smth Nd style a bit
     */
    public static function analyzeVerbose():\Generator{
        OperationManager::setAllTableConfiguration();
        foreach(OperationManager::$operationList as $operation){
            $set = [];
            $reflection = new \ReflectionClass($operation::class);
            $set['operation'] = $reflection->getShortName();
            $set['id'] = $operation->id;
            $set['label'] = $operation->label;
            if(is_null($operation->exception)){
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
            $set['exception'] = is_null($operation->exception) ? null : [
                'type' => $operation->exception::class,
                'message' => $operation->exception->getMessage(),
                'place' => $operation->exception->getFile().':'.$operation->exception->getLine(),
                'previous' => is_null($operation->exception->getPrevious()) ? null : $operation->exception->getPrevious()::class
            ];
            if(self::isCli()){
                echo $operation->id.' : '.TerminalHelper::color('blue', 'Analyzing... ');
                if(is_null($operation->exception)){
                    echo TerminalHelper::color('green', 'OK').PHP_EOL;
                }else{
                    echo TerminalHelper::color('red', 'Failed').PHP_EOL;
                    echo "\t".TerminalHelper::color('yellow', 'Exception')." : ".$set['exception']['type'].PHP_EOL;
                    echo "\t  ".TerminalHelper::color('yellow', 'Message')." : ".$set['exception']['message'].PHP_EOL;
                }
            }
            yield $set;
        }
    }
    /**
     * Analyzes the current registry.
     * @return array[]
     */
    public static function analyze():mixed{
        $maps = [];
        foreach(self::analyzeVerbose() as $set){
            $maps[] = $set;
        }
        return $maps;
    }
    /**
     * Create all intermediate tables registered in operation. Is created in database set in `R3T::setOperationalDB()`.
     * @param bool $reset `true` deletes all existing tables inside operational DB. Useful when re-running many times to test the configuration.
     * @throws \Exception if any operation has exception, or if the operational DB is not connected.
     */
    public static function createTables(bool $reset = false):bool{
        OperationManager::setAllTableConfiguration();
        if(self::isDBConnected('opr') === false){
            throw new R3TException("Cannot create tables. Operational DB is not connected. Please run `R3T::connectDBs()` first.");
        }
        if($reset)OprDB::dropAllTables();
        array_map(function($operation):void{
            if($operation->exception !== null){
                throw new R3TException("Cannot create table for operation: `".$operation->id.'` due to exception: "'.$operation->exception->getMessage().'". Please check the configuration and fix the exception before proceeding.');
            }
        }, OperationManager::$operationList);
        foreach(OperationManager::$operationList as $operation){
            if($operation instanceof SettleOperation){
                continue;
            }
            if(OprDB::exec($operation->tableConfig->creator->query) === false){
                throw new R3TException("Failed to create intermediate table: ".$operation->tableConfig->tablename);
            }
        }
        if(self::isCli()){
            // yellow
            echo 'All '.TerminalHelper::color('yellow', (string)count(OperationManager::$operationList)).' intermediate tables created successfully in Operational DB.'.PHP_EOL;
        }
        return true;
    }
    /**
     * Execute the transfer apart from settling to new DB. This will create all new intermediate tables, and transfer the data from source to operational DB.
     * This is a generator function that yields the progress of each operation. Each yield will return a boolean indicating whether the operation was successful or not. If an operation fails, the generator will throw an exception and stop the transfer process.
     * Since it is a generator it has to be consumed in a loop to execute the transfer.
     * @return \Generator array
     */
    public static function transferVerbose():\Generator{
        // @todo [2026/11/16 from:torhc17311@gmail.com to:all] should I even run createTables() here? or should I let the caller run it?
        self::createTables(true); // delibarate no catch here, let the exception propagate to the caller
        foreach(OperationManager::$operationList as $operation){
            $operationRecord = [
                'operation' => $operation::class,
                'id' => $operation->id,
                'label' => $operation->label,
                'success' => false,
                'rows' => 0,
            ];
            if(self::isCli()){
                TerminalHelper::spinnerStart($operation->id.' : '.TerminalHelper::color('cyan', 'Started... '));
            }
            if($operation instanceof SettleOperation){
                if(self::isCli()){
                    TerminalHelper::spinnerFinish(TerminalHelper::color('white', 'Skipped for later.'));
                }
            }else{
                if($operation instanceof CaptureOperation){
                    $data = SrcDB::selectUnbuffered($operation->selectQueryFromPrevious());
                }else{
                    $data = OprDB::selectUnbuffered($operation->selectQueryFromPrevious());
                }
                $newData = $operation->transform($data);
                $count = 0;
                $chunkSize = 1024; // number of rows to insert at once, to reduce the number of queries and improve performance. Has to be adjustable based on memory and stuff, atm is fixed.
                $buffer = [];
                OprDB::beginTransaction();
                foreach($newData as $newRecord){
                    $buffer[] = $newRecord;
                    if(self::isCli()){
                        TerminalHelper::spinnerUpdate();
                    }
                    if(count($buffer) >= $chunkSize){
                        foreach($buffer as $record){
                            // let it throw exception if failed, and let the caller handle it. This is to ensure that the transfer process stops immediately when an error occurs, and the caller can decide how to handle the error (e.g., log it, retry, etc.).
                            OprDB::run($operation->tableConfig->inserter->query, DBQueryFormatter::prependColon($record));
                            $count++;
                        }
                        OprDB::commit();
                        $buffer = [];
                        OprDB::beginTransaction();
                    }
                }
                // Flush remaining records
                foreach($buffer as $record){
                    OprDB::run($operation->tableConfig->inserter->query, DBQueryFormatter::prependColon($record));
                    $count++;
                }
                OprDB::commit();
                $operationRecord['rows'] = $count;
                if(self::isCli()){
                    TerminalHelper::spinnerFinish(TerminalHelper::color('green', 'Transfer completed.').' '.TerminalHelper::color('yellow', number_format($count)).' rows transfered.');
                }
            }
            $operationRecord['success'] = true;
            usleep(100000); // sleep for 0.1 second to allow user to recognize the progress of the transfer. This is to avoid overwhelming the user with too much information at once, and to give them a chance to see the progress of each operation. The sleep time can be adjusted based on user preference or system performance.
            yield $operationRecord;
        }
    }
    /**
     * Execute the transfer apart from settling to new DB. This will create all new intermediate tables, and transfer the data from source to operational DB.
     */
    public static function transfer():bool{
        try{
            foreach(self::transferVerbose() as $result){
                if($result['success'] === false){
                    throw new R3TException("Transfer failed for operation: ".$result['id']." (".$result['label'].")");
                }
            }
            return true;
        }catch(\Throwable $t){
            if(self::isCli()){
                echo $t->getMessage();
            }
            return false;
        }
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
                if(self::isCli()){
                    TerminalHelper::spinnerStart($operation->id.' : '.TerminalHelper::color('cyan', 'Started... '));
                }
                $data = OprDB::selectUnbuffered($operation->selectQueryFromPrevious());
                $newData = $operation->transform($data);
                $count = 0;
                $chunkSize = 1024; // number of rows to insert at once, to reduce the number of queries and improve performance. Has to be adjustable based on memory and stuff, atm is fixed.
                $buffer = [];
                TrgtDB::beginTransaction();
                foreach($newData as $newRecord){
                    $buffer[] = $newRecord;
                    if(self::isCli()){
                        TerminalHelper::spinnerUpdate();
                    }
                    if(count($buffer) >= $chunkSize){
                        foreach($buffer as $record){
                            // let it throw exception if failed, and let the caller handle it. This is to ensure that the transfer process stops immediately when an error occurs, and the caller can decide how to handle the error (e.g., log it, retry, etc.).
                            TrgtDB::run($operation->tableConfig->inserter->query, DBQueryFormatter::prependColon($record));
                            $count++;
                        }
                        TrgtDB::commit();
                        $buffer = [];
                        TrgtDB::beginTransaction();
                    }
                }
                // Flush remaining records
                foreach($buffer as $record){
                    TrgtDB::run($operation->tableConfig->inserter->query, DBQueryFormatter::prependColon($record));
                    $count++;
                }
                TrgtDB::commit();
                if(self::isCli()){
                    TerminalHelper::spinnerFinish(TerminalHelper::color('green', 'Settle completed.').' '.TerminalHelper::color('yellow', number_format($count)).' rows settled.');
                }
            }
            usleep(100000); // sleep for 0.1 second to allow user to recognize the progress of the settle. This is to avoid overwhelming the user with too much information at once, and to give them a chance to see the progress of each operation. The sleep time can be adjusted based on user preference or system performance.
        }
        return true;
    }
    /**
     * Transfer and settle to new DB. Does all methods for transfering the data and settling to the new DB.
     * Order is `self::createTables()`, `self::transfer()`, `self::settle()`.
     * @todo not implemented yet correctly
     */
    public static function execute():bool{
        if(self::transform()){
            return self::settle();
        }
        return false;
    }
    private static function isCli():bool{
        return self::$config['cli'] === true;
    }
    /**
     * @param 'src'|'opr'|'trgt'|'all' $dbType
     */
    private static function isDBConnected(string $dbType):bool{
        return match($dbType){
            'src' => self::$isSrcDBSet,
            'opr' => self::$isOprDBSet,
            'trgt' => self::$isTrgtDBSet,
            'all' => self::$isSrcDBSet && self::$isOprDBSet && self::$isTrgtDBSet,
            default => throw new R3TException("Invalid DB type: ".$dbType),
        };
    }
}