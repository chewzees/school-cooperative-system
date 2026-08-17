<?php
function view_purchases(PDO $pdo): void
{
    $products  = $pdo->query('SELECT id, sku || " - " || name AS name FROM products ORDER BY name')->fetchAll();
    $suppliers = $pdo->query('SELECT id, name FROM suppliers WHERE status = "Active" ORDER BY name')->fetchAll();
    $page_num  = max(1, (int)($_GET['p'] ?? 1));
    $per_page  = 20;
    $offset    = ($page_num - 1) * $per_page;
    $total_rows = (int)$pdo->query('SELECT COUNT(*) FROM purchases')->fetchColumn();
    $rows = $pdo->query("SELECT p.purchase_no, COALESCE(s.name,\"-\") AS supplier, COUNT(pi.id) AS items, p.total, p.purchase_date FROM purchases p LEFT JOIN suppliers s ON s.id=p.supplier_id LEFT JOIN purchase_items pi ON pi.purchase_id=p.id GROUP BY p.id ORDER BY p.id DESC LIMIT $per_page OFFSET $offset")->fetchAll();
    ?>
    <section class="grid two">
        <form method="post" class="panel form-grid purchase-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_purchase">
            <h2>New Purchase Invoice</h2>
            <label>Supplier
                <select name="supplier_id">
                    <option value="">None</option>
                    <?= options($suppliers, 'id', 'name') ?>
                </select>
            </label>
            <div id="purchase-items">
                <?php for ($i = 0; $i < 3; $i++): ?>
                <div class="purchase-row">
                    <select name="items[<?= $i ?>][product_id]">
                        <option value="">Product</option>
                        <?= options($products, 'id', 'name') ?>
                    </select>
                    <input name="items[<?= $i ?>][quantity]" type="number" min="0" placeholder="Qty">
                    <input name="items[<?= $i ?>][unit_cost]" type="number" step="0.01" min="0" placeholder="Cost">
                </div>
                <?php endfor; ?>
            </div>
            <button type="button" class="button-light" id="add-purchase-row">+ Add Row</button>
            <label>Date <input name="purchase_date" type="date" required value="<?= date('Y-m-d') ?>"></label>
            <label>Note <input name="note" maxlength="250"></label>
            <div class="live-total">
                <span>Estimated Total</span>
                <strong id="purchase-total"><?= CURRENCY_SYMBOL ?> 0.00</strong>
            </div>
            <button>Record Purchase</button>
        </form>
        <section class="panel wide">
            <h2>Purchase Record</h2>
            <?= table($rows, ['purchase_no' => 'Purchase No', 'supplier' => 'Supplier', 'items' => 'Items', 'total' => 'Total', 'purchase_date' => 'Date']) ?>
            <?= pagination($total_rows, $per_page, $page_num, 'purchases') ?>
        </section>
    </section>
    <script>
    (function() {
        let rowIndex = <?= 3 ?>;
        const products = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name']], $products)) ?>;
        document.getElementById('add-purchase-row')?.addEventListener('click', () => {
            const opts = products.map(p => `<option value="${p.id}">${p.name.replace(/</g,'&lt;')}</option>`).join('');
            const div = document.createElement('div');
            div.className = 'purchase-row';
            div.innerHTML = `<select name="items[${rowIndex}][product_id]"><option value="">Product</option>${opts}</select><input name="items[${rowIndex}][quantity]" type="number" min="0" placeholder="Qty"><input name="items[${rowIndex}][unit_cost]" type="number" step="0.01" min="0" placeholder="Cost">`;
            document.getElementById('purchase-items').appendChild(div);
            rowIndex++;
        });
    })();
    </script>
    <?php
}
