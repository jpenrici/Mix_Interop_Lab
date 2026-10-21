// demo/public/ui/app.js

const API_URL = '../index.php?action=multiply';
const REQUEST_TIMEOUT_MS = 30_000;
const MAX_FILE_BYTES = 5 * 1024 * 1024;

const form = document.getElementById('matrix-form');
const submitBtn = document.getElementById('compute-btn');
const compat = document.getElementById('compat');
const statusEl = document.getElementById('status');
const output = document.getElementById('output');
const numberFormat = new Intl.NumberFormat(undefined, { maximumFractionDigits: 4 });

/** Parses free-form text into a rectangular number[][]; throws Error with a readable message. */
function parseMatrix(text) {
    const lines = text.split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
    if (lines.length === 0) {
        throw new Error('Matrix is empty.');
    }

    const rows = lines.map((line, i) =>
        line.split(/[,;\s]+/).filter(Boolean).map((cell, j) => {
            const value = Number(cell);
            if (!Number.isFinite(value)) {
                throw new Error(`Row ${i + 1}, column ${j + 1}: "${cell}" is not a number.`);
            }
            return value;
        })
    );

    const cols = rows[0].length;
    const bad = rows.findIndex((row) => row.length !== cols);
    if (bad !== -1) {
        throw new Error(`Row ${bad + 1} has ${rows[bad].length} values, expected ${cols}.`);
    }
    return rows;
}

/** One matrix input panel: owns its DOM nodes and its parsed state. */
class MatrixField {
    constructor(root) {
        this.textarea = root.querySelector('textarea');
        this.fileInput = root.querySelector('input[type="file"]');
        this.dims = root.querySelector('.dims');
        this.errorEl = root.querySelector('.field-error');
        this.name = root.dataset.matrix.toUpperCase();
        this.rows = null;

        this.textarea.addEventListener('input', () => this.refresh());
        this.fileInput.addEventListener('change', () => this.loadFile());
    }

    get shape() {
        return this.rows ? { rows: this.rows.length, cols: this.rows[0].length } : null;
    }

    refresh() {
        this.errorEl.hidden = true;
        this.textarea.removeAttribute('aria-invalid');
        this.rows = null;

        if (this.textarea.value.trim() === '') {
            this.dims.textContent = '';
        } else {
            try {
                this.rows = parseMatrix(this.textarea.value);
                this.dims.textContent = `${this.shape.rows} × ${this.shape.cols}`;
            } catch (err) {
                this.dims.textContent = '';
                this.errorEl.textContent = err.message;
                this.errorEl.hidden = false;
                this.textarea.setAttribute('aria-invalid', 'true');
            }
        }
        updateCompatibility();
    }

    async loadFile() {
        const [file] = this.fileInput.files;
        this.fileInput.value = '';
        if (!file) return;

        if (file.size > MAX_FILE_BYTES) {
            this.textarea.value = '';
            this.refresh();
            this.errorEl.textContent = 'File is larger than 5 MB.';
            this.errorEl.hidden = false;
            return;
        }
        this.textarea.value = await file.text();
        this.refresh();
    }

    clear() {
        this.textarea.value = '';
        this.refresh();
    }
}

const fieldA = new MatrixField(document.querySelector('[data-matrix="a"]'));
const fieldB = new MatrixField(document.querySelector('[data-matrix="b"]'));

function updateCompatibility() {
    const a = fieldA.shape;
    const b = fieldB.shape;
    let message;
    let ready = false;

    if (!a || !b) {
        message = 'Enter two valid matrices.';
    } else if (a.cols !== b.rows) {
        message = `Not compatible: A has ${a.cols} columns but B has ${b.rows} rows.`;
    } else {
        message = `Result will be ${a.rows} × ${b.cols}.`;
        ready = true;
    }

    compat.textContent = message;
    compat.classList.toggle('compat-error', Boolean(a && b) && !ready);
    submitBtn.disabled = !ready;
}

function showStatus(message, isError = false) {
    statusEl.textContent = message;
    statusEl.classList.toggle('error', isError);
    statusEl.hidden = message === '';
}

function renderMatrix(rows) {
    const wrapper = document.createElement('div');
    wrapper.className = 'table-scroll';

    const table = document.createElement('table');
    table.className = 'matrix-table';

    for (const row of rows) {
        const tr = table.insertRow();
        for (const value of row) {
            tr.insertCell().textContent = numberFormat.format(value);
        }
    }

    wrapper.append(table);
    output.replaceChildren(wrapper);
}

async function multiply(a, b) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ a, b }),
            signal: controller.signal,
        });

        let data = null;
        try {
            data = await response.json();
        } catch {
            /* non-JSON error body */
        }

        if (!response.ok || data?.status !== 'success') {
            throw new Error(data?.error ?? `Server responded with HTTP ${response.status}.`);
        }
        return data;
    } finally {
        clearTimeout(timer);
    }
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!fieldA.rows || !fieldB.rows) return;

    submitBtn.disabled = true;
    form.setAttribute('aria-busy', 'true');
    showStatus('Processing…');

    try {
        const data = await multiply(fieldA.rows, fieldB.rows);
        renderMatrix(data.result);
        showStatus(`A × B = ${data.dimensions.rows} × ${data.dimensions.cols}`);
    } catch (err) {
        output.replaceChildren();
        const message = err.name === 'AbortError'
            ? 'The request timed out.'
            : err instanceof TypeError
                ? 'Failed to connect to the PHP backend.'
                : err.message;
        showStatus(message, true);
        console.error(err);
    } finally {
        form.removeAttribute('aria-busy');
        updateCompatibility();
    }
});

form.addEventListener('reset', (event) => {
    event.preventDefault();
    fieldA.clear();
    fieldB.clear();
    showStatus('');
    const placeholder = document.createElement('p');
    placeholder.className = 'placeholder';
    placeholder.textContent = 'No computation executed yet.';
    output.replaceChildren(placeholder);
});

updateCompatibility();
