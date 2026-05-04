<?php

declare(strict_types=1);

namespace App\Database\Builder;

use App\Database\Connection;

class InsertQuery
{
    private string $table;
    private array $fieldsAndValues = [];
    public static function insert(string $table): ?self
    {
        try {
            $self = new self;
            $self->table = $table;
            return $self;
            /** @phpstan-ignore catch.neverThrown */
        } catch (\Throwable $e) {
            throw new \Throwable("Restrição: " . $e->getMessage(), 1);
        }
    }
    private function createQuery(): string
    {
        if (!$this->table) {
            throw new \Exception("A consulta precisa invocar o método insert.");
        }
        if (!$this->fieldsAndValues) {
            throw new \Exception("A consulta precisa dos dados para realizar a inserção.");
        }
        $query = '';
        $query = "insert into {$this->table} (";
        $query .= implode(',', array_keys($this->fieldsAndValues)) . ') values (';
        $query .= ':' . implode(',:', array_keys($this->fieldsAndValues)) . ');';
        return $query;
    }
    public function executeQuery($query): ?bool
    {
        $connection = Connection::connection();
        $prepare = $connection->prepare($query);
        return $prepare->execute($this->fieldsAndValues);
    }
    public function save(array $fieldsAndValues): ?bool
    {
        $this->fieldsAndValues = $fieldsAndValues;
        $query = $this->createQuery();
        try {
            $IsSave = $this->executeQuery($query);
            return $IsSave;
        } catch (\PDOException $e) {
            throw new \Exception("Restrição: {$e->getMessage()}");
        }
    }
}
