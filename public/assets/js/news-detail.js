const detailShell = document.querySelector('.detail-shell');
const detailLoading = document.querySelector('#detail-loading');
const detailError = document.querySelector('#detail-error');
const detailArticle = document.querySelector('#news-detail');
const backToTopButton = document.querySelector('#back-to-top');
const utils = window.NewsPortalUtils;

const detailEndpointCandidates = Array.from(new Set([
    detailShell ? detailShell.dataset.apiEndpoint : null,
    '/api/news.php',
    '../api/news.php',
].filter(Boolean)));

const requestedId = detailShell ? detailShell.dataset.newsId : '';

document.addEventListener('DOMContentLoaded', function () {
    setupBackToTop();
    loadNewsDetail();
});

async function loadNewsDetail() {
    if (!requestedId) {
        setDetailState('error');
        return;
    }

    setDetailState('loading');

    try {
        const payload = await fetchDetailPayload();
        const news = Array.isArray(payload && payload.data) ? payload.data : [];
        const item = news.find(function (entry) {
            return String(entry.guid || '') === requestedId || String(entry.link || '') === requestedId;
        });

        if (!item) {
            setDetailState('error');
            return;
        }

        renderDetail(item);
        setDetailState('success');
    } catch (error) {
        console.error('Error loading detail:', error);
        setDetailState('error');
    }
}

async function fetchDetailPayload() {
    let lastError = new Error('No se pudo obtener la API.');

    for (const endpoint of detailEndpointCandidates) {
        try {
            const response = await fetch(endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now(), {
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`Respuesta ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            lastError = error;
        }
    }

    throw lastError;
}

function renderDetail(item) {
    const imageMarkup = item.image
        ? `<img class="detail-hero__image" src="${utils.escapeAttribute(item.image)}" alt="${utils.escapeAttribute(item.title || 'Noticia ABI')}" loading="eager">`
        : '';

    const fullText = utils.normalizeArticleText(item.summary || 'Sin contenido disponible.');

    detailArticle.innerHTML = `
        <div class="detail-hero">
            ${imageMarkup}
            <div class="detail-hero__body">
                <p class="section-kicker">${utils.escapeHtml(item.source || 'ABI')}</p>
                <h2 class="detail-title">${utils.escapeHtml(item.title || 'Sin titulo')}</h2>
                <div class="detail-meta">
                    <span>${utils.formatDate(item.published_at, true)}</span>
                    <span>Fuente oficial ABI</span>
                </div>
            </div>
        </div>
        <div class="detail-content">
            <p class="detail-text">${utils.escapeHtml(fullText)}</p>
            <div class="detail-actions">
                <a class="detail-action" href="/">Volver al portal</a>
                <a class="detail-action detail-action--primary" href="${utils.escapeAttribute(item.link || '#')}" target="_blank" rel="noopener noreferrer">Ver fuente original en ABI</a>
            </div>
        </div>
    `;

    document.title = `${item.title || 'Noticia'} | Portal Noticias ABI`;
}

function setDetailState(state) {
    detailLoading.classList.toggle('is-hidden', state !== 'loading');
    detailError.classList.toggle('is-hidden', state !== 'error');
    detailArticle.classList.toggle('is-hidden', state !== 'success');
}

function setupBackToTop() {
    utils.setupBackToTop(backToTopButton);
}

