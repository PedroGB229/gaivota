<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Customer extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table("customer", ['id' => false, 'primary_key' => ['id'], 'comment' => 'Tabela de cliente do sistema']);
        $table->addColumn('id', 'biginteger', ['identity' => true, 'null' => false])
            ->addColumn('nome_fantasia', 'text', ['null' => true])
            ->addColumn('sobrenome_razao', 'text', ['null' => true])
            ->addColumn('cpf_cnpj', 'text', ['null' => true])
            ->addColumn('inscricao_estadual', 'text', ['null' => true])
            ->addColumn('nascimento_fundacao', 'date', ['null' => true])
            ->addColumn('ativo', 'boolean', ['null' => true, 'default' => true])
            ->addColumn('criado_em', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => 'Data e hora de criação do registro'])
            ->addColumn('atualizado_em', 'timestamp', ['null' => true, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP', 'comment' => 'Data e hora da última atualização do registro'])
            ->addIndex(['cpf_cnpj'], ['unique' => true])
            ->create();
    }
}
