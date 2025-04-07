<?php
require_once 'data.php';
require_once 'functions.php';
session_start();

// Check if user is already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $sql = "SELECT id FROM users WHERE username = :username";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $error = 'This username is already taken.';
        } else {
            $sql = "INSERT INTO users (username, password) VALUES (:username, :password)";
            $stmt = $db->prepare($sql);
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $user_id = $db->lastInsertId();
                
                $role_sql = "INSERT INTO user_roles (user_id, role_id) 
                            SELECT :user_id, r.id 
                            FROM roles r 
                            WHERE r.role_name = 'user'";
                $role_stmt = $db->prepare($role_sql);
                $role_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                
                if ($role_stmt->execute()) {
                    header('Location: login.php?registered=true');
                    exit;
                } else {
                    $delete_sql = "DELETE FROM users WHERE id = :user_id";
                    $delete_stmt = $db->prepare($delete_sql);
                    $delete_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $delete_stmt->execute();
                    
                    $error = 'Something went wrong. Please try again later.';
                }
            } else {
                $error = 'Something went wrong. Please try again later.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Invoice Manager</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .register-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #fff;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .btn-register {
            background-color: #28a745;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
        }
        .btn-register:hover {
            background-color: #218838;
        }
        .login-link {
            text-align: center;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Register</h2>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required minlength="3" maxlength="50" 
                       value="<?php echo htmlspecialchars($username ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required minlength="6">
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required minlength="6">
            </div>
            <button type="submit" class="btn-register">Register</button>
        </form>
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html> 