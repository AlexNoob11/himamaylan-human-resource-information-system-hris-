<?php
session_start();
require 'conn.php'; // Database connection

/* ----------------------------------------
   HANDLE REGISTRATION
---------------------------------------- */
if (isset($_POST['register'])) {
    $username = trim($_POST['reg_username']);
    $email = trim($_POST['reg_email']);
    $password = password_hash(trim($_POST['reg_password']), PASSWORD_BCRYPT);
    $role = $_POST['reg_role']; 

    if ($conn->connect_errno) {
        $_SESSION['error'] = "Failed to connect to database.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT admin_id FROM admin WHERE username = ? OR email = ?");
    if (!$stmt) {
        $_SESSION['error'] = "Error in prepare statement: " . $conn->error;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION['error'] = "Username or email already exists.";
    } else {
        // Insert user and role
        $stmt = $conn->prepare("INSERT INTO admin (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            $_SESSION['error'] = "Error in prepare statement: " . $conn->error;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }

        $stmt->bind_param("ssss", $username, $email, $password, $role);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Registration successful. You can now log in.";
        } else {
            $_SESSION['error'] = "Error: Could not register user.";
        }
    }

    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

/* ----------------------------------------
   HANDLE LOGIN
---------------------------------------- */
if (isset($_POST['login'])) {
    $username = trim($_POST['log_username']);
    $password = trim($_POST['log_password']);

    if ($conn->connect_errno) {
        $_SESSION['error'] = "Failed to connect to database.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Fetch user including role
    $stmt = $conn->prepare("SELECT admin_id, password_hash, role FROM admin WHERE username = ? AND is_active = 1");
    if (!$stmt) {
        $_SESSION['error'] = "Error in prepare statement: " . $conn->error;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($admin_id, $hash, $role);
        $stmt->fetch();
        if (password_verify($password, $hash)) {
            $_SESSION['admin_id'] = $admin_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role; 
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error'] = "Invalid password.";
        }
    } else {
        $_SESSION['error'] = "User not found or inactive.";
    }

    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UPC BioEnergy PH - Admin Portal</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <div class="container">
        <div class="message-box">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
        </div>

        <div class="form-box login">
            <form action="" method="POST">
                <h1>Admin Login</h1>
                <p class="form-subtitle">Management & Operations Panel</p>
                <div class="input-box">
                    <input type="text" name="log_username" placeholder="Username" required>
                    <i class='bx bxs-user'></i>
                </div>
                <div class="input-box">
                    <input type="password" name="log_password" placeholder="Password" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>
                <div class="forgot-link">
                    <a href="#">Forgot Password?</a>
                </div>
                <button type="submit" name="login" class="btn">Login to Console</button>
            </form>
        </div>

        <div class="form-box register">
            <form action="" method="POST">
                <h1>Admin Registration</h1>
                <p class="form-subtitle">Create internal administrative credentials</p>
                <div class="input-box">
                    <input type="text" name="reg_username" placeholder="Username" required>
                    <i class='bx bxs-user'></i>
                </div>
                <div class="input-box">
                    <input type="email" name="reg_email" placeholder="Corporate Email" required>
                    <i class='bx bxs-envelope'></i>
                </div>
                <div class="input-box">
                    <input type="password" name="reg_password" placeholder="Password" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>
                <div class="input-box">
                    <select name="reg_role" required>
                        <option value="admin">Admin</option>
                        <option value="moderator" selected>Moderator</option>
                    </select>
                </div>
                <button type="submit" name="register" class="btn">Register Admin</button>
            </form>
        </div>

        <div class="toggle-box">
            <div class="toggle-panel toggle-left">
                <h2>UPC BioEnergy PH</h2>
                <span class="facility-tag">Industrial Park II Corp.</span>
                <p class="description-text">Large-scale renewable energy & wood processing facility.</p>
                <div class="location-info">
                    <i class='bx bxs-map'></i> Brgy. Talaban, Himamaylan City, Negros Occidental <br><small>(Directly in front of the New City Hall)</small>
                </div>
                <button class="btn register-btn">Create Admin Profile</button>
            </div>

            <div class="toggle-panel toggle-right">
                <h2>Welcome Back!</h2>
                <p class="description-text">Authorized Operations and Centralized Administrative Management Network.</p>
                <div class="location-info">
                    <i class='bx bxs-shield-quarter'></i> Administrative Personnel Gateway
                </div>
                <button class="btn login-btn">Back to Login</button>
            </div>
        </div>
    </div>

    <script>
        const container = document.querySelector('.container');
        const registerBtn = document.querySelector('.register-btn');
        const loginBtn = document.querySelector('.login-btn');

        registerBtn.addEventListener('click', () => {
            container.classList.add('active');
        });

        loginBtn.addEventListener('click', () => {
            container.classList.remove('active');
        });
    </script>
</body>
</html>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: "Poppins", sans-serif;
    text-decoration: none;
    list-style: none;
}

body {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background: linear-gradient(90deg, #e2e2e2, #c9d6ff);
}

.container {
    position: relative;
    width: 850px;
    height: 550px;
    background: #fff;
    margin: 20px;
    border-radius: 30px;
    box-shadow: 0 0 30px rgba(0, 0, 0, .2);
    overflow: hidden;
}

.container h1 {
    font-size: 34px;
    margin-bottom: 4px;
}

.form-subtitle {
    font-size: 13.5px;
    color: #666;
    margin-bottom: 10px;
}

form { width: 100%; }

.form-box {
    position: absolute;
    right: 0;
    width: 50%;
    height: 100%;
    background: #fff;
    display: flex;
    align-items: center;
    color: #333;
    text-align: center;
    padding: 40px;
    z-index: 1;
    transition: .6s ease-in-out 1.2s, visibility 0s 1s;
}

.container.active .form-box { right: 50%; }

.form-box.register { visibility: hidden; }
.container.active .form-box.register { visibility: visible; }

.input-box {
    position: relative;
    margin: 25px 0;
}

.input-box input {
    width: 100%;
    padding: 13px 50px 13px 20px;
    background: #eee;
    border-radius: 8px;
    border: none;
    outline: none;
    font-size: 16px;
    color: #333;
    font-weight: 500;
}

.input-box input::placeholder {
    color: #888;
    font-weight: 400;
}

.input-box select {
    width: 100%;
    padding: 13px 20px;
    background: #eee;
    border-radius: 8px;
    border: none;
    outline: none;
    font-size: 16px;
    color: #333;
    font-weight: 500;
    appearance: none;
}

.input-box i {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 20px;
}

.forgot-link { margin: -10px 0 20px; text-align: left; }
.forgot-link a {
    font-size: 14px;
    color: #333;
}

.btn {
    width: 100%;
    height: 48px;
    background: #7494ec;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0, 0, 0, .1);
    border: none;
    cursor: pointer;
    font-size: 16px;
    color: #fff;
    font-weight: 600;
}

/* Informational Toggle Boxes */
.toggle-box {
    position: absolute;
    width: 100%;
    height: 100%;
}

.toggle-box::before {
    content: '';
    position: absolute;
    left: -250%;
    width: 300%;
    height: 100%;
    background: #7494ec;
    border-radius: 150px;
    z-index: 2;
    transition: 1.8s ease-in-out;
}

.container.active .toggle-box::before { left: 50%; }

.toggle-panel {
    position: absolute;
    width: 50%;
    height: 100%;
    color: #fff;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    z-index: 2;
    padding: 0 35px;
    text-align: center;
    transition: .6s ease-in-out;
}

.toggle-panel h2 {
    font-size: 26px;
    font-weight: 700;
}

.facility-tag {
    font-size: 13px;
    background: rgba(255, 255, 255, 0.2);
    padding: 3px 10px;
    border-radius: 50px;
    margin-top: 4px;
    font-weight: 500;
}

.description-text {
    font-size: 13.5px;
    margin: 15px 0;
    line-height: 1.5;
}

.location-info {
    font-size: 12px;
    background: rgba(0, 0, 0, 0.15);
    padding: 10px 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: left;
    width: 100%;
}

.location-info i {
    margin-right: 3px;
}

.toggle-panel .btn {
    width: 160px;
    height: 46px;
    background: transparent;
    border: 2px solid #fff;
    box-shadow: none;
}

.toggle-panel.toggle-left { 
    left: 0;
    transition-delay: 1.2s; 
}
.container.active .toggle-panel.toggle-left {
    left: -50%;
    transition-delay: .6s;
}

.toggle-panel.toggle-right { 
    right: -50%;
    transition-delay: .6s;
}
.container.active .toggle-panel.toggle-right {
    right: 0;
    transition-delay: 1.2s;
}

/* Global Notification Box Settings */
.message-box {
    position: absolute;
    top: 15px;
    left: 20px;
    z-index: 100;
    width: calc(50% - 40px);
}

.container.active .message-box {
    left: auto;
    right: 20px;
}

.alert {
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.alert.error { background-color: #ffcdd2; color: #b71c1c; border: 1px solid #f44336; }
.alert.success { background-color: #c8e6c9; color: #2e7d32; border: 1px solid #4caf50; }

/* System Responsiveness rules */
@media screen and (max-width: 650px){
    .container { height: calc(100vh - 40px); }
    .form-box { bottom: 0; width: 100%; height: 70%; }
    .container.active .form-box { right: 0; bottom: 30%; }
    
    .message-box { width: calc(100% - 40px); left: 20px; top: 10px; }
    .container.active .message-box { left: 20px; right: 20px; }

    .toggle-box::before { left: 0; top: -270%; width: 100%; height: 300%; border-radius: 20vw; }
    .container.active .toggle-box::before { left: 0; top: 70%; }

    .toggle-panel { width: 100%; height: 30%; }
    .toggle-panel.toggle-left { top: 0; }
    .container.active .toggle-panel.toggle-left { left: 0; top: -30%; }
    
    .toggle-panel.toggle-right { right: 0; bottom: -30%; }
    .container.active .toggle-panel.toggle-right { bottom: 0; }
    
    .description-text, .location-info { display: none; }
    .toggle-panel h2 { font-size: 22px; }
}

@media screen and (max-width: 400px){
    .form-box { padding: 20px; }
    .toggle-panel h1 { font-size: 30px; }
}
</style>