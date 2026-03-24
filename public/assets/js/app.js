const shell = document.querySelector('.site-shell');
const newsList = document.querySelector('#news-list');
const loadingState = document.querySelector('#loading-state');
const errorState = document.querySelector('#error-state');
const emptyState = document.querySelector('#empty-state');
const emptyStateMessage = document.querySelector('#empty-state-message');
const lastUpdated = document.querySelector('#last-updated');
const backToTopButton = document.querySelector('#back-to-top');
const filterYear = document.querySelector('#filter-year');
const filterMonth = document.querySelector('#filter-month');
const filterDay = document.querySelector('#filter-day');
const filterToday = document.querySelector('#filter-today');
const filterClear = document.querySelector('#filter-clear');
const filterToggle = document.querySelector('#filter-toggle');
const filtersPanel = document.querySelector('#filters-panel');
const themeToggle = document.querySelector('#theme-toggle');

const AUTO_REFRESH_INTERVAL_MS = 5 * 60 * 1000;
const utils = window.NewsPortalUtils;
const timezone = shell && shell.dataset.timezone ? shell.dataset.timezone : 'America/La_Paz';

const endpointCandidates = Array.from(new Set([
    shell ? shell.dataset.apiEndpoint : null,
    '../api/news.php',
    '/api/news.php',
    'api/news.php',
].filter(Boolean)));

let allNews = [];

const DAYS_IN_MONTH = 31;
const DEFAULT_EMPTY_MESSAGE = 'Cuando el sistema vuelva a actualizarse, las publicaciones apareceran aqui.';
const THEME_STORAGE_KEY = 'portal_theme';

initializeTheme();

document.addEventListener('DOMContentLoaded', function () {
    setupThemeToggle();
    setupBackToTop();
    setupFilters();
    setupFilterToggle();
    applyTodayFilter();
    setupAutoRefresh();
    loadNews();
});

async function loadNews(showLoadingState = true) {
    if (showLoadingState) {
        setState('loading');
    }

    try {
        const payload = await fetchFromAvailableEndpoint();
        const news = Array.isArray(payload && payload.data) ? payload.data : Array.isArray(payload) ? payload : [];

        allNews = news;
        updateLastUpdated(payload && payload.updated_at ? payload.updated_at : null);
        refreshFilterOptions();
        applyFiltersAndRender();
    } catch (error) {
        console.error('Error loading news:', error);
        setState('error');
    }
}

async function fetchFromAvailableEndpoint() {
    let lastError = new Error('No se pudo obtener la API.');
    const filters = getFilterSelection();

    for (const endpoint of endpointCandidates) {
        try {
            const endpointWithFilters = appendDateFilters(endpoint, filters);
            const response = await fetch(withCacheBuster(endpointWithFilters), {
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


function setupThemeToggle() {
    if (!themeToggle) {
        return;
    }

    themeToggle.addEventListener('click', function () {
        const currentTheme = document.body.classList.contains('theme-dark') ? 'dark' : 'light';
        const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';

        applyTheme(nextTheme);

        try {
            localStorage.setItem(THEME_STORAGE_KEY, nextTheme);
        } catch (error) {
        }
    });
}

function initializeTheme() {
    const storedTheme = readStoredTheme();

    if (storedTheme === 'light' || storedTheme === 'dark') {
        applyTheme(storedTheme);
        return;
    }

    let preferredTheme = 'light';

    try {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            preferredTheme = 'dark';
        }
    } catch (error) {
    }

    applyTheme(preferredTheme);
}

function readStoredTheme() {
    try {
        return localStorage.getItem(THEME_STORAGE_KEY);
    } catch (error) {
        return null;
    }
}

function applyTheme(theme) {
    const isDark = theme === 'dark';

    document.body.classList.toggle('theme-dark', isDark);

    if (!themeToggle) {
        return;
    }

    themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
    themeToggle.setAttribute('aria-label', isDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro');
    themeToggle.textContent = isDark ? '\u2600' : '\u263D';
}
function setupFilters() {
    syncDateFilterControls();

    if (filterYear) {
        filterYear.addEventListener('change', function () {
            syncDateFilterControls();
            loadNews();
        });
    }

    if (filterMonth) {
        filterMonth.addEventListener('change', function () {
            syncDateFilterControls();
            loadNews();
        });
    }

    if (filterDay) {
        filterDay.addEventListener('change', function () {
            if (isFutureFilterSelection(getFilterSelection())) {
                syncDateFilterControls();
            }

            loadNews();
        });
    }

    if (filterToday) {
        filterToday.addEventListener('click', function () {
            applyTodayFilter();
            syncDateFilterControls();
            loadNews();
        });
    }

    if (filterClear) {
        filterClear.addEventListener('click', function () {
            clearFilters();
            syncDateFilterControls();
            loadNews();
        });
    }
}


function setupFilterToggle() {
    if (!filterToggle || !filtersPanel) {
        return;
    }

    const setExpanded = function (expanded) {
        filtersPanel.classList.toggle('filters-panel--collapsed', !expanded);
        filtersPanel.setAttribute('aria-hidden', expanded ? 'false' : 'true');
        filterToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        filterToggle.textContent = expanded ? 'Ocultar busqueda' : 'Buscar por fecha';
    };

    setExpanded(!filtersPanel.classList.contains('filters-panel--collapsed'));

    filterToggle.addEventListener('click', function () {
        const expanded = filterToggle.getAttribute('aria-expanded') === 'true';
        setExpanded(!expanded);
    });
}

function refreshFilterOptions() {
    if (!filterYear) {
        return;
    }

    const selectedYear = filterYear.value;
    const selectedMonth = filterMonth ? filterMonth.value : '';
    const selectedDay = filterDay ? filterDay.value : '';

    populateYearOptions(selectedYear);
    populateMonthOptions(selectedMonth);
    populateDayOptions(selectedDay);
    syncDateFilterControls();
}

function applyTodayFilter() {
    const today = getTodayParts();

    if (!today) {
        return;
    }

    if (filterYear) {
        ensureOption(filterYear, String(today.year), String(today.year));
        filterYear.value = String(today.year);
    }

    if (filterMonth) {
        populateMonthOptions(String(today.month));
        filterMonth.value = String(today.month);
    }

    if (filterDay) {
        populateDayOptions(String(today.day));
        ensureOption(filterDay, String(today.day), String(today.day));
        filterDay.value = String(today.day);
    }
}

function clearFilters() {
    if (filterYear) {
        filterYear.value = '';
    }

    if (filterMonth) {
        filterMonth.value = '';
    }

    if (filterDay) {
        filterDay.value = '';
    }
}

function populateYearOptions(selectedYear) {
    if (!filterYear) {
        return;
    }

    const years = Array.from(new Set(
        allNews
            .map(function (item) {
                const parts = getDatePartsFromItem(item);
                return parts ? parts.year : null;
            })
            .filter(function (year) {
                return Number.isInteger(year);
            })
    )).sort(function (a, b) {
        return b - a;
    });

    const today = getTodayParts();

    if (today) {
        years.push(today.year);
    }

    const distinctYears = Array.from(new Set(years))
        .filter(function (year) {
            return !today || year <= today.year;
        })
        .sort(function (a, b) {
            return b - a;
        });

    filterYear.innerHTML = '<option value="">Todos</option>';

    distinctYears.forEach(function (year) {
        const option = document.createElement('option');
        option.value = String(year);
        option.textContent = String(year);
        filterYear.appendChild(option);
    });

    if (selectedYear && Array.from(filterYear.options).some(function (option) { return option.value === selectedYear; })) {
        filterYear.value = selectedYear;
    }
}

function populateMonthOptions(selectedMonth) {
    if (!filterMonth) {
        return;
    }

    const today = getTodayParts();
    const year = parseInteger(filterYear ? filterYear.value : '');
    const maxMonth = today && year !== null && year === today.year ? today.month : 12;

    Array.from(filterMonth.options).forEach(function (option) {
        if (option.value === '') {
            option.disabled = false;
            return;
        }

        const monthValue = parseInteger(option.value);
        option.disabled = monthValue !== null && monthValue > maxMonth;
    });

    if (selectedMonth && Array.from(filterMonth.options).some(function (option) {
        return option.value === selectedMonth && !option.disabled;
    })) {
        filterMonth.value = selectedMonth;
        return;
    }

    const currentValueOption = Array.from(filterMonth.options).find(function (option) {
        return option.value === filterMonth.value;
    });

    if (currentValueOption && currentValueOption.disabled) {
        filterMonth.value = '';
    }
}

function populateDayOptions(selectedDay) {
    if (!filterDay) {
        return;
    }

    const year = parseInteger(filterYear ? filterYear.value : '');
    const month = parseInteger(filterMonth ? filterMonth.value : '');
    const maxDay = getMaxSelectableDay(year, month);

    filterDay.innerHTML = '<option value="">Todos</option>';

    for (let day = 1; day <= maxDay; day += 1) {
        const option = document.createElement('option');
        option.value = String(day);
        option.textContent = String(day);
        filterDay.appendChild(option);
    }

    if (selectedDay && Array.from(filterDay.options).some(function (option) { return option.value === selectedDay; })) {
        filterDay.value = selectedDay;
    }
}

function ensureOption(selectElement, value, label) {
    if (!selectElement) {
        return;
    }

    const exists = Array.from(selectElement.options).some(function (option) {
        return option.value === value;
    });

    if (exists) {
        return;
    }

    const option = document.createElement('option');
    option.value = value;
    option.textContent = label;
    selectElement.appendChild(option);
}

function applyFiltersAndRender() {
    if (allNews.length === 0) {
        setEmptyMessage(buildNoResultsMessage(getFilterSelection()));
        setState('empty');
        return;
    }

    const filter = getFilterSelection();
    const filteredNews = allNews.filter(function (item) {
        return matchesDateFilter(item, filter);
    });

    if (filteredNews.length === 0) {
        setEmptyMessage(buildNoResultsMessage(filter));
        setState('empty');
        return;
    }

    renderNews(filteredNews);
    setState('success');
}

function matchesDateFilter(item, filter) {
    const parts = getDatePartsFromItem(item);

    if (!parts) {
        return false;
    }

    if (filter.year !== null && parts.year !== filter.year) {
        return false;
    }

    if (filter.month !== null && parts.month !== filter.month) {
        return false;
    }

    if (filter.day !== null && parts.day !== filter.day) {
        return false;
    }

    return true;
}

function getFilterSelection() {
    return {
        year: parseInteger(filterYear ? filterYear.value : ''),
        month: parseInteger(filterMonth ? filterMonth.value : ''),
        day: parseInteger(filterDay ? filterDay.value : ''),
    };
}

function getTodayParts() {
    return getDatePartsByTimezone(new Date());
}

function getMaxSelectableDay(year, month) {
    if (month === null || month < 1 || month > 12) {
        return DAYS_IN_MONTH;
    }

    const baseYear = year !== null ? year : 2000;
    const monthLength = new Date(baseYear, month, 0).getDate();
    const today = getTodayParts();

    if (today && year !== null && year === today.year && month === today.month) {
        return Math.min(monthLength, today.day);
    }

    return monthLength;
}

function isFutureFilterSelection(filter) {
    const today = getTodayParts();

    if (!today || filter.year === null) {
        return false;
    }

    if (filter.year > today.year) {
        return true;
    }

    if (filter.year < today.year || filter.month === null) {
        return false;
    }

    if (filter.month > today.month) {
        return true;
    }

    if (filter.month < today.month || filter.day === null) {
        return false;
    }

    return filter.day > today.day;
}

function clampFutureSelection() {
    const today = getTodayParts();

    if (!today) {
        return;
    }

    const selectedYear = parseInteger(filterYear ? filterYear.value : '');

    if (filterYear && selectedYear !== null && selectedYear > today.year) {
        filterYear.value = String(today.year);
    }

    const adjustedYear = parseInteger(filterYear ? filterYear.value : '');
    const selectedMonth = parseInteger(filterMonth ? filterMonth.value : '');

    if (filterMonth && adjustedYear !== null && adjustedYear === today.year && selectedMonth !== null && selectedMonth > today.month) {
        filterMonth.value = String(today.month);
    }

    const adjustedMonth = parseInteger(filterMonth ? filterMonth.value : '');
    const selectedDay = parseInteger(filterDay ? filterDay.value : '');

    if (filterDay
        && adjustedYear !== null
        && adjustedYear === today.year
        && adjustedMonth !== null
        && adjustedMonth === today.month
        && selectedDay !== null
        && selectedDay > today.day) {
        filterDay.value = String(today.day);
    }
}

function syncDateFilterControls() {
    const selectedMonth = filterMonth ? filterMonth.value : '';
    const selectedDay = filterDay ? filterDay.value : '';

    populateMonthOptions(selectedMonth);
    populateDayOptions(selectedDay);

    if (isFutureFilterSelection(getFilterSelection())) {
        clampFutureSelection();
        populateMonthOptions(filterMonth ? filterMonth.value : '');
        populateDayOptions(filterDay ? filterDay.value : '');
    }
}
function parseInteger(value) {
    const parsed = Number.parseInt(String(value || '').trim(), 10);

    return Number.isFinite(parsed) ? parsed : null;
}

function getDatePartsFromItem(item) {
    if (!item || !item.published_at) {
        return null;
    }

    const date = new Date(item.published_at);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return getDatePartsByTimezone(date);
}

function getDatePartsByTimezone(date) {
    try {
        const formatter = new Intl.DateTimeFormat('en-CA', {
            timeZone: timezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        });

        const parts = formatter.formatToParts(date);
        const partMap = {};

        parts.forEach(function (part) {
            if (part.type === 'year' || part.type === 'month' || part.type === 'day') {
                partMap[part.type] = part.value;
            }
        });

        return {
            year: Number.parseInt(partMap.year, 10),
            month: Number.parseInt(partMap.month, 10),
            day: Number.parseInt(partMap.day, 10),
        };
    } catch (error) {
        return {
            year: date.getFullYear(),
            month: date.getMonth() + 1,
            day: date.getDate(),
        };
    }
}

function buildNoResultsMessage(filter) {
    if (isFutureFilterSelection(filter)) {
        return 'No se pueden buscar noticias en fechas futuras.';
    }

    if (filter.year !== null && filter.month !== null && filter.day !== null) {
        const day = String(filter.day).padStart(2, '0');
        const month = String(filter.month).padStart(2, '0');

        return `No hay noticias para la fecha ${day}/${month}/${filter.year}.`;
    }

    if (filter.year !== null || filter.month !== null || filter.day !== null) {
        return 'No hay noticias que coincidan con los filtros seleccionados.';
    }

    return DEFAULT_EMPTY_MESSAGE;
}

function setEmptyMessage(message) {
    if (!emptyStateMessage) {
        return;
    }

    emptyStateMessage.textContent = message;
}

function renderNews(news) {
    const cards = news.map(createCardMarkup).join('');
    newsList.innerHTML = cards;
}

function createCardMarkup(item) {
    const imageMarkup = item.image
        ? `<img class="news-card__image" src="${utils.escapeAttribute(item.image)}" alt="${utils.escapeAttribute(item.title)}" loading="lazy">`
        : `<div class="news-card__image news-card__image--placeholder" aria-hidden="true">Sin imagen</div>`;

    const detailUrl = buildDetailUrl(item);
    const shortSummary = truncateText(utils.normalizeArticleText(item.summary || 'Sin resumen disponible.'), 280);

    return `
        <article class="news-card">
            <div class="news-card__media">
                ${imageMarkup}
            </div>
            <div class="news-card__body">
                <div class="news-card__meta">
                    <span>${utils.escapeHtml(item.source || 'ABI')}</span>
                    <span>${utils.formatDate(item.published_at)}</span>
                </div>
                <h3 class="news-card__title">${utils.escapeHtml(item.title || 'Sin titulo')}</h3>
                <p class="news-card__summary">${utils.escapeHtml(shortSummary)}</p>
                <a class="news-card__link" href="${utils.escapeAttribute(detailUrl)}">
                    Leer mas
                </a>
            </div>
        </article>
    `;
}

function buildDetailUrl(item) {
    const id = item.guid || item.link || item.title || '';
    return `/news.php?id=${encodeURIComponent(id)}`;
}

function truncateText(text, limit) {
    const normalized = String(text || '').trim();

    if (normalized.length <= limit) {
        return normalized;
    }

    const slice = normalized.slice(0, limit);
    const lastSpace = slice.lastIndexOf(' ');

    return `${(lastSpace > 0 ? slice.slice(0, lastSpace) : slice).trim()}...`;
}

function setState(state) {
    newsList.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');

    loadingState.classList.toggle('is-hidden', state !== 'loading');
    errorState.classList.toggle('is-hidden', state !== 'error');
    emptyState.classList.toggle('is-hidden', state !== 'empty');
    newsList.classList.toggle('is-hidden', state !== 'success');
}

function updateLastUpdated(dateString) {
    if (!lastUpdated) {
        return;
    }

    if (!dateString) {
        lastUpdated.textContent = 'Actualizacion local disponible';
        return;
    }

    lastUpdated.textContent = `Actualizado: ${utils.formatDate(dateString, true)}`;
}

function appendDateFilters(endpoint, filters) {
    const params = new URLSearchParams();

    if (filters.year !== null) {
        params.set('year', String(filters.year));
    }

    if (filters.month !== null) {
        params.set('month', String(filters.month));
    }

    if (filters.day !== null) {
        params.set('day', String(filters.day));
    }

    const queryString = params.toString();

    if (queryString === '') {
        return endpoint;
    }

    const separator = endpoint.indexOf('?') === -1 ? '?' : '&';

    return endpoint + separator + queryString;
}

function withCacheBuster(endpoint) {
    const separator = endpoint.indexOf('?') === -1 ? '?' : '&';

    return endpoint + separator + '_=' + Date.now();
}

function setupAutoRefresh() {
    window.setInterval(function () {
        loadNews(false);
    }, AUTO_REFRESH_INTERVAL_MS);
}

function setupBackToTop() {
    utils.setupBackToTop(backToTopButton);
}









