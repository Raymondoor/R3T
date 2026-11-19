<?php declare(strict_types=1);
namespace R3T\Database;
/**
 * Static PDO wrapper to execute queries easily.
 */
class TrgtDB extends Database{
	public static \PDO $connection;
	public static string $driver;
	public static string $dsn;
	public static string $user;
	public static string $pass;
	public static array $options = [
		\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
		\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
		\PDO::ATTR_TIMEOUT => 10,
		\PDO::ATTR_EMULATE_PREPARES => false,
		\PDO::ATTR_STRINGIFY_FETCHES => true,
	];
}
