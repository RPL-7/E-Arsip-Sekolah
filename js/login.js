const loginForm = document.getElementById('loginForm');
        const messageDiv = document.getElementById('message');

        function showMessage(text, type) {
            messageDiv.textContent = text;
            messageDiv.className = 'message ' + type;
            messageDiv.style.display = 'block';
            
            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 3000);
        }

        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            // Validasi sederhana (untuk demo)
            if (username && password) {
                showMessage('Login berhasil! Selamat datang, ' + username, 'success');
                
                // Di sini Anda bisa menambahkan logika untuk mengirim data ke server
                console.log('Username:', username);
                console.log('Password:', password);
                
                // Reset form setelah 2 detik
                setTimeout(() => {
                    loginForm.reset();
                }, 2000);
            } else {
                showMessage('Mohon isi semua field!', 'error');
            }
        });