<?php declare(strict_types=1);
namespace R3T\Database;

use R3T\Exception\R3TException;
/**
 * Static PDO wrapper to execute queries easily.
 */
abstract class Database{
	public static \PDO $connection;
	/**
	 * dsn prefix
	 */
	public static string $driver;
	/**
	 * Rest of the DSN after `static::$driver.':'`
	 */
	public static string $dsn;
	public static string $user = '';
	public static string $pass = '';
	public static array $options = [
		\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
		\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
		\PDO::ATTR_TIMEOUT => 10,
		\PDO::ATTR_EMULATE_PREPARES => false,
	];
	public static function connect():void{
		if(static::$driver === 'sqlite'){
			$dsn = "sqlite:".static::$dsn;
		}else{
			$dsn = static::$driver.':'.static::$dsn;
		}
		static::$connection = new \PDO($dsn,static::$user,static::$pass,static::$options);
		// if(static::$driver === 'sqlite'){
		// 	//
		// }
		// elseif(static::$driver === 'mysql'){
		// 	static::$connection->setAttribute(\Pdo\Mysql::ATTR_FOUND_ROWS,true);
		// }// ...
	}
	public static function getConnection():\PDO{
		if(!isset(static::$connection)){
			static::connect();
		}
		return static::$connection;
	}
	/**
	 * Prepare and execute the PDOStatement.
	 * @param array<int|string,mixed> $params Positional (`?`) or named (`:key`) bind values.
	 */
	public static function run(string $query, array $params = []):\PDOStatement{
		$statement = static::getConnection()->prepare($query);
		$statement->execute($params);
		return $statement;
	}
	/**
	 * Returns result of `PDO::exec()`.
	 */
	public static function exec(string $query):int|bool{
		return static::getConnection()->exec($query);
	}
	/**
	 * Returns DB data with `fetchAll()`
	 * @param array<int|string,mixed> $params
	 * @return array<int, array<string, mixed>> A list of database rows.
	 */
	public static function select(string $query, array $params = []):array{
		$statement = static::run($query,$params);
		return $statement->fetchAll();
	}
	/**
	 * Returns DB data with `yield` using `fetch()`
	 * @param array<int|string,mixed> $params
	 * @return \Generator<int, array<string,mixed>>
	 */
	public static function selectUnbuffered(string $query, array $params = []):\Generator{
		$statement = static::run($query,$params);
		while($data = $statement->fetch(\PDO::FETCH_ASSOC)){
			yield $data;
		}
	}
	public static function rowCount(\PDOStatement $statement):int{
		return $statement->rowCount();
	}
	public static function lastInsertId(string|null $name = null):string|bool{
		return static::getConnection()->lastInsertId($name);
	}
	public static function beginTransaction():bool{
		return static::getConnection()->beginTransaction();
	}
	public static function rollback():bool{
		return static::getConnection()->rollBack();
	}
	public static function selectColumns(string $table):array{
		return match(static::$driver){
			'sqlite' => static::sanitizeSelectColumnsSqlite($table),
			'pgsql' => static::sanitizeSelectColumnsPgsql($table),
			'mysql' => static::sanitizeSelectColumnsMysql($table),
			default => throw new R3TException('wrong driver?')
		};
	}
	public static function sanitizeSelectColumnsSqlite(string $table):array{
		$raw = static::select("select name from pragma_table_info('".$table."')");
		if($raw === []){
			throw new R3TException("Table '$table' does not exist in the database.");
		}
		$sanitized = [];
		foreach($raw as $column){
			$sanitized[] = $column['name'];
		}
		return $sanitized;
	}
	public static function sanitizeSelectColumnsPgsql(string $table):array{
		$raw = static::select("SELECT column_name FROM information_schema.columns WHERE table_name = '".$table."'");
		if($raw === []){
			throw new R3TException("Table '$table' does not exist in the database.");
		}
		$sanitized = [];
		foreach($raw as $column){
			$sanitized[] = $column['column_name'];
		}
		return $sanitized;
	}
	public static function sanitizeSelectColumnsMysql(string $table):array{
		$raw = static::select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$table."'");
		if($raw === []){
			throw new R3TException("Table '$table' does not exist in the database.");
		}
		$sanitized = [];
		foreach($raw as $column){
			$sanitized[] = $column['COLUMN_NAME'];
		}
		return $sanitized;
	}
	/**
     * @todo not fully implemented yet. remaining: mysql, pgsql
     */
    public static function selectAllTables():array{
		return match(static::$driver){
			'sqlite' => static::sanitizeSelectTablesSqlite(),
			'pgsql' => static::sanitizeSelectTablesPgsql(),
			'mysql' => static::sanitizeSelectTablessMysql(),
			default => throw new R3TException('wrong driver?')
		};
	}
	public static function sanitizeSelectTablesSqlite():array{
		$raw = static::select("SELECT name FROM sqlite_schema WHERE type='table'");
		$sanitized = [];
		foreach($raw as $table){
			$sanitized[] = $table['name'];
		}
		return $sanitized;
	}
	public static function sanitizeSelectTablesPgsql():array{
		// @todo not implemented yet
		return [];
	}
	public static function sanitizeSelectTablessMysql():array{
		// @todo not fully tested yet
		$raw = static::select("SELECT TABLE_NAME FROM information_schema.TABLES");
		foreach($raw as $table){
			$sanitized[] = $table['TABLE_NAME'];
		}
		return $sanitized;
	}
}
