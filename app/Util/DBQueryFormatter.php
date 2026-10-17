<?php declare(strict_types=1);
namespace R3T\Util;
class DBQueryFormatter{
    /**
     * Adds a `:` in front of the key of parameters for a `:foo` style prepared statement.
     * ['foo' => 'bar'] becomes [':foo' => 'bar']
     */
    public static function prependColon(iterable $data):iterable{
        $returnArray = [];
        foreach($data as $key => $value){
            $returnArray[':'.$key] = $value;
        }
        return $returnArray;
    }
    /**
     * Wraps each element in an array with given string.
     *  If '"' was given, ['foo'] becomes ['"foo"']
     */
    public static function wrapWithEncapsulation(iterable|string $data, string $encapsulation = '"'):iterable{
        if(is_string($data)){
            $data = [$data];
        }
        $returnArray = [];
        foreach($data as $column){
            $returnArray[] = $encapsulation.$column.$encapsulation;
        }
        return $returnArray;
    }
    public static function getEncapsulation(string $driver):string{
        if($driver === 'mysql'){
			return '`';
		}
		return '"';
    }
    public static function wrapWithEncapsulationOnDriver(iterable|string $data, string $driver):iterable{
        return self::wrapWithEncapsulation($data,self::getEncapsulation($driver));

    }
    public static function getTextType(string $driver):string{
        if($driver === 'sqlite'){
            return ''; // Type Affinity
        }elseif($driver === 'mysql'){
            return 'LONGTEXT';
        }elseif($driver === 'pgsql'){
            return 'TEXT';
        }
        return 'VARCHAR';
    }
}