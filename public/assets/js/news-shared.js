window.NewsPortalUtils = (function () {
    function normalizeArticleText(text) {
        return String(text || '')
            .replace(/\s+(?:(?:\/\/?[A-Z]{2,6}\/\/?)|(?:[A-Za-z]{2,6}(?:\/[A-Za-z]{2,6})*))?\s*Navegaci\S*\s+de\s+entradas[\s\S]*$/iu, '')
            .replace(/\s*\.?\s*(?=[\/A-Za-z]*\/)(?:\/{0,3}[A-Za-z]{2,6}(?:\/[A-Za-z]{2,6})*\/{0,3})\s*$/u, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function formatDate(dateString, withTime) {
        if (!dateString) {
            return 'Fecha no disponible';
        }

        const date = new Date(dateString);

        if (Number.isNaN(date.getTime())) {
            return 'Fecha no disponible';
        }

        return new Intl.DateTimeFormat('es-BO', Object.assign({
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }, withTime ? {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        } : {})).format(date);
    }

    function setupBackToTop(button) {
        if (!button) {
            return;
        }

        const toggleVisibility = function () {
            button.classList.toggle('is-visible', window.scrollY > 500);
        };

        button.addEventListener('click', function () {
            window.scrollTo({
                top: 0,
                behavior: 'smooth',
            });
        });

        window.addEventListener('scroll', toggleVisibility, { passive: true });
        toggleVisibility();
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function escapeAttribute(value) {
        return escapeHtml(value);
    }

    return {
        normalizeArticleText,
        formatDate,
        setupBackToTop,
        escapeHtml,
        escapeAttribute,
    };
})();
