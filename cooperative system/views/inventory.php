<?php
function view_inventory(PDO $pdo): void
{
    $products = $pdo->query('SELECT id, sku || " - " || name AS name FROM products ORDER BY name')->fetchAll();
    $page_num = max(1, (int)($_GET['p'] ?? 1));
    $per_page = 20;
    $offset   = ($page_num - 1) * $per_page;
    $total_rows = (int)$pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn();
    $rows = $pdo->query("SELECT sm.created_at, p.name, sm.movement_type, sm.quantity, sm.note FROM stock_movements sm JOIN products p ON p.id=sm.product_id ORDER BY sm.id DESC LIMIT $per_page OFFSET $offset")->fetchAll();
    ?>
    <section class="grid two">
        <form method="post" class="panel form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="stock_movement">
            <h2>Stock Movement</h2>
            <label>Product
                <select name="product_id" required>
                    <?= options($products, 'id', 'name') ?>
                </select>
            </label>
            <label>Type
                <select name="movement_type">
                    <option>In</option>
                    <option>Out</option>
                </select>
            </label>
            <label>Quantity <input name="quantity" type="number" min="1" required></label>
            <label>Note <input name="note" maxlength="200"></label>
            <button>Update Stock</button>
        </form>
        <section class="panel wide">
            <h2>Recent Movements</h2>
            <?= table($rows, ['created_at' => 'Date', 'name' => 'Product', 'movement_type' => 'Type', 'quantity' => 'Qty', 'note' => 'Note']) ?>
            <?= pagination($total_rows, $per_page, $page_num, 'inventory') ?>
        </section>
    </section>
    <?php
}
