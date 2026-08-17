<?php
function view_sales(PDO $pdo): void
{
    $productRows = $pdo->query('SELECT id, name, sale_price, stock FROM products WHERE stock > 0 ORDER BY name')->fetchAll();
    $members     = $pdo->query('SELECT id, name FROM members WHERE status = "Active" ORDER BY name')->fetchAll();
    $page_num    = max(1, (int)($_GET['p'] ?? 1));
    $per_page    = 20;
    $offset      = ($page_num - 1) * $per_page;
    $total_rows  = (int)$pdo->query('SELECT COUNT(*) FROM sales')->fetchColumn();
    $rows = $pdo->query("SELECT s.receipt_no, COALESCE(m.name,'Walk-in') AS member, s.payment_method, s.total, s.paid, s.created_at FROM sales s LEFT JOIN members m ON m.id=s.member_id ORDER BY s.id DESC LIMIT $per_page OFFSET $offset")->fetchAll();
    ?>
    <section class="grid two">
        <form method="post" class="panel form-grid sale-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_sale">
            <h2>New Sale</h2>
            <label>Customer
                <select name="member_id">
                    <option value="">Walk-in</option>
                    <?= options($members, 'id', 'name') ?>
                </select>
            </label>
            <div id="sale-items">
                <?php for ($i = 0; $i < 3; $i++): ?>
                <div class="sale-row enhanced">
                    <select name="items[<?= $i ?>][product_id]">
                        <option value="">Product</option>
                        <?php foreach ($productRows as $product): ?>
                        <option value="<?= e((string)$product['id']) ?>" data-price="<?= e((string)$product['sale_price']) ?>" data-stock="<?= e((string)$product['stock']) ?>">
                            <?= e($product['name']) ?> - <?= money($product['sale_price']) ?> / Stock <?= e((string)$product['stock']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input name="items[<?= $i ?>][quantity]" type="number" min="0" placeholder="Qty">
                    <span class="line-total"><?= CURRENCY_SYMBOL ?> 0.00</span>
                </div>
                <?php endfor; ?>
            </div>
            <button type="button" class="button-light" id="add-sale-row">+ Add Row</button>
            <div class="live-total">
                <span>Total</span><strong id="sale-total"><?= CURRENCY_SYMBOL ?> 0.00</strong>
                <span>Change / Balance</span><strong id="sale-change"><?= CURRENCY_SYMBOL ?> 0.00</strong>
            </div>
            <label>Payment Method
                <select name="payment_method">
                    <option>Cash</option>
                    <option>Touch n Go eWallet</option>
                    <option>Bank Transfer</option>
                </select>
            </label>
            <label>Payment Reference <input name="payment_reference" maxlength="100"></label>
            <label>Paid <input name="paid" type="number" step="0.01" placeholder="Leave blank for exact"></label>
            <button>Save Sale</button>
        </form>
        <section class="panel wide">
            <h2>Latest Sales</h2>
            <?= table($rows, ['receipt_no' => 'Invoice', 'member' => 'Customer', 'payment_method' => 'Payment', 'total' => 'Total', 'paid' => 'Paid', 'created_at' => 'Date']) ?>
            <?= pagination($total_rows, $per_page, $page_num, 'sales') ?>
        </section>
    </section>
    <script>
    (function() {
        let rowIndex = <?= 3 ?>;
        const products = <?= json_encode(array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name'], 'price' => $p['sale_price'], 'stock' => $p['stock']], $productRows)) ?>;
        document.getElementById('add-sale-row')?.addEventListener('click', () => {
            const opts = products.map(p => `<option value="${p.id}" data-price="${p.price}" data-stock="${p.stock}">${p.name.replace(/</g,'&lt;')} - <?= CURRENCY_SYMBOL ?> ${Number(p.price).toFixed(2)} / Stock ${p.stock}</option>`).join('');
            const div = document.createElement('div');
            div.className = 'sale-row enhanced';
            div.innerHTML = `<select name="items[${rowIndex}][product_id]"><option value="">Product</option>${opts}</select><input name="items[${rowIndex}][quantity]" type="number" min="0" placeholder="Qty"><span class="line-total"><?= CURRENCY_SYMBOL ?> 0.00</span>`;
            document.getElementById('sale-items').appendChild(div);
            rowIndex++;
            if (window.updateSaleTotals) window.updateSaleTotals();
        });
    })();
    </script>
    <?php
}
