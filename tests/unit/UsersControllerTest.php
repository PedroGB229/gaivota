<?php

declare(strict_types=1);

use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

afterEach(function () {
    \app\database\DB::connection()->executeStatement(
        "DELETE FROM users WHERE cpf = '444.555.666-77'"
    );
});

test('users insert com dados válidos retorna 201 com status true', function () {

    $request = (new RequestFactory())
        ->createRequest('POST', '/users')
        ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
        ->withParsedBody([
            'nome'      => 'Felipe Santos',
            'sobrenome' => 'Silva',
            'cpf'       => '444.555.666-77',
            'rg'        => '123456',
            'senha'     => '123456',
            'ativo'     => 'true',
        ]);

    $response = (new ResponseFactory())->createResponse();

    $result = (new app\controller\Users())->insert($request, $response);

    $result->getBody()->rewind();

    $json = json_decode($result->getBody()->getContents(), true);

    expect($result->getStatusCode())->toBe(201);

    expect($json['status'])->toBeTrue();
});