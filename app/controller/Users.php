<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Types\Types;

final class Users extends Base
{
    public function insert($request, $response)
    {
        $form = $request->getParsedBody();

        $senha = trim((string) ($form['senha'] ?? ''));
        if ($senha === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe a senha.', 'id' => 0], 400);
        }

        $fields = [
            'nome'      => $form['nome']      ?? '',
            'sobrenome' => $form['sobrenome'] ?? '',
            'cpf'       => $form['cpf']       ?? '',
            'rg'        => $form['rg']        ?? '',
            'senha'     => password_hash($senha, PASSWORD_DEFAULT),
            'ativo'     => false, # Sempre false no cadastro — admin ativa depois
        ];

        # Mapeia explicitamente o tipo de cada coluna para o Doctrine DBAL
        # sem isso o pdo_pgsql converte false PHP para string vazia e o Postgres rejeita
        $types = [
            'nome'      => Types::STRING,
            'sobrenome' => Types::STRING,
            'cpf'       => Types::STRING,
            'rg'        => Types::STRING,
            'senha'     => Types::STRING,
            'ativo'     => Types::BOOLEAN,
        ];

        try {
            $isInserted = \App\Database\DB::connection()->insert('users', $fields, $types);
            if (!$isInserted) {
                return $this->json($response, ['status' => false, 'msg' => 'Não foi possível cadastrar o usuário.', 'id' => 0], 500);
            }

            $id = (int) \App\Database\DB::connection()->fetchOne(
                'SELECT id FROM users WHERE cpf = ? ORDER BY id DESC LIMIT 1',
                [$fields['cpf']]
            );

            return $this->json($response, ['status' => true, 'msg' => 'Usuário cadastrado com sucesso!', 'id' => $id], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
}




