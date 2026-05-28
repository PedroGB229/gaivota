<?php

declare(strict_types=1);

// ─────────────────────────────────────────────────────────────────────────────
// 1. Todos os arquivos PHP de App\ devem usar strict_types=1
// ─────────────────────────────────────────────────────────────────────────────
arch('Todos os arquivos usam strict types')
    ->expect('App')
    ->toUseStrictTypes();

// ─────────────────────────────────────────────────────────────────────────────
// 2. Controllers devem ser classes final (exceto Base, que é abstract)
// ─────────────────────────────────────────────────────────────────────────────
arch('Controllers devem ser classes finais')
    ->expect('App\\Controller')
    ->toBeFinal()
    ->ignoring('App\\Controller\\Base');

// ─────────────────────────────────────────────────────────────────────────────
// 3. Ausência de funções de debug em todo o namespace App\
// ─────────────────────────────────────────────────────────────────────────────
arch('Sem funções de debug no código de produção')
    ->expect('App')
    ->not->toUse(['var_dump', 'dd', 'dump', 'die', 'print_r', 'var_export']);

// ─────────────────────────────────────────────────────────────────────────────
// 4. Ausência de funções perigosas (execução de sistema) em todo o namespace App\
// ─────────────────────────────────────────────────────────────────────────────
arch('Sem funções perigosas no código')
    ->expect('App')
    ->not->toUse([
        'eval',
        'exec',
        'shell_exec',
        'system',
        'passthru',
        'proc_open',
        'popen',
    ]);

// ─────────────────────────────────────────────────────────────────────────────
// 5. Controllers não devem instanciar PDO diretamente
//    (a persistência deve passar por App\Database\DB ou App\Database\Connection)
// ─────────────────────────────────────────────────────────────────────────────
arch('Controllers não instanciam PDO diretamente')
    ->expect('App\\Controller')
    ->not->toUse('PDO');

// ─────────────────────────────────────────────────────────────────────────────
// 6. Controllers não devem depender de classes de Migração (separação de camadas)
// ─────────────────────────────────────────────────────────────────────────────
arch('Controllers não dependem da camada de migração')
    ->expect('App\\Controller')
    ->not->toUse('App\\Database\\Migration');
