<?php 
  session_start();
  try{
    $dsn = 'mysql:host=localhost;dbname=invoice_manager';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    $db = new PDO($dsn, 'root', 'root', $options);

    // Get statuses (needed for both logged in and non-logged in states)
    $statuses_stmt = $db->prepare("SELECT * FROM statuses");
    $statuses_stmt->execute();
    $statuses = $statuses_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Only fetch invoices if user is logged in
    $invoices = [];
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($_SESSION['id'])) {
        $invoices_sql = "SELECT invoices.*, statuses.status 
                        FROM invoices 
                        JOIN statuses ON invoices.status_id = statuses.id 
                        WHERE invoices.user_id = :user_id 
                        ORDER BY invoices.id";
        $invoices_stmt = $db->prepare($invoices_sql);
        $invoices_stmt->execute([':user_id' => $_SESSION['id']]);
        $invoices = $invoices_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

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
