<?php
function view_customers(PDO $pdo): void
{
    $q = search_term();
    $edit = null;
    if (isset($_GET['edit_customer'])) {
        $stmt = $pdo->prepare('SELECT id, member_no, name, type, class_or_department, phone, share_amount, status FROM members WHERE id = ?');
        $stmt->execute([(int)$_GET['edit_customer']]);
        $edit = $stmt->fetch() ?: null;
    }
    if ($q !== '') {
        $like = '%' . $q . '%';
        $stmt = $pdo->prepare('SELECT id, member_no, name, type, class_or_department, phone, share_amount, status FROM members WHERE member_no LIKE ? OR name LIKE ? OR phone LIKE ? OR class_or_department LIKE ? ORDER BY id DESC');
        $stmt->execute([$like, $like, $like, $like]);
        $rows = $stmt->fetchAll();
    } else {
        $rows = $pdo->query('SELECT id, member_no, name, type, class_or_department, phone, share_amount, status FROM members ORDER BY id DESC')->fetchAll();
    }
    $types = ['Student', 'Teacher', 'Staff', 'Walk-in'];
    ?>
    <section class="grid two">
        <?php if ($edit): ?>
        <form method="post" class="panel form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_member">
            <input type="hidden" name="member_id" value="<?= (int)$edit['id'] ?>">
            <h2>Edit Customer</h2>
            <label>Customer No <input name="member_no" required maxlength="30" value="<?= e($edit['member_no']) ?>"></label>
            <label>Name <input name="name" required maxlength="150" value="<?= e($edit['name']) ?>"></label>
            <label>Type
                <select name="type">
                    <?php foreach ($types as $t): ?>
                        <option <?= $edit['type']===$t?'selected':'' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Class / Department <input name="class_or_department" maxlength="80" value="<?= e($edit['class_or_department']) ?>"></label>
            <label>Phone <input name="phone" maxlength="30" value="<?= e($edit['phone']) ?>"></label>
            <label>Share Amount <input name="share_amount" type="number" step="0.01" min="0" value="<?= e((string)$edit['share_amount']) ?>"></label>
            <label>Status
                <select name="status">
                    <option <?= $edit['status']==='Active'?'selected':'' ?>>Active</option>
                    <option <?= $edit['status']==='Inactive'?'selected':'' ?>>Inactive</option>
                </select>
            </label>
            <div style="display:flex;gap:8px">
                <button>Save Changes</button>
                <a class="button-light" href="index.php?page=customers">Cancel</a>
            </div>
        </form>
        <?php else: ?>
        <form method="post" class="panel form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_member">
            <h2>Add Customer</h2>
            <label>Customer No <input name="member_no" required maxlength="30"></label>
            <label>Name <input name="name" required maxlength="150"></label>
            <label>Type
                <select name="type">
                    <?php foreach ($types as $t): ?><option><?= e($t) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Class / Department <input name="class_or_department" maxlength="80"></label>
            <label>Phone <input name="phone" maxlength="30"></label>
            <label>Share Amount <input name="share_amount" type="number" step="0.01" min="0" value="0"></label>
            <label>Status
                <select name="status">
                    <option>Active</option>
                    <option>Inactive</option>
                </select>
            </label>
            <button>Add Customer</button>
        </form>
        <?php endif; ?>
        <section class="panel wide">
            <h2>Customer List</h2>
            <?= search_form('Search customers') ?>
            <?= table($rows, ['member_no' => 'No', 'name' => 'Name', 'type' => 'Type', 'class_or_department' => 'Class/Dept', 'phone' => 'Phone', 'share_amount' => 'Shares', 'status' => 'Status'], 'customers', 'edit_customer') ?>
        </section>
    </section>
    <?php
}
