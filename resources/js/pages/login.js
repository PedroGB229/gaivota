import Modal from 'bootstrap/js/dist/modal';
import Swal from 'sweetalert2';

const registerButton = document.getElementById('btnCadastro');
const registerModalEl = document.getElementById('registerModal');
const saveButton = document.getElementById('saveRegister');
const registerForm = document.getElementById('registerForm');

if (registerModalEl) {
    const registerModal = new Modal(registerModalEl);
    const cpfField = document.getElementById('registerCpf');
    const passwordField = document.getElementById('registerSenha');
    const confirmField = document.getElementById('registerSenhaConfirm');

    if (window.Inputmask && cpfField) {
        window.Inputmask({ mask: ['999.999.999-99'], keepStatic: true }).mask(cpfField);
    }

    if (registerButton) {
        registerButton.addEventListener('click', () => {
            registerModal.show();
        });
    }

    if (saveButton && registerForm) {
        saveButton.addEventListener('click', async () => {
            registerForm.classList.remove('was-validated');

            if (!registerForm.checkValidity()) {
                registerForm.classList.add('was-validated');
                return;
            }

            if (confirmField) {
                confirmField.setCustomValidity('');
            }

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
                    headers: {
                        Accept: 'application/json'
                    },
                    body: formData,
                    credentials: 'same-origin'
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
