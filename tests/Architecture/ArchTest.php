<?php

declare(strict_types=1);

arch('Todos os arquivos usam strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('Sem debug no código de produção')
    ->expect('App\Controller')
    ->not->toUse(['var_dump', 'dd', 'dump', 'die']);

// PDO::class detecta tanto "use PDO;" no topo quanto new PDO(), \PDO::FETCH_ASSOC, etc.
arch('Controllers não acessam banco direto')
    ->expect('App\Controller')
    ->not->toUse(PDO::class);

#Nenhuma classe deve usar funções perigosas
arch('Sem funções perigosas no código')
    ->expect('App')
    ->not->toUse([
        'eval',
        'exec',
        'shell_exec',
        'system',
        'passthru',
        'proc_open',
    ]);

#Garantir que classes são finais ou abstratas
arch('Controllers devem ser classes finais')
    ->expect('App\Controller')
    ->toBeFinal()
    ->ignoring('App\Controller\Base');

# Controllers não devem conhecer a camada de migração (separação de camadas)
arch('Controllers não dependem da camada de migração')
    ->expect('App\Controller')
    ->not->toUse('App\Database\Migration');