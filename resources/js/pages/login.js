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

// ── Botão Google — fluxo OAuth redirect (funciona em localhost e HTTP) ──
// O Chrome bloqueia o One Tap em HTTP/localhost via FedCM.
// Redireciona para o Google com response_type=code e volta em /auth/google/callback.
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

    // Máscaras
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
}