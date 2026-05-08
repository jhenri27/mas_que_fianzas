/**
 * MQF Components — JavaScript Utilities
 * Funciones de UI reutilizables para toda la plataforma.
 * Versión: 1.0 | Mayo 2026
 */

const MQF = (() => {

  /* ──────────────────────────────────────────────
     TOAST NOTIFICATIONS
  ────────────────────────────────────────────── */
  let _toastContainer = null;

  function _ensureToastContainer() {
    if (!_toastContainer) {
      _toastContainer = document.getElementById('mqf-toast-container');
      if (!_toastContainer) {
        _toastContainer = document.createElement('div');
        _toastContainer.id = 'mqf-toast-container';
        document.body.appendChild(_toastContainer);
      }
    }
    return _toastContainer;
  }

  const TOAST_ICONS = {
    success: 'fa-circle-check',
    error:   'fa-circle-xmark',
    warning: 'fa-triangle-exclamation',
    info:    'fa-circle-info'
  };

  /**
   * Muestra una notificación toast.
   * @param {string} message  - Texto del mensaje
   * @param {string} type     - 'success' | 'error' | 'warning' | 'info'
   * @param {number} duration - Milisegundos antes de auto-cerrar (default 4000)
   */
  function toast(message, type = 'info', duration = 4000) {
    const container = _ensureToastContainer();
    const icon = TOAST_ICONS[type] || TOAST_ICONS.info;

    const el = document.createElement('div');
    el.className = `mqf-toast mqf-toast--${type}`;
    el.innerHTML = `
      <i class="mqf-toast-icon fa-solid ${icon}"></i>
      <span class="mqf-toast-msg">${message}</span>
      <button class="mqf-toast-close" aria-label="Cerrar">✕</button>
    `;

    const close = el.querySelector('.mqf-toast-close');
    const remove = () => {
      el.classList.add('mqf-toast-out');
      el.addEventListener('animationend', () => el.remove(), { once: true });
      setTimeout(() => el.remove(), 300);
    };

    close.addEventListener('click', remove);
    container.appendChild(el);

    if (duration > 0) setTimeout(remove, duration);
    return el;
  }

  /* ──────────────────────────────────────────────
     MODAL MANAGEMENT
  ────────────────────────────────────────────── */

  /**
   * Abre un modal por su ID de backdrop.
   * @param {string} backdropId - ID del elemento .mqf-modal-backdrop
   */
  function modalOpen(backdropId) {
    const backdrop = document.getElementById(backdropId);
    if (!backdrop) return;
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Cerrar al click en backdrop
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) modalClose(backdropId);
    }, { once: true });

    // Cerrar con ESC
    const escHandler = (e) => {
      if (e.key === 'Escape') { modalClose(backdropId); document.removeEventListener('keydown', escHandler); }
    };
    document.addEventListener('keydown', escHandler);

    // Enfocar primer input
    setTimeout(() => {
      const first = backdrop.querySelector('input:not([type=hidden]), select, textarea');
      if (first) first.focus();
    }, 100);
  }

  /**
   * Cierra un modal por su ID de backdrop.
   */
  function modalClose(backdropId) {
    const backdrop = document.getElementById(backdropId);
    if (!backdrop) return;
    backdrop.classList.remove('active');
    document.body.style.overflow = '';
  }

  /* ──────────────────────────────────────────────
     CONFIRM DIALOG
  ────────────────────────────────────────────── */

  /**
   * Muestra un diálogo de confirmación customizado.
   * @param {string} message   - Mensaje de confirmación
   * @param {object} options   - { title, confirmText, cancelText, type }
   * @returns {Promise<boolean>}
   */
  function confirm(message, options = {}) {
    return new Promise((resolve) => {
      const {
        title       = '¿Confirmar acción?',
        confirmText = 'Confirmar',
        cancelText  = 'Cancelar',
        type        = 'primary'
      } = options;

      const CONFIRM_ID = 'mqf-confirm-modal';
      let existing = document.getElementById(CONFIRM_ID);
      if (existing) existing.remove();

      const backdrop = document.createElement('div');
      backdrop.id = CONFIRM_ID;
      backdrop.className = 'mqf-modal-backdrop';
      backdrop.innerHTML = `
        <div class="mqf-modal mqf-modal--sm" role="dialog" aria-modal="true">
          <div class="mqf-modal-header">
            <h3 class="mqf-modal-title">
              <i class="fa-solid fa-circle-question"></i> ${title}
            </h3>
          </div>
          <div class="mqf-modal-body">
            <p style="color:var(--mqf-text-secondary);font-size:var(--mqf-text-base);line-height:1.6;">${message}</p>
          </div>
          <div class="mqf-modal-footer">
            <button id="mqf-confirm-cancel" class="mqf-btn mqf-btn--secondary">${cancelText}</button>
            <button id="mqf-confirm-ok" class="mqf-btn mqf-btn--${type}">${confirmText}</button>
          </div>
        </div>
      `;

      document.body.appendChild(backdrop);
      setTimeout(() => backdrop.classList.add('active'), 10);
      document.body.style.overflow = 'hidden';

      const cleanup = (result) => {
        backdrop.classList.remove('active');
        setTimeout(() => { backdrop.remove(); document.body.style.overflow = ''; }, 200);
        resolve(result);
      };

      backdrop.querySelector('#mqf-confirm-ok').addEventListener('click', () => cleanup(true));
      backdrop.querySelector('#mqf-confirm-cancel').addEventListener('click', () => cleanup(false));
      backdrop.addEventListener('click', (e) => { if (e.target === backdrop) cleanup(false); });
    });
  }

  /* ──────────────────────────────────────────────
     BUTTON LOADING STATE
  ────────────────────────────────────────────── */

  /**
   * Activa/desactiva el estado de carga en un botón.
   * @param {HTMLElement|string} btn - Elemento o ID del botón
   * @param {boolean} loading        - true para mostrar spinner
   * @param {string}  loadingText    - Texto mientras carga (opcional)
   */
  function btnLoading(btn, loading, loadingText = 'Procesando...') {
    const el = typeof btn === 'string' ? document.getElementById(btn) : btn;
    if (!el) return;

    if (loading) {
      el._originalHTML = el.innerHTML;
      el.disabled = true;
      el.classList.add('mqf-loading');
      if (loadingText) el.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${loadingText}`;
    } else {
      el.disabled = false;
      el.classList.remove('mqf-loading');
      if (el._originalHTML) { el.innerHTML = el._originalHTML; delete el._originalHTML; }
    }
  }

  /* ──────────────────────────────────────────────
     SEMÁFORO DE VIGENCIA
  ────────────────────────────────────────────── */

  /**
   * Retorna la clase CSS de semáforo según días restantes.
   * @param {number} dias - Días hasta vencimiento
   * @returns {string} clase CSS
   */
  function semaforo(dias) {
    if (dias > 30)  return 'mqf-semaforo--verde';
    if (dias > 15)  return 'mqf-semaforo--amarillo';
    return 'mqf-semaforo--rojo';
  }

  /**
   * Genera HTML de semáforo con etiqueta.
   */
  function semaforoHtml(dias) {
    const cls  = semaforo(dias);
    const text = dias <= 0 ? 'Vencida' : dias === 1 ? '1 día' : `${dias} días`;
    return `<span class="mqf-semaforo ${cls}">${text}</span>`;
  }

  /* ──────────────────────────────────────────────
     BADGE DE ESTADO
  ────────────────────────────────────────────── */

  const BADGE_MAP = {
    // Positivos
    'ACTIVO': 'success', 'ACTIVA': 'success', 'PAGADO': 'success',
    'APROBADO': 'success', 'COBRADO': 'success', 'AL_DIA': 'success',
    'VIGENTE': 'success', 'EMITIDA': 'success',
    // Negativos
    'CANCELADO': 'danger', 'CANCELADA': 'danger', 'ANULADO': 'danger',
    'VENCIDO': 'danger', 'VENCIDA': 'danger', 'EJECUTADA': 'danger',
    'RECHAZADO': 'danger', 'BLOQUEADO': 'danger',
    // Advertencia
    'PENDIENTE': 'warning', 'POR_VENCER': 'warning', 'MORA': 'warning',
    'REVISION': 'warning', 'EN_PROCESO': 'warning',
    // Info
    'COTIZADA': 'info', 'BORRADOR': 'info', 'NUEVO': 'info',
    'EMITIDO': 'info', 'ENVIADO': 'info',
  };

  /**
   * Genera HTML de badge para un estado.
   * @param {string} estado - Texto del estado
   * @returns {string} HTML del badge
   */
  function badge(estado) {
    if (!estado) return '';
    const key   = String(estado).toUpperCase().replace(/ /g, '_');
    const type  = BADGE_MAP[key] || 'neutral';
    const label = String(estado).replace(/_/g, ' ');
    return `<span class="mqf-badge mqf-badge--${type}">${label}</span>`;
  }

  /* ──────────────────────────────────────────────
     FORMATTERS
  ────────────────────────────────────────────── */

  /**
   * Formatea número como moneda DOP.
   */
  function fmtMoney(n) {
    return Number(n || 0).toLocaleString('es-DO', { style: 'currency', currency: 'DOP' });
  }

  /**
   * Formatea fecha DD/MM/YYYY.
   */
  function fmtDate(str) {
    if (!str) return '—';
    try {
      const d = new Date(str + (str.includes('T') ? '' : 'T00:00:00'));
      return d.toLocaleDateString('es-DO', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } catch { return str; }
  }

  /**
   * Calcula días entre hoy y una fecha futura.
   */
  function diasHasta(fechaStr) {
    if (!fechaStr) return 0;
    const hoy    = new Date(); hoy.setHours(0,0,0,0);
    const target = new Date(fechaStr + 'T00:00:00');
    return Math.ceil((target - hoy) / 86400000);
  }

  /* ──────────────────────────────────────────────
     TABLA HELPERS
  ────────────────────────────────────────────── */

  /**
   * Aplica filtro de búsqueda a filas de una tabla.
   * @param {string} tableId   - ID de la tabla
   * @param {string} query     - Texto a buscar
   * @param {number[]} cols    - Índices de columnas a buscar (omitir = todas)
   */
  function filterTable(tableId, query, cols = null) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const q = query.toLowerCase().trim();
    table.querySelectorAll('tbody tr').forEach(row => {
      const cells = cols
        ? cols.map(i => row.cells[i]).filter(Boolean)
        : Array.from(row.cells);
      const text = cells.map(c => c.textContent).join(' ').toLowerCase();
      row.style.display = !q || text.includes(q) ? '' : 'none';
    });
  }

  /* ──────────────────────────────────────────────
     API DE INIT
  ────────────────────────────────────────────── */

  /**
   * Inicializa automáticamente los modales con data-mqf-modal.
   * Uso: <button data-mqf-modal="miModalId">Abrir</button>
   *      <button data-mqf-close="miModalId">Cerrar</button>
   */
  function init() {
    document.addEventListener('click', (e) => {
      const openBtn  = e.target.closest('[data-mqf-modal]');
      const closeBtn = e.target.closest('[data-mqf-close]');
      if (openBtn)  modalOpen(openBtn.dataset.mqfModal);
      if (closeBtn) modalClose(closeBtn.dataset.mqfClose);
    });
  }

  // Auto-init en DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  /* ── API PÚBLICA ──────────────────────────────── */
  return {
    toast,
    modal: { open: modalOpen, close: modalClose },
    confirm,
    btnLoading,
    semaforo,
    semaforoHtml,
    badge,
    fmt: { money: fmtMoney, date: fmtDate, diasHasta }
  };

})();
