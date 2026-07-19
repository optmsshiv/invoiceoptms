<?php
// ================================================================
//  includes/modals/invoice_preview.php
//  Global modal — backs openPreviewModal() in invoice-render-shared.js
//  (Phase 3). Loaded on every page for consistency with the other
//  global modals here; harmless (just unused HTML) on pages that
//  don't load invoice-render-shared.js.
// ================================================================
?>
<div class="modal-overlay" id="modal-preview">
  <div class="modal modal-xl" style="max-height:94vh;display:flex;flex-direction:column;">
    <div class="modal-header" style="flex-shrink:0"><span id="mp-title">Invoice Preview</span><button class="modal-close" onclick="closeModal('modal-preview')"><i class="fas fa-times"></i></button></div>
    <div class="modal-body" id="mp-body" style="padding:24px;overflow-y:auto;flex:1;min-height:0"></div>
    <div class="modal-footer" style="flex-shrink:0;border-top:1px solid var(--border);padding:14px 22px;display:flex;gap:10px;justify-content:flex-end;background:var(--card)">
      <button class="btn btn-primary" onclick="printFromModal()"><i class="fas fa-print"></i> Print / Save PDF</button>
      <button class="btn btn-whatsapp" onclick="sendWAFromModal()"><i class="fab fa-whatsapp"></i> WhatsApp</button>
      <button class="btn btn-email" onclick="sendEmailFromModal()"><i class="fas fa-envelope"></i> Email</button>
      <button class="btn btn-outline" onclick="closeModal('modal-preview')">Close</button>
    </div>
  </div>
</div>
