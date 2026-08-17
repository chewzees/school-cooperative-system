<?php
function view_suppliers(PDO $pdo): void
{
    $rows = $pdo->query('SELECT id, name, contact_person, phone, email, status FROM suppliers ORDER BY id DESC')->fetchAll();
    $edit = null;
    if (isset($_GET['edit_supplier'])) {
        $stmt = $pdo->prepare('SELECT id, name, contact_person, phone, email, address, status FROM suppliers WHERE id = ?');
        $stmt->execute([(int)$_GET['edit_supplier']]);
        $edit = $stmt->fetch() ?: null;
    }
    ?>
    <section class="grid two">
        <?php if ($edit): ?>
        <form method="post" class="panel form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_supplier">
            <input type="hidden" name="supplier_id" value="<?= (int)$edit['id'] ?>">
            <h2>Edit Supplier</h2>
            <label>Name <input name="name" required maxlength="150" value="<?= e($edit['name']) ?>"></label>
            <label>Contact Person <input name="contact_person" maxlength="100" value="<?= e($edit['contact_person']) ?>"></label>
            <label>Phone <input name="phone" maxlength="30" value="<?= e($edit['phone']) ?>"></label>
            <label>Email <input name="email" type="email" maxlength="150" value="<?= e($edit['email']) ?>"></label>
            <label>Address <input name="address" maxlength="250" value="<?= e($edit['address']) ?>"></label>
            <label>Status
                <select name="status">
                    <option <?= $edit['status']==='Active'?'selected':'' ?>>Active</option>
                    <option <?= $edit['status']==='Inactive'?'selected':'' ?>>Inactive</option>
                </select>
            </label>
            <div style="display:flex;gap:8px">
                <button>Save Changes</button>
                <a class="button-light" href="index.php?page=suppliers">Cancel</a>
            </div>
        </form>
        <?php else: ?>
        <form method="post" class="panel form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_supplier">
            <h2>Add Supplier</h2>
            <label>Name <input name="name" required maxlength="150"></label>
            <label>Contact Person <input name="contact_person" maxlength="100"></label>
            <label>Phone <input name="phone" maxlength="30"></label>
            <label>Email <input name="email" type="email" maxlength="150"></label>
            <label>Address <input name="address" maxlength="250"></label>
            <label>Status
                <select name="status">
                    <option>Active</option>
                    <option>Inactive</option>
                </select>
            </label>
            <button>Add Supplier</button>
        </form>
        <?php endif; ?>
        <section class="panel wide">
            <h2>Supplier List</h2>
            <?= table($rows, ['name' => 'Supplier', 'contact_person' => 'Contact', 'phone' => 'Phone', 'email' => 'Email', 'status' => 'Status'], 'suppliers', 'edit_supplier') ?>
        </section>
    </section>
    <?php
}
