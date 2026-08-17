<?php
function view_products(PDO $pdo): void
{
    $q          = search_term();
    $categories = $pdo->query('SELECT id, name FROM categories WHERE status = "Active" ORDER BY name')->fetchAll();
    $suppliers  = $pdo->query('SELECT id, name FROM suppliers WHERE status = "Active" ORDER BY name')->fetchAll();

    $edit = null;
    if (isset($_GET['edit_product'])) {
        $stmt = $pdo->prepare('SELECT id, sku, name, category, category_id, supplier_id, cost_price, sale_price, stock, reorder_level FROM products WHERE id = ?');
        $stmt->execute([(int)$_GET['edit_product']]);
        $edit = $stmt->fetch() ?: null;
    }

    if ($q !== '') {
        $like = '%' . $q . '%';
        $stmt = $pdo->prepare('SELECT p.id, p.sku, p.name, p.category, COALESCE(s.name,"-") AS supplier, p.cost_price, p.sale_price, p.stock, p.reorder_level FROM products p LEFT JOIN suppliers s ON s.id=p.supplier_id WHERE p.sku LIKE ? OR p.name LIKE ? OR p.category LIKE ? OR s.name LIKE ? ORDER BY p.id DESC');
        $stmt->execute([$like, $like, $like, $like]);
        $rows = $stmt->fetchAll();
    } else {
        $rows = $pdo->query('SELECT p.id, p.sku, p.name, p.category, COALESCE(s.name,"-") AS supplier, p.cost_price, p.sale_price, p.stock, p.reorder_level FROM products p LEFT JOIN suppliers s ON s.id=p.supplier_id ORDER BY p.id DESC')->fetchAll();
    }
    ?>
    <section class="grid two">
        <?php if ($edit): ?>
        <form method="post" class="panel form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_product">
            <input type="hidden" name="product_id" value="<?= (int)$edit['id'] ?>">
            <h2>Edit Product</h2>
            <label>SKU <input name="sku" required maxlength="50" value="<?= e($edit['sku']) ?>"></label>
            <label>Name <input name="name" required maxlength="150" value="<?= e($edit['name']) ?>"></label>
            <label>Category
                <select name="category_id">
                    <option value="">Manual category</option>
                    <?= options($categories, 'id', 'name', (int)$edit['category_id']) ?>
                </select>
            </label>
            <label>Manual Category <input name="category" maxlength="80" value="<?= e($edit['category']) ?>"></label>
            <label>Supplier
                <select name="supplier_id">
                    <option value="">None</option>
                    <?= options($suppliers, 'id', 'name', (int)$edit['supplier_id']) ?>
                </select>
            </label>
            <label>Cost Price <input name="cost_price" type="number" step="0.01" min="0" value="<?= e((string)$edit['cost_price']) ?>"></label>
            <label>Sale Price <input name="sale_price" type="number" step="0.01" min="0" value="<?= e((string)$edit['sale_price']) ?>"></label>
            <label>Reorder Level <input name="reorder_level" type="number" min="0" value="<?= e((string)$edit['reorder_level']) ?>"></label>
            <div style="display:flex;gap:8px">
                <button>Save Changes</button>
                <a class="button-light" href="index.php?page=products">Cancel</a>
            </div>
        </form>
        <?php else: ?>
        <form method="post" class="panel form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_product">
            <h2>Add Product</h2>
            <label>SKU <input name="sku" required maxlength="50"></label>
            <label>Name <input name="name" required maxlength="150"></label>
            <label>Category
                <select name="category_id">
                    <option value="">Manual category</option>
                    <?= options($categories, 'id', 'name') ?>
                </select>
            </label>
            <label>Manual Category <input name="category" maxlength="80" placeholder="Stationery"></label>
            <label>Supplier
                <select name="supplier_id">
                    <option value="">None</option>
                    <?= options($suppliers, 'id', 'name') ?>
                </select>
            </label>
            <label>Cost Price <input name="cost_price" type="number" step="0.01" min="0" value="0"></label>
            <label>Sale Price <input name="sale_price" type="number" step="0.01" min="0" value="0"></label>
            <label>Opening Stock <input name="stock" type="number" min="0" value="0"></label>
            <label>Reorder Level <input name="reorder_level" type="number" min="0" value="5"></label>
            <button>Add Product</button>
        </form>
        <?php endif; ?>
        <section class="panel wide">
            <h2>Product List</h2>
            <?= search_form('Search products') ?>
            <?= table($rows, ['sku' => 'SKU', 'name' => 'Product', 'category' => 'Category', 'supplier' => 'Supplier', 'cost_price' => 'Cost', 'sale_price' => 'Price', 'stock' => 'Stock', 'reorder_level' => 'Reorder'], 'products', 'edit_product') ?>
        </section>
    </section>
    <?php
}
