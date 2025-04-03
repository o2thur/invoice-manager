<?php
    require "data.php";
    require_once 'functions.php';
    check_login();

    $status = array_map(function($status) {
        return $status['status'];
    }, $statuses);
    
    if(isset($_GET['status'])){
        $invoices = filterInvoices($invoices, $_GET['status']);
        $status = $_GET['status'];
    }

    // var_dump($invoices)
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <title>Invoice Manager</title>
</head>
<body>
    <main class="container my-4">
        <nav class="mb-3">
            <div id="infoContainer" class="d-flex justify-content-between align-items-center">
                <div>
                    <h1>Invoice Manager</h1>
                    <p>There are <?php echo count($invoices) ?> invoices</p>
                </div>
                <div class="d-flex align-items-center">
                    <span class="me-3">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="logout.php" class="btn btn-outline-danger">Logout</a>
                </div>
            </div>
            <ul class="nav">
                <li class="nav-item">
                    <a href="index.php" class="nav-link">All</a>
                </li>
                <?php foreach ($statuses as $status_row): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?status=<?php echo $status_row['status'] ;?>">
                            <?php echo ucfirst($status_row['status']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li class="nav-item">
                    <a class="nav-link" href="add.php">Add ></a>
                </li>        
            </ul>
        </nav>
        <section>
            <?php foreach ($invoices as $invoice): ?>
                <div class="row mb-2 border rounded align-items-center">
                    <div class="col-lg-2 col-md-3 col-6">
                        <div class="p-3 d-flex justify-content-center">
                            <p class="fw-bold m-0"><?php echo htmlspecialchars($invoice['number']); ?></p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <div class="p-3 d-flex justify-content-center">
                            <p class="text-primary m-0"><?php echo htmlspecialchars($invoice['client']); ?></p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <div class="p-3 d-flex justify-content-center">
                            <p class="m-0">$<?php echo htmlspecialchars($invoice['amount']); ?></p>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <div class="p-3 d-flex justify-content-center" >
                            <div class="w-75 py-1 px-3 rounded" style="background-color:<?php echo ($invoice['status'] === 'draft' || $invoice['status'] === 'paid') ? '#c9e3d8' : ($invoice['status'] === 'pending' ? '#fff1c5' : '#fff'); ?>">
                                <p class="rounded text-center m-0">
                                    <?php echo htmlspecialchars(ucfirst($invoice['status'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="row col-lg-4">
                        <div class="col-4 col-lg-4 d-flex justify-content-center">
                            <a class="btn btn-primary" href="update.php?number=<?php echo htmlspecialchars(urlencode($invoice['number'])); ?>">Edit</a>
                        </div>
                        <div class="col-4 col-lg-4 d-flex justify-content-center">
                            <form action="delete.php" method="post">
                                <input type="hidden" name="number" value="<?php echo htmlspecialchars($invoice['number']); ?>">
                                <input type="submit" value="Delete" class="btn btn-danger">
                            </form>
                        </div>
                        <?php if(file_exists("documents/" . $invoice['number'] . ".pdf")): ?>
                            <div class="col-4 col-lg-4 d-flex justify-content-center">
                                 <a class="btn btn-secondary" href="documents/<?php echo htmlspecialchars(urlencode($invoice['number'])); ?>.pdf" target="_blank">View</a>
                            </div>
                        <?php endif;?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>