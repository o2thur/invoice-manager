<?php
require_once 'data.php';
require_once 'functions.php';

check_login();
check_permission('user_manage');

$error = '';
$success = '';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_roles'])) {
    $selected_roles = $_POST['roles'] ?? [];
    
    try {
        $db->beginTransaction();
        
        $delete_sql = "DELETE FROM user_roles WHERE user_id = :user_id";
        $delete_stmt = $db->prepare($delete_sql);
        $delete_stmt->execute([':user_id' => $user_id]);
        
        if (!empty($selected_roles)) {
            $insert_sql = "INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)";
            $insert_stmt = $db->prepare($insert_sql);
            
            foreach ($selected_roles as $role_id) {
                $insert_stmt->execute([
                    ':user_id' => $user_id,
                    ':role_id' => $role_id
                ]);
            }
        }
        
        $db->commit();
        $success = 'User roles updated successfully.';
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Failed to update user roles.';
    }
}

$user_sql = "SELECT id, username FROM users WHERE id = :user_id";
$user_stmt = $db->prepare($user_sql);
$user_stmt->execute([':user_id' => $user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: admin.php');
    exit;
}

$roles_sql = "SELECT r.*, CASE WHEN ur.user_id IS NOT NULL THEN 1 ELSE 0 END as is_selected
              FROM roles r
              LEFT JOIN user_roles ur ON r.id = ur.role_id AND ur.user_id = :user_id
              ORDER BY r.role_name";
$roles_stmt = $db->prepare($roles_sql);
$roles_stmt->execute([':user_id' => $user_id]);
$roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Invoice Manager</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .edit-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .role-list {
            margin: 20px 0;
        }
        .role-item {
            margin: 10px 0;
        }
        .success {
            color: #28a745;
            margin-bottom: 15px;
        }
        .error {
            color: #dc3545;
            margin-bottom: 15px;
        }
        .btn-save {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-save:hover {
            background-color: #218838;
        }
        .nav-links {
            margin-bottom: 20px;
        }
        .nav-links a {
            margin-right: 15px;
            color: #007bff;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="edit-container">
        <div class="nav-links">
            <a href="admin.php">Back to Admin Panel</a>
        </div>

        <h2>Edit User: <?php echo htmlspecialchars($user['username']); ?></h2>
        
        <?php if (!empty($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="role-list">
                <?php foreach ($roles as $role): ?>
                <div class="role-item">
                    <label>
                        <input type="checkbox" name="roles[]" value="<?php echo $role['id']; ?>"
                               <?php echo $role['is_selected'] ? 'checked' : ''; ?>
                               <?php echo ($user_id === 1 && $role['role_name'] === 'admin') ? 'disabled checked' : ''; ?>>
                        <?php echo htmlspecialchars(ucfirst($role['role_name'])); ?>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button type="submit" name="update_roles" class="btn-save">Save Changes</button>
        </form>
    </div>
</body>
</html> 