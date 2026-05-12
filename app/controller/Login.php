<?php

namespace app\controller;

final class Login extends Base
{
    public function login($request, $response)
{
    try {
        return $this->getTwig()
            ->render($response, $this->setView('login'), [
                'titulo' => 'Início',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    } catch (\Exception $e) {
        //  Sempre retorna um ResponseInterface
        $response->getBody()->write('Erro: ' . $e->getMessage());
        return $response->withStatus(500);
    }
}
}
