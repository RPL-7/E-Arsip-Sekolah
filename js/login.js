const userTypeBtns = document.querySelectorAll('.user-type-btn');
const identifierInput = document.getElementById('identifier');
const identifierLabel = document.getElementById('identifier_label');
const userTypeInput = document.getElementById('user_type');
const loginForm = document.getElementById('loginForm');
const messageDiv = document.getElementById('message');
const btnLogin = document.getElementById('btnLogin');

const labelMap = {
    'siswa': { label: 'NIS', placeholder: 'Masukkan NIS' },
    'guru': { label: 'ID Guru', placeholder: 'Masukkan ID Guru' },
    'admin': { label: 'Username', placeholder: 'Masukkan Username' }
};

userTypeBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        userTypeBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');
                
        const type = this.dataset.type;
        userTypeInput.value = type;
                
        identifierLabel.textContent = labelMap[type].label;
        identifierInput.placeholder = labelMap[type].placeholder;
        identifierInput.value = '';
                
        messageDiv.style.display = 'none';
    });
});

function showMessage(text, type) {
    messageDiv.textContent = text;
    messageDiv.className = 'message ' + type;
    messageDiv.style.display = 'block';
}

loginForm.addEventListener('submit', function(e) {
    e.preventDefault();
            
    btnLogin.disabled = true;
    btnLogin.textContent = 'Memproses...';
            
    const formData = new FormData(loginForm);
            
    fetch('login.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 1000);
        } else {
            showMessage(data.message, 'error');
            btnLogin.disabled = false;
            btnLogin.textContent = 'Login';
        }
    })
    .catch(error => {
        showMessage('Terjadi kesalahan koneksi', 'error');
        btnLogin.disabled = false;
        btnLogin.textContent = 'Login';
    });
});