<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PEST - Arquivo de configuração global dos testes
|--------------------------------------------------------------------------
|
| Este arquivo é carregado automaticamente pelo PEST antes de qualquer suite
| de testes. Use-o para registrar helpers globais, aliases de datasets e
| configurações de expectativas reutilizáveis.
|
*/

uses()->beforeAll(function (): void {
    // Ponto de extensão: inicializações globais antes de todos os testes.
})->in('Architecture', 'Unit', 'Feature', 'Integration');
