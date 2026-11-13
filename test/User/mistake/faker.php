<?php declare(strict_types=1);
require_once __DIR__.'/../../../vendor/autoload.php';

use R3T\Database\SrcDB;

$faker = \Faker\Factory::create();
SrcDB::$driver = 'sqlite';
SrcDB::$host = __DIR__.'/databaseS.db';
SrcDB::$user = '';
SrcDB::$pass = '';
SrcDB::connect();

for($i=0; $i<100; $i++){
    $name = $faker->name();
    $group = $faker->randomElement(['admin', 'viewer', 'guest', 'moderator', 'editor']);
    SrcDB::run('INSERT INTO users (`name`, `group`) VALUES (:name, :group)', [
        ':name' => $name,
        ':group' => $group
    ]);
    echo "Inserted user: $name with group: $group\n";
}