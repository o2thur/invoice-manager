<?php
    require 'data.php';
    require 'functions.php';
    check_login();

    $invoice = [];
    $errors = [];

    if($_SERVER['REQUEST_METHOD'] === 'POST'){
        // Verify CSRF token if implemented
        // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        //     die("CSRF attack detected");
        // }
        
        $invoice = sanitize($_POST);
        $errors = validate($invoice);

        if(count($errors) === 0){
            try {
                updateInvoice($invoice);
            } catch (Exception $e) {
                error_log("Error updating invoice: " . $e->getMessage());
                $errors['system'] = "An error occurred while processing your request. Please try again later.";
            }
        }
    } 

    if(isset($_GET['number'])){
        $invoice_number = htmlspecialchars(trim($_GET['number']), ENT_QUOTES, 'UTF-8');
        
        $check_sql = "SELECT invoices.*, statuses.status 
                      FROM invoices 
                      JOIN statuses ON invoices.status_id = statuses.id 
                      WHERE invoices.number = :number AND invoices.user_id = :user_id";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->execute([
            ':number' => $invoice_number,
            ':user_id' => $_SESSION['id']
        ]);
        
        if ($check_stmt->rowCount() === 0) {
            $_SESSION['error_message'] = "Invoice not found or you don't have permission to edit it.";
            header('Location: index.php');
            exit;
        }
        
        $invoice = $check_stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        header('Location: index.php');
        exit;
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
    <title>Invoice Manager | Update</title>
</head>
<body>
        <main class="container my-4">
        <nav class="mb-3">
            <div id="infoContainer">
                <h1>Invoice Manager</h1>
                <p>Updating invoice</p>
            </div>
            <ul class="nav">
                <li class="nav-item">
                    <a href="index.php" class="nav-link">All</a>
                </li>
                <?php foreach ($statuses as $status): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?status=<?php echo $status['status'] ;?>">
                            <?php echo ucfirst($status['status']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <section class="bg-light p-5">
            <div class="container">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" id="number" name="number" value="<?php echo $invoice['number'] ?>">
                    
                    <div class="form-group mb-3">
                        <label for="name">Client name:</label>
                        <input type="text" id="client" name="client" class="form-control" value="<?php echo $invoice['client'] ?? '';?>">
                        <div class="error text-danger"><?php echo $errors['client'] ?? '' ;?></div>
                    </div>
                    <div class="form-group mb-3">
                        <label for="email">Client email:</label>
                        <input type="text" id="email" name="email" class="form-control" value="<?php echo $invoice['email'] ?? '';?>">
                        <div class="error text-danger"><?php echo $errors['email'] ?? '' ;?></div>
                    </div>
                    <div class="form-group mb-3">
                        <label for="amount">Invoice Amount:</label>
                        <input type="number" id="amount" name="amount" class="form-control" value="<?php echo $invoice['amount'] ?? '';?>">
                        <div class="error text-danger"><?php echo $errors['amount'] ?? '' ;?></div>
                    </div>
                    <div class="form-group mb-3">
                        <label for="status">Invoice Status:</label>
                        <select name="status" id="status" class="form-control">
                            <?php foreach($statuses as $status): ?>
                            <option value="<?php echo $status['status']?>" 
                            <?php if($status['status'] == $invoice['status']): ?> selected <?php endif; ?>
                                >
                                <?php echo ucfirst($status['status']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="error text-danger"><?php echo $errors['status'] ?? '' ;?></div>
                    </div>
                    <div class="form-group mb-3">
                        <input type="file" name="file" id="" accept=".pdf">
                    </div>
                    <button type="submit" class="btn btn-primary">Update</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>