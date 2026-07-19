// ================================================================
//  assets/js/edit-approval-shared.js
//  Requires: common.js, shared-data.js (loaded before this file —
//  needs canDo(), toast(), api(), escHtml() from common.js).
//
//  Non-owner/admin users who try to edit a saved record are routed
//  through this workflow instead of opening the edit form directly.
//  Admin/owner sees pending requests via the dashboard's "Pending
//  Approvals" card (renderPendingApprovalsCard, called from
//  dashboard.js) and the topbar notification bell (renderNotifications,
//  in common.js). Approval unlocks the edit form for a short window.
//
//  Used by: Purchases, Sales, Suppliers, Customers, Products, Stock —
//  anywhere editWithApproval()/_editProductWithApproval() is called.
//  Load this file on any page that edits those entity types.
// ================================================================

// ═══════════════════════════════════════════════════════════════
//  EDIT APPROVAL WORKFLOW
//  Non-owner/admin users who try to edit a saved record are routed
//  through this system instead of opening the edit form directly.
//  Admin/owner sees pending requests in the dashboard bell and a
//  card; approval unlocks the edit form for 1 hour.
// ═══════════════════════════════════════════════════════════════

const EAR = {
  requestId: null,        // active request id
  entityType: null,
  entityId: null,
  entityLabel: null,
  editCallback: null,     // fn to call when approved
  pollTimer: null,
  approvedFor: null,      // { entityType, entityId } — tracks which record was unlocked
};

// Called after every successful save — consumes the single-use approval
// so the same approval can't be reused for another edit.
async function consumeEditApproval() {
  if (!EAR.requestId) return;
  const id = EAR.requestId;
  EAR.requestId = null;
  EAR.approvedFor = null;
  clearInterval(EAR.pollTimer);
  try {
    await api('api/edit_approvals.php?action=consume', 'POST', { id });
  } catch(e) { /* non-fatal — server also expires it on next poll */ }
}

// Check if the current user has an active approval for this specific entity
function hasActiveApproval(entityType, entityId) {
  return EAR.approvedFor &&
    EAR.approvedFor.entityType === entityType &&
    String(EAR.approvedFor.entityId) === String(entityId);
}

// ── Intercept: called instead of editX() when user lacks action.edit ──
// entityType: 'purchase'|'sale'|'supplier'|'customer'|'product'
// entityId, entityLabel: for display and deduplication
// editFn: the actual edit function to call when approved
async function requestEditApproval(entityType, entityId, entityLabel, editFn) {
  EAR.entityType  = entityType;
  EAR.entityId    = entityId;
  EAR.entityLabel = entityLabel;
  EAR.editCallback = editFn;
  EAR.requestId   = null;
  clearInterval(EAR.pollTimer);

  document.getElementById('ear-entity-label').textContent = entityLabel;
  document.getElementById('ear-reason').value = '';
  _earShowView('request');
  openModal('modal-edit-approval');

  // Check if there's already a live request for this entity
  try {
    const r = await api(`api/edit_approvals.php?action=check_entity&entity_type=${entityType}&entity_id=${entityId}`);
    if (r.data?.status === 'pending') { EAR.requestId = r.data.id; _earShowView('waiting'); _earStartPolling(); }
    else if (r.data?.status === 'approved') { EAR.requestId = r.data.id; _earShowApproved(r.data); }
  } catch(e) { /* no existing request, show request form */ }
}

async function submitEditRequest() {
  const reason = document.getElementById('ear-reason').value.trim();
  if (!reason) { toast('⚠️ Please describe why you need to edit this record', 'warning'); return; }
  try {
    const r = await api('api/edit_approvals.php?action=request', 'POST', {
      entity_type: EAR.entityType, entity_id: EAR.entityId,
      entity_label: EAR.entityLabel, reason
    });
    EAR.requestId = r.id;
    _earShowView('waiting');
    _earStartPolling();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

function _earStartPolling() {
  clearInterval(EAR.pollTimer);
  EAR.pollTimer = setInterval(async () => {
    if (!EAR.requestId) return;
    try {
      const r = await api(`api/edit_approvals.php?action=check&id=${EAR.requestId}`);
      const req = r.data;
      if (!req) return;
      if (req.status === 'approved') { clearInterval(EAR.pollTimer); _earShowApproved(req); }
      else if (req.status === 'rejected') { clearInterval(EAR.pollTimer); _earShowRejected(req); }
      else if (req.status === 'expired') {
        clearInterval(EAR.pollTimer);
        toast('⏰ Your edit request expired — please submit a new one', 'warning');
        _earShowView('request');
      }
    } catch(e) { /* network blip, keep polling */ }
  }, 10000); // poll every 10 seconds
}

function _earShowApproved(req) {
  _earShowView('approved');
  const byEl = document.getElementById('ear-approved-by');
  const noteEl = document.getElementById('ear-approved-note');
  if (byEl) byEl.textContent = 'Approved by ' + (req.reviewer_name || 'Admin');
  if (noteEl) noteEl.textContent = req.review_note?.replace(' [Used — edit saved]','') ? '"' + req.review_note + '"' : '';
  // Track which entity this approval covers
  EAR.approvedFor = { entityType: EAR.entityType, entityId: EAR.entityId };
  // Auto-dismiss and open edit after 2s
  setTimeout(() => {
    const modal = document.getElementById('modal-edit-approval');
    if (modal?.classList.contains('open')) proceedWithEdit();
  }, 2000);
}

function _earShowRejected(req) {
  _earShowView('rejected');
  const byEl = document.getElementById('ear-rejected-by');
  const noteEl = document.getElementById('ear-rejected-note');
  if (byEl) byEl.textContent = 'Declined by ' + (req.reviewer_name || 'Admin');
  if (noteEl) noteEl.textContent = req.review_note ? '"' + req.review_note + '"' : 'No reason given.';
}

function _earShowView(view) {
  ['request','waiting','approved','rejected'].forEach(v => {
    const el = document.getElementById(`ear-${v}-view`);
    if (el) el.style.display = v === view ? '' : 'none';
  });
}

function proceedWithEdit() {
  closeModal('modal-edit-approval');
  clearInterval(EAR.pollTimer);
  if (EAR.editCallback) EAR.editCallback();
}

function cancelEditRequest() {
  clearInterval(EAR.pollTimer);
  closeModal('modal-edit-approval');
}

// ── Admin dashboard: pending approvals card ────────────────────
let EAR_ADMIN_PENDING = [];

async function loadPendingApprovals() {
  if (!SERVER.canApproveEdits) return;
  try {
    const r = await api('api/edit_approvals.php?action=pending');
    const prev = EAR_ADMIN_PENDING.length;
    EAR_ADMIN_PENDING = r.data || [];

    // ── Topbar alert pill ─────────────────────────────────────────
    const pill = document.getElementById('ear-topbar-alert');
    const cnt  = document.getElementById('ear-topbar-count');
    const plrl = document.getElementById('ear-topbar-plural');
    if (pill) {
      if (EAR_ADMIN_PENDING.length > 0) {
        pill.style.display = 'inline-flex';
        if (cnt) cnt.textContent = EAR_ADMIN_PENDING.length;
        if (plrl) plrl.textContent = EAR_ADMIN_PENDING.length > 1 ? 's' : '';
      } else {
        pill.style.display = 'none';
      }
    }

    // ── Browser tab title badge ───────────────────────────────────
    const base = document.title.replace(/^\(\d+\)\s*/, '');
    document.title = EAR_ADMIN_PENDING.length > 0
      ? `(${EAR_ADMIN_PENDING.length}) ${base}` : base;

    // ── Toast alert when NEW requests arrive (not on first load) ─
    if (prev > 0 && EAR_ADMIN_PENDING.length > prev) {
      const newest = EAR_ADMIN_PENDING[0];
      toast(`🔔 ${newest.requester_name} is requesting permission to edit ${newest.entity_label || newest.entity_type}`, 'warning', 6000);
    }

    // ── Update bell count + notification panel ────────────────────
    renderNotifications();
    renderPendingApprovalsCard();
  } catch(e) { /* non-fatal — keep app working even if this fails */ }
}

function renderPendingApprovalsCard() {
  const cards = ['db-edit-approvals-card','db-edit-approvals-card-svc']
    .map(id => document.getElementById(id)).filter(Boolean);
  if (!cards.length) return;

  if (!EAR_ADMIN_PENDING.length) {
    cards.forEach(c => { c.style.display = 'none'; c.innerHTML = ''; });
    return;
  }

  const entityIcons = { purchase:'fa-cart-shopping', sale:'fa-file-invoice-dollar', supplier:'fa-truck', customer:'fa-user', product:'fa-box', stock_adjustment:'fa-sliders', stock_in:'fa-boxes-stacked' };

  const requestsHtml = EAR_ADMIN_PENDING.map(req => {
    const icon = entityIcons[req.entity_type] || 'fa-file';
    const age = Math.round((new Date() - new Date(req.created_at.replace(' ','T'))) / 60000);
    const ageStr = age < 60 ? age + 'm ago' : Math.round(age/60) + 'h ago';
    return `<div style="background:var(--bg);border-radius:10px;padding:12px 14px;margin-bottom:10px">
      <div style="display:flex;align-items:flex-start;gap:10px">
        <span style="width:32px;height:32px;border-radius:8px;background:var(--teal-bg);color:var(--teal);display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0"><i class="fas ${icon}"></i></span>
        <div style="flex:1;min-width:0">
          <div style="font-size:13px;font-weight:600">${escHtml(req.requester_name)} wants to edit <span style="color:var(--teal)">${escHtml(req.entity_label || req.entity_type + ' #' + req.entity_id)}</span></div>
          ${req.reason ? '<div style="font-size:12px;color:var(--muted);margin-top:3px;font-style:italic">"' + escHtml(req.reason) + '"</div>' : ''}
          <div style="font-size:11px;color:var(--muted);margin-top:4px"><i class="fas fa-clock"></i> ${ageStr}</div>
        </div>
      </div>
      <div style="display:flex;gap:8px;margin-top:10px">
        <button class="btn btn-primary" style="flex:1;font-size:12px" onclick="approveEditRequest(${req.id})"><i class="fas fa-check"></i> Approve</button>
        <button class="btn btn-outline" style="flex:1;font-size:12px;color:var(--red);border-color:var(--red)" onclick="rejectEditRequest(${req.id})"><i class="fas fa-times"></i> Reject</button>
      </div>
    </div>`;
  }).join('');

  const html = `<div class="pne-card" style="border:2px solid var(--amber);margin-bottom:16px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
      <span style="width:36px;height:36px;border-radius:10px;background:var(--amber-bg);color:var(--amber);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0"><i class="fas fa-shield-halved"></i></span>
      <div>
        <div style="font-size:14px;font-weight:700">Edit Approval Requests <span style="background:var(--amber);color:#fff;font-size:11px;padding:2px 8px;border-radius:10px;margin-left:6px">${EAR_ADMIN_PENDING.length}</span></div>
        <div style="font-size:11.5px;color:var(--muted)">Team members waiting for your approval to edit records</div>
      </div>
    </div>
    ${requestsHtml}
  </div>`;

  cards.forEach(c => { c.style.display = ''; c.innerHTML = html; });
}


async function approveEditRequest(id) {
  const { value: note } = await Swal.fire({
    title: 'Approve edit request?',
    input: 'text', inputPlaceholder: 'Optional note to the requester…',
    showCancelButton: true, confirmButtonText: 'Approve',
    confirmButtonColor: 'var(--teal)', customClass: { popup: 'swal-compact' }
  });
  if (note === undefined) return; // cancelled
  try {
    await api('api/edit_approvals.php?action=approve', 'POST', { id, note: note||'' });
    EAR_ADMIN_PENDING = EAR_ADMIN_PENDING.filter(r => r.id !== id);
    toast('✅ Edit request approved — the user can now proceed', 'success');
    renderPendingApprovalsCard();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

async function rejectEditRequest(id) {
  const { value: note } = await Swal.fire({
    title: 'Reject edit request?',
    input: 'text', inputPlaceholder: 'Reason for rejection (shown to user)…',
    showCancelButton: true, confirmButtonText: 'Reject',
    confirmButtonColor: '#E53935', customClass: { popup: 'swal-compact' }
  });
  if (note === undefined) return;
  try {
    await api('api/edit_approvals.php?action=reject', 'POST', { id, note: note||'' });
    EAR_ADMIN_PENDING = EAR_ADMIN_PENDING.filter(r => r.id !== id);
    toast('Request rejected', 'info');
    renderPendingApprovalsCard();
  } catch(e) { toast('❌ ' + e.message, 'error'); }
}

// ── editWithApproval: gate function for all edit buttons ────────
// If user has action.edit permission → call editFn directly.
// Otherwise → route through the approval workflow.
function editWithApproval(entityType, entityId, entityLabel, editFn) {
  if (canDo('edit') || hasActiveApproval(entityType, entityId)) {
    editFn();
  } else {
    requestEditApproval(entityType, entityId, entityLabel, editFn);
  }
}

// ── Modal edit helper ───────────────────────────────────────────
// Used by detail-view modal footers (Customer Profile, Sale Details etc)
// where inline string escaping in onclick attributes is messy.
// Looks up the entity label from STATE at click time.
function _modalEdit(entityType, entityId, editFn) {
  let label = entityType + ' #' + entityId;
  const id = String(entityId);
  if (entityType === 'customer') {
    const c = (STATE.customers||[]).find(x => String(x.id) === id);
    if (c) label = c.name || label;
  } else if (entityType === 'sale') {
    const s = (STATE.sales||[]).find(x => String(x.id) === id);
    if (s) label = 'Invoice ' + (s.invoice_no || label);
  } else if (entityType === 'purchase') {
    const p = (STATE.purchases||[]).find(x => String(x.id) === id);
    if (p) label = 'Purchase ' + (p.purchase_no || label);
  } else if (entityType === 'supplier') {
    const s = (typeof splAllSuppliers === 'function' ? splAllSuppliers() : (STATE.suppliers || [])).find(x => String(x.id) === id);
    if (s) label = s.name || label;
  }
  editWithApproval(entityType, entityId, label, editFn);
}

function _editProductWithApproval(productId, editFn) {
  const p = (STATE.products || []).find(x => String(x.id) === String(productId));
  // Strip "p" prefix — API entity_id is integer, (int)"p12" = 0 → "Invalid entity"
  const numericId = String(productId).replace(/\D/g, '');
  editWithApproval('product', numericId, (p?.name || 'Product #' + numericId), editFn);
}
