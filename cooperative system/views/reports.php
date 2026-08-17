<?php
function view_reports(PDO $pdo): void
{
    $from = (string)($_GET['from'] ?? date('Y-m-01'));
    $to   = (string)($_GET['to']   ?? date('Y-m-d'));

    // Clamp dates to valid format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    $sales = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(refund_amount),0) FROM sales_returns WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    $returns = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    $expenses = (float)$stmt->fetchColumn();

    $stockValue = (float)$pdo->query('SELECT COALESCE(SUM(stock * cost_price),0) FROM products')->fetchColumn();

    $stmt = $pdo->prepare("SELECT p.name, SUM(si.quantity) AS sold, SUM(si.line_total) AS revenue FROM sale_items si JOIN products p ON p.id=si.product_id JOIN sales s ON s.id=si.sale_id WHERE DATE(s.created_at) BETWEEN ? AND ? GROUP BY p.id ORDER BY sold DESC LIMIT 10");
    $stmt->execute([$from, $to]);
    $topProducts = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT category, SUM(amount) AS total FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC");
    $stmt->execute([$from, $to]);
    $expenseBreakdown = $stmt->fetchAll();
    ?>
    <form method="get" class="toolbar" style="margin-bottom:20px;max-width:500px">
        <input type="hidden" name="page" value="reports">
        <label style="display:inline-flex;align-items:center;gap:6px;font-size:14px">From <input type="date" name="from" value="<?= e($from) ?>" style="min-height:36px"></label>
        <label style="display:inline-flex;align-items:center;gap:6px;font-size:14px">To <input type="date" name="to" value="<?= e($to) ?>" style="min-height:36px"></label>
        <button style="min-height:36px">Filter</button>
        <a class="button-light" href="index.php?page=reports">This Month</a>
    </form>
    <section class="stats">
        <div><span>Total Sales</span><strong><?= money($sales) ?></strong></div>
        <div><span>Returns</span><strong><?= money($returns) ?></strong></div>
        <div><span>Expenses</span><strong><?= money($expenses) ?></strong></div>
        <div><span>Estimated Profit</span><strong><?= money($sales - $returns - $expenses) ?></strong></div>
        <div><span>Stock Value (current)</span><strong><?= money($stockValue) ?></strong></div>
    </section>
    <section class="alert-grid">
        <section class="panel">
            <h2>Top Products</h2>
            <?= table($topProducts, ['name' => 'Product', 'sold' => 'Sold', 'revenue' => 'Revenue']) ?>
        </section>
        <section class="panel">
            <h2>Expense Breakdown</h2>
            <?= table($expenseBreakdown, ['category' => 'Category', 'total' => 'Total']) ?>
        </section>
    </section>
    <?php
}
