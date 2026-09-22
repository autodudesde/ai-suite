class AuditResultLink {
    scoreCircle(score, range, size = '') {
        const circle = document.createElement('span');
        circle.className = 'aisuite-audit-score aisuite-audit-score--' + range + (size !== '' ? ' aisuite-audit-score--' + size : '');
        circle.textContent = String(score);
        return circle;
    }

    link(entry) {
        const link = document.createElement('a');
        link.className = 'btn btn-default aisuite-audit-link-btn';
        link.href = entry.viewUrl;
        if (entry.score !== null && entry.score !== undefined && entry.range) {
            link.append(this.scoreCircle(entry.score, entry.range, 'sm'));
        }
        const text = document.createElement('span');
        text.className = 'aisuite-audit-link-btn__text';
        const label = document.createElement('span');
        label.className = 'aisuite-audit-link-btn__label';
        label.textContent = entry.label;
        text.append(label);
        if (entry.date) {
            const date = document.createElement('small');
            date.className = 'aisuite-audit-link-btn__date';
            date.textContent = entry.date;
            text.append(date);
        }
        link.append(text);
        return link;
    }
}

export default new AuditResultLink();
