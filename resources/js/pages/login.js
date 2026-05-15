import Modal from 'bootstrap/js/dist/modal';
import Swal from 'sweetalert2';

// ── Botão de login ───────────────────────────────────────────────
const btnLogin  = document.getElementById('btnLogin');
const loginInput = document.getElementById('login');
const senhaInput = document.getElementById('senha');

if (btnLogin) {
    // Permite usar Enter nos campos para submeter
    [loginInput, senhaInput].forEach(input => {
        if (!input) return;
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') btnLogin.click();
        });
    });

    btnLogin.addEventListener('click', async () => {
        const login = loginInput?.value?.trim();
        const senha = senhaInput?.value;

        // Limpa erro anterior
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

            // Login bem-sucedido → redireciona
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
            window.__showLoginError?.('Não foi possível conectar ao servidor.');
        } finally {
            window.__setLoginLoading?.(false);
        }
    });
}

// ── Modal de cadastro ────────────────────────────────────────────
const registerButton  = document.getElementById('btnCadastro');
const registerModalEl = document.getElementById('registerModal');
const saveButton      = document.getElementById('saveRegister');
const registerForm    = document.getElementById('registerForm');

if (registerModalEl) {
    const registerModal = new Modal(registerModalEl);
    const cpfField      = document.getElementById('registerCpf');
    const passwordField = document.getElementById('registerSenha');
    const confirmField  = document.getElementById('registerSenhaConfirm');

    if (window.Inputmask && cpfField) {
        window.Inputmask({ mask: ['999.999.999-99'], keepStatic: true }).mask(cpfField);
    }

    if (registerButton) {
        registerButton.addEventListener('click', () => registerModal.show());
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
                const response = await fetch('/usuario/insert', {
                    method: 'POST',
                    headers: { Accept: 'application/json' },
                    body: formData,
                    credentials: 'same-origin',
                });

                const result = await response.json();

                if (!response.ok || !result.status) {
                    throw new Error(result.msg || response.statusText || 'Erro ao cadastrar o usuário');
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Sucesso',
                    text: result.msg || 'Usuário cadastrado com sucesso!',
                    timer: 2500,
                    timerProgressBar: true,
                });

                registerModal.hide();
                registerForm.reset();
                registerForm.classList.remove('was-validated');

            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: error.message || 'Não foi possível cadastrar o usuário.',
                });
            } finally {
                saveButton.disabled = false;
            }
        });
    }
}