<?php
function view_users(PDO $pdo): void
{
    $rows = $pdo->query('SELECT name, username, role, status, created_at FROM users ORDER BY id DESC')->fetchAll();
    $editUser = null;
    if (isset($_GET['edit_user'])) {
        $stmt = $pdo->prepare('SELECT id, name, username, role, status FROM users WHERE id = ?');
        $stmt->execute([(int)$_GET['edit_user']]);
        $editUser = $stmt->fetch() ?: null;
    }
    ?>
    <section class="grid two">
        <?php if ($editUser): ?>
        <form method="post" class="panel form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" value="<?= (int)$editUser['id'] ?>">
            <h2>Edit User</h2>
            <label>Name <input name="name" required maxlength="100" value="<?= e($editUser['name']) ?>"></label>
            <label>Username <input name="username" required maxlength="50" value="<?= e($editUser['username']) ?>"></label>
            <label>New Password <input name="password" type="password" maxlength="128" placeholder="Leave blank to keep current"></label>
            <label>Role
                <select name="role">
                    <option <?= $editUser['role']==='Administrator'?'selected':'' ?>>Administrator</option>
                    <option <?= $editUser['role']==='Staff / Cashier'?'selected':'' ?>>Staff / Cashier</option>
                </select>
            </label>
            <label>Status
                <select name="status">
                    <option <?= $editUser['status']==='Active'?'selected':'' ?>>Active</option>
                    <option <?= $editUser['status']==='Inactive'?'selected':'' ?>>Inactive</option>
                </select>
            </label>
            <div style="display:flex;gap:8px">
                <button>Save Changes</button>
                <a class="button-light" href="index.php?page=users">Cancel</a>
            </div>
        </form>
        <?php else: ?>
        <form method="post" class="panel form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_user">
            <h2>Add User</h2>
            <label>Name <input name="name" required maxlength="100"></label>
            <label>Username <input name="username" required maxlength="50"></label>
            <label>Password <input name="password" type="password" required maxlength="128"></label>
            <label>Role
                <select name="role">
                    <option>Administrator</option>
                    <option>Staff / Cashier</option>
                </select>
            </label>
            <label>Status
                <select name="status">
                    <option>Active</option>
                    <option>Inactive</option>
                </select>
            </label>
            <button>Add User</button>
        </form>
        <?php endif; ?>
        <section class="panel wide">
            <h2>User List</h2>
            <?= table($rows, ['name' => 'Name', 'username' => 'Username', 'role' => 'Role', 'status' => 'Status', 'created_at' => 'Created'], 'users', 'edit_user') ?>
        </section>
    </section>
    <?php
}
