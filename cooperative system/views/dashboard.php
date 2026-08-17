<?php
function view_dashboard(PDO $pdo): void
{
    $stats = [
        'Customers'   => $pdo->query('SELECT COUNT(*) FROM members')->fetchColumn(),
        'Products'    => $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'Stock Units' => $pdo->query('SELECT COALESCE(SUM(stock),0) FROM products')->fetchColumn(),
        'Sales Today' => money($pdo->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(created_at)=DATE('now')")->fetchColumn()),
    ];
    $lowStock   = $pdo->query('SELECT sku, name, stock, reorder_level FROM products WHERE stock > 0 AND stock <= reorder_level ORDER BY stock ASC LIMIT 8')->fetchAll();
    $outStock   = $pdo->query('SELECT sku, name, stock FROM products WHERE stock <= 0 ORDER BY name LIMIT 8')->fetchAll();
    $returnsToday = money($pdo->query("SELECT COALESCE(SUM(refund_amount),0) FROM sales_returns WHERE DATE(created_at)=DATE('now')")->fetchColumn());
    $recentLogs = $pdo->query('SELECT COALESCE(u.name,"System") AS user, al.action, al.details, al.created_at FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id ORDER BY al.id DESC LIMIT 10')->fetchAll();
    ?>
    <section class="stats">
        <?php foreach ($stats as $label => $value): ?>
            <div><span><?= e($label) ?></span><strong><?= e((string)$value) ?></strong></div>
        <?php endforeach; ?>
        <div><span>Returns Today</span><strong><?= e($returnsToday) ?></strong></div>
    </section>
    <section class="alert-grid">
        <section class="panel">
            <h2>Low Stock Alerts</h2>
            <?= table($lowStock, ['sku' => 'SKU', 'name' => 'Product', 'stock' => 'Stock', 'reorder_level' => 'Reorder']) ?>
        </section>
        <section class="panel">
            <h2>Out Of Stock</h2>
            <?= table($outStock, ['sku' => 'SKU', 'name' => 'Product', 'stock' => 'Stock']) ?>
        </section>
    </section>
    <section class="panel">
        <h2>Recent Activity</h2>
        <?= table($recentLogs, ['created_at' => 'Date', 'user' => 'User', 'action' => 'Action', 'details' => 'Details']) ?>
    </section>
    <?php
}
