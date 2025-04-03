<?php 
  session_start();
  try{
    $dsn = 'mysql:host=localhost;dbname=invoice_manager';
    $db = new PDO($dsn, 'root', 'root');

    // Get invoices for the logged-in user
    $invoices_sql = "SELECT invoices.*, statuses.status 
                     FROM invoices 
                     JOIN statuses ON invoices.status_id = statuses.id 
                     WHERE invoices.user_id = :user_id 
                     ORDER BY invoices.id";
    $invoices_stmt = $db->prepare($invoices_sql);
    $invoices_stmt->execute([':user_id' => $_SESSION['id']]);
    $invoices = $invoices_stmt->fetchAll(PDO::FETCH_ASSOC);

    $statuses_result = $db->query("SELECT * FROM statuses");
    $statuses = $statuses_result->fetchAll(PDO::FETCH_ASSOC);
  
  } catch (PDOException $e) {
    print "Error!: " . $e->getMessage() . "</br>";
    exit();
  }

  // $statuses = [
  //   [
  //     'id' => '1',
  //     'status' => 'draft',
  //   ],
  //   [
  //     'id' => '2',
  //     'status' => 'pending',
  //   ],
  //   [
  //     'id' => '3',
  //     'status' => 'paid',
  //   ]
  // ]
