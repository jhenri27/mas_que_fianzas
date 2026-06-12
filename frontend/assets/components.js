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
  let chatPollingInterval = null;
  let activeChatRecipientId = null;

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
      
      // Remove inline or prior event listener and attach our own
      const newNotifBtn = notifBtn.cloneNode(true);
      notifBtn.parentNode.replaceChild(newNotifBtn, notifBtn);
      newNotifBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        abrirChatCSR();
      });
    }

    // Inject CSS
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
        display: none; position: fixed; bottom: 90px; right: 20px; width: 380px; height: 500px;
        background: rgba(30, 41, 59, 0.9); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 16px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3); z-index: 10000;
        flex-direction: column; overflow: hidden; font-family: 'Segoe UI', sans-serif;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      }
      .chat-msg-row {
        display: flex; width: 100%; margin-bottom: 8px;
      }
      .chat-msg-row.yo {
        justify-content: flex-end;
      }
      .chat-msg-bubble {
        max-width: 75%; padding: 8px 12px; border-radius: 12px; line-height: 1.4; word-break: break-word;
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

    // Inject Chat Window
    const win = document.createElement('div');
    win.id = 'chat-csr-window';
    win.className = 'chat-csr-window';
    win.innerHTML = `
      <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 12px 15px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.1); flex-shrink:0;">
          <div style="display: flex; align-items: center; gap: 10px;">
              <div style="width: 10px; height: 10px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 8px #22c55e;"></div>
              <div style="display: flex; flex-direction: column;">
                  <strong style="color: white; font-size: 13.5px;">Chat-CSR de Soporte</strong>
                  <span id="chat-csr-recipient-name" style="color: #94a3b8; font-size: 11px;">Conectando con Supervisor...</span>
              </div>
          </div>
          <button id="chat-csr-close-btn" style="background: transparent; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; padding: 0 5px; line-height:1;">&times;</button>
      </div>
      <div id="chat-csr-messages" style="flex: 1; padding: 15px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; color: white; font-size: 13px;">
      </div>
      <div id="chat-csr-file-preview" style="display: none; align-items: center; justify-content: space-between; background: rgba(245, 158, 11, 0.15); border-top: 1px solid rgba(255,255,255,0.1); padding: 6px 15px; font-size: 12px; color: #f59e0b; flex-shrink:0;">
          <span id="chat-csr-file-name" style="text-overflow: ellipsis; overflow: hidden; white-space: nowrap; max-width: 300px;">📎 archivo.pdf</span>
          <button id="chat-csr-file-cancel" style="background: none; border: none; color: #ef4444; font-weight: bold; cursor: pointer; font-size: 16px; padding: 0 5px;">&times;</button>
      </div>
      <div style="padding: 10px 15px; border-top: 1px solid rgba(255,255,255,0.1); background: rgba(15, 23, 42, 0.5); display: flex; gap: 8px; align-items: center; flex-shrink:0;">
          <input type="file" id="chat-file-input" style="display: none;" accept=".xls,.xlsx,.csv,.xml,.json,.doc,.docx,.ppt,.pptx,.pdf,image/png,image/jpeg">
          <button id="chat-csr-clip-btn" style="background: transparent; border: none; color: #94a3b8; font-size: 18px; cursor: pointer; padding: 4px; display: flex; align-items: center; justify-content: center;" title="Adjuntar Archivo">📎</button>
          <input type="text" id="chat-csr-input" placeholder="Escribe un mensaje..." style="flex: 1; background: rgba(255,255,255,0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 20px; padding: 8px 15px; color: white; font-size: 13px; outline: none;">
          <button id="chat-csr-send-btn" style="background: #f59e0b; border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: white; font-size: 14px; box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);">&#10148;</button>
      </div>
    `;
    document.body.appendChild(win);

    win.querySelector('#chat-csr-close-btn').addEventListener('click', cerrarChatCSR);
    
    const clipBtn = win.querySelector('#chat-csr-clip-btn');
    const fileInput = win.querySelector('#chat-file-input');
    const previewArea = win.querySelector('#chat-csr-file-preview');
    const fileNameSpan = win.querySelector('#chat-csr-file-name');
    const fileCancelBtn = win.querySelector('#chat-csr-file-cancel');

    clipBtn.addEventListener('click', () => fileInput.click());

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

    // Initial check for unread messages (polling badge every 8 seconds)
    checkUnreadMessagesCount();
    setInterval(checkUnreadMessagesCount, 8000);
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
    
    // Load recipient and initial messages
    try {
      const token = localStorage.getItem('token_sesion') || '';
      const response = await fetch('/PLATAFORMA_INTEGRADA/backend/api/chat.php', {
        headers: { 'Authorization': 'Bearer ' + token }
      });
      const res = await response.json();
      
      if (res.exito) {
        const conversations = res.conversaciones || [];
        if (conversations.length > 0) {
          const recipient = conversations[0];
          activeChatRecipientId = recipient.id;
          document.getElementById('chat-csr-recipient-name').textContent = `${recipient.nombre} ${recipient.apellido} (${recipient.nombre_perfil})`;
          
          // Load messages history
          cargarMensajesChatCSR();
          
          // Start polling every 2.5 seconds
          if (chatPollingInterval) clearInterval(chatPollingInterval);
          chatPollingInterval = setInterval(cargarMensajesChatCSR, 2500);
        } else {
          document.getElementById('chat-csr-recipient-name').textContent = "Soporte General (Admin)";
          activeChatRecipientId = 1; // Admin fallback
          cargarMensajesChatCSR();
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
        const oldScrollHeight = chatMsgs.scrollHeight;
        const oldScrollTop = chatMsgs.scrollTop;
        const oldClientHeight = chatMsgs.clientHeight;
        const wasAtBottom = (oldScrollHeight - oldScrollTop <= oldClientHeight + 50);

        let html = '';
        
        // Add welcome BHN-Bot-HelpNow message if empty
        if (res.mensajes.length === 0) {
          html = `
            <div class="chat-msg-row">
              <div class="chat-msg-bubble">
                🤖 <strong>BHN-Bot-HelpNow</strong>: ¡Hola! Soy tu asistente de soporte. Puedes chatear conmigo o escribir un mensaje directo para comunicarte con tu Supervisor Responsable.
              </div>
            </div>
          `;
        } else {
          res.mensajes.forEach(m => {
            const rowClass = m.yo ? 'yo' : '';
            let contentHtml = m.mensaje;

            if (m.archivo_nombre) {
              const ext = m.archivo_nombre.split('.').pop().toLowerCase();
              const isImage = ['png', 'jpg', 'jpeg'].includes(ext);
              const downloadUrl = `/PLATAFORMA_INTEGRADA/backend/api/chat.php?action=descargar_archivo&id=${m.id}`;
              
              if (isImage) {
                // Previsualización de imagen estética comprimida
                contentHtml = `
                  <div style="display:flex; flex-direction:column; gap:6px;">
                    <img src="${downloadUrl}" class="chat-msg-img-preview" alt="${m.archivo_nombre}" style="max-width:100%; max-height:150px; border-radius:8px; cursor:pointer;" onclick="window.open('${downloadUrl}', '_blank')">
                    <span style="font-size:10px; opacity:0.8; display:flex; align-items:center; justify-content:space-between; gap:10px; padding:0 2px;">
                      <span style="text-overflow:ellipsis; overflow:hidden; white-space:nowrap; max-width:140px;">${m.archivo_nombre}</span>
                      <a href="${downloadUrl}" download style="color:inherit; text-decoration:underline; font-weight:bold;">Descargar</a>
                    </span>
                  </div>
                `;
              } else {
                // Badge del archivo con icono del tipo
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
              <div class="chat-msg-row ${rowClass}">
                <div class="chat-msg-bubble">
                  ${contentHtml}
                </div>
              </div>
            `;
          });
        }
        
        chatMsgs.innerHTML = html;

        // Auto-scroll to bottom only if user was already at the bottom or it is the first load
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

    // Show loading spinner on button
    const originalBtnHTML = sendBtn.innerHTML;
    sendBtn.innerHTML = `<span class="chat-loading-spinner"></span>`;
    sendBtn.disabled = true;

    // Form data
    const formData = new FormData();
    formData.append('receptor_id', activeChatRecipientId);
    if (mensaje) formData.append('mensaje', mensaje);
    if (hasFile) formData.append('archivo', fileInput.files[0]);

    // Clear inputs immediately to feel responsive
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
      
      // Restore send button
      sendBtn.innerHTML = originalBtnHTML;
      sendBtn.disabled = false;

      let res;
      const contentType = response.headers.get("content-type");
      if (contentType && contentType.includes("application/json")) {
        try {
          res = await response.json();
        } catch (jsonErr) {
          const text = await response.text();
          res = { exito: false, mensaje: 'Error al procesar JSON. Detalle: ' + text.substring(0, 100) };
        }
      } else {
        const text = await response.text();
        res = { exito: false, mensaje: 'Error del Servidor (' + response.status + '): ' + text.substring(0, 100) };
      }

      if (res.exito) {
        // Reload messages immediately
        cargarMensajesChatCSR();

        // 🤖 BHN-Bot-HelpNow: Simular flujo conversacional inteligente
        const txtUpper = mensaje.toUpperCase();
        let botReply = "";
        
        if (txtUpper.includes('TICKET') || txtUpper.includes('SOPORTE') || txtUpper.includes('PROBLEMA') || txtUpper.includes('ERROR')) {
          botReply = "🤖 <strong>BHN-Bot-HelpNow</strong>: Veo que tienes un problema o deseas crear un ticket de soporte. Puedes abrir el módulo de <strong>Helpdesk e Incidencias</strong> desde tu menú lateral para registrar el caso, y yo escanearé automáticamente los logs de tu sesión para resolverlo.";
        } else if (txtUpper.includes('AYUDA') || txtUpper.includes('BOT') || txtUpper.includes('HOLA')) {
          botReply = "🤖 <strong>BHN-Bot-HelpNow</strong>: Hola, estoy aquí para guiarte. Puedes consultarme sobre pólizas, pagos, o contactar a tu supervisor directo a través de este chat.";
        } else if (txtUpper.includes('SUPERVISOR') || txtUpper.includes('LLAMAR') || txtUpper.includes('TELEFONO')) {
          botReply = "🤖 <strong>BHN-Bot-HelpNow</strong>: Tu mensaje ha sido enviado a la bandeja de tu Supervisor. Si es urgente, también puedes usar el botón de llamadas o notificaciones para alertarlo.";
        }

        if (botReply) {
          // Guardar mensaje del bot
          setTimeout(() => {
            const chatMsgs = document.getElementById('chat-csr-messages');
            if (chatMsgs) {
              const row = document.createElement('div');
              row.className = 'chat-msg-row';
              row.innerHTML = `
                <div class="chat-msg-bubble">
                  ${botReply}
                </div>
              `;
              chatMsgs.appendChild(row);
              chatMsgs.scrollTop = chatMsgs.scrollHeight;
            }
          }, 800);
        }

      } else {
        toast('Fallo al enviar mensaje: ' + res.mensaje, 'error');
      }
    } catch (err) {
      // Restore send button
      sendBtn.innerHTML = originalBtnHTML;
      sendBtn.disabled = false;
      console.error(err);
      toast('Error de red al enviar mensaje: ' + err.message, 'error');
    }
  }

  function init() {
    document.addEventListener('click', (e) => {
      const openBtn  = e.target.closest('[data-mqf-modal]');
      const closeBtn = e.target.closest('[data-mqf-close]');
      if (openBtn)  modalOpen(openBtn.dataset.mqfModal);
      if (closeBtn) modalClose(closeBtn.dataset.mqfClose);
    });

    // Iniciar Chat CSR si aplica en la UI del Dashboard
    try {
      _initChatCSR();
    } catch (err) {
      console.warn('Error inicializando Chat-CSR:', err);
    }
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
    fmt: { money: fmtMoney, date: fmtDate, diasHasta },
    abrirChatCSR,
    cerrarChatCSR,
    enviarMensajeChatCSR
  };

})();

