<?php

declare(strict_types=1);

namespace app\controller;


final class Customer extends Base
{
    public function list($request, $response)
    {
        return $this->getTwig()
            ->render($response, $this->setView('list-customer'), [
                'titulo' => 'Lista de clientes',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function details($request, $response, $args)
    {
        $id = $args['id'] ?? null;
        $action = ($id === null) ? 'c' : 'e';
        $customer = [];
        if (!is_null($id)) {
            $customer = \App\Database\Builder\SelectQuery::select()->from('customer')->where('id', '=', $id)->fetch();
        }
        return $this->getTwig()
            ->render($response, $this->setView('customer'), [
                'titulo' => 'Detalhes do cliente',
                'id' => $id,
                'action' => $action,
                'customer' => $customer
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    public function insert($request, $response)
    {
        $form = $request->getParsedBody();
        $FieldsAndValues = [
            'nome_fantasia' => $form['nomeExibicao'],
            'sobrenome_razao' => $form['nomeLegal'] ?? '',
            'cpf_cnpj' => $form['numeroDocumento'] ?? '',
            'inscricao_estadual' => $form['registroSecundario'] ?? '',
            'nascimento_fundacao' => $this->convertBrDateToDatabaseFormat($form['dataRegistro']),
            'ativo' => ($form['ativo'] === 'true') ? true : false
        ];
        try {
            $IsInserted = \App\Database\Builder\InsertQuery::insert('customer')->save($FieldsAndValues);
            if (!$IsInserted) {
                return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $IsInserted, 'id' => 0], 500);
            }
            $id = \App\Database\Builder\SelectQuery::select('id')->from('customer')->order('id', 'desc')->fetch();

            return $this->json($response, ['status' => true, 'msg' => 'Salvo com sucesso!', 'id' => $id['id']], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
    public function update($request, $response)
    {
        $form = $request->getParsedBody();
        $id = $form['id'] ?? null;
        if (is_null($id)) {
            return $this->json($response, ['status' => false, 'msg' => 'Por favor informe o ID do registro', 'id' => 0], 403);
        }
        $FieldsAndValues = [
            'nome_fantasia' => $form['nomeExibicao'] ?? null,
            'sobrenome_razao' => $form['nomeLegal'] ?? null,
            'cpf_cnpj' => $form['numeroDocumento'] ?? null,
            'inscricao_estadual' => $form['registroSecundario'] ?? null,
            'nascimento_fundacao' => $this->convertBrDateToDatabaseFormat($form['dataRegistro']),
            'ativo' => ($form['ativo'] === 'true') ? true : false
        ];
        try {
            $IsUpdated = \App\Database\Builder\UpdateQuery::table('customer')->set($FieldsAndValues)->where('id', '=', $id)->update();
            if (!$IsUpdated) {
                return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $IsUpdated, 'id' => 0], 403);
            }
            return $this->json($response, ['status' => true, 'msg' => 'Alterado com sucesso!', 'id' => $id], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
    public function delete($request, $response)
    {
        $form = $request->getParsedBody();
        $id = $form['id'] ?? null;
        if (is_null($id) || $id === '') {
            return $this->json($response, ['status' => false, 'msg' => 'Informe o código do cliente', 'id' => 0], 403);
        }
        try {
            $IsDeleted = \App\Database\Builder\DeleteQuery::table('customer')->where('id', '=', $id)->delete();
            if (!$IsDeleted) {
                return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $IsDeleted, 'id' => $id], 403);
            }
            return $this->json($response, ['status' => true, 'msg' => 'Removido com sucesso!', 'id' => $id]);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
    public function listingdata($request, $response)
    {
        $form = $request->getParsedBody();
        #Captura o termo da pesquisa 
        $term = $form['search']['value'] ?? null;
        #Captura o valor do registro inicial
        $start = (int)$form['start'];
        #Captura o valor do registro final
        $length = (int)$form['length'];
        $columns = [
            0 => 'id',
            1 => 'nome_fantasia',
            2 => 'cpf_cnpj',
            3 => 'inscricao_estadual',
            4 => 'nascimento_fundacao',
            5 => 'criado_em',
            6 => 'atualizado_em'
        ];
        #Captura a posição do campo a ser filtrado
        $posField = (isset($form['order'][0]['column']) && count($columns) > intval($form['order'][0]['column'])) ?
            intval($form['order'][0]['column'])
            :
            0;
        #Captura o tipo de ordenação, caso seja nula passaremos por padrão o valor DESC
        $orderType = $form['order'][0]['dir'] ?? 'desc';
        #Captura o nome_fantasia do campo para ordenação
        $orderField = $columns[$posField];

        $query = \App\Database\Builder\SelectQuery::select()->from('customer');

        try {
            if (!is_null($term) && $term !== '') {
                $query->where('id', 'ILIKE', "{$term}", 'or')
                    ->where('nome_fantasia', 'ILIKE', "{$term}", 'or')
                    ->where('sobrenome_razao', 'ILIKE', "{$term}", 'or')
                    ->where('cpf_cnpj', 'ILIKE', "{$term}", 'or')
                    ->where('inscricao_estadual', 'ILIKE', "{$term}", 'or')
                    ->where('nascimento_fundacao', 'ILIKE', "{$term}", 'or')
                    ->where('criado_em', 'ILIKE', "{$term}", 'or')
                    ->where('atualizado_em', 'ILIKE', "{$term}");
            }

            $customers = $query->order($orderField, $orderType)
                ->limit($length, $start)
                ->fetchAll();

            $queryCount = \App\Database\Builder\SelectQuery::select('count(*) as qtd')->from('customer')->fetch();

            $customer = [];

            foreach ($customers as $key => $value) {
                $cpfCnpj = $value['cpf_cnpj'] ?? '';
                $nomeFantasia = $value['nome_fantasia'] ?? '';
                $sobrenomeRazao = $value['sobrenome_razao'] ?? '';

                $nomeCompleto = (strlen($cpfCnpj) <= 14) ? trim($nomeFantasia . ' ' . $sobrenomeRazao) : $nomeFantasia;

                $customer[$key] = [
                    $value["id"],
                    $nomeCompleto,
                    $cpfCnpj,
                    (new \DateTime($value['nascimento_fundacao'] ?? date('Y-m-d')))->format('d/m/Y'),
                    ($value['ativo'] === true) ? "Ativo" : "Inativo",
                    (new \DateTime($value['criado_em']))->format('d/m/Y H:i:s'),
                    (new \DateTime($value['atualizado_em']))->format('d/m/Y H:i:s'),
                    "<td>
                        <a class='btn btn-sm btn-warning' href='/cliente/detalhes/" . $value['id'] . "'> <i class='fa-solid fa-pen-to-square'></i> Editar</a>
                        <button type='button' class='btn btn-sm btn-danger' onclick='ShowModal(" . $value['id'] . ");'> <i class='fa-solid fa-trash'></i> Excluir</button>
                    </td>"
                ];
            }

            $data = [
                'recordsTotal' => count($customers),
                'recordsFiltered' => $queryCount['qtd'] ?? 0,
                'data' => $customer
            ];
            return $this->json($response, $data, 200);
        } catch (\Exception $e) {
            return $this->json($response, ['status' => false, 'msg' => 'Restrição: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }
}
