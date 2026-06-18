/**
 * Module: @autodudes/ai-suite/backend/nav-overflow
 *
 */
import DocumentService from "@typo3/core/document-service.js";

class NavOverflow {
    constructor() {
        DocumentService.ready().then(() => this.init());
    }

    init() {
        this.column = document.querySelector('.module-docheader-buttons .module-docheader-column-grow')
            || document.querySelector('.module-docheader-bar-buttons .module-docheader-bar-column-left');
        this.toolbar = this.column ? this.column.querySelector('.btn-toolbar') : null;
        this.group = this.toolbar ? this.toolbar.querySelector('.btn-group') : null;
        if (!this.column || !this.toolbar || !this.group) {
            return;
        }
        this.buttons = Array.from(this.group.children).filter((el) => el.matches('a.btn, button.btn'));
        if (this.buttons.length < 2) {
            this.toolbar.classList.add('ai-suite-nav-ready');
            return;
        }

        this.buildMore();
        this.bindEvents();
        this.layout();
        this.toolbar.classList.add('ai-suite-nav-ready');
    }

    buildMore() {
        const label = (window.TYPO3 && TYPO3.lang && TYPO3.lang['aiSuite.nav.more']) || 'More';
        this.more = document.createElement('div');
        this.more.className = 'btn-group ai-suite-nav-more';
        this.more.hidden = true;
        this.more.innerHTML =
            '<button type="button" class="btn btn-default rounded ai-suite-nav-more-toggle" aria-haspopup="true" aria-expanded="false">'
            + '<span class="ai-suite-nav-more-dots" aria-hidden="true">⋯</span>'
            + '<span class="ai-suite-nav-more-label"></span>'
            + '</button>'
            + '<ul class="ai-suite-nav-more-menu" role="menu"></ul>';
        this.toggle = this.more.querySelector('.ai-suite-nav-more-toggle');
        this.menu = this.more.querySelector('.ai-suite-nav-more-menu');
        this.more.querySelector('.ai-suite-nav-more-label').textContent = label;
        this.toolbar.appendChild(this.more);

        this.toggle.addEventListener('click', (ev) => {
            ev.preventDefault();
            this.setOpen(!this.more.classList.contains('open'));
        });
        document.addEventListener('click', (ev) => {
            if (this.more.classList.contains('open') && !this.more.contains(ev.target)) {
                this.setOpen(false);
            }
        });
        document.addEventListener('keydown', (ev) => {
            if (ev.key === 'Escape') {
                this.setOpen(false);
            }
        });
    }

    setOpen(open) {
        this.more.classList.toggle('open', open);
        this.toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    bindEvents() {
        if ('ResizeObserver' in window) {
            this.observer = new ResizeObserver(() => this.scheduleLayout());
            this.observer.observe(this.column);
            const navigation = document.querySelector('.scaffold-content-navigation');
            if (navigation) {
                this.observer.observe(navigation);
            }
        }
        window.addEventListener('resize', () => this.scheduleLayout());
    }

    scheduleLayout() {
        if (this.frame) {
            cancelAnimationFrame(this.frame);
        }
        this.frame = requestAnimationFrame(() => this.layout());
    }

    layout() {
        this.setOpen(false);
        this.buttons.forEach((btn) => this.group.appendChild(btn));
        this.menu.replaceChildren();
        this.more.hidden = true;
        this.toggle.classList.remove('btn-primary');
        this.toggle.classList.add('btn-default');

        const styles = getComputedStyle(this.group);
        const gap = parseFloat(styles.columnGap || styles.gap) || 8;
        const columnRect = this.column.getBoundingClientRect();
        let left = columnRect.left;
        const navigation = document.querySelector('.scaffold-content-navigation');
        if (navigation) {
            const navRect = navigation.getBoundingClientRect();
            if (navRect.width > 0 && navRect.right > left) {
                left = navRect.right;
            }
        }
        const viewportRight = document.documentElement.clientWidth;
        const available = Math.min(this.column.clientWidth, viewportRight - left);
        const widths = this.buttons.map((btn) => btn.offsetWidth);

        const total = widths.reduce((sum, w) => sum + w, 0) + gap * (this.buttons.length - 1);
        if (total <= available) {
            return;
        }

        const moreWidth = 90;
        let used = 0;
        const overflow = [];
        this.buttons.forEach((btn, i) => {
            const w = widths[i] + (i > 0 ? gap : 0);
            if (overflow.length === 0 && used + w + moreWidth <= available) {
                used += w;
            } else {
                overflow.push(btn);
            }
        });

        if (overflow.length === 0) {
            return;
        }

        let hiddenActive = false;
        overflow.forEach((btn) => {
            const li = document.createElement('li');
            const item = document.createElement('a');
            item.className = 'ai-suite-nav-more-item';
            item.href = btn.getAttribute('href') || '#';
            item.innerHTML = btn.innerHTML;
            if (btn.classList.contains('btn-primary')) {
                item.classList.add('active');
                hiddenActive = true;
            }
            li.appendChild(item);
            this.menu.appendChild(li);
            btn.remove();
        });

        this.more.hidden = false;
        this.toolbar.appendChild(this.more);
        if (hiddenActive) {
            this.toggle.classList.remove('btn-default');
            this.toggle.classList.add('btn-primary');
        }
    }
}

export default new NavOverflow();
