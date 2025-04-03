<?php

require_once 'data.php';

function check_login() {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: login.php');
        exit;
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

function updateInvoice($invoice)
{
    global $db, $statuses;

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


    saveFile($new['number']);

    header("Location: index.php");
}

function addInvoice($invoice)
{
    global $db, $statuses;

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
    
    $sql = "SELECT invoices.*, statuses.status 
            FROM invoices 
            JOIN statuses ON invoices.status_id = statuses.id 
            WHERE invoices.number = :number 
            AND invoices.user_id = :user_id";
            
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':number' => $number,
        ':user_id' => $_SESSION['id']
    ]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function deleteInvoice($number)
{
    global $db;
    
    try {
        $check_sql = "SELECT id FROM invoices WHERE number = :number AND user_id = :user_id";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->execute([
            ':number' => $number,
            ':user_id' => $_SESSION['id']
        ]);
        
        if ($check_stmt->rowCount() > 0) {
            $sql = "DELETE FROM invoices WHERE number = :number AND user_id = :user_id";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                ':number' => $number,
                ':user_id' => $_SESSION['id']
            ]);
            
            $pdf_file = "documents/" . $number . ".pdf";
            if (file_exists($pdf_file)) {
                unlink($pdf_file);
            }
            
            return $result;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Error deleting invoice: " . $e->getMessage());
        return false;
    }
}
