<?php

<<<<<<< Updated upstream
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
=======
declare(strict_types=1);

namespace App\Database;

use PDO;

class Connection
{
    #Variável de conexão com banco de dados.
    private static $pdo = null;

    private static function loadEnv(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Arquivo .env não encontrado: {$path}");
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            #Ignora comentários
            if (str_starts_with(trim($line), '#')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);
            #Remove aspas ao redor do valor, se houver
            $value = trim($value, '"\'');
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    #Método de conexão com banco de dados.
    public static function connection(): PDO
    {
        #Tentativa de estabelecer uma conexão com o banco de dados com tratamento de exceções.
        try {
            #Caso já exista a conexão com banco de dados retornamos a conexão.
            if (self::$pdo) {
                return self::$pdo;
            }
            #Ajuste o caminho para a raiz do seu projeto
            self::loadEnv(__DIR__ . '/../../.env');
            # Definindo as opções para a conexão PDO.
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, # Lança exceções em caso de erros.
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, # Define o modo de fetch padrão como array associativo.
                PDO::ATTR_EMULATE_PREPARES => false, # Desativa a emulação de prepared statements.
                #PDO::ATTR_PERSISTENT => true, # Conexão persistente para melhorar performance.
                PDO::ATTR_STRINGIFY_FETCHES => false, # Desativa a conversão de valores numéricos para strings.
            ];
            # Criação da nova conexão PDO com os parâmetros do banco de dados.
            self::$pdo = new PDO(
                sprintf(
                    '%s:host=%s;port=%s;dbname=%s',
                    $_ENV['DB_CONNECTION'],
                    $_ENV['DB_HOST'],
                    $_ENV['DB_PORT'],
                    $_ENV['DB_NAME']
                ),
                $_ENV['DB_USER'],
                $_ENV['DB_PASSWORD'],
                $options # Opções para a conexão PDO.
            );
            self::$pdo->exec("SET NAMES 'utf8'");
            #Caso seja bem-sucedida a conexão retornamos a variável $pdo;
            return self::$pdo;
        } catch (\PDOException $e) {
            throw new \PDOException("Erro: " . $e->getMessage(), 1);
        }
>>>>>>> Stashed changes
    }
}
