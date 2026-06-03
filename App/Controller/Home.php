<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database\DB;

final class Home extends Base
{
    public function home($request, $response)
    {
        try {
            return $this->getTwig()
                ->render($response, $this->setView('home'), [
                    'titulo' => 'Início',
                ])
                ->withHeader('Content-Type', 'text/html')
                ->withStatus(200);
        } catch (\Exception $e) {
            error_log('[Home] ' . $e->getMessage());
            return $response->withStatus(500);
        }
    }

    // gráfico de barras — total de cadastros por tabela
    public function resultadoVendas($request, $response)
    {
        $clientes     = DB::select('COUNT(*) as total')->from('customer')->fetchOne();
        $fornecedores = DB::select('COUNT(*) as total')->from('supplier')->fetchOne();
        $produtos     = DB::select('COUNT(*) as total')->from('product')->fetchOne();
        $empresas     = DB::select('COUNT(*) as total')->from('company')->fetchOne();

        return $this->json($response, [
            'categories' => ['Clientes', 'Fornecedores', 'Produtos', 'Empresas'],
            'values'     => [(int)$clientes, (int)$fornecedores, (int)$produtos, (int)$empresas],
        ]);
    }

    // gráfico de pizza — distribuição percentual dos cadastros
    public function resultadoMarketing($request, $response)
    {
        $clientes     = DB::select('COUNT(*) as total')->from('customer')->fetchOne();
        $fornecedores = DB::select('COUNT(*) as total')->from('supplier')->fetchOne();
        $produtos     = DB::select('COUNT(*) as total')->from('product')->fetchOne();
        $empresas     = DB::select('COUNT(*) as total')->from('company')->fetchOne();

        return $this->json($response, [
            'series' => [
                ['name' => 'Clientes',     'value' => (int)$clientes],
                ['name' => 'Fornecedores', 'value' => (int)$fornecedores],
                ['name' => 'Produtos',     'value' => (int)$produtos],
                ['name' => 'Empresas',     'value' => (int)$empresas],
            ]
        ]);
    }
}