<?php declare(strict_types=1);
require_once __DIR__.'/../../../vendor/autoload.php';

use R3T\Database\SrcDB;

$faker = \Faker\Factory::create();
SrcDB::$driver = 'sqlite';
SrcDB::$dsn = __DIR__.'/databaseS.db';
SrcDB::$user = '';
SrcDB::$pass = '';
SrcDB::connect();
// dunno which is heavy, the faker or the transfer script, but prod carry such data so it's fair. I ran this twice, so double the data.
for($i=0; $i<8_192; $i++){ // (1,048,576 = 1024^2) / 128 = 8,192
    SrcDB::beginTransaction(); // divide to chunks to avoid memory overflow, but still heavy.
    for($j=0; $j<128; $j++){ // 128 insertions per transaction, 128*8,192 = 1,048,576
        $name = $faker->name();
        $group = $faker->randomElement([ // 20
            'admin', 'viewer', 'guest', 
            'moderator', 'editor', 'contributor', 
            'subscriber', 'author', 'manager', 
            'analyst', 'developer', 'tester', 
            'support', 'operator', 'coordinator', 
            'specialist', 'consultant', 'strategist', 
            'planner', 'designer'
        ]);
        
        SrcDB::run('INSERT INTO users (`name`, `group`) VALUES (:name, :group)', [
            ':name' => $name,
            ':group' => $group
        ]);
        echo "Inserted user: $name with group: $group\n";
    }
    SrcDB::commit();
}