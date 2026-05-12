<?php

declare(strict_types=1);

namespace app\controller;

final class User extends Base
{
    public function insert($request, $response)
    {
        $form = $request->getParsedBody();

        $senha = trim((string) ($form['senha'] ?? ''));
        if ($senha === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe a senha.', 'id' => 0], 400);
        }

        $fields = [
            'nome' => $form['nome'] ?? '',
            'sobrenome' => $form['sobrenome'] ?? '',
            'cpf' => $form['cpf'] ?? '',
            'rg' => $form['rg'] ?? '',
            'senha' => password_hash($senha, PASSWORD_DEFAULT),
            'ativo' => ($form['ativo'] === 'true') ? true : false,
        ];

        try {
            $isInserted = \app\database\DB::connection()->insert('users', $fields);
            if (!$isInserted) {
                return $this->json($response, ['status' => false, 'msg' => 'Não foi possível cadastrar o usuário.', 'id' => 0], 500);
            }

            $id = (int) \app\database\DB::connection()->fetchOne(
                'SELECT id FROM users WHERE cpf = ? ORDER BY id DESC LIMIT 1',
                [$fields['cpf']]
            );

            return $this->json($response, ['status' => true, 'msg' => 'Usuário cadastrado com sucesso!', 'id' => $id], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
}
