/* PartsBin — small browser helpers (no libraries) */

// Mobile menu: the ☰ button shows / hides the navigation on small screens
(function () {
    var topbar = document.querySelector('[data-topbar]');
    var toggle = document.querySelector('[data-nav-toggle]');
    if (!topbar || !toggle) { return; }

    function setOpen(open) {
        topbar.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    }
    function isOpen() { return topbar.classList.contains('is-open'); }

    toggle.addEventListener('click', function () { setOpen(!isOpen()); });

    // Close with Escape, or by tapping anywhere outside the menu
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && isOpen()) { setOpen(false); toggle.focus(); }
    });
    document.addEventListener('click', function (event) {
        if (isOpen() && !topbar.contains(event.target)) { setOpen(false); }
    });

    // Back to a wide window: the full menu shows anyway, so reset the button
    var wide = window.matchMedia('(min-width: 961px)');
    var reset = function (e) { if (e.matches) { setOpen(false); } };
    if (wide.addEventListener) { wide.addEventListener('change', reset); } else { wide.addListener(reset); }
})();

// Tables: copy each column heading onto its cells as data-label="…".
// On phones the CSS turns every row into a small card and shows these labels
// next to the values, so wide tables don't need sideways scrolling.
function labelTable(table) {
    var heads = [];
    var headRow = table.tHead && table.tHead.rows[0];
    if (!headRow) { return; }
    Array.prototype.forEach.call(headRow.cells, function (th) {
        for (var i = 0; i < th.colSpan; i++) { heads.push(th.textContent.trim()); }
    });

    var rows = table.querySelectorAll('tbody tr, tfoot tr');
    Array.prototype.forEach.call(rows, function (tr) {
        var col = 0;
        Array.prototype.forEach.call(tr.cells, function (cell) {
            cell.setAttribute('data-label', heads[col] || '');
            col += cell.colSpan;
        });
        // The row's main cell becomes the card title (first cell unless marked)
        if (tr.cells.length && !tr.querySelector('.cell-main')) {
            tr.cells[0].classList.add('cell-main');
        }
    });
    table.classList.add('table-stack');
}
document.querySelectorAll('.table-wrap table').forEach(labelTable);

// Live preview: <input type="file" data-preview="img-id"> shows the chosen picture
document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
        var img = document.getElementById(input.dataset.preview);
        if (img && input.files && input.files[0]) {
            img.src = URL.createObjectURL(input.files[0]);
        }
    });
});

// Variant rows: "+ Add another variant" copies the template row,
// "×" removes a row (at least one row always stays)
document.querySelectorAll('[data-variant-rows]').forEach(function (box) {
    var body     = box.querySelector('tbody');
    var template = box.querySelector('template');
    var addBtn   = box.querySelector('[data-add-row]');

    function renumber() {
        body.querySelectorAll('tr').forEach(function (tr, i) {
            var n = tr.querySelector('.row-no');
            if (n) { n.textContent = i + 1; }
        });
    }

    addBtn.addEventListener('click', function () {
        body.appendChild(template.content.cloneNode(true));
        renumber();
        labelTable(body.closest('table'));   // label the new row's cells too
        var last = body.querySelector('tr:last-child input');
        if (last) { last.focus(); }
    });

    body.addEventListener('click', function (event) {
        if (event.target.matches('[data-remove-row]')) {
            if (body.querySelectorAll('tr').length > 1) {
                event.target.closest('tr').remove();
                renumber();
            } else {
                event.target.closest('tr').querySelectorAll('input').forEach(function (i) { i.value = ''; });
            }
        }
    });
});
