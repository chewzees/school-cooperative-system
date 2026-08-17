<?php
function view_sales_records(PDO $pdo): void
{
    $q        = search_term();
    $page_num = max(1, (int)($_GET['p'] ?? 1));
    $per_page = 20;
    $offset   = ($page_num - 1) * $per_page;

    if ($q !== '') {
        $like = '%' . $q . '%';
        $stmt = $pdo->prepare("SELECT s.id, s.receipt_no, COALESCE(m.name,'Walk-in') AS member, COALESCE(u.name,'-') AS cashier, s.payment_method, s.payment_reference, s.total, s.paid, s.created_at FROM sales s LEFT JOIN members m ON m.id=s.member_id LEFT JOIN users u ON u.id=s.cashier_id WHERE s.receipt_no LIKE ? OR m.name LIKE ? OR u.name LIKE ? OR s.payment_method LIKE ? ORDER BY s.id DESC LIMIT $per_page OFFSET $offset");
        $stmt->execute([$like, $like, $like, $like]);
        $rows = $stmt->fetchAll();
        $total_rows = count($rows); // simplified for search
    } else {
        $total_rows = (int)$pdo->query('SELECT COUNT(*) FROM sales')->fetchColumn();
        $rows = $pdo->query("SELECT s.id, s.receipt_no, COALESCE(m.name,'Walk-in') AS member, COALESCE(u.name,'-') AS cashier, s.payment_method, s.payment_reference, s.total, s.paid, s.created_at FROM sales s LEFT JOIN members m ON m.id=s.member_id LEFT JOIN users u ON u.id=s.cashier_id ORDER BY s.id DESC LIMIT $per_page OFFSET $offset")->fetchAll();
    }
    foreach ($rows as &$row) {
        $row['receipt'] = '<a class="table-action" target="_blank" href="index.php?page=receipt&id=' . (int)$row['id'] . '">Print</a>';
    }
    unset($row);
    ?>
    <section class="panel">
        <h2>Sales Record</h2>
        <?= search_form('Search sales') ?>
        <?= table($rows, ['receipt_no' => 'Invoice', 'member' => 'Customer', 'cashier' => 'Cashier', 'payment_method' => 'Payment', 'payment_reference' => 'Reference', 'total' => 'Total', 'paid' => 'Paid', 'created_at' => 'Date', 'receipt' => 'Receipt']) ?>
        <?= pagination($total_rows, $per_page, $page_num, 'sales_records') ?>
    </section>
    <?php
}
