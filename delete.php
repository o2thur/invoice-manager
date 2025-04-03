<?php
require_once 'data.php';
require_once 'functions.php';
check_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['number'])) {
    // First check if the invoice belongs to the logged-in user
    $check_sql = "SELECT id FROM invoices WHERE number = :number AND user_id = :user_id";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->execute([
        ':number' => $_POST['number'],
        ':user_id' => $_SESSION['id']
    ]);

    if ($check_stmt->rowCount() > 0) {
        deleteInvoice($_POST['number']);
    }
}

header('Location: index.php');
exit;