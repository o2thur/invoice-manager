<?php
require_once 'data.php';
require_once 'functions.php';

check_login();
check_permission('user_manage');

if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    if ($user_id !== 1) { // Prevent deleting the main admin
        $sql = "DELETE FROM users WHERE id = :user_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
    }
}

$sql = "SELECT u.id, u.username, GROUP_CONCAT(r.role_name) as roles
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        GROUP BY u.id, u.username
        ORDER BY u.id";
$stmt = $db->prepare($sql);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT i.*, s.status, u.username as created_by
        FROM invoices i
        JOIN statuses s ON i.status_id = s.id
        JOIN users u ON i.user_id = u.id
        ORDER BY i.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Invoice Manager</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .section {
            background: white;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .user-table, .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .user-table th, .user-table td,
        .invoice-table th, .invoice-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .user-table th, .invoice-table th {
            background-color: #f5f5f5;
        }
        .btn {
            padding: 5px 10px;
            border-radius: 3px;
            border: none;
            cursor: pointer;
            margin-right: 5px;
            color: white;
        }
        .btn-edit {
            background-color: #007bff;
        }
        .btn-delete {
            background-color: #dc3545;
        }
        .btn-edit:hover {
            background-color: #0056b3;
        }
        .btn-delete:hover {
            background-color: #c82333;
        }
        .nav-links {
            margin-bottom: 20px;
        }
        .nav-links a {
            margin-right: 15px;
            color: #007bff;
            text-decoration: none;
        }
        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.85em;
        }
        .status-paid { background-color: #28a745; color: white; }
        .status-pending { background-color: #ffc107; }
        .status-overdue { background-color: #dc3545; color: white; }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="admin.php" class="active">Admin Panel</a>
            <a href="logout.php">Logout</a>
        </div>

        <div class="section">
            <h2>User Management</h2>
            <table class="user-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Roles</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['roles'] ?? 'No roles'); ?></td>
                        <td>
                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-edit">Edit</a>
                            <?php if ($user['id'] !== 1): // Prevent deleting main admin ?>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="delete_user" class="btn btn-delete" 
                                        onclick="return confirm('Are you sure you want to delete this user?')">Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>All Invoices</h2>
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Client</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($invoice['number']); ?></td>
                        <td><?php echo htmlspecialchars($invoice['client']); ?></td>
                        <td>$<?php echo number_format($invoice['amount'], 2); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($invoice['status']); ?>">
                                <?php echo htmlspecialchars($invoice['status']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($invoice['created_by']); ?></td>
                        <td>
                            <a href="update.php?number=<?php echo urlencode($invoice['number']); ?>" class="btn btn-edit">Edit</a>
                            <a href="delete.php?number=<?php echo urlencode($invoice['number']); ?>" class="btn btn-delete"
                               onclick="return confirm('Are you sure you want to delete this invoice?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 