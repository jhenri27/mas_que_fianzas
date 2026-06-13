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
  /* ──────────────────────────────────────────────
     CHAT-CSR (SUPPORT CHAT) & BHN-BOT-HELPNOW
  ────────────────────────────────────────────── */
  /* ──────────────────────────────────────────────
     CHAT-CSR (SUPPORT CHAT) & BOTS (BHN / SSINDI)
  ────────────────────────────────────────────── */
  let chatPollingInterval = null;
  let activeChatRecipientId = null;
  let conversationsList = [];

  function _initChatCSR() {
    // Only inject on dashboard.html (indicated by the presence of sidebar-nav or top-header)
    if (!document.getElementById('pageTitle') && !document.querySelector('.top-header')) {
      return;
    }

    // Yellow bell handler
    const notifBtn = document.getElementById('notificationBtn');
    if (notifBtn) {
      notifBtn.title = "Chat de Comunicación CSR";
      notifBtn.style.background = "#f59e0b";
      notifBtn.style.color = "white";
      notifBtn.style.borderRadius = "50%";
      notifBtn.style.boxShadow = "0 0 10px rgba(245, 158, 11, 0.4)";
      
      const newNotifBtn = notifBtn.cloneNode(true);
      notifBtn.parentNode.replaceChild(newNotifBtn, notifBtn);
      newNotifBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        abrirChatCSR();
      });
    }

    // Inject CSS for Dual-Pane Layout
    const style = document.createElement('style');
    style.textContent = `
      .chat-csr-fab {
        position: fixed; bottom: 20px; right: 20px; width: 56px; height: 56px; border-radius: 50%;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white;
        display: flex; align-items: center; justify-content: center; font-size: 24px; cursor: pointer;
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4); z-index: 9999;
        transition: all 0.2s ease-in-out; border: 2px solid rgba(255,255,255,0.2);
        user-select: none;
      }
      .chat-csr-fab:hover {
        transform: scale(1.08);
        box-shadow: 0 6px 20px rgba(245, 158, 11, 0.6);
      }
      .chat-csr-window {
        display: none; position: fixed; bottom: 85px; right: 20px; width: 850px; height: 580px;
        background: rgba(30, 41, 59, 0.95); backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px);
        border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 16px;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4); z-index: 10000;
        flex-direction: row; overflow: hidden; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        min-width: 500px; min-height: 400px; max-width: 95vw; max-height: 90vh;
      }
      .chat-csr-resizer {
        position: absolute; right: 0; bottom: 0; width: 16px; height: 16px;
        cursor: se-resize; z-index: 10001;
        background: linear-gradient(135deg, transparent 60%, rgba(255,255,255,0.35) 60%);
        border-bottom-right-radius: 16px;
      }
      .chat-csr-sidebar {
        width: 260px; border-right: 1px solid rgba(255, 255, 255, 0.12); display: flex; flex-direction: column; background: rgba(15, 23, 42, 0.5); flex-shrink: 0;
      }
      .chat-csr-main {
        flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative;
      }
      .chat-csr-search-container {
        padding: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      }
      .chat-csr-search-input {
        width: 100%; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 20px; padding: 7px 15px; color: white; font-size: 12px; outline: none; box-sizing: border-box;
      }
      .chat-csr-search-input::placeholder {
        color: rgba(255,255,255,0.4);
      }
      .chat-csr-contact-list {
        flex: 1; overflow-y: auto; display: flex; flex-direction: column;
      }
      .chat-csr-contact-item {
        display: flex; align-items: center; gap: 12px; padding: 12px 14px; cursor: pointer;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04); transition: all 0.2s ease;
      }
      .chat-csr-contact-item:hover {
        background: rgba(255, 255, 255, 0.06);
      }
      .chat-csr-contact-item.active {
        background: rgba(245, 158, 11, 0.16);
        border-left: 4px solid #f59e0b;
        padding-left: 10px;
      }
      .chat-csr-avatar {
        width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 14px; position: relative; flex-shrink: 0; color: white;
        text-shadow: 0 1px 2px rgba(0,0,0,0.2);
      }
      .chat-csr-avatar.bhn {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
      }
      .chat-csr-avatar.bbs {
        background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
      }
      .chat-csr-avatar.human {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
      }
      .chat-csr-status-dot {
        position: absolute; bottom: 1px; right: 1px; width: 10px; height: 10px; border-radius: 50%;
        border: 2px solid #111827;
      }
      .chat-csr-status-dot.online {
        background: #22c55e;
      }
      .chat-csr-status-dot.bot {
        background: #3b82f6;
      }
      .chat-csr-contact-info {
        flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0;
      }
      .chat-csr-contact-header {
        display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;
      }
      .chat-csr-contact-name {
        font-size: 13.5px; font-weight: 600; color: #f1f5f9; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
      }
      .chat-csr-contact-time {
        font-size: 10px; color: rgba(255,255,255,0.4); flex-shrink: 0;
      }
      .chat-csr-contact-preview {
        font-size: 11.5px; color: rgba(255,255,255,0.45); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
      }
      .chat-csr-contact-badge {
        background: #ef4444; color: white; font-size: 10px; font-weight: 700; border-radius: 10px;
        min-width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; padding: 0 5px;
        box-sizing: border-box; flex-shrink: 0; margin-left: 6px;
      }
      .chat-msg-row {
        display: flex; width: 100%; margin-bottom: 8px;
        user-select: none !important; -webkit-user-select: none !important; -moz-user-select: none !important; -ms-user-select: none !important;
      }
      .chat-msg-row.yo {
        justify-content: flex-end;
      }
      #chat-csr-messages {
        user-select: none !important; -webkit-user-select: none !important; -moz-user-select: none !important; -ms-user-select: none !important;
      }
      .chat-msg-bubble {
        max-width: 75%; padding: 8px 12px; border-radius: 12px; line-height: 1.4; word-break: break-word;
        user-select: text !important; -webkit-user-select: text !important; -moz-user-select: text !important; -ms-user-select: text !important;
      }
      .chat-msg-row.yo .chat-msg-bubble {
        background: #f59e0b; color: white; border-bottom-right-radius: 2px;
      }
      .chat-msg-row:not(.yo) .chat-msg-bubble {
        background: rgba(255,255,255,0.1); color: #f1f5f9; border-bottom-left-radius: 2px;
        border: 1px solid rgba(255,255,255,0.05);
      }
      .chat-loading-spinner {
        display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3);
        border-radius: 50%; border-top-color: white; animation: chat-spin 1s linear infinite;
      }
      @keyframes chat-spin { to { transform: rotate(360deg); } }
      .chat-msg-img-preview {
        max-width:100%; max-height:150px; border-radius:8px; cursor:pointer; transition: opacity 0.2s;
      }
      .chat-msg-img-preview:hover { opacity: 0.9; }
      .chat-actions { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
      .chat-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid rgba(255,255,255,0.2); transition: all 0.2s ease; text-decoration: none !important; }
      .chat-btn.btn-download { background: #2563eb; color: white !important; }
      .chat-btn.btn-email { background: #10b981; color: white !important; }
      .chat-btn:hover { transform: translateY(-1px); filter: brightness(1.1); }
      
      /* Premium Glassmorphic Quote Cards */
      .chat-quote-row {
        display: flex; align-items: center; gap: 12px; margin: 10px 0; width: 100%; max-width: 520px;
      }
      .chat-quote-card {
        display: flex; align-items: center; gap: 12px; padding: 10px 18px; border-radius: 24px;
        background: linear-gradient(135deg, rgba(0, 82, 212, 0.45) 0%, rgba(67, 100, 247, 0.25) 50%, rgba(111, 177, 252, 0.1) 100%);
        backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        flex-grow: 1; min-width: 0; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      }
      .chat-quote-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 22px rgba(0, 82, 212, 0.3);
        border-color: rgba(255, 255, 255, 0.35);
        background: linear-gradient(135deg, rgba(0, 82, 212, 0.55) 0%, rgba(67, 100, 247, 0.35) 50%, rgba(111, 177, 252, 0.15) 100%);
      }
      .chat-quote-logo {
        width: 28px; height: 28px; border-radius: 50%;
        background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.25);
        display: flex; align-items: center; justify-content: center;
        font-size: 13px; font-weight: 700; color: #f59e0b; flex-shrink: 0;
      }
      .chat-quote-company {
        color: #f8fafc; font-size: 14px; font-weight: 650; letter-spacing: 0.3px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex-grow: 1;
      }
      .chat-quote-price {
        color: #ffffff; font-size: 15.5px; font-weight: 800; letter-spacing: 0.5px;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.4); white-space: nowrap; flex-shrink: 0;
      }
      .chat-quote-actions {
        display: flex; align-items: center; gap: 8px; flex-shrink: 0;
      }
      .chat-quote-btn {
        width: 32px; height: 32px; border-radius: 50%;
        background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.12);
        color: #e2e8f0; display: flex; align-items: center; justify-content: center;
        font-size: 15px; cursor: pointer; transition: all 0.2s ease;
        text-decoration: none !important; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
      }
      .chat-quote-btn:hover {
        background: rgba(255, 255, 255, 0.22); color: #ffffff;
        border-color: rgba(255, 255, 255, 0.3); transform: scale(1.1);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25);
      }
      .chat-quote-btn.btn-download:hover {
        color: #60a5fa; background: rgba(59, 130, 246, 0.2); border-color: rgba(59, 130, 246, 0.4);
      }
      .chat-quote-btn.btn-email:hover {
        color: #34d399; background: rgba(16, 185, 129, 0.2); border-color: rgba(16, 185, 129, 0.4);
      }
      .chat-quote-btn.sending {
        width: auto !important; border-radius: 16px !important; padding: 0 12px !important;
        gap: 6px !important; font-size: 12px !important; white-space: nowrap !important;
        background: rgba(255, 255, 255, 0.15) !important; color: #ffffff !important;
      }
    `;
    document.head.appendChild(style);

    // Inject FAB
    const fab = document.createElement('div');
    fab.id = 'chat-csr-fab';
    fab.className = 'chat-csr-fab';
    fab.innerHTML = `
      💬
      <span id="chat-csr-fab-badge" style="position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 11px; display: none; align-items: center; justify-content: center; font-weight: bold; border: 2px solid white;">0</span>
    `;
    fab.addEventListener('click', () => {
      const win = document.getElementById('chat-csr-window');
      if (win && win.style.display === 'flex') {
        cerrarChatCSR();
      } else {
        abrirChatCSR();
      }
    });
    document.body.appendChild(fab);

    // Inject Chat Window with Sidebar + Main Thread
    const win = document.createElement('div');
    win.id = 'chat-csr-window';
    win.className = 'chat-csr-window';
    win.innerHTML = `
      <!-- LEFT SIDEBAR -->
      <div class="chat-csr-sidebar">
        <div style="background: rgba(15, 23, 42, 0.4); padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.1); flex-shrink:0;">
          <strong style="color: white; font-size: 14px; display: block; letter-spacing: 0.5px;">Chats CSR</strong>
        </div>
        <div class="chat-csr-search-container">
          <input type="text" id="chat-csr-search" class="chat-csr-search-input" placeholder="Buscar bot o supervisor...">
        </div>
        <div id="chat-csr-contact-list" class="chat-csr-contact-list">
          <!-- Dynamically populated -->
        </div>
      </div>
      
      <!-- RIGHT PANEL (MAIN THREAD) -->
      <div class="chat-csr-main">
        <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 12px 15px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.1); flex-shrink:0;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 8px; height: 8px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 8px #22c55e;"></div>
                <div style="display: flex; flex-direction: column;">
                    <strong id="chat-csr-recipient-name" style="color: white; font-size: 13.5px;">Seleccione chat...</strong>
                    <span id="chat-csr-recipient-role" style="color: #94a3b8; font-size: 11px;">—</span>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 12px;">
                <button id="chat-csr-refresh-btn" style="background: transparent; border: none; color: #94a3b8; font-size: 15px; cursor: pointer; padding: 0; line-height:1; display: flex; align-items: center;" title="Actualizar Chat">🔄</button>
                <button id="chat-csr-clear-btn" style="background: transparent; border: none; color: #94a3b8; font-size: 15px; cursor: pointer; padding: 0; line-height:1; display: flex; align-items: center;" title="Nueva Conversación / Limpiar Pantalla">🧹</button>
                <button id="chat-csr-close-btn" style="background: transparent; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; padding: 0; line-height:1; display: flex; align-items: center;">&times;</button>
            </div>
        </div>
        <div id="chat-csr-messages" style="flex: 1; padding: 15px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; color: white; font-size: 13px;">
            <div style="text-align:center; color:rgba(255,255,255,0.4); font-size:12px; margin-top:20px;">Por favor, seleccione un contacto de la barra lateral para iniciar la comunicación.</div>
        </div>
        <div id="chat-csr-file-preview" style="display: none; align-items: center; justify-content: space-between; background: rgba(245, 158, 11, 0.15); border-top: 1px solid rgba(255,255,255,0.1); padding: 6px 15px; font-size: 12px; color: #f59e0b; flex-shrink:0;">
            <span id="chat-csr-file-name" style="text-overflow: ellipsis; overflow: hidden; white-space: nowrap; max-width: 300px;">📎 archivo.pdf</span>
            <button id="chat-csr-file-cancel" style="background: none; border: none; color: #ef4444; font-weight: bold; cursor: pointer; font-size: 16px; padding: 0 5px;">&times;</button>
        </div>
        <div style="padding: 10px 15px; border-top: 1px solid rgba(255,255,255,0.1); background: rgba(15, 23, 42, 0.5); display: flex; gap: 8px; align-items: center; flex-shrink:0;">
            <input type="file" id="chat-file-input" style="display: none;" accept=".xls,.xlsx,.csv,.xml,.json,.doc,.docx,.ppt,.pptx,.pdf,image/png,image/jpeg">
            <button id="chat-csr-clip-btn" style="background: transparent; border: none; color: #94a3b8; font-size: 18px; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center;" title="Adjuntar Archivo">📎</button>
            <input type="text" id="chat-csr-input" placeholder="Escribe un mensaje..." style="flex: 1; background: rgba(255,255,255,0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 20px; padding: 8px 15px; color: white; font-size: 13px; outline: none;" disabled>
            <button id="chat-csr-send-btn" style="background: #f59e0b; border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: white; font-size: 14px; box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);" disabled>&#10148;</button>
        </div>
      </div>
      <div class="chat-csr-resizer"></div>
    `;
    document.body.appendChild(win);

    // Resizing Drag logic
    const resizer = win.querySelector('.chat-csr-resizer');
    if (resizer) {
      resizer.addEventListener('mousedown', (e) => {
        e.preventDefault();
        const startWidth = win.offsetWidth;
        const startHeight = win.offsetHeight;
        const startX = e.clientX;
        const startY = e.clientY;
        
        function doDrag(ev) {
          let newWidth = startWidth + (ev.clientX - startX);
          let newHeight = startHeight + (ev.clientY - startY);
          
          if (newWidth < 500) newWidth = 500;
          if (newHeight < 400) newHeight = 400;
          if (newWidth > window.innerWidth * 0.95) newWidth = window.innerWidth * 0.95;
          if (newHeight > window.innerHeight * 0.9) newHeight = window.innerHeight * 0.9;
          
          win.style.width = newWidth + 'px';
          win.style.height = newHeight + 'px';
        }
        
        function stopDrag() {
          document.documentElement.removeEventListener('mousemove', doDrag, false);
          document.documentElement.removeEventListener('mouseup', stopDrag, false);
        }
        
        document.documentElement.addEventListener('mousemove', doDrag, false);
        document.documentElement.addEventListener('mouseup', stopDrag, false);
      });
    }

    win.querySelector('#chat-csr-close-btn').addEventListener('click', cerrarChatCSR);
    
    win.querySelector('#chat-csr-refresh-btn').addEventListener('click', () => {
      cargarMensajesChatCSR();
      toast('Chat actualizado', 'info');
    });

    win.querySelector('#chat-csr-clear-btn').addEventListener('click', () => {
      if (!activeChatRecipientId) return;
      if (confirm('¿Desea iniciar una nueva conversación y limpiar la pantalla? El historial anterior se guardará y podrá volver a mostrarse en cualquier momento.')) {
        const chatMsgs = document.getElementById('chat-csr-messages');
        const rows = chatMsgs.querySelectorAll('[data-msg-id]');
        let maxId = 0;
        rows.forEach(r => {
          const mId = parseInt(r.dataset.msgId || 0);
          if (mId > maxId) maxId = mId;
        });
        
        if (maxId > 0) {
          localStorage.setItem(`chat_clear_id_${activeChatRecipientId}`, maxId);
          cargarMensajesChatCSR();
          toast('Pantalla limpia. Nueva conversación iniciada.', 'success');
        } else {
          toast('La pantalla ya está limpia.', 'info');
        }
      }
    });

    const searchInput = win.querySelector('#chat-csr-search');
    searchInput.addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase().trim();
      renderContactList(q);
    });
    
    const clipBtn = win.querySelector('#chat-csr-clip-btn');
    const fileInput = win.querySelector('#chat-file-input');
    const previewArea = win.querySelector('#chat-csr-file-preview');
    const fileNameSpan = win.querySelector('#chat-csr-file-name');
    const fileCancelBtn = win.querySelector('#chat-csr-file-cancel');

    clipBtn.addEventListener('click', () => {
      if (activeChatRecipientId) fileInput.click();
    });

    fileInput.addEventListener('change', () => {
      if (fileInput.files.length > 0) {
        const file = fileInput.files[0];
        fileNameSpan.textContent = "📎 " + file.name + " (" + (file.size / 1024).toFixed(1) + " KB)";
        previewArea.style.display = 'flex';
      } else {
        previewArea.style.display = 'none';
      }
    });

    fileCancelBtn.addEventListener('click', () => {
      fileInput.value = '';
      previewArea.style.display = 'none';
    });

    const input = win.querySelector('#chat-csr-input');
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') enviarMensajeChatCSR();
    });
    
    win.querySelector('#chat-csr-send-btn').addEventListener('click', enviarMensajeChatCSR);

    checkUnreadMessagesCount();
    setInterval(checkUnreadMessagesCount, 8000);
  }

  function renderContactList(query = '') {
    const listEl = document.getElementById('chat-csr-contact-list');
    if (!listEl) return;
    
    let html = '';
    const filtered = conversationsList.filter(c => {
      const name = `${c.nombre} ${c.apellido}`.toLowerCase();
      const username = (c.username || '').toLowerCase();
      return name.includes(query) || username.includes(query);
    });
    
    filtered.forEach(c => {
      const isActive = (c.id == activeChatRecipientId) ? 'active' : '';
      const isBot = parseInt(c.es_bot || 0) === 1;
      const avatarClass = isBot ? (c.bot_code === 'BHN' ? 'bhn' : 'bbs') : 'human';
      const avatarContent = isBot ? (c.bot_code === 'BHN' ? '🛠️' : '📈') : `${c.nombre.charAt(0)}${c.apellido.charAt(0)}`.toUpperCase();
      const statusDotClass = isBot ? 'bot' : 'online';
      
      let previewText = c.ultimo_mensaje || '';
      if (previewText.startsWith('🤖')) {
        previewText = previewText.replace(/^🤖\s*\*\*.*?\*\*:\s*/, '');
      }
      if (!previewText && isBot) {
        previewText = c.bot_code === 'BHN' ? 'Asistencia técnica' : 'Servicios comerciales';
      }
      
      const unreadBadge = parseInt(c.no_leidos || 0) > 0 ? `<span class="chat-csr-contact-badge">${c.no_leidos}</span>` : '';
      
      html += `
        <div class="chat-csr-contact-item ${isActive}" onclick="MQF.selectContact(${c.id})">
          <div class="chat-csr-avatar ${avatarClass}">
            ${avatarContent}
            <div class="chat-csr-status-dot ${statusDotClass}"></div>
          </div>
          <div class="chat-csr-contact-info">
            <div class="chat-csr-contact-header">
              <span class="chat-csr-contact-name">${c.nombre} ${c.apellido}</span>
              <span class="chat-csr-contact-time">${c.ultima_fecha ? c.ultima_fecha.substring(11, 16) : ''}</span>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <span class="chat-csr-contact-preview">${previewText}</span>
              ${unreadBadge}
            </div>
          </div>
        </div>
      `;
    });
    
    listEl.innerHTML = html;
  }

  function selectContact(id) {
    activeChatRecipientId = id;
    
    // Enable inputs
    const input = document.getElementById('chat-csr-input');
    const sendBtn = document.getElementById('chat-csr-send-btn');
    if (input) input.disabled = false;
    if (sendBtn) sendBtn.disabled = false;
    
    // Find selected conversation and update header
    const recipient = conversationsList.find(c => c.id == id);
    if (recipient) {
      document.getElementById('chat-csr-recipient-name').textContent = `${recipient.nombre} ${recipient.apellido}`;
      document.getElementById('chat-csr-recipient-role').textContent = parseInt(recipient.es_bot || 0) === 1 
        ? (recipient.bot_code === 'BHN' ? '🤖 Bot de Soporte Técnico' : '🤖 Asistente de Seguros (SSINDI)') 
        : `🟢 ${recipient.nombre_perfil || 'Supervisor'}`;
    }
    
    // Clear local unread counts
    if (recipient) {
      recipient.no_leidos = 0;
    }
    
    renderContactList();
    cargarMensajesChatCSR();
    
    // Restart polling interval
    if (chatPollingInterval) clearInterval(chatPollingInterval);
    chatPollingInterval = setInterval(cargarMensajesChatCSR, 2500);
  }

  async function checkUnreadMessagesCount() {
    try {
      const token = localStorage.getItem('token_sesion') || '';
      if (!token) return;
      const response = await fetch('/PLATAFORMA_INTEGRADA/backend/api/chat.php', {
        headers: { 'Authorization': 'Bearer ' + token }
      });
      const res = await response.json();
      if (res.exito && Array.isArray(res.conversaciones)) {
        let totalNoLeidos = 0;
        res.conversaciones.forEach(c => {
          totalNoLeidos += parseInt(c.no_leidos || 0);
        });

        const badge = document.getElementById('chat-csr-fab-badge');
        if (badge) {
          if (totalNoLeidos > 0) {
            badge.textContent = totalNoLeidos;
            badge.style.display = 'flex';
          } else {
            badge.style.display = 'none';
          }
        }
      }
    } catch (e) {
      console.warn('Error checking chat status', e);
    }
  }

  async function abrirChatCSR() {
    const win = document.getElementById('chat-csr-window');
    if (!win) return;

    win.style.display = 'flex';
    
    try {
      const token = localStorage.getItem('token_sesion') || '';
      const response = await fetch('/PLATAFORMA_INTEGRADA/backend/api/chat.php', {
        headers: { 'Authorization': 'Bearer ' + token }
      });
      const res = await response.json();
      
      if (res.exito) {
        conversationsList = res.conversaciones || [];
        renderContactList();
        
        if (conversationsList.length > 0) {
          // Default to select first bot or contact
          selectContact(conversationsList[0].id);
        }
      }
    } catch (err) {
      console.error('Error opening chat CSR:', err);
    }
  }

  function cerrarChatCSR() {
    const win = document.getElementById('chat-csr-window');
    if (win) win.style.display = 'none';
    if (chatPollingInterval) {
      clearInterval(chatPollingInterval);
      chatPollingInterval = null;
    }
  }

  async function cargarMensajesChatCSR() {
    if (!activeChatRecipientId) return;
    try {
      const token = localStorage.getItem('token_sesion') || '';
      const response = await fetch(`/PLATAFORMA_INTEGRADA/backend/api/chat.php?chat_con_id=${activeChatRecipientId}`, {
        headers: { 'Authorization': 'Bearer ' + token }
      });
      const res = await response.json();
      if (res.exito && Array.isArray(res.mensajes)) {
        const chatMsgs = document.getElementById('chat-csr-messages');
        
        const clearId = parseInt(localStorage.getItem(`chat_clear_id_${activeChatRecipientId}`) || 0);
        const mensajesFiltrados = res.mensajes.filter(m => m.id > clearId);
        
        const oldScrollHeight = chatMsgs.scrollHeight;
        const oldScrollTop = chatMsgs.scrollTop;
        const oldClientHeight = chatMsgs.clientHeight;
        const wasAtBottom = (oldScrollHeight - oldScrollTop <= oldClientHeight + 50);

        let html = '';
        
        if (clearId > 0 && res.mensajes.some(m => m.id <= clearId)) {
          html += `
            <div style="text-align: center; margin-bottom: 10px; border-bottom: 1px dashed rgba(255,255,255,0.15); padding-bottom: 10px; flex-shrink: 0;">
              <span style="color: #f59e0b; font-size: 11px; cursor: pointer; text-decoration: underline;" onclick="MQF.restaurarHistorialChat()">
                📂 Mostrar historial de mensajes anteriores
              </span>
            </div>
          `;
        }
        
        if (mensajesFiltrados.length === 0) {
          const recipient = conversationsList.find(c => c.id == activeChatRecipientId);
          if (recipient && parseInt(recipient.es_bot || 0) === 1) {
            if (recipient.bot_code === 'BHN') {
              html += `
                <div class="chat-msg-row">
                  <div class="chat-msg-bubble">
                    🤖 <strong>BHN-Bot-HelpNow (Asistente de Soporte)</strong>: ¡Hola! Soy tu asistente de soporte técnico. Cuéntame qué inconveniente presentas y con gusto te asisto. 🛠️
                  </div>
                </div>
              `;
            } else {
              html += `
                <div class="chat-msg-row">
                  <div class="chat-msg-bubble">
                    🤖 <strong>BBS-BOT-BUSINES-SERVICE (SSINDI)</strong>: ¡Hola! Soy tu asistente de seguros. Puedo ayudarte a realizar cotizaciones de Seguro de Ley, emitir pólizas, consultar estados de cuentas y realizar renovaciones. Escribe <strong>bbs</strong> para ver los comandos estructurados o hazme tu consulta directamente. 📈
                  </div>
                </div>
              `;
            }
          } else {
            const rName = recipient ? `${recipient.nombre} ${recipient.apellido}` : 'Supervisor';
            html += `
              <div class="chat-msg-row" style="justify-content:center;">
                <div style="font-size:11.5px; color:rgba(255,255,255,0.4); background:rgba(0,0,0,0.25); padding:4px 12px; border-radius:15px; letter-spacing:0.5px;">
                  Inicia tu conversación con ${rName}
                </div>
              </div>
            `;
          }
        } else {
          mensajesFiltrados.forEach(m => {
            const rowClass = m.yo ? 'yo' : '';
            let contentHtml = m.mensaje;

            if (m.archivo_nombre) {
              const ext = m.archivo_nombre.split('.').pop().toLowerCase();
              const isImage = ['png', 'jpg', 'jpeg'].includes(ext);
              const downloadUrl = `/PLATAFORMA_INTEGRADA/backend/api/chat.php?action=descargar_archivo&id=${m.id}`;
              
              if (isImage) {
                contentHtml = `
                  <div style="display:flex; flex-direction:column; gap:6px;">
                    <img src="${downloadUrl}" class="chat-msg-img-preview" alt="${m.archivo_nombre}" onclick="window.open('${downloadUrl}', '_blank')">
                    <span style="font-size:10px; opacity:0.8; display:flex; align-items:center; justify-content:space-between; gap:10px; padding:0 2px;">
                       <span style="text-overflow:ellipsis; overflow:hidden; white-space:nowrap; max-width:140px;">${m.archivo_nombre}</span>
                       <a href="${downloadUrl}" download style="color:inherit; text-decoration:underline; font-weight:bold;">Descargar</a>
                    </span>
                  </div>
                `;
              } else {
                let fileIcon = '📄';
                if (['xls', 'xlsx', 'csv'].includes(ext)) fileIcon = '📊';
                if (['doc', 'docx'].includes(ext)) fileIcon = '📝';
                if (['ppt', 'pptx'].includes(ext)) fileIcon = '📁';
                if (ext === 'pdf') fileIcon = '📕';
                if (ext === 'xml') fileIcon = '🌐';
                if (ext === 'json') fileIcon = '⚙️';
                
                contentHtml = `
                  <div style="display:flex; flex-direction:column; gap:4px;">
                    <span style="font-size:13px;">${m.mensaje}</span>
                    <div class="chat-msg-file-badge" style="display:flex; align-items:center; gap:8px; padding:6px 10px; background:rgba(255,255,255,0.08); border-radius:8px; border:1px solid rgba(255,255,255,0.15); margin-top:4px;">
                       <span style="font-size:20px;">${fileIcon}</span>
                       <div style="display:flex; flex-direction:column; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; min-width:0; text-align:left;">
                         <span style="font-weight:600; font-size:12px; color:white; overflow:hidden; text-overflow:ellipsis;">${m.archivo_nombre}</span>
                         <span style="font-size:10px; opacity:0.7;">${m.archivo_size ? (m.archivo_size / 1024).toFixed(1) + ' KB' : 'N/D'}</span>
                       </div>
                       <a href="${downloadUrl}" download style="color:#f59e0b; font-size:16px; text-decoration:none;" title="Descargar archivo">⬇️</a>
                    </div>
                  </div>
                `;
              }
            }

            html += `
              <div class="chat-msg-row ${rowClass}" data-msg-id="${m.id}">
                <div class="chat-msg-bubble">
                  ${contentHtml}
                </div>
              </div>
            `;
          });
        }
        
        chatMsgs.innerHTML = html;

        if (wasAtBottom || oldScrollTop === 0) {
          chatMsgs.scrollTop = chatMsgs.scrollHeight;
        }
      }
    } catch (e) {
      console.warn('Error reloading chat messages', e);
    }
  }

  async function enviarMensajeChatCSR() {
    const input = document.getElementById('chat-csr-input');
    const fileInput = document.getElementById('chat-file-input');
    const previewArea = document.getElementById('chat-csr-file-preview');
    const sendBtn = document.getElementById('chat-csr-send-btn');
    
    const mensaje = input.value.trim();
    const hasFile = fileInput && fileInput.files.length > 0;

    if (!mensaje && !hasFile) return;
    if (!activeChatRecipientId) return;

    const originalBtnHTML = sendBtn.innerHTML;
    sendBtn.innerHTML = `<span class="chat-loading-spinner"></span>`;
    sendBtn.disabled = true;

    const formData = new FormData();
    formData.append('receptor_id', activeChatRecipientId);
    if (mensaje) formData.append('mensaje', mensaje);
    if (hasFile) formData.append('archivo', fileInput.files[0]);

    input.value = '';
    if (fileInput) fileInput.value = '';
    if (previewArea) previewArea.style.display = 'none';

    try {
      const token = localStorage.getItem('token_sesion') || '';
      const response = await fetch('/PLATAFORMA_INTEGRADA/backend/api/chat.php', {
        method: 'POST',
        headers: {
          'Authorization': 'Bearer ' + token
        },
        body: formData
      });
      
      sendBtn.innerHTML = originalBtnHTML;
      sendBtn.disabled = false;

      let res;
      const text = await response.text();
      const contentType = response.headers.get("content-type");
      if (contentType && contentType.includes("application/json")) {
        try {
          res = JSON.parse(text);
        } catch (jsonErr) {
          res = { exito: false, mensaje: 'Error al procesar JSON. Detalle: ' + text.substring(0, 100) };
        }
      } else {
        res = { exito: false, mensaje: 'Error del Servidor (' + response.status + '): ' + text.substring(0, 100) };
      }

      if (res.exito) {
        cargarMensajesChatCSR();
      } else {
        toast('Fallo al enviar mensaje: ' + res.mensaje, 'error');
      }
    } catch (err) {
      sendBtn.innerHTML = originalBtnHTML;
      sendBtn.disabled = false;
      console.error(err);
      toast('Error de red al enviar mensaje: ' + err.message, 'error');
    }
  }

  async function enviarEmailCotizacion(btn, cotId) {
    if (!cotId) return;
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.classList.add('sending');
    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Enviando...`;
    
    try {
      const token = localStorage.getItem('token_sesion') || '';
      const response = await fetch(`/PLATAFORMA_INTEGRADA/backend/api/chat.php?action=enviar_email_cotizacion&id=${cotId}`, {
        headers: { 'Authorization': 'Bearer ' + token }
      });
      const res = await response.json();
      btn.disabled = false;
      btn.classList.remove('sending');
      btn.innerHTML = originalHTML;
      
      if (res.exito) {
        toast(res.mensaje, 'success');
      } else {
        toast('Error al enviar correo: ' + res.mensaje, 'error');
      }
    } catch (err) {
      btn.disabled = false;
      btn.classList.remove('sending');
      btn.innerHTML = originalHTML;
      console.error(err);
      toast('Error de red al enviar cotización por correo', 'error');
    }
  }

  function init() {
    document.addEventListener('click', (e) => {
      const openBtn  = e.target.closest('[data-mqf-modal]');
      const closeBtn = e.target.closest('[data-mqf-close]');
      if (openBtn)  modalOpen(openBtn.dataset.mqfModal);
      if (closeBtn) modalClose(closeBtn.dataset.mqfClose);
    });

    try {
      _initChatCSR();
    } catch (err) {
      console.warn('Error inicializando Chat-CSR:', err);
    }
  }

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
    fmt: { money: fmtMoney, date: fmtDate, diasHasta },
    abrirChatCSR,
    cerrarChatCSR,
    enviarMensajeChatCSR,
    selectContact,
    enviarEmailCotizacion,
    restaurarHistorialChat: function() {
      localStorage.removeItem(`chat_clear_id_${activeChatRecipientId}`);
      cargarMensajesChatCSR();
      toast('Historial completo restaurado.', 'info');
    }
  };

})();

