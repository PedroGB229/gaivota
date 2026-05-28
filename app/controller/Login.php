<?php

namespace app\controller;

final class Login extends Base
{
    // -------------------------------------------------------------------------
    // Renderiza a página de login
    // -------------------------------------------------------------------------
    public function login($request, $response)
    {
        try {
            return $this->getTwig()
                ->render($response, $this->setView('login'), [
                    'titulo'           => 'Início',
                    'google_client_id' => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
                ])
                ->withHeader('Content-Type', 'text/html')
                ->withStatus(200);
        } catch (\Exception $e) {
            error_log('[login][view] ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Autenticação via CPF / e-mail / celular / telefone + senha
    // -------------------------------------------------------------------------
    public function authenticate($request, $response)
    {
        $form  = $request->getParsedBody();
        $login = $form['login'] ?? null;
        $senha = $form['senha'] ?? null;

        if (is_null($login) || is_null($senha)) {
            return $this->json($response, ['status' => false, 'msg' => 'Por favor informe seu usuário e senha!', 'id' => 0]);
        }

        if (isset($_SESSION['login_locked_until']) && $_SESSION['login_locked_until'] > time()) {
            return $this->json($response, ['status' => false, 'msg' => 'Muitas tentativas. Tente novamente em alguns minutos.', 'id' => 0], 429);
        }

        try {
            $qb          = \app\database\DB::select('*')->from('vw_user');
            $placeholder = $qb->createNamedParameter($login);

            $qb->where('cpf = '       . $placeholder)
                ->orWhere('email = '   . $placeholder)
                ->orWhere('celular = ' . $placeholder)
                ->orWhere('telefone = ' . $placeholder);

            $user = $qb->fetchAssociative();

            $dummyHash   = '$2y$10$CwTycUXWue0Thq9StjUM0uJ8.k3.kK1m3Sv7lJ1uG9N9Yvb.MqYsa';
            $senhaValida = password_verify($senha, $user['senha'] ?? $dummyHash);

            if (!$user || !$senhaValida) {
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                if ($_SESSION['login_attempts'] >= 5) {
                    $_SESSION['login_locked_until'] = time() + 900;
                    $_SESSION['login_attempts']     = 0;
                }
                return $this->json($response, ['status' => false, 'msg' => 'Verifique seu e-mail e senha e tente novamente!', 'id' => 0], 403);
            }

            $ativo = $user['ativo'] ?? false;
            if ($ativo === false || $ativo === 'f' || $ativo === '0' || $ativo === '') {
                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'Sua conta ainda não foi ativada. Aguarde a aprovação de um administrador.',
                    'id'     => 0,
                ], 403);
            }

            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);

            if (password_needs_rehash($user['senha'], PASSWORD_DEFAULT)) {
                \app\database\DB::connection()->update(
                    'users',
                    ['senha' => password_hash($senha, PASSWORD_DEFAULT), 'atualizado_em' => date('Y-m-d H:i:s')],
                    ['id' => $user['id']],
                );
            }

            unset($user['senha']);

            return $this->_criarSessaoERetornar($response, $user);
        } catch (\PDOException $e) {
            error_log('[auth][DB] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Não foi possível concluir o login. Tente novamente.', 'id' => 0], 500);
        } catch (\UnexpectedValueException | \DomainException $e) {
            error_log('[auth][JWT] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Não foi possível concluir o login. Tente novamente.', 'id' => 0], 500);
        } catch (\Throwable $e) {
            error_log('[auth][GERAL] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro inesperado. Tente novamente.', 'id' => 0], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Autenticação via Google One Tap (credential JWT direto)
    // -------------------------------------------------------------------------
    public function google($request, $response)
    {
        $form                = $request->getParsedBody();
        $credential          = $form['credential']   ?? null;
        $form_g_csrf_token   = $form['g_csrf_token'] ?? null;
        $cookie_g_csrf_token = $_COOKIE['g_csrf_token'] ?? null;
        $google_client_id    = $_ENV['GOOGLE_CLIENT_ID'] ?? null;

        if (is_null($credential) || is_null($form_g_csrf_token) || is_null($cookie_g_csrf_token)) {
            return $this->json($response, ['status' => false, 'msg' => 'Credenciais Google ausentes.', 'id' => 0], 400);
        }

        if ($form_g_csrf_token !== $cookie_g_csrf_token) {
            return $this->json($response, ['status' => false, 'msg' => 'Falha na verificação de segurança (CSRF).', 'id' => 0], 400);
        }

        try {
            $provider = new \League\OAuth2\Client\Provider\Google([
                'clientId'     => $google_client_id,
                'clientSecret' => '',
                'redirectUri'  => '',
            ]);

            $httpResponse = $provider->getHttpClient()->request(
                'GET',
                'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential),
                ['timeout' => 3, 'connect_timeout' => 2]
            );

            $claims = json_decode((string) $httpResponse->getBody(), true, 512, JSON_THROW_ON_ERROR);

            if (($claims['aud'] ?? '') !== $google_client_id) {
                return $this->json($response, ['status' => false, 'msg' => 'Token do Google inválido.', 'id' => 0], 401);
            }

            return $this->_autenticarPorEmail($response, $claims['email'] ?? null);
        } catch (\JsonException $e) {
            error_log('[google][JSON] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Resposta inválida do Google. Tente novamente.', 'id' => 0], 502);
        } catch (\Throwable $e) {
            error_log('[google][GERAL] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Falha na autenticação com Google. Tente novamente.', 'id' => 0], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Callback OAuth — Google redireciona aqui após o usuário escolher a conta
    // Fluxo: GET /auth/google/callback?code=xxx
    // Se e-mail não existe → redireciona para /login?google_new=1&email=xxx
    // Se existe e está ativo → cria sessão e redireciona para /home
    // -------------------------------------------------------------------------
    public function googleCallback($request, $response)
    {
        $params           = $request->getQueryParams();
        $code             = $params['code']  ?? null;
        $google_client_id = $_ENV['GOOGLE_CLIENT_ID']     ?? null;
        $google_secret    = $_ENV['GOOGLE_CLIENT_SECRET']  ?? null;
        $redirect_uri     = $_ENV['GOOGLE_REDIRECT_URI']   ?? null;

        if (!$code) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        try {
            $provider = new \League\OAuth2\Client\Provider\Google([
                'clientId'     => $google_client_id,
                'clientSecret' => $google_secret,
                'redirectUri'  => $redirect_uri,
            ]);

            $token      = $provider->getAccessToken('authorization_code', ['code' => $code]);
            $googleUser = $provider->getResourceOwner($token);
            $userArray  = $googleUser->toArray();
            $email      = $userArray['email'] ?? null;

            return $this->_autenticarPorEmail($response, $email, true);
        } catch (\Throwable $e) {
            error_log('[googleCallback] ' . $e->getMessage());
            return $response
                ->withHeader('Location', '/login?google_error=1')
                ->withStatus(302);
        }
    }

    // -------------------------------------------------------------------------
    // Finaliza cadastro Google — recebe email + senha escolhida pelo usuário
    // POST /auth/google/set-password
    // Body: { email, senha, nome (opcional) }
    // -------------------------------------------------------------------------
    public function setGooglePassword($request, $response)
    {
        $form  = $request->getParsedBody();
        $email = trim($form['email'] ?? '');
        $senha = $form['senha'] ?? '';
        $nome  = trim($form['nome'] ?? '');

        if (!$email || !$senha) {
            return $this->json($response, ['status' => false, 'msg' => 'E-mail e senha são obrigatórios.'], 400);
        }

        if (strlen($senha) < 6) {
            return $this->json($response, ['status' => false, 'msg' => 'A senha deve ter pelo menos 6 caracteres.'], 400);
        }

        try {
            // Garante que o e-mail ainda não está cadastrado (duplo clique, etc.)
            $qb   = \app\database\DB::select('id_usuario')->from('contact');
            $qb->where('contato = ' . $qb->createNamedParameter($email))
               ->andWhere("tipo = 'EMAIL'");
            $existing = $qb->fetchAssociative();

            if ($existing) {
                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'Este e-mail já está cadastrado. Tente fazer login normalmente.',
                ], 409);
            }

            // Cria o usuário com a senha definida pelo usuário
            \app\database\DB::connection()->insert('users', [
                'nome'      => $nome ?: explode('@', $email)[0],
                'sobrenome' => '',
                'cpf'       => '',
                'rg'        => '',
                'senha'     => password_hash($senha, PASSWORD_DEFAULT),
                'ativo'     => false,
            ], [
                'nome'      => \Doctrine\DBAL\Types\Types::STRING,
                'sobrenome' => \Doctrine\DBAL\Types\Types::STRING,
                'cpf'       => \Doctrine\DBAL\Types\Types::STRING,
                'rg'        => \Doctrine\DBAL\Types\Types::STRING,
                'senha'     => \Doctrine\DBAL\Types\Types::STRING,
                'ativo'     => \Doctrine\DBAL\Types\Types::BOOLEAN,
            ]);

            $id_usuario = \app\database\DB::connection()->lastInsertId();

            \app\database\DB::connection()->insert('contact', [
                'id_usuario' => $id_usuario,
                'tipo'       => 'EMAIL',
                'contato'    => $email,
            ]);

            return $this->json($response, [
                'status' => true,
                'msg'    => 'Conta criada com sucesso! Aguarde a aprovação de um administrador para acessar.',
            ], 200);

        } catch (\PDOException $e) {
            error_log('[setGooglePassword][DB] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Não foi possível criar a conta. Tente novamente.'], 500);
        } catch (\Throwable $e) {
            error_log('[setGooglePassword][GERAL] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro inesperado. Tente novamente.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Remove conta cadastrada via Google (deleta do banco pelo e-mail)
    // POST /auth/google/delete-account
    // Body: { email: "usuario@gmail.com" }
    // -------------------------------------------------------------------------
    public function deleteGoogleAccount($request, $response)
    {
        $form  = $request->getParsedBody();
        $email = trim($form['email'] ?? '');

        if (!$email) {
            return $this->json($response, ['status' => false, 'msg' => 'E-mail não informado.'], 400);
        }

        try {
            $qb = \app\database\DB::select('id_usuario')->from('contact');
            $qb->where('contato = ' . $qb->createNamedParameter($email))
               ->andWhere("tipo = 'EMAIL'");

            $row = $qb->fetchAssociative();

            if (!$row) {
                return $this->json($response, ['status' => false, 'msg' => 'Nenhuma conta encontrada com este e-mail.'], 404);
            }

            $id_usuario = $row['id_usuario'];

            \app\database\DB::connection()->delete('contact', ['id_usuario' => $id_usuario]);
            \app\database\DB::connection()->delete('users', ['id' => $id_usuario]);

            return $this->json($response, ['status' => true, 'msg' => 'Conta removida com sucesso.'], 200);

        } catch (\PDOException $e) {
            error_log('[deleteGoogleAccount][DB] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Não foi possível remover a conta. Tente novamente.'], 500);
        } catch (\Throwable $e) {
            error_log('[deleteGoogleAccount][GERAL] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro inesperado. Tente novamente.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Pré-cadastro de usuário (com email e telefone)
    // -------------------------------------------------------------------------
    public function preRegister($request, $response)
    {
        $form      = $request->getParsedBody();
        $nome      = trim($form['nome']      ?? '');
        $sobrenome = trim($form['sobrenome'] ?? '');
        $cpf       = trim($form['cpf']       ?? '');
        $rg        = trim($form['rg']        ?? '');
        $senha     = $form['senha']          ?? '';
        $email     = trim($form['email']     ?? '');
        $telefone  = trim($form['telefone']  ?? '');

        if (!$nome || !$sobrenome || !$cpf || !$senha) {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Nome, sobrenome, CPF e senha são obrigatórios.',
            ], 400);
        }

        $DataUser = [
            'nome'      => $nome,
            'sobrenome' => $sobrenome,
            'cpf'       => $cpf,
            'rg'        => $rg,
            'senha'     => password_hash($senha, PASSWORD_DEFAULT),
            'ativo'     => false,
        ];

        $DataUserTypes = [
            'nome'      => \Doctrine\DBAL\Types\Types::STRING,
            'sobrenome' => \Doctrine\DBAL\Types\Types::STRING,
            'cpf'       => \Doctrine\DBAL\Types\Types::STRING,
            'rg'        => \Doctrine\DBAL\Types\Types::STRING,
            'senha'     => \Doctrine\DBAL\Types\Types::STRING,
            'ativo'     => \Doctrine\DBAL\Types\Types::BOOLEAN,
        ];

        try {
            \app\database\DB::connection()->insert('users', $DataUser, $DataUserTypes);
            $id_usuario = \app\database\DB::connection()->lastInsertId();

            if ($email) {
                \app\database\DB::connection()->insert('contact', [
                    'id_usuario' => $id_usuario,
                    'tipo'       => 'EMAIL',
                    'contato'    => $email,
                ]);
            }

            if ($telefone) {
                \app\database\DB::connection()->insert('contact', [
                    'id_usuario' => $id_usuario,
                    'tipo'       => 'TELEFONE',
                    'contato'    => preg_replace('/\D/', '', $telefone),
                ]);
            }

            return $this->json($response, ['status' => true, 'msg' => 'Usuário cadastrado com sucesso!'], 200);
        } catch (\PDOException $e) {
            error_log('[preRegister][DB] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Não foi possível realizar o cadastro. Tente novamente.'], 500);
        } catch (\Throwable $e) {
            error_log('[preRegister][GERAL] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro inesperado. Tente novamente.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------
    public function logout($request, $response)
    {
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

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }

        session_destroy();

        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

        setcookie('auth_token', '', [
            'expires'  => time() - 42000,
            'path'     => '/',
            'domain'   => $_SERVER['HTTP_HOST'],
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    // -------------------------------------------------------------------------
    // Busca usuário pelo e-mail e autentica, ou redireciona para definir senha
    // se for um cadastro novo via Google.
    //
    // MUDANÇA: quando o e-mail não existe no banco, em vez de criar a conta
    // com senha vazia, redireciona para /login?google_new=1&email=xxx
    // (fluxo redirect) ou retorna JSON com google_new=true (One Tap),
    // para que o frontend abra o Swal pedindo a senha antes de cadastrar.
    // -------------------------------------------------------------------------
    private function _autenticarPorEmail($response, ?string $email, bool $redirect = false)
    {
        if (!$email) {
            if ($redirect) return $response->withHeader('Location', '/login?google_error=1')->withStatus(302);
            return $this->json($response, ['status' => false, 'msg' => 'E-mail não encontrado no token Google.', 'id' => 0], 400);
        }

        $qb = \app\database\DB::select('*')->from('vw_user');
        $qb->where('email = ' . $qb->createNamedParameter($email));
        $user = $qb->fetchAssociative();

        // ── Usuário não existe: pede senha antes de criar a conta ─────────────
        if (!$user) {
            if ($redirect) {
                // Callback OAuth: redireciona para a tela de login com parâmetros
                // O frontend detecta google_new=1 e abre o Swal de senha
                return $response
                    ->withHeader('Location', '/login?google_new=1&email=' . urlencode($email))
                    ->withStatus(302);
            }

            // One Tap: retorna JSON para o JS abrir o modal de senha
            return $this->json($response, [
                'status'     => false,
                'google_new' => true,
                'email'      => $email,
                'msg'        => 'Primeiro acesso! Defina uma senha para concluir o cadastro.',
            ], 200);
        }

        // ── Usuário existe mas está inativo ───────────────────────────────────
        $ativo = $user['ativo'] ?? false;
        if ($ativo === false || $ativo === 'f' || $ativo === '0' || $ativo === '') {
            if ($redirect) return $response->withHeader('Location', '/login?google_error=inativo')->withStatus(302);
            return $this->json($response, ['status' => false, 'msg' => 'Sua conta ainda não foi ativada. Aguarde a aprovação de um administrador.', 'id' => 0], 403);
        }

        // ── Usuário existe e está ativo: autentica normalmente ────────────────
        unset($user['senha']);

        if ($redirect) {
            $response = $this->_criarSessaoERetornar($response, $user);
            return $response->withHeader('Location', '/home')->withStatus(302);
        }

        return $this->_criarSessaoERetornar($response, $user);
    }

    private function _criarSessaoERetornar($response, array $user)
    {
        session_regenerate_id(true);

        $_SESSION['user']           = $user;
        $_SESSION['user']['logado'] = true;

        $lifetime = (int) (ini_get('session.gc_maxlifetime') ?: 3600);
        $now      = time();
        $jti      = bin2hex(random_bytes(16));

        $payload = [
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $lifetime,
            'sub' => (string) $user['id'],
            'iss' => $_SERVER['HTTP_HOST'],
            'aud' => $_SERVER['HTTP_HOST'],
            'jti' => $jti,
        ];

        $jwt      = \Firebase\JWT\JWT::encode($payload, SECRET_KEY, 'HS256');
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

        setcookie('auth_token', $jwt, [
            'expires'  => $now + $lifetime,
            'path'     => '/',
            'domain'   => $_SERVER['HTTP_HOST'],
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $agora = (new \DateTimeImmutable())->setTimestamp($now);
        $_SESSION['user']['sessao_criada_em'] = $agora->format('Y-m-d H:i:s');
        $_SESSION['user']['sessao_expira_em'] = $agora->modify("+{$lifetime} seconds")->format('Y-m-d H:i:s');

        return $this->json($response, [
            'status'           => true,
            'msg'              => 'Seja bem vindo de volta!',
            'id'               => $user['id'],
            'sessao_expira_em' => $_SESSION['user']['sessao_expira_em'],
        ], 200);
    }
}