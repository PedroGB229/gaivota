<?php

declare(strict_types=1);

namespace App\Database\Builder;

use app\database\Connection;

class DeleteQuery
{
    private string $table;
    private array $conditions = [];

    /**
     * Define a tabela da qual o registro será excluído.
     * Exemplo: DeleteQuery::table('customer')
     */
    public static function table(string $table): self
    {
        $self        = new self;
        $self->table = $table;
        return $self;
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
        $sql    = "DELETE FROM {$this->table}";
        $params = [];

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
     * Executa o DELETE e retorna true em caso de sucesso.
     */
    public function delete(): bool
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
