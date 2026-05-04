<?php

declare(strict_types=1);

namespace App\Database\Builder;

use App\Database\Connection;
use PDO;

class SelectQuery
{
    private ?string $table = null;
    private ?string $fields = null;
    private string $order;
    private string $group;
    private int $limit = 10;
    private int $offset = 0;
    private array $where = [];
    private array $join = [];
    private array $binds = [];
    private string $limits;

    public static function select(string $fields = '*'): ?self
    {
        $self = new self;
        $self->fields = $fields;
        return $self;
    }
    public function from(string $table): self
    {
        $this->table = $table;
        return $this;
    }
    public function WhereAnd(string $field, string $operator, string|int|bool|float $value, ?string $logic = null): self
    {
        return $this;
    }
    public function WhereOr(string $field, string $operator, string|int|bool|float $value, ?string $logic = null): self
    {
        return $this;
    }
    public function where(string $field, string $operator, string|int|bool|float $value, ?string $logic = null): ?self
    {
        $op = strtoupper($operator);
        // placeholder baseado no nome da coluna (sem alias)
        $placeholder = str_contains($field, '.')
            ? substr($field, strpos($field, '.') + 1)
            : $field;
        $placeholder = preg_replace('/[^a-z0-9_]/i', '_', $placeholder);
        // evita colisão
        $base = $placeholder;
        $i = 1;
        while (array_key_exists($placeholder, $this->binds)) {
            $i++;
            $placeholder = "{$base}_{$i}";
        }
        // monta LHS
        $lhs = ($op === 'LIKE' || $op === 'ILIKE') ? "{$field}::TEXT" : $field;
        // valor + curingas
        $param = $value;
        if ($op === 'LIKE' || $op === 'ILIKE') {
            $param = "%" . (string)$value . "%";
        }
        $cond = "{$lhs} {$op} :{$placeholder}";
        $this->where[] = $logic ? "({$cond}) {$logic}" : "({$cond})";
        $this->binds[$placeholder] = $param;

        return $this;
    }
    public function order(string $field, string $value): ?self
    {
        $this->order = " order by {$field} {$value}";
        return $this;
    }
    public function createQuery(): ?string
    {
        if (!$this->fields) {
            throw new \Exception("A query precisa chamar o metodo select");
        }
        if (!$this->table) {
            throw new \Exception("A query precisa chamar o metodo from");
        }
        $query = '';
        $query = 'select ';
        $query .= $this->fields . ' from ';
        $query .= $this->table;
        $query .= (isset($this->join) and (count($this->join) > 0)) ? implode(' ', $this->join) : '';
        $query .= (isset($this->where) and (count($this->where) > 0)) ? ' where ' . implode(' ', $this->where) : '';
        $query .= $this->group ?? '';
        $query .= $this->order ?? '';
        $query .= $this->limits ?? '';
        return $query;
    }
    public function join(string $foreingTable, string $logic, string $type = 'inner'): ?self
    {
        $this->join[] = " {$type} join {$foreingTable} on {$logic}";
        return $this;
    }
    public function group(string $field): ?self
    {
        $this->group = " group by {$field}";
        return $this;
    }
    public function limit(int $limit, int $offset): ?self
    {
        $this->limit = $limit;
        $this->offset = $offset;
        $this->limits = " limit {$this->limit} offset {$this->offset}";
        return $this;
    }
    public function between(string $field, string|int $value1, string|int $value2, ?string $logic = null): ?self
    {
        $placeHolder1 = $field . '_1';
        $placeHolder2 = $field . '_2';
        $this->where[] = "{$field} between :{$placeHolder1} and :{$placeHolder2} {$logic}";
        $this->binds[$placeHolder1] = $value1;
        $this->binds[$placeHolder2] = $value2;
        return $this;
    }
    public function fetch($IsArray = true)
    {
        $query = '';
        $query = $this->createQuery();
        try {
            $connection = Connection::connection();
            $prepare = $connection->prepare($query);
            $prepare->execute($this->binds ?? []);
            return $IsArray ? $prepare->fetch(\PDO::FETCH_ASSOC) : $prepare->fetch(\PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            throw new \Exception("Restrição: {$e->getMessage()}");
        }
    }
    public function fetchAll($IsArray = true)
    {
        $query = $this->createQuery();
        try {
            $connection = Connection::connection();
            $prepare = $connection->prepare($query);
            $prepare->execute($this->binds ?? []);
            return $IsArray ? $prepare->fetchAll(\PDO::FETCH_ASSOC) : $prepare->fetchAll(\PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            throw new \Exception("Restrição: {$e->getMessage()}");
        }
    }
}
