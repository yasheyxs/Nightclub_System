<?php

declare(strict_types=1);

function getPdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = "aws-1-us-east-2.pooler.supabase.com";
    $port = "5432";
    $dbname = "postgres";
    $user = "postgres.kxvogvgsgwfvtmidabyp";
    $password = "lapicero30!";

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
        $host,
        $port,
        $dbname
    );

    $pdo = new PDO(
        $dsn,
        $user,
        $password,
        [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
        ]
    );

    $pdo->exec("SET TIME ZONE 'UTC'");

    return $pdo;
}
