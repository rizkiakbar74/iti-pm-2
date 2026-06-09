<script>
(() => {
    const body = document.body;
    if (!body.classList.contains('app-ui')) return;

    const revealTargets = [...document.querySelectorAll('main > section, main > form, main > article, main > .grid, main > .mb-6, main > .rounded-3xl')];
    revealTargets.forEach((element, index) => {
        if (element.classList.contains('dashboard-shell')) return;
        element.classList.add('app-reveal');
        element.style.setProperty('--app-delay', `${Math.min(index * 45, 360)}ms`);
    });

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('app-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: .06 });
    revealTargets.forEach(element => observer.observe(element));

    document.querySelectorAll('button.bg-orange-600, button.bg-orange-500, button.bg-slate-900, button.bg-green-600, button.bg-red-600, a.bg-slate-900, a.bg-orange-600').forEach(button => {
        button.classList.add('app-ripple');
        button.addEventListener('pointerdown', event => {
            const rect = button.getBoundingClientRect();
            const dot = document.createElement('span');
            dot.className = 'app-ripple-dot';
            dot.style.left = `${event.clientX - rect.left}px`;
            dot.style.top = `${event.clientY - rect.top}px`;
            button.appendChild(dot);
            dot.addEventListener('animationend', () => dot.remove());
        });
    });

    document.querySelectorAll('article.rounded-3xl, a.block.rounded-2xl, a.grid.rounded-2xl').forEach(card => card.classList.add('app-clickable-card'));
    document.querySelectorAll('[class*="bg-red-50"], [class*="bg-green-50"], [class*="bg-amber-50"]').forEach(notice => notice.classList.add('app-toast-like'));
    document.querySelectorAll('[class*="fixed"][class*="inset-0"] > div').forEach(panel => panel.classList.add('app-modal-panel'));
    document.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
        if (form.checkValidity()) form.classList.add('is-submitting');
    }));

    const modalOverlays = [...document.querySelectorAll('[class*="fixed"][class*="inset-0"]')].filter(element => !element.classList.contains('mobile-drawer-backdrop'));
    const syncModalScroll = () => {
        const hasOpenModal = modalOverlays.some(modal => modal.classList.contains('flex') && !modal.classList.contains('hidden'));
        if (!document.querySelector('.mobile-navigation-drawer.is-open')) body.style.overflow = hasOpenModal ? 'hidden' : '';
    };
    document.addEventListener('click', () => requestAnimationFrame(syncModalScroll));
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        modalOverlays.forEach(modal => {
            if (modal.classList.contains('flex') && !modal.classList.contains('hidden')) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        });
        syncModalScroll();
    });
    syncModalScroll();
})();
</script>
</div>
</body>
</html>
