import Modal from 'bootstrap/js/dist/modal';
import Swal from 'sweetalert2';

// ── Botão de login ───────────────────────────────────────────────
const btnLogin   = document.getElementById('btnLogin');
const loginInput = document.getElementById('login');
const senhaInput = document.getElementById('senha');

if (btnLogin) {
    [loginInput, senhaInput].forEach(input => {
        if (!input) return;
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') btnLogin.click();
        });
    });

    btnLogin.addEventListener('click', async () => {
        const login = loginInput?.value?.trim();
        const senha = senhaInput?.value;

        window.__hideLoginError?.();

        if (!login || !senha) {
            window.__showLoginError?.('Preencha o usuário e a senha.');
            return;
        }

        window.__setLoginLoading?.(true);

        try {
            const formData = new FormData();
            formData.append('login', login);
            formData.append('senha', senha);

            const res = await fetch('/auth/login', {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: formData,
                credentials: 'same-origin',
            });

            const data = await res.json();

            if (!res.ok || !data.status) {
                window.__showLoginError?.(data.msg || 'Usuário ou senha inválidos.');
                return;
            }

            await Swal.fire({
                icon: 'success',
                title: 'Bem-vindo!',
                text: data.msg || 'Login realizado com sucesso.',
                timer: 1500,
                timerProgressBar: true,
                showConfirmButton: false,
            });

            window.location.href = '/home';

        } catch (err) {
            let texto = err.message || 'Não foi possível conectar ao servidor.';
            if (texto.includes('429'))      texto = 'Sua conta foi temporariamente bloqueada. Tente novamente em alguns minutos.';
            else if (texto.includes('403')) texto = 'Usuário ou senha incorretos.';
            else if (texto.includes('500')) texto = 'Ocorreu um problema interno. Tente novamente em instantes.';
            window.__showLoginError?.(texto);
        } finally {
            window.__setLoginLoading?.(false);
        }
    });
}

// ── Botão Google — fluxo OAuth redirect ─────────────────────────
const btnGoogle = document.getElementById('loginGoogle');
if (btnGoogle) {
    btnGoogle.addEventListener('click', () => {
        const clientId = document.querySelector('meta[name="google-signin-client_id"]')?.content;
        if (!clientId) {
            window.__showLoginError?.('Google Client ID não configurado no servidor.');
            return;
        }
        const redirectUri = encodeURIComponent(window.location.origin + '/auth/google/callback');
        const scope       = encodeURIComponent('openid email profile');
        const url = 'https://accounts.google.com/o/oauth2/v2/auth'
                  + '?client_id='     + clientId
                  + '&redirect_uri='  + redirectUri
                  + '&response_type=code'
                  + '&scope='         + scope
                  + '&access_type=online'
                  + '&prompt=select_account';
        window.location.href = url;
    });
}

// ── Google: detecção de novo usuário e erros vindos do callback ──
(function handleGoogleCallbackParams() {
    const params = new URLSearchParams(window.location.search);

    // Novo usuário: abre fluxo de definição de senha
    if (params.get('google_new') === '1') {
        const email = params.get('email') || '';
        window.history.replaceState({}, '', '/login');
        _googleNewPasswordFlow(email);
        return;
    }

    // Conta inativa
    if (params.get('google_error') === 'inativo') {
        window.history.replaceState({}, '', '/login');
        window.__showLoginError?.('Sua conta ainda não foi ativada. Aguarde a aprovação de um administrador.');
        return;
    }

    // Erro genérico do Google
    if (params.get('google_error') === '1') {
        window.history.replaceState({}, '', '/login');
        window.__showLoginError?.('Não foi possível autenticar com o Google. Tente novamente.');
    }
})();

/**
 * Abre o Swal pedindo senha para novo usuário Google
 * e envia para /auth/google/set-password.
 * Também é chamado pelo fluxo One Tap quando o backend
 * retorna { google_new: true, email: "..." }.
 */
async function _googleNewPasswordFlow(email) {
    const { value: senha, isConfirmed } = await Swal.fire({
        title: 'Defina sua senha',
        html: `
            <p style="margin-bottom:12px;color:#555;font-size:.95rem;">
                Olá! Para concluir seu cadastro com o e-mail<br>
                <strong>${email}</strong>, escolha uma senha de acesso.
            </p>
            <input
                id="swal-senha"
                type="password"
                class="swal2-input"
                placeholder="Mínimo 6 caracteres"
                autocomplete="new-password"
            />
            <input
                id="swal-senha-confirm"
                type="password"
                class="swal2-input"
                placeholder="Confirme a senha"
                autocomplete="new-password"
            />
        `,
        confirmButtonText: 'Cadastrar',
        showCancelButton: true,
        cancelButtonText: 'Cancelar',
        allowOutsideClick: false,
        focusConfirm: false,
        preConfirm: () => {
            const s1 = document.getElementById('swal-senha').value;
            const s2 = document.getElementById('swal-senha-confirm').value;
            if (!s1 || s1.length < 6) {
                Swal.showValidationMessage('A senha deve ter pelo menos 6 caracteres.');
                return false;
            }
            if (s1 !== s2) {
                Swal.showValidationMessage('As senhas não coincidem.');
                return false;
            }
            return s1;
        },
    });

    if (!isConfirmed || !senha) return;

    try {
        Swal.fire({
            title: 'Criando sua conta...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
        });

        const formData = new FormData();
        formData.append('email', email);
        formData.append('senha', senha);

        const res  = await fetch('/auth/google/set-password', {
            method: 'POST',
            headers: { Accept: 'application/json' },
            body: formData,
            credentials: 'same-origin',
        });

        const data = await res.json();

        if (data.status) {
            await Swal.fire({
                icon: 'success',
                title: 'Conta criada!',
                text: data.msg,
                confirmButtonText: 'Ok',
            });
            // Conta criada mas inativa — permanece na tela de login
        } else if (res.status === 409) {
            await Swal.fire({
                icon: 'info',
                title: 'E-mail já cadastrado',
                text: data.msg,
            });
        } else {
            await Swal.fire({
                icon: 'error',
                title: 'Erro ao criar conta',
                text: data.msg || 'Tente novamente.',
            });
        }
    } catch (err) {
        console.error('[googleNewPasswordFlow]', err);
        Swal.fire({
            icon: 'error',
            title: 'Erro de conexão',
            text: 'Não foi possível conectar ao servidor. Tente novamente.',
        });
    }
}

// ── Google One Tap: integração com handler existente ─────────────
// Se você já tem um handler para o One Tap que faz fetch('/auth/google'),
// adicione este trecho após receber a resposta:
//
//   const data = await res.json();
//   if (data.google_new) {
//       await _googleNewPasswordFlow(data.email);  // << novo usuário
//       return;
//   }
//   if (data.status) {
//       window.location.href = '/home';
//   } else {
//       window.__showLoginError?.(data.msg);
//   }

// ── Botão Remover Conta Google ───────────────────────────────────
const btnRemoverConta = document.getElementById('btnRemoverContaGoogle');
if (btnRemoverConta) {
    btnRemoverConta.addEventListener('click', async () => {
        const { value: email } = await Swal.fire({
            title: 'Remover conta',
            text: 'Digite o e-mail da conta Google que deseja remover:',
            input: 'email',
            inputPlaceholder: 'seu@gmail.com',
            showCancelButton: true,
            confirmButtonText: 'Remover',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2626',
            inputValidator: (value) => {
                if (!value) return 'Informe o e-mail!';
            },
        });

        if (!email) return;

        const confirmar = await Swal.fire({
            icon: 'warning',
            title: 'Tem certeza?',
            html: `A conta <strong>${email}</strong> será <strong>removida permanentemente</strong> do sistema.`,
            showCancelButton: true,
            confirmButtonText: 'Sim, remover',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2626',
        });

        if (!confirmar.isConfirmed) return;

        try {
            const formData = new FormData();
            formData.append('email', email);

            const res = await fetch('/auth/google/delete-account', {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: formData,
                credentials: 'same-origin',
            });

            const data = await res.json();

            if (!res.ok || !data.status) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: data.msg || 'Não foi possível remover a conta.',
                });
                return;
            }

            await Swal.fire({
                icon: 'success',
                title: 'Conta removida!',
                text: 'A conta foi removida com sucesso.',
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false,
            });

        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Não foi possível conectar ao servidor.',
            });
        }
    });
}

// ── Modal de cadastro ────────────────────────────────────────────
const registerModalEl = document.getElementById('registerModal');
const saveButton      = document.getElementById('saveRegister');
const registerForm    = document.getElementById('registerForm');

if (registerModalEl) {
    const registerModal = new Modal(registerModalEl);
    const btnCadastro   = document.getElementById('btnCadastro');

    const cpfField      = document.getElementById('registerCpf');
    const rgField       = document.getElementById('registerRg');
    const telefoneField = document.getElementById('registerTelefone');
    const passwordField = document.getElementById('registerSenha');
    const confirmField  = document.getElementById('registerSenhaConfirm');

    if (window.Inputmask) {
        if (cpfField)      window.Inputmask({ mask: '999.999.999-99' }).mask(cpfField);
        if (rgField)       window.Inputmask({ mask: '99.999.999-9' }).mask(rgField);
        if (telefoneField) window.Inputmask({ mask: ['(99) 9999-9999', '(99) 99999-9999'], keepStatic: true }).mask(telefoneField);
    }

    if (btnCadastro) {
        btnCadastro.addEventListener('click', () => registerModal.show());
    }

    if (saveButton && registerForm) {
        saveButton.addEventListener('click', async () => {
            registerForm.classList.remove('was-validated');

            if (!registerForm.checkValidity()) {
                registerForm.classList.add('was-validated');
                return;
            }

            confirmField?.setCustomValidity('');

            if (passwordField && confirmField && passwordField.value !== confirmField.value) {
                confirmField.setCustomValidity('As senhas não coincidem.');
                registerForm.classList.add('was-validated');
                confirmField.reportValidity();
                return;
            }

            saveButton.disabled = true;

            try {
                const formData = new FormData(registerForm);

                if (cpfField)      formData.set('cpf',      cpfField.value.replace(/\D/g, ''));
                if (rgField)       formData.set('rg',       rgField.value.replace(/\D/g, ''));
                if (telefoneField) formData.set('telefone', telefoneField.value.replace(/\D/g, ''));

                const res = await fetch('/auth/preregister', {
                    method: 'POST',
                    headers: { Accept: 'application/json' },
                    body: formData,
                    credentials: 'same-origin',
                });

                const result = await res.json();

                if (!res.ok || !result.status) {
                    throw new Error(result.msg || res.statusText || 'Erro ao cadastrar o usuário');
                }

                await Swal.fire({
                    icon: 'success',
                    title: 'Cadastro realizado!',
                    text: result.msg || 'Aguarde a aprovação de um administrador para acessar o sistema.',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                });

                registerModal.hide();
                registerForm.reset();
                registerForm.classList.remove('was-validated');

            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro no cadastro',
                    text: error.message || 'Não foi possível cadastrar o usuário.',
                });
            } finally {
                saveButton.disabled = false;
            }
        });
    }
} // ← fecha o if (registerModalEl)

// ── Toggle senha visível/oculta ──────────────────────────────────
const toggleSenha = document.getElementById('toggleSenha');
const senhaField  = document.getElementById('senha');
const eyeIcon     = document.getElementById('eyeIcon');

if (toggleSenha && senhaField) {
    toggleSenha.addEventListener('click', () => {
        const visible = senhaField.type === 'text';
        senhaField.type = visible ? 'password' : 'text';
        eyeIcon.classList.toggle('fa-eye',       visible);
        eyeIcon.classList.toggle('fa-eye-slash', !visible);
    });
}