<?php

namespace app\aatabase;

use Doctrine\DBAL\Connection;

class Database
{
    private static ?Connection $conn = null;

    public static function connection(): Connection
    {
        if (!self::$conn) {
            self::$conn = require __DIR__ . '/config/connection.php';
        }

        return self::$conn;
    }

    public static function qb()
    {
        return self::connection()->createQueryBuilder();
    }
}
