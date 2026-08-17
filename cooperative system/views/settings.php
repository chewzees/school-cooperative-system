<?php
function view_settings(PDO $pdo): void
{
    $page_num = max(1, (int)($_GET['p'] ?? 1));
    $per_page = 20;
    $offset   = ($page_num - 1) * $per_page;
    $total_rows = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
    $logs = $pdo->query("SELECT COALESCE(u.name,'System') AS user, al.action, al.details, al.created_at FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id ORDER BY al.id DESC LIMIT $per_page OFFSET $offset")->fetchAll();
    ?>
    <section class="grid two">
        <form method="post" class="panel form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">
            <h2>Change Password</h2>
            <label>Current Password <input name="current_password" type="password" required maxlength="128"></label>
            <label>New Password <input name="new_password" type="password" required maxlength="128" minlength="8"></label>
            <label>Confirm New Password <input name="confirm_password" type="password" required maxlength="128"></label>
            <button>Update Password</button>
        </form>
        <section class="panel">
            <h2>User Management</h2>
            <p class="empty">Manage administrator and cashier accounts.</p>
            <p><a class="button-link" href="index.php?page=users">Open User Management</a></p>
        </section>
    </section>
    <br>
    <section class="panel">
        <h2>Audit Log</h2>
        <?= table($logs, ['created_at' => 'Date', 'user' => 'User', 'action' => 'Action', 'details' => 'Details']) ?>
        <?= pagination($total_rows, $per_page, $page_num, 'settings') ?>
    </section>
    <?php
}
