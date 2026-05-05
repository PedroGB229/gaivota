<?php

declare(strict_types=1);

namespace app\database\builder;

use app\database\Connection;

class SelectQuery
{
    private string $fields = '*';
    private string $table  = '';
    private array  $conditions  = [];
    private string $orderField  = '';
    private string $orderType   = 'ASC';
    private ?int   $limit  = null;
    private ?int   $offset = null;

    public static function select(string $fields = '*'): self
    {
        $self         = new self;
        $self->fields = $fields;
        return $self;
    }

    public function from(string $table): self
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \Exception("Nome de tabela inválido");
        }

        $this->table = $table;
        return $this;
    }

    public function where(string $field, string $operator, mixed $value, string $type = 'AND'): self
    {
        $operator = strtoupper($operator);
        $type     = strtoupper($type);

        $allowedOperators = ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'ILIKE'];

        if (!in_array($operator, $allowedOperators)) {
            throw new \Exception("Operador inválido: {$operator}");
        }

        $this->conditions[] = [
            'field'    => $field,
            'operator' => $operator,
            'value'    => $value,
            'type'     => $type,
        ];

        return $this;
    }

    public function order(string $field, string $type = 'ASC'): self
    {
        $type = strtoupper($type);

        if (!in_array($type, ['ASC', 'DESC'])) {
            throw new \Exception("Tipo de ordenação inválido");
        }

        $this->orderField = $field;
        $this->orderType  = $type;

        return $this;
    }

    public function limit(int $limit, int $offset = 0): self
    {
        $this->limit  = max(0, $limit);
        $this->offset = max(0, $offset);
        return $this;
    }

    private function buildQuery(): array
    {
        if (empty($this->table)) {
            throw new \Exception("Tabela não definida");
        }

        $sql    = "SELECT {$this->fields} FROM {$this->table}";
        $params = [];

        if (!empty($this->conditions)) {
            $whereParts = [];

            foreach ($this->conditions as $index => $condition) {
                $placeholder = 'param_' . $index;
                $operator    = $condition['operator'];
                $value       = $condition['value'];

                if ($operator === 'ILIKE' || $operator === 'LIKE') {
                    $value = '%' . $value . '%';
                    $expr  = "CAST({$condition['field']} AS TEXT) {$operator} :{$placeholder}";
                } else {
                    $expr = "{$condition['field']} {$operator} :{$placeholder}";
                }

                $params[$placeholder] = $value;

                if ($index === 0) {
                    $whereParts[] = "WHERE {$expr}";
                } else {
                    $whereParts[] = "{$condition['type']} {$expr}";
                }
            }

            $sql .= ' ' . implode(' ', $whereParts);
        }

        if ($this->orderField !== '') {
            $sql .= " ORDER BY {$this->orderField} {$this->orderType}";
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
            if ($this->offset !== null && $this->offset > 0) {
                $sql .= " OFFSET {$this->offset}";
            }
        }

        return [$sql, $params];
    }

    public function fetch(): array
    {
        [$sql, $params] = $this->buildQuery();

        try {
            $con = Connection::get();

            $result = $con->executeQuery($sql, $params)->fetchAssociative();

            return $result === false ? [] : $result;

        } catch (\Exception $e) {
            throw new \Exception("Erro na query: " . $e->getMessage());
        }
    }

    public function fetchAll(): array
    {
        [$sql, $params] = $this->buildQuery();

        try {
            $con = Connection::get();

            return $con->executeQuery($sql, $params)->fetchAllAssociative();

        } catch (\Exception $e) {
            throw new \Exception("Erro na query: " . $e->getMessage());
        }
    }
}