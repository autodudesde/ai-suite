import Chart from '../vendor/chart.esm.js';

const PALETTE = ['#F09C42', '#E8942E', '#FDE5C0', '#6f6f6f', '#2a8c3e', '#c8412c', '#3a7bd5', '#9b59b6'];

function buildTable(section) {
    const table = document.createElement('table');
    table.className = 'table table-striped';
    const dataset = (section.datasets || [])[0] || { data: [] };
    const labels = section.labels || [];
    const head = document.createElement('thead');
    head.innerHTML = `<tr><th>—</th><th>${dataset.label || ''}</th></tr>`;
    table.appendChild(head);
    const body = document.createElement('tbody');
    labels.forEach((label, i) => {
        const row = document.createElement('tr');
        const th = document.createElement('th');
        th.textContent = String(label);
        const td = document.createElement('td');
        td.textContent = String((dataset.data || [])[i] ?? 0);
        row.appendChild(th);
        row.appendChild(td);
        body.appendChild(row);
    });
    table.appendChild(body);
    return table;
}

function buildChart(Chart, section, body) {
    const isCircular = section.type === 'doughnut' || section.type === 'pie';
    const horizontal = section.orientation === 'horizontal';
    const labelCount = (section.labels || []).length;

    let host = body;
    if (section.scroll) {
        const scroll = document.createElement('div');
        scroll.className = 'ai-suite-statistics__scroll';
        body.appendChild(scroll);
        host = scroll;
    } else if (section.scrollX) {
        const scroll = document.createElement('div');
        scroll.className = 'ai-suite-statistics__scroll-x';
        body.appendChild(scroll);
        host = scroll;
    }
    const holder = document.createElement('div');
    holder.style.position = 'relative';
    holder.style.height = section.scroll
        ? `${Math.max(280, labelCount * 22 + 40)}px`
        : '300px';
    if (section.scrollX) {
        holder.style.width = '100%';
        holder.style.minWidth = `${labelCount * 32}px`;
    }
    host.appendChild(holder);

    const canvas = document.createElement('canvas');
    holder.appendChild(canvas);

    const datasets = (section.datasets || []).map((ds, i) => ({
        label: ds.label || '',
        data: ds.data || [],
        backgroundColor: isCircular ? PALETTE : PALETTE[i % PALETTE.length],
        borderColor: isCircular ? '#fff' : PALETTE[i % PALETTE.length],
        borderWidth: 1,
    }));

    new Chart(canvas, {
        type: section.type || 'bar',
        data: { labels: section.labels || [], datasets },
        options: {
            indexAxis: horizontal ? 'y' : 'x',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: isCircular || datasets.length > 1 } },
        },
    });
}

function initMonthFilter() {
    const filter = document.querySelector('[data-ai-suite-statistics-month-filter]');
    if (!filter) {
        return;
    }
    filter.addEventListener('change', () => {
        const option = filter.options[filter.selectedIndex];
        const url = option && option.getAttribute('data-url');
        if (url) {
            window.location.href = url;
        }
    });
}

async function init() {
    initMonthFilter();

    const dataEl = document.querySelector('[data-ai-suite-statistics-data]');
    const mount = document.querySelector('[data-ai-suite-statistics-mount]');
    if (!dataEl || !mount) {
        return;
    }

    let payload;
    try {
        payload = JSON.parse(dataEl.textContent || '{}');
    } catch (_e) {
        return;
    }
    const sections = Array.isArray(payload.sections) ? payload.sections : [];
    if (sections.length === 0) {
        return;
    }

    sections.forEach((section) => {
        if (typeof section.heading === 'string' && section.heading !== '') {
            const groupHeading = document.createElement('h2');
            groupHeading.className = 'ai-suite-statistics__group-heading';
            groupHeading.textContent = section.heading;
            mount.appendChild(groupHeading);
        }

        const card = document.createElement('div');
        card.className = 'panel panel-default ai-suite-statistics__card';

        const heading = document.createElement('div');
        heading.className = 'panel-heading fw-bold';
        heading.textContent = section.title || '';
        card.appendChild(heading);

        const body = document.createElement('div');
        body.className = 'panel-body ai-suite-statistics__body';
        card.appendChild(body);

        if (section.description) {
            const desc = document.createElement('p');
            desc.className = 'text-muted small';
            desc.textContent = section.description;
            body.appendChild(desc);
        }

        const hasData = (section.labels || []).length > 0
            && (section.datasets || []).some((ds) => (ds.data || []).length > 0);

        if (!hasData) {
            const note = document.createElement('p');
            note.className = 'text-muted';
            note.textContent = section.emptyText || 'Noch keine Daten vorhanden.';
            body.appendChild(note);
            body.style.minHeight = '0';
            mount.appendChild(card);
            return;
        }

        try {
            buildChart(Chart, section, body);
        } catch (_e) {
            if (section.scroll || section.scrollX) {
                const scroll = document.createElement('div');
                scroll.className = section.scrollX
                    ? 'ai-suite-statistics__scroll-x'
                    : 'ai-suite-statistics__scroll';
                scroll.appendChild(buildTable(section));
                body.appendChild(scroll);
            } else {
                body.appendChild(buildTable(section));
            }
        }

        mount.appendChild(card);
    });
}

init();
