<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

// Load all view files
foreach (glob(__DIR__ . '/views/*.php') as $view) {
    require $view;
}

$pdo  = db();
$page = $_GET['page'] ?? 'dashboard';

// ------------------------------------------------------------------
// Logout
// ------------------------------------------------------------------
if ($page === 'logout') {
    session_destroy();
    redirect('index.php?page=login');
}

// ------------------------------------------------------------------
// Login page
// ------------------------------------------------------------------
if ($page === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $username = str_limit((string)post('username'), 50);

        if (!check_login_rate_limit($pdo, $username)) {
            flash('Too many login attempts. Please wait 15 minutes.', 'danger');
        } else {
            record_login_attempt($pdo, $username);
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND status = "Active"');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            if ($user && password_verify((string)post('password'), $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name'], 'role' => $user['role']];
                if ((int)$user['must_change_password'] === 1) {
                    flash('Please change your password before continuing.', 'danger');
                    redirect('index.php?page=settings');
                }
                redirect('index.php');
            }
            flash('Invalid username or password.', 'danger');
        }
    }
    render_login();
    exit;
}

// ------------------------------------------------------------------
// Force password change for flagged accounts
// ------------------------------------------------------------------
require_login();

if ((int)($_SESSION['user']['must_change'] ?? 0) !== 1) {
    // Re-check from DB on every load so a forced reset takes effect
    $stmt = $pdo->prepare('SELECT must_change_password FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user']['id']]);
    $mustChange = (int)$stmt->fetchColumn();
    if ($mustChange === 1 && $page !== 'settings' && $page !== 'logout') {
        flash('You must change your default password before continuing.', 'danger');
        redirect('index.php?page=settings');
    }
}

// ------------------------------------------------------------------
// Receipt page (standalone, outside main layout)
// ------------------------------------------------------------------
if ($page === 'receipt') {
    render_receipt($pdo, (int)($_GET['id'] ?? 0));
    exit;
}

// ------------------------------------------------------------------
// POST actions
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_actions($pdo);
}

// ------------------------------------------------------------------
// Render main page
// ------------------------------------------------------------------
render_page($pdo, $page);

// ==================================================================
// HELPER FUNCTIONS
// ==================================================================

function is_admin(): bool
{
    return ($_SESSION['user']['role'] ?? '') === 'Administrator';
}

function search_term(): string
{
    return trim((string)($_GET['q'] ?? ''));
}

function search_form(string $placeholder = 'Search'): string
{
    $page = (string)($_GET['page'] ?? 'dashboard');
    $q    = search_term();
    return '<form class="toolbar" method="get">'
        . '<input type="hidden" name="page" value="' . e($page) . '">'
        . '<input name="q" value="' . e($q) . '" placeholder="' . e($placeholder) . '" maxlength="100">'
        . '<button>Search</button>'
        . '<a class="button-light" href="index.php?page=' . e($page) . '">Clear</a>'
        . '</form>';
}

function pagination(int $total, int $per_page, int $current, string $page_key): string
{
    $pages = (int)ceil($total / $per_page);
    if ($pages <= 1) return '';
    $html = '<div class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $cls  = $i === $current ? ' class="active"' : '';
        $html .= '<a' . $cls . ' href="index.php?page=' . e($page_key) . '&p=' . $i . '">' . $i . '</a>';
    }
    return $html . '</div>';
}

function table(array $rows, array $columns, string $edit_page = '', string $edit_param = ''): string
{
    if (!$rows) {
        return '<p class="empty">No records yet.</p>';
    }
    $money_keys = ['total', 'paid', 'amount', 'revenue', 'cost_price', 'sale_price', 'share_amount', 'refund_amount', 'unit_price', 'line_total'];
    $html  = '<div class="table-wrap"><table><thead><tr>';
    foreach ($columns as $label) {
        $html .= '<th>' . e($label) . '</th>';
    }
    if ($edit_page && $edit_param) {
        $html .= '<th></th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($columns as $key => $label) {
            $value = $row[$key] ?? '';
            if ($key === 'receipt') {
                $html .= '<td>' . (string)$value . '</td>';
                continue;
            }
            if (in_array($key, $money_keys, true)) {
                $value = money($value);
            }
            $html .= '<td>' . e((string)$value) . '</td>';
        }
        if ($edit_page && $edit_param && isset($row['id'])) {
            $html .= '<td><a class="table-action" href="index.php?page=' . e($edit_page) . '&' . e($edit_param) . '=' . (int)$row['id'] . '">Edit</a></td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}

function options(array $rows, string $valueKey, string $labelKey, int $selected = 0): string
{
    $html = '';
    foreach ($rows as $row) {
        $sel   = (int)$row[$valueKey] === $selected ? ' selected' : '';
        $html .= '<option value="' . e((string)$row[$valueKey]) . '"' . $sel . '>' . e((string)$row[$labelKey]) . '</option>';
    }
    return $html;
}

// ==================================================================
// ACTIONS
// ==================================================================

function handle_actions(PDO $pdo): void
{
    verify_csrf();
    $action = (string)post('action');

    $adminActions = ['save_user', 'update_user', 'save_supplier', 'update_supplier', 'save_category', 'update_category', 'save_member', 'update_member', 'save_product', 'update_product', 'save_purchase', 'save_return', 'save_expense', 'stock_movement'];
    if (in_array($action, $adminActions, true) && !is_admin()) {
        flash('You do not have permission to perform that action.', 'danger');
        redirect('index.php?page=dashboard');
    }

    match ($action) {
        'change_password' => action_change_password($pdo),
        'save_user'       => action_save_user($pdo),
        'update_user'     => action_update_user($pdo),
        'save_supplier'   => action_save_supplier($pdo),
        'update_supplier' => action_update_supplier($pdo),
        'save_category'   => action_save_category($pdo),
        'update_category' => action_update_category($pdo),
        'save_member'     => action_save_member($pdo),
        'update_member'   => action_update_member($pdo),
        'save_product'    => action_save_product($pdo),
        'update_product'  => action_update_product($pdo),
        'save_purchase'   => action_save_purchase($pdo),
        'stock_movement'  => action_stock_movement($pdo),
        'save_sale'       => action_save_sale($pdo),
        'save_return'     => action_save_return($pdo),
        'save_expense'    => action_save_expense($pdo),
        default           => null,
    };
}

function audit_log(PDO $pdo, string $action, string $details = ''): void
{
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)');
    $stmt->execute([$_SESSION['user']['id'] ?? null, $action, $details]);
}

function action_change_password(PDO $pdo): void
{
    $current = (string)post('current_password');
    $new     = (string)post('new_password');
    $confirm = (string)post('confirm_password');
    if (strlen($new) < 8) {
        flash('New password must be at least 8 characters.', 'danger');
        redirect('index.php?page=settings');
    }
    if ($new !== $confirm) {
        flash('New passwords do not match.', 'danger');
        redirect('index.php?page=settings');
    }
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user']['id'] ?? 0]);
    $hash = (string)$stmt->fetchColumn();
    if (!password_verify($current, $hash)) {
        flash('Current password is incorrect.', 'danger');
        redirect('index.php?page=settings');
    }
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?');
    $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user']['id'] ?? 0]);
    audit_log($pdo, 'Password changed', $_SESSION['user']['name'] ?? 'User');
    flash('Password changed successfully.');
    redirect('index.php?page=settings');
}

function action_save_user(PDO $pdo): void
{
    $password = (string)post('password');
    if (strlen($password) < 8) {
        flash('Password must be at least 8 characters.', 'danger');
        redirect('index.php?page=users');
    }
    $stmt = $pdo->prepare('INSERT INTO users (name, username, password_hash, role, status) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([str_limit((string)post('name'), 100), str_limit((string)post('username'), 50), password_hash($password, PASSWORD_DEFAULT), post('role'), post('status', 'Active')]);
    audit_log($pdo, 'User added', (string)post('username'));
    flash('User added successfully.');
    redirect('index.php?page=users');
}

function action_update_user(PDO $pdo): void
{
    $userId   = (int)post('user_id');
    $password = (string)post('password');
    if ($password !== '') {
        if (strlen($password) < 8) {
            flash('Password must be at least 8 characters.', 'danger');
            redirect('index.php?page=users');
        }
        $stmt = $pdo->prepare('UPDATE users SET name=?, username=?, password_hash=?, role=?, status=? WHERE id=?');
        $stmt->execute([str_limit((string)post('name'), 100), str_limit((string)post('username'), 50), password_hash($password, PASSWORD_DEFAULT), post('role'), post('status'), $userId]);
    } else {
        $stmt = $pdo->prepare('UPDATE users SET name=?, username=?, role=?, status=? WHERE id=?');
        $stmt->execute([str_limit((string)post('name'), 100), str_limit((string)post('username'), 50), post('role'), post('status'), $userId]);
    }
    audit_log($pdo, 'User updated', (string)post('username'));
    flash('User updated.');
    redirect('index.php?page=users');
}

function action_save_supplier(PDO $pdo): void
{
    $stmt = $pdo->prepare('INSERT INTO suppliers (name, contact_person, phone, email, address, status) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([str_limit((string)post('name'), 150), str_limit((string)post('contact_person'), 100), str_limit((string)post('phone'), 30), str_limit((string)post('email'), 150), str_limit((string)post('address'), 250), post('status', 'Active')]);
    audit_log($pdo, 'Supplier added', (string)post('name'));
    flash('Supplier added successfully.');
    redirect('index.php?page=suppliers');
}

function action_update_supplier(PDO $pdo): void
{
    $id = (int)post('supplier_id');
    $stmt = $pdo->prepare('UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=?, status=? WHERE id=?');
    $stmt->execute([str_limit((string)post('name'), 150), str_limit((string)post('contact_person'), 100), str_limit((string)post('phone'), 30), str_limit((string)post('email'), 150), str_limit((string)post('address'), 250), post('status'), $id]);
    audit_log($pdo, 'Supplier updated', (string)post('name'));
    flash('Supplier updated.');
    redirect('index.php?page=suppliers');
}

function action_save_category(PDO $pdo): void
{
    $stmt = $pdo->prepare('INSERT INTO categories (name, description, status) VALUES (?, ?, ?)');
    $stmt->execute([str_limit((string)post('name'), 80), str_limit((string)post('description'), 250), post('status', 'Active')]);
    audit_log($pdo, 'Category added', (string)post('name'));
    flash('Category added successfully.');
    redirect('index.php?page=categories');
}

function action_update_category(PDO $pdo): void
{
    $id = (int)post('category_id');
    $stmt = $pdo->prepare('UPDATE categories SET name=?, description=?, status=? WHERE id=?');
    $stmt->execute([str_limit((string)post('name'), 80), str_limit((string)post('description'), 250), post('status'), $id]);
    audit_log($pdo, 'Category updated', (string)post('name'));
    flash('Category updated.');
    redirect('index.php?page=categories');
}

function action_save_member(PDO $pdo): void
{
    $stmt = $pdo->prepare('INSERT INTO members (member_no, name, type, class_or_department, phone, share_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([str_limit((string)post('member_no'), 30), str_limit((string)post('name'), 150), post('type'), str_limit((string)post('class_or_department'), 80), str_limit((string)post('phone'), 30), post('share_amount', 0), post('status', 'Active')]);
    audit_log($pdo, 'Customer added', (string)post('name'));
    flash('Customer added successfully.');
    redirect('index.php?page=customers');
}

function action_update_member(PDO $pdo): void
{
    $id = (int)post('member_id');
    $stmt = $pdo->prepare('UPDATE members SET member_no=?, name=?, type=?, class_or_department=?, phone=?, share_amount=?, status=? WHERE id=?');
    $stmt->execute([str_limit((string)post('member_no'), 30), str_limit((string)post('name'), 150), post('type'), str_limit((string)post('class_or_department'), 80), str_limit((string)post('phone'), 30), post('share_amount', 0), post('status'), $id]);
    audit_log($pdo, 'Customer updated', (string)post('name'));
    flash('Customer updated.');
    redirect('index.php?page=customers');
}

function action_save_product(PDO $pdo): void
{
    $categoryId = post('category_id') !== '' ? (int)post('category_id') : null;
    $category   = str_limit((string)post('category'), 80);
    if ($categoryId) {
        $stmt = $pdo->prepare('SELECT name FROM categories WHERE id = ?');
        $stmt->execute([$categoryId]);
        $category = (string)($stmt->fetchColumn() ?: $category);
    }
    $stmt = $pdo->prepare('INSERT INTO products (sku, name, category, category_id, supplier_id, cost_price, sale_price, stock, reorder_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([str_limit((string)post('sku'), 50), str_limit((string)post('name'), 150), $category, $categoryId, post('supplier_id') !== '' ? (int)post('supplier_id') : null, post('cost_price', 0), post('sale_price', 0), post('stock', 0), post('reorder_level', 5)]);
    audit_log($pdo, 'Product added', (string)post('name'));
    flash('Product added successfully.');
    redirect('index.php?page=products');
}

function action_update_product(PDO $pdo): void
{
    $id         = (int)post('product_id');
    $categoryId = post('category_id') !== '' ? (int)post('category_id') : null;
    $category   = str_limit((string)post('category'), 80);
    if ($categoryId) {
        $stmt = $pdo->prepare('SELECT name FROM categories WHERE id = ?');
        $stmt->execute([$categoryId]);
        $category = (string)($stmt->fetchColumn() ?: $category);
    }
    $stmt = $pdo->prepare('UPDATE products SET sku=?, name=?, category=?, category_id=?, supplier_id=?, cost_price=?, sale_price=?, reorder_level=? WHERE id=?');
    $stmt->execute([str_limit((string)post('sku'), 50), str_limit((string)post('name'), 150), $category, $categoryId, post('supplier_id') !== '' ? (int)post('supplier_id') : null, post('cost_price', 0), post('sale_price', 0), post('reorder_level', 5), $id]);
    audit_log($pdo, 'Product updated', (string)post('name'));
    flash('Product updated.');
    redirect('index.php?page=products');
}

function action_save_purchase(PDO $pdo): void
{
    $items      = $_POST['items'] ?? [];
    $supplierId = post('supplier_id') !== '' ? (int)post('supplier_id') : null;
    $purchaseNo = 'P' . date('YmdHis');
    $purchaseDate = str_limit((string)post('purchase_date', date('Y-m-d')), 10);
    $note       = str_limit((string)post('note'), 250);
    $purchaseItems = [];
    $total      = 0.0;

    foreach ($items as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $quantity  = (int)($item['quantity'] ?? 0);
        $unitCost  = (float)($item['unit_cost'] ?? 0);
        if ($productId <= 0 || $quantity <= 0) continue;
        $lineTotal = $quantity * $unitCost;
        $total    += $lineTotal;
        $purchaseItems[] = [$productId, $quantity, $unitCost, $lineTotal];
    }

    if (!$purchaseItems) {
        flash('Add at least one purchase item.', 'danger');
        redirect('index.php?page=purchases');
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO purchases (purchase_no, supplier_id, total, purchase_date, note) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$purchaseNo, $supplierId, $total, $purchaseDate, $note]);
    $purchaseId = (int)$pdo->lastInsertId();

    $itemStmt  = $pdo->prepare('INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_cost, line_total) VALUES (?, ?, ?, ?, ?)');
    $stockStmt = $pdo->prepare('UPDATE products SET stock = stock + ?, cost_price = CASE WHEN ? > 0 THEN ? ELSE cost_price END WHERE id = ?');
    $moveStmt  = $pdo->prepare('INSERT INTO stock_movements (product_id, movement_type, quantity, note) VALUES (?, ?, ?, ?)');
    foreach ($purchaseItems as [$productId, $quantity, $unitCost, $lineTotal]) {
        $itemStmt->execute([$purchaseId, $productId, $quantity, $unitCost, $lineTotal]);
        $stockStmt->execute([$quantity, $unitCost, $unitCost, $productId]);
        $moveStmt->execute([$productId, 'In', $quantity, 'Purchase ' . $purchaseNo]);
    }
    audit_log($pdo, 'Purchase recorded', $purchaseNo . ' total ' . money($total));
    $pdo->commit();

    flash("Purchase $purchaseNo recorded.");
    redirect('index.php?page=purchases');
}

function action_stock_movement(PDO $pdo): void
{
    $productId = (int)post('product_id');
    $quantity  = max(1, (int)post('quantity'));
    $type      = (string)post('movement_type');
    $delta     = $type === 'Out' ? -$quantity : $quantity;

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
    $stmt->execute([$delta, $productId]);
    $stmt = $pdo->prepare('INSERT INTO stock_movements (product_id, movement_type, quantity, note) VALUES (?, ?, ?, ?)');
    $stmt->execute([$productId, $type, $quantity, str_limit((string)post('note'), 200)]);
    $pdo->commit();

    audit_log($pdo, 'Inventory updated', $type . ' ' . $quantity . ' units');
    flash('Inventory updated.');
    redirect('index.php?page=inventory');
}

function action_save_sale(PDO $pdo): void
{
    $items    = $_POST['items'] ?? [];
    $memberId = post('member_id') !== '' ? (int)post('member_id') : null;
    $receiptNo = 'R' . date('YmdHis');
    $total    = 0.0;
    $saleItems = [];

    foreach ($items as $item) {
        $productId = (int)($item['product_id'] ?? 0);
        $quantity  = (int)($item['quantity'] ?? 0);
        if ($productId <= 0 || $quantity <= 0) continue;
        $stmt = $pdo->prepare('SELECT id, sale_price, stock FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if (!$product || (int)$product['stock'] < $quantity) {
            flash('Insufficient stock for one or more products.', 'danger');
            redirect('index.php?page=sales');
        }
        $lineTotal  = (float)$product['sale_price'] * $quantity;
        $total     += $lineTotal;
        $saleItems[] = [$productId, $quantity, (float)$product['sale_price'], $lineTotal];
    }

    if (!$saleItems) {
        flash('Add at least one sale item.', 'danger');
        redirect('index.php?page=sales');
    }

    $paymentMethod    = (string)post('payment_method', 'Cash');
    $paymentReference = str_limit(trim((string)post('payment_reference')), 100);
    $paid             = post('paid') === '' ? $total : (float)post('paid', $total);

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO sales (receipt_no, member_id, cashier_id, payment_method, payment_reference, total, paid) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$receiptNo, $memberId, $_SESSION['user']['id'] ?? null, $paymentMethod, $paymentReference, $total, $paid]);
    $saleId = (int)$pdo->lastInsertId();

    $itemStmt  = $pdo->prepare('INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)');
    $stockStmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
    foreach ($saleItems as [$productId, $quantity, $unitPrice, $lineTotal]) {
        $itemStmt->execute([$saleId, $productId, $quantity, $unitPrice, $lineTotal]);
        $stockStmt->execute([$quantity, $productId]);
    }
    $pdo->commit();

    audit_log($pdo, 'Sale recorded', $receiptNo . ' total ' . money($total));
    flash("Sales invoice $receiptNo saved.");
    redirect('index.php?page=sales');
}

function action_save_return(PDO $pdo): void
{
    $productId = (int)post('product_id');
    $quantity  = max(1, (int)post('quantity'));
    $refund    = (float)post('refund_amount', 0);
    $saleId    = post('sale_id') !== '' ? (int)post('sale_id') : null;
    $returnNo  = 'RT' . date('YmdHis');

    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO sales_returns (return_no, sale_id, product_id, quantity, refund_amount, reason) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$returnNo, $saleId, $productId, $quantity, $refund, str_limit((string)post('reason'), 200)]);
    $stmt = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
    $stmt->execute([$quantity, $productId]);
    $pdo->commit();

    audit_log($pdo, 'Sales return recorded', $returnNo);
    flash("Sales return $returnNo recorded.");
    redirect('index.php?page=sales_returns');
}

function action_save_expense(PDO $pdo): void
{
    $stmt = $pdo->prepare('INSERT INTO expenses (title, category, amount, expense_date, note) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([str_limit((string)post('title'), 150), str_limit((string)post('category'), 80), post('amount', 0), str_limit((string)post('expense_date'), 10), str_limit((string)post('note'), 250)]);
    audit_log($pdo, 'Expense recorded', (string)post('title'));
    flash('Expense recorded.');
    redirect('index.php?page=expenses');
}

// ==================================================================
// RENDERING
// ==================================================================

function render_receipt(PDO $pdo, int $saleId): void
{
    $stmt = $pdo->prepare('SELECT s.*, COALESCE(m.name,"Walk-in") AS customer, COALESCE(u.name,"-") AS cashier FROM sales s LEFT JOIN members m ON m.id=s.member_id LEFT JOIN users u ON u.id=s.cashier_id WHERE s.id = ?');
    $stmt->execute([$saleId]);
    $sale = $stmt->fetch();
    if (!$sale) {
        flash('Receipt not found.', 'danger');
        redirect('index.php?page=sales_records');
    }
    $stmt = $pdo->prepare('SELECT p.name, si.quantity, si.unit_price, si.line_total FROM sale_items si JOIN products p ON p.id=si.product_id WHERE si.sale_id = ?');
    $stmt->execute([$saleId]);
    $items   = $stmt->fetchAll();
    $balance = (float)$sale['paid'] - (float)$sale['total'];
    ?>
    <!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Receipt <?= e($sale['receipt_no']) ?></title><link rel="stylesheet" href="assets/style.css"></head>
    <body class="receipt-body"><main class="receipt">
    <h1>E-Koperasi</h1><p><?= APP_NAME ?></p><hr>
    <div class="receipt-meta">
        <span>Receipt</span><strong><?= e($sale['receipt_no']) ?></strong>
        <span>Date</span><strong><?= e($sale['created_at']) ?></strong>
        <span>Customer</span><strong><?= e($sale['customer']) ?></strong>
        <span>Cashier</span><strong><?= e($sale['cashier']) ?></strong>
    </div>
    <?= table($items, ['name' => 'Item', 'quantity' => 'Qty', 'unit_price' => 'Price', 'line_total' => 'Total']) ?>
    <div class="receipt-total">
        <span>Total</span><strong><?= money($sale['total']) ?></strong>
        <span>Paid</span><strong><?= money($sale['paid']) ?></strong>
        <span>Balance</span><strong><?= money($balance) ?></strong>
    </div>
    <div class="print-actions">
        <button onclick="window.print()">Print</button>
        <a class="button-light" href="index.php?page=sales_records">Back</a>
    </div>
    </main></body></html>
    <?php
}

function render_login(): void
{
    $flash = flash();
    ?>
    <!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= APP_NAME ?></title><link rel="stylesheet" href="assets/style.css"></head>
    <body class="login-body">
    <div class="login-layout">
    <main class="login-panel">
    <h1>E-Koperasi</h1>
    <p>Sign in to manage cooperative operations.</p>
    <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
    <form method="post" class="stack" id="login-form">
        <?= csrf_field() ?>
        <label>Username <input name="username" id="login-username" required autofocus maxlength="50" autocomplete="username"></label>
        <label>Password <input name="password" id="login-password" type="password" required maxlength="128" autocomplete="current-password"></label>
        <button type="submit">Sign In</button>
    </form>
    <div class="demo-fill">
        <span>Quick fill</span>
        <div class="demo-fill-actions">
            <button type="button" class="button-light" data-fill-user="admin" data-fill-pass="admin123">Admin</button>
            <button type="button" class="button-light" data-fill-user="user" data-fill-pass="user1234">User</button>
        </div>
        <small>Admin: full access &middot; User: sales &amp; stock access</small>
    </div>
    </main>
    <aside class="login-manual" id="user-manual">
        <h2>User Manual</h2>
        <p class="manual-intro">Quick guide to signing in and using the School Cooperative System.</p>

        <details open>
            <summary>1. Sign in</summary>
            <ol>
                <li>Enter your username and password, then click <strong>Sign In</strong>.</li>
                <li>Use <strong>Admin</strong> or <strong>User</strong> quick fill for demo accounts.</li>
                <li>New admin accounts may be asked to change the default password on first login.</li>
                <li>After 10 failed attempts, login is locked for 15 minutes.</li>
            </ol>
        </details>

        <details>
            <summary>2. Roles</summary>
            <ul>
                <li><strong>Administrator</strong> — manage users, suppliers, categories, products, customers, purchases, sales, returns, inventory, expenses, reports, and settings.</li>
                <li><strong>Staff / Cashier</strong> — dashboard, sales, sales records, inventory view, and reports.</li>
            </ul>
            <p>New accounts are created by an Administrator under <em>Settings → User Management</em> (there is no public self-registration).</p>
        </details>

        <details>
            <summary>3. Daily workflow</summary>
            <ol>
                <li><strong>Dashboard</strong> — check sales today, low stock, and recent activity.</li>
                <li><strong>Master data</strong> (admin) — keep suppliers, categories, products, and customers up to date.</li>
                <li><strong>Purchases</strong> (admin) — record stock received from suppliers.</li>
                <li><strong>Sales</strong> — add items, choose customer/payment, save the invoice, then print the receipt from Sales Record.</li>
                <li><strong>Sales Return</strong> (admin) — record returned items and refunds; stock is restored.</li>
                <li><strong>Inventory</strong> — review stock; admins can post In/Out movements.</li>
                <li><strong>Expenses &amp; Reports</strong> (admin for expenses) — track costs and review date-range financial summaries.</li>
            </ol>
        </details>

        <details>
            <summary>4. Tips</summary>
            <ul>
                <li>Use Search and Clear on list pages to find records quickly.</li>
                <li>Change your password anytime in <strong>Settings</strong>.</li>
                <li>Logout from the sidebar when you finish your shift.</li>
                <li>Currency amounts are shown in RM.</li>
            </ul>
        </details>
    </aside>
    </div>
    <script>
    document.querySelectorAll('[data-fill-user]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('login-username').value = btn.dataset.fillUser || '';
            document.getElementById('login-password').value = btn.dataset.fillPass || '';
            document.getElementById('login-username').focus();
        });
    });
    </script>
    </body></html>
    <?php
}

function render_page(PDO $pdo, string $page): void
{
    $allowed = ['dashboard', 'suppliers', 'categories', 'products', 'customers', 'purchases', 'sales', 'sales_records', 'sales_returns', 'inventory', 'reports', 'settings', 'users', 'expenses'];
    if (!in_array($page, $allowed, true)) {
        $page = 'dashboard';
    }

    $flash      = flash();
    $menuGroups = [
        'Overview'     => ['dashboard' => 'Dashboard'],
        'Master Data'  => ['suppliers' => 'Supplier', 'categories' => 'Category', 'products' => 'Product', 'customers' => 'Customer'],
        'Transactions' => ['purchases' => 'Purchases', 'sales' => 'Sales', 'sales_records' => 'Sales Record', 'sales_returns' => 'Sales Return'],
        'Stock Control'=> ['inventory' => 'Inventory'],
        'Analysis'     => ['reports' => 'Reports'],
        'System'       => ['settings' => 'Settings'],
    ];
    if (!is_admin()) {
        $menuGroups = [
            'Overview'     => ['dashboard' => 'Dashboard'],
            'Transactions' => ['sales' => 'Sales', 'sales_records' => 'Sales Record'],
            'Stock Control'=> ['inventory' => 'Inventory'],
            'Analysis'     => ['reports' => 'Reports'],
        ];
    }
    $menu = array_merge(...array_values($menuGroups));
    ?>
    <!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= APP_NAME ?></title><link rel="stylesheet" href="assets/style.css"></head>
    <body>
    <aside class="sidebar">
        <div><div class="brand">E-Koperasi</div><small>Management System</small></div>
        <nav>
        <?php foreach ($menuGroups as $group => $items): ?>
            <div class="nav-group"><span><?= e($group) ?></span>
            <?php foreach ($items as $key => $label): ?>
                <a class="<?= ($page === $key || ($page === 'users' && $key === 'settings')) ? 'active' : '' ?>" href="index.php?page=<?= $key ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        </nav>
        <a class="logout" href="index.php?page=logout">Logout</a>
    </aside>
    <main class="content">
        <header class="topbar">
            <div>
                <h1><?= e($menu[$page] ?? 'User Management') ?></h1>
                <p><?= e($_SESSION['user']['name']) ?> &middot; <?= e($_SESSION['user']['role']) ?></p>
            </div>
        </header>
        <?php if ($flash): ?>
        <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
        <?php call_user_func('view_' . $page, $pdo); ?>
    </main>
    <script src="assets/app.js"></script>
    </body></html>
    <?php
}
