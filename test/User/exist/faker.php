<?php declare(strict_types=1);
require_once __DIR__.'/../../../vendor/autoload.php';

use R3T\Database\SrcDB;

$faker = \Faker\Factory::create();
SrcDB::$driver = 'sqlite';
SrcDB::$dsn = __DIR__.'/databaseS.db';
SrcDB::$user = '';
SrcDB::$pass = '';
SrcDB::connect();

for($i=0; $i<1024; $i++){
    $name = $faker->name();
    $group = $faker->randomElement(['admin', 'viewer', 'guest', 'moderator', 'editor', 'contributor', 'subscriber', 'author', 'manager', 'analyst']);
    SrcDB::run('INSERT INTO users (`name`, `group`) VALUES (:name, :group)', [
        ':name' => $name,
        ':group' => $group
    ]);
    echo "Inserted user: $name with group: $group\n";
}