<?php declare(strict_types=1);
namespace R3T\Operation;

use ReflectionProperty;
use R3T\Exception\{R3TException, R3TValueException};

class OperationManager{
    /**
     * @var Operation[]
     */
    public static array $operationList = [];
    public static int $idCounter = 0;

    public static function boot():self{
        return new static();
    }
    public static function generateId():string{
        // internal counter is int but generate with string zero padding for better readability
        return 'opr_'.str_pad((string)self::incrementIdCounter(), 4, '0', STR_PAD_LEFT);
    }
    private static function incrementIdCounter():int{
        return ++self::$idCounter;
    }
    /**
     * Intercept and register the operation to list and return directly.
     */
    public static function register(Operation $operation):Operation{
        self::$operationList[] = $operation;
        return $operation;
    }
    /**
     * Validates and creates a Table configuration.
     * Stores `Operation::exception` if there is an incompatible configuration
     * @return void
     */
    public static function setAllTableConfiguration():void{
        $rp = new ReflectionProperty(Operation::class, 'tableConfig');
        foreach(self::$operationList as $op){
            if($rp->isInitialized($op)){
                continue;
            }
            try{
                if($op->previousOperation !== null && !$rp->isInitialized($op->previousOperation)){ // Root operations such as CaptureOperation have no previous operation (set to null).
                    throw new R3TException("Cannot set table configuration for operation `".$op->id."`, previous operation's configuration (`".$op->previousOperation->id."`) is not initialized.");
                }
                if(is_null($op->previousOperation) || is_null($op->previousOperation->exception)){
                    $op->setTableConfiguration();
                }else{
                    $op->exception = new R3TException('Previous operation '.$op->previousOperation->id.' has an unresolved error',$op->previousOperation->exception->getCode(),$op->previousOperation->exception);
                }
            }catch(\Throwable $t){
                if(!is_null($op->previousOperation) && !is_null($op->previousOperation->exception)){
                    $op->exception = new R3TException('Previous operation '.$op->previousOperation->id.' has an unresolved error',$op->previousOperation->exception->getCode(),$op->previousOperation->exception);
                }else{
                    $op->exception = $t;
                }
            }
        }
    }
}