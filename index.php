<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Sekolah</title>
    <link rel="stylesheet" href="css/login.css">
</head>

<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Sistem Sekolah</h1>
            <p>Silakan pilih jenis user dan login</p>
        </div>

        <div class="user-type-selector">
            <button type="button" class="user-type-btn active" data-type="siswa">Siswa</button>
            <button type="button" class="user-type-btn" data-type="guru">Guru</button>
            <button type="button" class="user-type-btn" data-type="admin">Admin</button>
        </div>

        <div id="message" class="message"></div>

        <form id="loginForm" action="login.php" method="POST">
            <input type="hidden" name="user_type" id="user_type" value="siswa">

            <div class="form-group">
                <label for="identifier" id="identifier_label">NIS</label>
                <input type="text" id="identifier" name="identifier" placeholder="Masukkan NIS" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Masukkan password" required>
            </div>

            <button type="submit" class="btn-login" id="btnLogin">Login</button>
        </form>
    </div>
    <script src="js/login.js"></script>
</body>

</html>