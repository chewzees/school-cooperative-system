<?php
function view_expenses(PDO $pdo): void
{
    $page_num = max(1, (int)($_GET['p'] ?? 1));
    $per_page = 20;
    $offset   = ($page_num - 1) * $per_page;
    $total_rows = (int)$pdo->query('SELECT COUNT(*) FROM expenses')->fetchColumn();
    $rows = $pdo->query("SELECT expense_date, title, category, amount, note FROM expenses ORDER BY expense_date DESC, id DESC LIMIT $per_page OFFSET $offset")->fetchAll();
    $expense_categories = ['Supplies', 'Utilities', 'Maintenance', 'Salary', 'Transport', 'Miscellaneous'];
    ?>
    <section class="grid two">
        <form method="post" class="panel form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_expense">
            <h2>Add Expense</h2>
            <label>Title <input name="title" required maxlength="150"></label>
            <label>Category
                <select name="category">
                    <?php foreach ($expense_categories as $cat): ?><option><?= e($cat) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Amount <input name="amount" type="number" step="0.01" min="0" required></label>
            <label>Date <input name="expense_date" type="date" required value="<?= date('Y-m-d') ?>"></label>
            <label>Note <input name="note" maxlength="250"></label>
            <button>Record Expense</button>
        </form>
        <section class="panel wide">
            <h2>Expense List</h2>
            <?= table($rows, ['expense_date' => 'Date', 'title' => 'Title', 'category' => 'Category', 'amount' => 'Amount', 'note' => 'Note']) ?>
            <?= pagination($total_rows, $per_page, $page_num, 'expenses') ?>
        </section>
    </section>
    <?php
}
