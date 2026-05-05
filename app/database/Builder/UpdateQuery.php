<?php

declare(strict_types=1);

namespace App\Database\Builder;

use app\database\Connection;

class UpdateQuery
{
    private string $table;
    private array $fields = [];
    private array $conditions = [];

    /**
     * Define a tabela a ser atualizada.
     * Exemplo: UpdateQuery::table('customer')
     */
    public static function table(string $table): self
    {
        $self        = new self;
        $self->table = $table;
        return $self;
    }

    /**
     * Define os campos e valores a serem atualizados.
     * Exemplo: ->set(['nome_fantasia' => 'Joao', 'ativo' => true])
     */
    public function set(array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * Adiciona uma condição WHERE.
     * Exemplo: ->where('id', '=', 5)
     */
    public function where(string $field, string $operator, mixed $value, string $type = 'AND'): self
    {
        $this->conditions[] = [
            'field'    => $field,
            'operator' => strtoupper($operator),
            'value'    => $value,
            'type'     => strtoupper($type),
        ];
        return $this;
    }

    private function buildQuery(): array
    {
        $setParts = [];
        $params   = [];

        foreach ($this->fields as $field => $value) {
            $placeholder          = ':set_' . $field;
            $setParts[]           = "{$field} = {$placeholder}";
            $params[$placeholder] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts);

        if (!empty($this->conditions)) {
            foreach ($this->conditions as $index => $condition) {
                $placeholder          = ':where_' . $index;
                $connector            = ($index === 0) ? 'WHERE' : $condition['type'];
                $sql                 .= " {$connector} {$condition['field']} {$condition['operator']} {$placeholder}";
                $params[$placeholder] = $condition['value'];
            }
        }

        return [$sql, $params];
    }

    /**
     * Executa o UPDATE e retorna true em caso de sucesso.
     */
    public function update(): bool
    {
        [$sql, $params] = $this->buildQuery();
        try {
            $con     = Connection::connection();
            $prepare = $con->prepare($sql);
            return $prepare->execute($params);
        } catch (\PDOException $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
