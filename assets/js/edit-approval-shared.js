// ============================================================
// edit-approval-shared.js — shared "Edit Approval Request" (EAR) system
// Used by any page where non-owner roles need approval before editing
// a locked record (sales, customers, and likely invoices later).
// Backed by api/edit_approvals.php.
// ============================================================
const EAR = {
  requestId: null,        // active request id
  entityType: null,
  entityId: null,
  entityLabel: null,
  editCallback: null,     // fn to call when approved
  pollTimer: null,
  approvedFor: null,      // { entityType, entityId } — tracks which record was unlocked
};
let EAR_ADMIN_PENDING = [];

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

function hasActiveApproval(entityType, entityId) {
  return EAR.approvedFor &&
    EAR.approvedFor.entityType === entityType &&
    String(EAR.approvedFor.entityId) === String(entityId);
}

function editWithApproval(entityType, entityId, entityLabel, editFn) {
  if (canDo('edit') || hasActiveApproval(entityType, entityId)) {
    editFn();
  } else {
    requestEditApproval(entityType, entityId, entityLabel, editFn);
  }
}

function proceedWithEdit() {
  closeModal('modal-edit-approval');
  clearInterval(EAR.pollTimer);
  if (EAR.editCallback) EAR.editCallback();
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

function _earShowView(view) {
  ['request','waiting','approved','rejected'].forEach(v => {
    const el = document.getElementById(`ear-${v}-view`);
    if (el) el.style.display = v === view ? '' : 'none';
  });
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

function _earShowRejected(req) {
  _earShowView('rejected');
  const byEl = document.getElementById('ear-rejected-by');
  const noteEl = document.getElementById('ear-rejected-note');
  if (byEl) byEl.textContent = 'Declined by ' + (req.reviewer_name || 'Admin');
  if (noteEl) noteEl.textContent = req.review_note ? '"' + req.review_note + '"' : 'No reason given.';
}

async function consumeEditApproval() {
  if (!EAR.requestId) return;
  const id = EAR.requestId;
  EAR.requestId = null;
  EAR.approvedFor = null;
  clearInterval(EAR.pollTimer);
  try {
    await api('/api/edit_approvals.php?action=consume', 'POST', { id });
  } catch(e) { /* non-fatal — server also expires it on next poll */ }
}

function canDo(action) {
  if (action === 'delete')  return SERVER.canDelete  === true;
  if (action === 'archive') return SERVER.canArchive === true;
  if (action === 'edit')    return SERVER.canEdit    === true;
  if (action === 'create')  return SERVER.canCreate  === true;
  return true;
}

function assertCanDelete(entityName = 'this record') {
  if (!canDo('delete')) {
    Swal.fire({ title: 'Permission Denied', html: `You don't have permission to delete ${entityName}.<br><small style="color:var(--muted)">Ask your Admin or Owner to grant delete access via Team Settings.</small>`, icon: 'error', confirmButtonColor: 'var(--teal)', customClass: { popup: 'swal-compact' } });
    return false;
  }
  return true;
}

