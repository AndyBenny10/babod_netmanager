document.addEventListener('DOMContentLoaded', () => {
    initVlanEditor();
    initTrafficChart();
});

function initVlanEditor() {
    const editor = document.querySelector('.vlan-editor');
    const form = document.getElementById('vlan-form');
    const hidden = document.getElementById('vlan_json');
    const addBtn = document.getElementById('add-vlan');

    if (!editor || !form || !hidden) {
        return;
    }

    const portCount = Number(editor.dataset.portCount || 8);

    const serialize = () => {
        const vlans = [];
        editor.querySelectorAll('.vlan-row').forEach((row) => {
            const vlanId = Number(row.dataset.vlanId);
            const ports = {};
            row.querySelectorAll('.vlan-port-mode').forEach((select) => {
                ports[select.dataset.port] = select.value;
            });
            vlans.push({ id: vlanId, ports });
        });
        hidden.value = JSON.stringify(vlans);
    };

    editor.addEventListener('change', (event) => {
        const select = event.target.closest('.vlan-port-mode');
        if (!select) return;
        const label = select.closest('.port-mode');
        label.classList.remove('port-mode-none', 'port-mode-untagged', 'port-mode-tagged');
        label.classList.add(`port-mode-${select.value}`);
        serialize();
    });

    editor.addEventListener('click', (event) => {
        const removeBtn = event.target.closest('.remove-vlan');
        if (!removeBtn) return;
        removeBtn.closest('.vlan-row')?.remove();
        serialize();
    });

    addBtn?.addEventListener('click', () => {
        const existingIds = Array.from(editor.querySelectorAll('.vlan-row'))
            .map((row) => Number(row.dataset.vlanId));
        let newId = 2;
        while (existingIds.includes(newId)) newId += 1;

        const row = document.createElement('div');
        row.className = 'vlan-row';
        row.dataset.vlanId = String(newId);

        let portsHtml = '';
        for (let p = 1; p <= portCount; p += 1) {
            portsHtml += `
                <label class="port-mode port-mode-none">
                    P${p}
                    <select class="vlan-port-mode" data-port="${p}">
                        <option value="none" selected>-</option>
                        <option value="untagged">Untagged</option>
                        <option value="tagged">Tagged</option>
                    </select>
                </label>`;
        }

        row.innerHTML = `
            <div class="vlan-row-header">
                <strong>VLAN ${newId}</strong>
                <button type="button" class="link-btn danger remove-vlan">Törlés</button>
            </div>
            <div class="port-modes">${portsHtml}</div>`;

        editor.appendChild(row);
        serialize();
    });

    form.addEventListener('submit', serialize);
    serialize();
}

function initTrafficChart() {
    const canvas = document.getElementById('traffic-chart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    const labels = JSON.parse(canvas.dataset.labels || '[]');
    const rx = JSON.parse(canvas.dataset.rx || '[]');
    const tx = JSON.parse(canvas.dataset.tx || '[]');

    new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'RX csomag',
                    data: rx,
                    borderColor: '#4da3ff',
                    tension: 0.25,
                },
                {
                    label: 'TX csomag',
                    data: tx,
                    borderColor: '#7c5cff',
                    tension: 0.25,
                },
            ],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { labels: { color: '#e8eef7' } },
            },
            scales: {
                x: { ticks: { color: '#9fb0c7' }, grid: { color: '#314158' } },
                y: { ticks: { color: '#9fb0c7' }, grid: { color: '#314158' } },
            },
        },
    });
}
