<?php

declare(strict_types=1);

# Domínio atual da requisição — usado no payload JWT (iss/aud) e no cookie auth_token
define('HOST', $_SERVER['HTTP_HOST']);

define('ROOT', dirname(__FILE__, 3));
# DIRETÓRIO DAS VIEWS
define('DIR_VIEWS', ROOT . '/app/view');
# EXTENSÃO PADRÃO DAS VIEWS
define('EXT_VIEWS', '.html');
# Chave secreta para geração de tokens JWT — nunca exponha em repositórios públicos
define('SECRET_KEY', '58ae142d-afae-4443-994a-43f2bef0e366');

# GOOGLE_CLIENT_ID é carregado via variável de ambiente (defina no .env ou docker-compose)
# Exemplo no .env: GOOGLE_CLIENT_ID=123456789-xxxx.apps.googleusercontent.com