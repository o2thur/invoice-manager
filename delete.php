<?php
require_once 'data.php';
require_once 'functions.php';
check_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['number'])) {
    $invoice_number = htmlspecialchars(trim($_POST['number']), ENT_QUOTES, 'UTF-8');
    
    try {
        $check_sql = "SELECT id FROM invoices WHERE number = :number AND user_id = :user_id";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->execute([
            ':number' => $invoice_number,
            ':user_id' => $_SESSION['id']
        ]);

        if ($check_stmt->rowCount() > 0) {
            if (deleteInvoice($invoice_number)) {
                $_SESSION['success_message'] = "Invoice deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to delete invoice.";
            }
        } else {
            $_SESSION['error_message'] = "Invoice not found or you don't have permission to delete it.";
        }
    } catch (Exception $e) {
        error_log("Error in delete.php: " . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred while processing your request.";
    }
} else {
    $_SESSION['error_message'] = "Invalid request.";
}

header('Location: index.php');
exit;