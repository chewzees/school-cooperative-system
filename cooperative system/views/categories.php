<?php
function view_categories(PDO $pdo): void
{
    $rows = $pdo->query('SELECT id, name, description, status FROM categories ORDER BY id DESC')->fetchAll();
    $edit = null;
    if (isset($_GET['edit_category'])) {
        $stmt = $pdo->prepare('SELECT id, name, description, status FROM categories WHERE id = ?');
        $stmt->execute([(int)$_GET['edit_category']]);
        $edit = $stmt->fetch() ?: null;
    }
    ?>
    <section class="grid two">
        <?php if ($edit): ?>
        <form method="post" class="panel form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_category">
            <input type="hidden" name="category_id" value="<?= (int)$edit['id'] ?>">
            <h2>Edit Category</h2>
            <label>Name <input name="name" required maxlength="80" value="<?= e($edit['name']) ?>"></label>
            <label>Description <input name="description" maxlength="250" value="<?= e($edit['description']) ?>"></label>
            <label>Status
                <select name="status">
                    <option <?= $edit['status']==='Active'?'selected':'' ?>>Active</option>
                    <option <?= $edit['status']==='Inactive'?'selected':'' ?>>Inactive</option>
                </select>
            </label>
            <div style="display:flex;gap:8px">
                <button>Save Changes</button>
                <a class="button-light" href="index.php?page=categories">Cancel</a>
            </div>
        </form>
        <?php else: ?>
        <form method="post" class="panel form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_category">
            <h2>Add Category</h2>
            <label>Name <input name="name" required maxlength="80"></label>
            <label>Description <input name="description" maxlength="250"></label>
            <label>Status
                <select name="status">
                    <option>Active</option>
                    <option>Inactive</option>
                </select>
            </label>
            <button>Add Category</button>
        </form>
        <?php endif; ?>
        <section class="panel wide">
            <h2>Category List</h2>
            <?= table($rows, ['name' => 'Category', 'description' => 'Description', 'status' => 'Status'], 'categories', 'edit_category') ?>
        </section>
    </section>
    <?php
}
