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
            var_dump($e->getMessage());
        }
    }
    public function authenticate($request, $response)
    {
        # Recupera as credenciais enviadas no corpo da requisição
        $form = $request->getParsedBody();
        $login = $form['login'] ?? null;
        $senha = $form['senha'] ?? null;
        # Bloqueia se algum campo veio vazio
        if (is_null($login) || is_null($senha)) {
            return $this->json($response, ['status' => false, 'msg' => 'Por favor informe seu usuário e senha!', 'id' => 0]);
        }
        # Verifica se a sessão está em "lockout" por excesso de tentativas falhas
        if (isset($_SESSION['login_locked_until']) && $_SESSION['login_locked_until'] > time()) {
            return $this->json($response, ['status' => false, 'msg' => 'Muitas tentativas. Tente novamente em alguns minutos.', 'id' => 0], 429);
        }
        try {
            # Começa a montar a query: SELECT * FROM vw_user
            $qb = \app\database\DB::select('*')
                ->from('vw_user');

            # Define o valor que será procurado nos três campos
            # O Doctrine cria um "placeholder seguro" no lugar do valor real,
            # protegendo a aplicação contra SQL injection.
            $login = $qb->createNamedParameter($login);

            # Monta a cláusula WHERE com quatro condições ligadas por OR:
            # WHERE cpf = :login OR email = :login OR celular = :login OR telefone = :login
            # ATENÇÃO: a view vw_user tem as colunas "celular" e "telefone" (não "whatsapp")
            $qb->where('cpf = '      . $login)
                ->orWhere('email = '    . $login)
                ->orWhere('celular = '  . $login)
                ->orWhere('telefone = ' . $login);

            # Executa a query e busca um único registro (a primeira linha encontrada)
            $user = $qb->fetchAssociative();

            # Hash bcrypt pré-computado e inválido, usado quando o usuário não existe (proteção contra timing attack)
            $dummyHash = '$2y$10$CwTycUXWue0Thq9StjUM0uJ8.k3.kK1m3Sv7lJ1uG9N9Yvb.MqYsa';

            # Sempre executa password_verify, mesmo sem usuário, para manter tempo de resposta constante
            $senhaValida = password_verify($senha, $user['senha'] ?? $dummyHash);

            # Falha de autenticação: mensagem genérica + contador de tentativas
            # Também bloqueia usuários com ativo = false (aguardando aprovação)
            if (!$user || !$senhaValida) {
                # Incrementa o contador de tentativas falhas da sessão atual
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                # Após 5 falhas, bloqueia novas tentativas por 15 minutos (rate limiting básico)
                if ($_SESSION['login_attempts'] >= 5) {
                    $_SESSION['login_locked_until'] = time() + 900;
                    $_SESSION['login_attempts'] = 0;
                }
                return $this->json($response, ['status' => false, 'msg' => 'Verifique seu e-mail e senha e tente novamente!', 'id' => 0], 403);
            }

            # Usuário encontrado e senha correta — verifica se a conta está ativa
            # Usuários cadastrados via pré-cadastro têm ativo = false até aprovação de um admin
            # O PostgreSQL pode retornar o boolean como string 'f'/'t' ou como '0'/'1'
            $ativo = $user['ativo'] ?? false;
            if ($ativo === false || $ativo === 'f' || $ativo === '0' || $ativo === '') {
                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'Sua conta ainda não foi ativada. Aguarde a aprovação de um administrador.',
                    'id'     => 0,
                ], 403);
            }

            # Login válido: zera contadores de tentativa e lockout
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);

            # Regenera o ID da sessão para mitigar session fixation
            session_regenerate_id(true);

            # Renova o hash da senha se o algoritmo/custo padrão tiver mudado
            if (password_needs_rehash($user['senha'], PASSWORD_DEFAULT)) {
                \app\database\DB::connection()->update(
                    'users',
                    [
                        'senha'         => password_hash($senha, PASSWORD_DEFAULT),
                        'atualizado_em' => date('Y-m-d H:i:s'),
                    ],
                    ['id' => $user['id']],
                );
            }

            # Remove o hash da senha antes de gravar o usuário na sessão (evita expor credencial)
            unset($user['senha']);

            # Persiste o usuário autenticado na sessão (fonte de verdade do estado)
            $_SESSION['user'] = $user;
            $_SESSION['user']['logado'] = true;

            # Calcula o tempo de vida da sessão a partir do php.ini, com fallback de 3600s
            $lifetime = (int) (ini_get('session.gc_maxlifetime') ?: 3600);

            # Monta o payload do JWT usando o ID do usuário como subject (identificador estável e único)
            $payload = [
                'iat' => time(),                 # Momento de emissão
                'exp' => time() + $lifetime,     # Expiração alinhada à sessão
                'sub' => (string) $user['id'], # Subject = ID do usuário
            ];

            # Assina o token JWT com a chave secreta da aplicação
            $jwt = \Firebase\JWT\JWT::encode($payload, SECRET_KEY, 'HS256');

            # Determina se a conexão está em HTTPS (define o atributo Secure do cookie)
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

            # Define o cookie auth_token usando COOKIE_DOMAIN (constante de configuração, imune a Host Header Injection)
            setcookie('auth_token', $jwt, [
                'expires'  => time() + $lifetime,
                'path'     => '/',
                'domain' => $_SERVER['HTTP_HOST'], #Usa dinamicamente o domínio correto
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            # Registra na sessão o horário de criação e o horário previsto de expiração (formato H:i:s correto)
            $_SESSION['user']['sessao_criada_em'] = (new \DateTime())->format('Y-m-d H:i:s');
            $_SESSION['user']['sessao_expira_em'] = (new \DateTime())->modify("+{$lifetime} seconds")->format('Y-m-d H:i:s');

            # Retorna a resposta de sucesso ao cliente
            return $this->json($response, [
                'status'           => true,
                'msg'              => 'Seja bem vindo de volta!',
                'id'               => $user['id'],
                'sessao_expira_em' => $_SESSION['user']['sessao_expira_em']
            ], 200);
        } catch (\PDOException $e) {
            # Erro de banco: loga internamente e responde de forma genérica
            error_log('[auth][DB] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Não foi possível concluir o login. Tente novamente.', 'id' => 0], 500);
        } catch (\UnexpectedValueException | \DomainException $e) {
            # Erro específico do Firebase JWT (chave inválida, payload malformado, etc.)
            error_log('[auth][JWT] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Não foi possível concluir o login. Tente novamente.', 'id' => 0], 500);
        } catch (\Throwable $e) {
            # Qualquer outra falha inesperada: loga e responde de forma genérica
            error_log('[auth][GERAL] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro inesperado. Tente novamente: ' . $e->getMessage(), 'id' => 0], 500);
        }
    }

    public function preRegister($request, $response)
    {
        $form = $request->getParsedBody();
        #Captura os dados informado pelo usuário no formulário de pré-cadastro
        $nome      = $form['nome'] ?? null;
        $sobrenome = $form['sobrenome'] ?? null;
        $cpf       = $form['cpf'] ?? null;
        $rg        = $form['rg'] ?? null;
        $senha     = $form['senha'] ?? null;
        #Dados de contato.
        $email     = $form['email'] ?? null;
        $telefone  = $form['telefone'] ?? null;
        #Criamos o array associativo com os dados do usuário, onde a 
        #chave é o nome da coluna no banco de dados e o valor é o dado 
        #informado pelo usuário.
        $DataUser = [
            'nome'      => $nome,
            'sobrenome' => $sobrenome,
            'cpf'       => $cpf,
            'rg'        => $rg,
            'senha'     => password_hash($senha, PASSWORD_DEFAULT),
            'ativo'     => false, # Conta inativa até aprovação de um administrador
        ];
        # Mapeia os tipos explicitamente — o Doctrine DBAL com pdo_pgsql
        # converte false PHP para string vazia se o tipo não for declarado
        $DataUserTypes = [
            'nome'      => \Doctrine\DBAL\Types\Types::STRING,
            'sobrenome' => \Doctrine\DBAL\Types\Types::STRING,
            'cpf'       => \Doctrine\DBAL\Types\Types::STRING,
            'rg'        => \Doctrine\DBAL\Types\Types::STRING,
            'senha'     => \Doctrine\DBAL\Types\Types::STRING,
            'ativo'     => \Doctrine\DBAL\Types\Types::BOOLEAN,
        ];
        $id_usuario = 0;
        #Insere os dados no data base com o Docrine e recebe o ID do usuário criado.
        $id_usuario = \app\database\DB::connection()->insert('users', $DataUser, $DataUserTypes);
        #Insere os dados do email do usuário na base.
        $DataEmail = [
            'id_usuario' => $id_usuario,
            'tipo' => 'EMAIL',
            'contato' => $email
        ];
        \app\database\DB::connection()->insert('contact', $DataEmail);
        #Insere os dados do telefone do usuário na base.
        $DataTel = [
            'id_usuario' => $id_usuario,
            'tipo' => 'TELEFONE',
            'contato' => $telefone
        ];
        \app\database\DB::connection()->insert('contact', $DataTel);
        #Retorna a resposta de sucesso ao cliente
        return $this->json($response, [
            'status' => true,
            'msg' => 'Usuário cadastrado com sucesso!'
        ], 200);
    }
    public function logout($request, $response)
    {
        // 1. Desativa o usuário no banco antes de destruir a sessão
        //    (pega o ID enquanto a sessão ainda existe)
        $userId = $_SESSION['user']['id'] ?? null;
        if ($userId) {
            try {
                \app\database\DB::connection()->update(
                    'users',
                    ['ativo' => false, 'atualizado_em' => date('Y-m-d H:i:s')],
                    ['id'    => (int) $userId],
                    ['ativo' => \Doctrine\DBAL\Types\Types::BOOLEAN]
                );
            } catch (\Throwable $e) {
                error_log('[logout][DB] ' . $e->getMessage());
            }
        }

        // 2. Destrói todos os dados da sessão atual
        $_SESSION = [];

        // 2. Remove o cookie de sessão do navegador (PHP_SESSION_COOKIE)
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => 'Lax',
                ]
            );
        }

        // 3. Finaliza a sessão no servidor
        session_destroy();

        // 4. Remove o cookie JWT auth_token do navegador
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || $_SERVER['SERVER_PORT'] == 443;

        setcookie('auth_token', '', [
            'expires'  => time() - 42000,   // Data no passado = apaga o cookie
            'path'     => '/',
            'domain'   => $_SERVER['HTTP_HOST'],
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // 5. Redireciona para a tela de login
        return $response
            ->withHeader('Location', '/login')
            ->withStatus(302);
    }
}