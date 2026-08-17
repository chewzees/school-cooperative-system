function money(value) {
    return 'RM ' + Number(value || 0).toFixed(2);
}

function updateSaleTotals() {
    let total = 0;
    document.querySelectorAll('.sale-row.enhanced').forEach((row) => {
        const selected = row.querySelector('select')?.selectedOptions[0];
        const price = Number(selected?.dataset.price || 0);
        const qty = Number(row.querySelector('input[type="number"]')?.value || 0);
        const line = price * qty;
        total += line;
        const target = row.querySelector('.line-total');
        if (target) target.textContent = money(line);
    });
    const paidInput = document.querySelector('input[name="paid"]');
    const paid = paidInput && paidInput.value !== '' ? Number(paidInput.value) : total;
    const totalNode = document.querySelector('#sale-total');
    const changeNode = document.querySelector('#sale-change');
    if (totalNode) totalNode.textContent = money(total);
    if (changeNode) changeNode.textContent = money(paid - total);
}

window.updateSaleTotals = updateSaleTotals;

function updatePurchaseTotals() {
    let total = 0;
    document.querySelectorAll('.purchase-row').forEach((row) => {
        const qty = Number(row.querySelector('input[name$="[quantity]"]')?.value || 0);
        const cost = Number(row.querySelector('input[name$="[unit_cost]"]')?.value || 0);
        total += qty * cost;
    });
    const totalNode = document.querySelector('#purchase-total');
    if (totalNode) totalNode.textContent = money(total);
}

document.addEventListener('input', (event) => {
    if (event.target.closest('.sale-form')) updateSaleTotals();
    if (event.target.closest('.purchase-form')) updatePurchaseTotals();
});

document.addEventListener('change', (event) => {
    if (event.target.closest('.sale-form')) updateSaleTotals();
    if (event.target.closest('.purchase-form')) updatePurchaseTotals();
});

updateSaleTotals();
updatePurchaseTotals();
