<?php
function view_sales_returns(PDO $pdo): void
{
    $products = $pdo->query('SELECT id, name FROM products ORDER BY name')->fetchAll();
    $sales    = $pdo->query('SELECT id, receipt_no FROM sales ORDER BY id DESC LIMIT 200')->fetchAll();
    $page_num = max(1, (int)($_GET['p'] ?? 1));
    $per_page = 20;
    $offset   = ($page_num - 1) * $per_page;
    $total_rows = (int)$pdo->query('SELECT COUNT(*) FROM sales_returns')->fetchColumn();
    $rows = $pdo->query("SELECT sr.return_no, COALESCE(s.receipt_no,'-') AS receipt_no, p.name, sr.quantity, sr.refund_amount, sr.reason, sr.created_at FROM sales_returns sr LEFT JOIN sales s ON s.id=sr.sale_id JOIN products p ON p.id=sr.product_id ORDER BY sr.id DESC LIMIT $per_page OFFSET $offset")->fetchAll();
    ?>
    <section class="grid two">
        <form method="post" class="panel form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_return">
            <h2>Add Sales Return</h2>
            <label>Original Invoice
                <select name="sale_id">
                    <option value="">No invoice selected</option>
                    <?= options($sales, 'id', 'receipt_no') ?>
                </select>
            </label>
            <label>Product
                <select name="product_id" required>
                    <?= options($products, 'id', 'name') ?>
                </select>
            </label>
            <label>Quantity <input name="quantity" type="number" min="1" required></label>
            <label>Refund Amount <input name="refund_amount" type="number" step="0.01" min="0" value="0"></label>
            <label>Reason <input name="reason" maxlength="200"></label>
            <button>Record Return</button>
        </form>
        <section class="panel wide">
            <h2>Sales Return List</h2>
            <?= table($rows, ['return_no' => 'Return No', 'receipt_no' => 'Invoice', 'name' => 'Product', 'quantity' => 'Qty', 'refund_amount' => 'Refund', 'reason' => 'Reason', 'created_at' => 'Date']) ?>
            <?= pagination($total_rows, $per_page, $page_num, 'sales_returns') ?>
        </section>
    </section>
    <?php
}
