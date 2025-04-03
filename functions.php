<?php

require_once 'data.php';

function check_login() {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: login.php');
        exit;
    }
    
    // Load user permissions if not already loaded
    if (!isset($_SESSION['permissions'])) {
        $_SESSION['permissions'] = getUserPermissions($_SESSION['id']);
    }
}

function sanitize($data)
{
    return array_map(function ($value) {
        if (is_string($value)) {
            $value = trim($value);
            $value = stripslashes($value);
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            return $value;
        }
        return $value;
    }, $data);
}


function validate($invoice)
{
    $fields = ['client', 'email', 'amount', 'status'];
    $errors = [];
    global $statuses;

    $status = array_map(function ($status) {
        return $status['status'];
    }, $statuses);

    foreach ($fields as $field) {
        switch ($field) {
            case 'client':
                if (empty($invoice[$field])) {
                    $errors[$field] = 'Client name is required';
                } else if (strlen($invoice[$field]) > 255) {
                    $errors[$field] = 'Client name must be fewer than 255 characters';
                } else if (!preg_match('/^[a-zA-Z\s]+$/', $invoice[$field])) {
                    $errors[$field] = 'Client name must contain only letters and spaces';
                }
                break;
            case 'email':
                if (empty($invoice[$field])) {
                    $errors[$field] = 'Email is required';
                } else if (filter_var($invoice[$field], FILTER_VALIDATE_EMAIL) === false) {
                    $errors[$field] = 'Email must be a valid address';
                }
                break;
            case 'amount':
                if (empty($invoice[$field])) {
                    $errors[$field] = 'Amount is required';
                } else if (filter_var($invoice[$field], FILTER_VALIDATE_INT) === false) {
                    $errors[$field] = 'Amount must contain only numbers';
                }
                break;
            case 'status':
                if (empty($invoice[$field])) {
                    $errors[$field] = 'Status is required';
                } else if (!in_array($invoice[$field], $status)) {
                    $errors[$field] = 'Status must be in the list of statuses';
                }
                break;
        }
    }
    return $errors;
}

function saveFile($invoice_number)
{
    $file = $_FILES['file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed_extensions = ['pdf'];
        $max_file_size = 5 * 1024 * 1024; 
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed_extensions)) {
            return false;
        }
        
        if ($file['size'] > $max_file_size) {
            return false;
        }
        
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $invoice_number) . "." . $ext;

        if (!file_exists('documents/')) {
            if (!mkdir('documents/', 0755, true)) {
                return false;
            }
        }

        $dest = "documents/" . $filename;

        if (file_exists($dest)) {
            unlink($dest);
        }

        return move_uploaded_file($file['tmp_name'], $dest);
    }

    return false;
}

function logAudit($action_type, $table_name, $record_id, $old_value = null, $new_value = null) {
    global $db;
    
    $user_id = isset($_SESSION['id']) ? $_SESSION['id'] : null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $sql = "INSERT INTO audit_logs (user_id, action_type, table_name, record_id, old_value, new_value, ip_address, user_agent)
            VALUES (:user_id, :action_type, :table_name, :record_id, :old_value, :new_value, :ip_address, :user_agent)";
            
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':user_id' => $user_id,
        ':action_type' => $action_type,
        ':table_name' => $table_name,
        ':record_id' => $record_id,
        ':old_value' => $old_value ? json_encode($old_value) : null,
        ':new_value' => $new_value ? json_encode($new_value) : null,
        ':ip_address' => $ip_address,
        ':user_agent' => $user_agent
    ]);
}

function updateInvoice($invoice)
{
    global $db, $statuses;
    
    check_permission('invoice_update');
    
    // Get the old invoice data for audit and permission check
    $old_invoice = getInvoice($invoice['number']);
    
    // Check if user has permission to update this specific invoice
    if (!$old_invoice || (!hasPermission('invoice_read_all') && $old_invoice['user_id'] != $_SESSION['id'])) {
        http_response_code(403);
        die('Access Denied: You do not have permission to update this invoice');
    }

    $status_id = null;
    foreach ($statuses as $status) {
        if ($status['status'] == $invoice['status']) {
            $status_id = $status['id'];
            break;
        }
    }

    $new = [
        'number' => $invoice['number'],
        'amount' => $invoice['amount'],
        'status' => $invoice['status'],
        'client' => $invoice['client'],
        'email'  => $invoice['email']
    ];

    $sql = "UPDATE invoices SET amount = :amount, status_id = :status_id, client = :client, email = :email WHERE number = :number";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':number' => $new['number'],
        ':client' => $new['client'],
        ':email'  => $new['email'],
        ':amount' => $new['amount'],
        ':status_id' => $status_id,
    ]);

    // Log the update
    logAudit('UPDATE', 'invoices', $old_invoice['id'], $old_invoice, $new);

    saveFile($new['number']);

    header("Location: index.php");
}

function addInvoice($invoice)
{
    global $db, $statuses;
    
    check_permission('invoice_create');
    
    $status_id = null;

    foreach ($statuses as $status) {
        if ($status['status'] == $invoice['status']) {
            $status_id = $status['id'];
            break;
        }
    }

    $newInvoice = [
        'number' => createInvoiceNumber(),
        'amount' => $invoice['amount'],
        'status' => $invoice['status'],
        'client' => $invoice['client'],
        'email'  => $invoice['email'],
        'user_id' => $_SESSION['id']
    ];

    $sql = "INSERT INTO invoices (`number`, `client`, `email`, `amount`, `status_id`, `user_id`)
        VALUES (:number, :client, :email, :amount, :status_id, :user_id)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':number' => $newInvoice['number'],
        ':client' => $newInvoice['client'],
        ':email'  => $newInvoice['email'],
        ':amount' => $newInvoice['amount'],
        ':status_id' => $status_id,
        ':user_id' => $newInvoice['user_id']
    ]);

    // Get the newly inserted invoice ID
    $new_invoice_id = $db->lastInsertId();
    
    // Log the insert
    logAudit('INSERT', 'invoices', $new_invoice_id, null, $newInvoice);

    saveFile($newInvoice['number']);

    header("Location: index.php");
}

function filterInvoices($invoices, $status)
{
    if ($status === "all") {
        return $invoices;
    }

    return array_filter($invoices, function ($invoice) use ($status) {
        return $invoice['status'] === $status;
    });
}

function createInvoiceNumber($length = 5)
{
    $letters = range('A', 'Z');
    $number = [];

    for ($i = 0; $i < $length; $i++) {
        array_push($number, $letters[rand(0, count($letters) - 1)]);
    }
    return implode($number);
}

function getInvoice($number)
{
    global $db;
    
    if (!isset($_SESSION['id'])) {
        return null;
    }
    
    check_permission('invoice_read');
    
    $sql = "SELECT invoices.*, statuses.status 
            FROM invoices 
            JOIN statuses ON invoices.status_id = statuses.id 
            WHERE invoices.number = :number";
            
    // Add row-level security check
    if (!hasPermission('invoice_read_all')) {
        $sql .= " AND invoices.user_id = :user_id";
    }
            
    $stmt = $db->prepare($sql);
    $params = [':number' => $number];
    
    if (!hasPermission('invoice_read_all')) {
        $params[':user_id'] = $_SESSION['id'];
    }
    
    $stmt->execute($params);
    
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function deleteInvoice($number)
{
    global $db;
    
    check_permission('invoice_delete');
    
    try {
        // Get the invoice data before deletion for audit and permission check
        $old_invoice = getInvoice($number);
        
        // Check if user has permission to delete this specific invoice
        if (!$old_invoice || (!hasPermission('invoice_read_all') && $old_invoice['user_id'] != $_SESSION['id'])) {
            http_response_code(403);
            die('Access Denied: You do not have permission to delete this invoice');
        }
        
        if ($old_invoice) {
            $sql = "DELETE FROM invoices WHERE number = :number AND user_id = :user_id";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                ':number' => $number,
                ':user_id' => $_SESSION['id']
            ]);
            
            if ($result) {
                // Log the deletion
                logAudit('DELETE', 'invoices', $old_invoice['id'], $old_invoice, null);
                
                // Delete associated file if it exists
                $file_path = "documents/" . $number . ".pdf";
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                return true;
            }
        }
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

function getUserPermissions($user_id) {
    global $db;
    
    $sql = "SELECT DISTINCT p.permission_name
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = :user_id";
            
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function hasPermission($permission) {
    if (!isset($_SESSION['id'])) {
        return false;
    }
    
    if (!isset($_SESSION['permissions'])) {
        $_SESSION['permissions'] = getUserPermissions($_SESSION['id']);
    }
    
    return in_array($permission, $_SESSION['permissions']);
}

function check_permission($permission) {
    if (!hasPermission($permission)) {
        http_response_code(403);
        die('Access Denied: Insufficient permissions');
    }
}
