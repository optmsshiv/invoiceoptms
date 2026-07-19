<?php
// ================================================================
//  pages/invoices/create.php
//  New / Edit Invoice — full page. ?id=123 edits that invoice; no
//  ?id= starts a blank one, matching the sales module's pattern.
// ================================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
requirePermission('menu.create');

$user = currentUser();

$activePage  = 'create';
$pageTitle   = 'New Invoice';
$pageScripts = [
    '/assets/js/shared-data.js',
    '/assets/js/wa-shared.js',
    '/assets/js/invoice-render-shared.js',
    '/assets/js/invoice-paid-shared.js',
    '/assets/js/clients.js',
    '/assets/js/create.js',
];

include __DIR__ . '/../../includes/layout_header.php';
?>
    <div id="page-create" class="page active">
      <div class="create-layout">
        <!-- FORM SIDE -->
        <div class="create-form">

          <!-- Invoice Meta -->
          <div class="form-section">
            <div class="fs-title"><i class="fas fa-hashtag"></i> Invoice Details</div>
            <div class="form-grid g2">
              <div class="field"><label>Invoice #</label><input id="f-num" value="" placeholder="Auto-generated" oninput="livePreview()"></div>
              <div class="field"><label>Service Type</label>
                <select id="f-service" onchange="onServiceSelect(this.value);livePreview()" style="margin-bottom:5px">
                  <option value="">-- Select from your services --</option>
                </select>
                <input id="f-service-custom" placeholder="Or type a custom service description…" oninput="syncServiceText(this.value);livePreview()" style="font-size:12.5px">
              </div>
              <div class="field"><label>Issue Date</label><input type="date" id="f-date" oninput="updateDueFromIssue();livePreview()"></div>
              <div class="field"><label>Due Date</label><input type="date" id="f-due" oninput="livePreview()"></div>
              <div class="field"><label>Currency</label>
                <select id="f-currency" onchange="livePreview()">
                  <option value="₹">INR (₹)</option><option value="$">USD ($)</option><option value="€">EUR (€)</option>
                </select>
              </div>
              <div class="field"><label>PDF Template</label>
                <select id="f-template" onchange="syncThemePicker();livePreview()">
                  <option value="2">Colorful Matte</option>
                  <option value="A">Clean Minimal</option>
                  <option value="B">Corporate Split</option>
                  <option value="E">Dark Header</option>
                  <option value="F">Formal Letterhead</option>
                </select>
              </div>
            </div>
            <div style="margin-top:14px">
              <label style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:8px">Payment Status</label>
              <div class="status-toggle-row">
                <label class="status-radio"><input type="radio" name="inv-status" value="Draft" checked onchange="livePreview()"><span class="sr-pill draft">Draft</span></label>
                <label class="status-radio"><input type="radio" name="inv-status" value="Estimate" onchange="onStatusChange('Estimate');livePreview()"><span class="sr-pill estimate">📋 Estimate</span></label>
                <label class="status-radio"><input type="radio" name="inv-status" value="Pending" onchange="onStatusChange('Pending');livePreview()"><span class="sr-pill pending">Pending</span></label>
                <label class="status-radio"><input type="radio" name="inv-status" value="Paid" onchange="livePreview()"><span class="sr-pill paid">Paid</span></label>
                <label class="status-radio"><input type="radio" name="inv-status" value="Overdue" onchange="livePreview()"><span class="sr-pill overdue">Overdue</span></label>
              </div>
            </div>
          </div>

          <!-- Client -->
          <div class="form-section">
            <div class="fs-title"><i class="fas fa-user"></i> Client Information</div>
            <div class="form-grid g1" style="margin-bottom:12px">
              <div class="field" style="position:relative">
                <label>Quick Select Client</label>
                <div style="display:flex;gap:8px;align-items:center">
                  <select id="f-client-select" onchange="fillClientForm(this.value)" style="flex:1">
                    <option value="">-- Quick Select Client --</option>
                    <option value="__onetime__" style="color:#E65100;font-weight:600">👤 One-Time / Walk-in Client (not saved)</option>
                  </select>
                  <span id="onetime-badge" style="display:none;background:#FBE9E7;border:1.5px solid #E65100;color:#E65100;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;white-space:nowrap;flex-shrink:0"><i class="fas fa-user-clock"></i> One-Time</span>
                </div>
              </div>
            </div>
            <div id="onetime-notice" style="display:none;background:#FFF3E0;border:1.5px solid #FFB300;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12.5px;color:#795548;display:none">
              <i class="fas fa-info-circle" style="color:#F9A825;margin-right:6px"></i>
              <strong>One-Time Client</strong> — details below are for this invoice only and will <strong>not</strong> be saved to your client list.
              <span onclick="switchToSaveClient()" style="margin-left:8px;color:#1976D2;cursor:pointer;font-weight:600;text-decoration:underline">Save this client instead →</span>
            </div>
            <div class="form-grid g2">
              <div class="field g-full"><label>Organization / Client Name *</label><input id="f-cname" placeholder="Organization / Client Name" oninput="livePreview()"></div>
              <div class="field"><label>Contact Person</label><input id="f-cperson" placeholder="Full Name" oninput="livePreview()"></div>
              <div class="field"><label>WhatsApp Number</label><input id="f-cwa" placeholder="+91 9876543210" oninput="livePreview()"></div>
              <div class="field"><label>Email Address</label><input id="f-cemail" type="email" placeholder="client@domain.com" oninput="livePreview()"></div>
              <div class="field"><label>GST Number</label><input id="f-cgst" placeholder="22AAAAA0000A1Z5" oninput="livePreview()"></div>
              <div class="field g-full"><label>Billing Address</label><textarea id="f-caddr" placeholder="Full address with city, state, PIN" oninput="livePreview()"></textarea></div>
            </div>
          </div>

          <!-- Items -->
          <div class="form-section">
            <div class="fs-title"><i class="fas fa-list-ul"></i> Line Items</div>
            <div class="items-head-row">
              <span>Description</span>
              <span>Type</span>
              <span style="text-align:center">Qty</span>
              <span style="text-align:right">Rate</span>
              <span style="text-align:right">Amount</span>
              <span style="text-align:center">GST%</span>
              <span style="text-align:right">Total</span>
              <span></span>
            </div>
            <div id="itemsList"></div>
            <div class="items-actions">
              <button class="add-line-btn" onclick="addItem()"><i class="fas fa-plus"></i> Add Line Item</button>
              <button class="add-line-btn" style="border-color:#1976D2;color:#1976D2" onclick="openProductPicker()"><i class="fas fa-box"></i> Pick from Services</button>
            </div>

            <!-- Totals -->
            <div class="totals-panel">
              <div class="tp-row">
                <span>Subtotal</span>
                <code id="tp-sub">₹0.00</code>
              </div>
              <div class="tp-row">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">

  <label style="font-size:13px;font-weight:600;color:var(--text);white-space:nowrap">
    Discount
  </label>
            <!-- Input -->
             <input type="number" id="f-disc" value="0" min="0"
               class="inline-num"
               oninput="calcTotals()"
               style="width:100px;padding:6px 8px;
               border:1px solid var(--border);
               border-radius:6px;
               background:var(--card);
               color:var(--text);">
           
             <!-- Type Selector -->
             <select id="f-disc-type"
               onchange="calcTotals()"
               style="width:70px;padding:6px 6px;
               border:1px solid var(--border);
               border-radius:6px;
               background:var(--card);
               color:var(--text);
               cursor:pointer;">
           
               <option value="pct">%</option>
               <option value="fixed">₹</option>
             </select>
           
           </div>
                <code class="neg" id="tp-disc">-₹0.00</code>
              </div>
              <div class="tp-row">
                <span style="font-weight:700">Amount</span>
                <code id="tp-amount" style="font-weight:700">₹0.00</code>
              </div>
              <div class="tp-row">
                <span style="display:flex;flex-direction:column;gap:2px">
                  <span style="font-size:11px;color:var(--muted);font-weight:600">Total GST</span>
                  <span id="tp-gst-breakdown" style="font-size:10px;color:var(--muted)"></span>
                </span>
                <code class="pos" id="tp-gst">+₹0.00</code>
              </div>
              <div class="tp-row grand">
                <span>Grand Total</span>
                <code id="tp-grand">₹0.00</code>
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div class="form-section">
            <div class="fs-title"><i class="fas fa-sticky-note"></i> Notes & Payment Info</div>
            <div class="form-grid g2">
              <div class="field g-full"><label>Notes to Client</label><textarea id="f-notes" oninput="livePreview(); debounceSaveInvoiceDraft()"><?= htmlspecialchars($settings['default_notes'] ?? '') ?></textarea></div>
              <div class="field g-full"><label>Bank Account Details</label><textarea id="f-bank" oninput="livePreview(); debounceSaveInvoiceDraft()"style="min-height:90px" placeholder="Enter bank account details..."></textarea></div>
              <div class="field g-full"><label>Terms & Conditions</label><textarea id="f-tnc" oninput="livePreview(); debounceSaveInvoiceDraft()" style="min-height:90px" placeholder="Enter terms and conditions..."></textarea></div>
              <div class="field g-full">
                <label>Invoice Generated By <span style="font-size:10px;color:var(--muted)">(shown at bottom of invoice)</span></label>
                <div style="display:flex;gap:8px;align-items:center">
                  <input id="f-generated-by" placeholder="e.g. <?= htmlspecialchars($companyName) ?> Invoice Manager" oninput="livePreview()" value="<?= htmlspecialchars($settings['generated_by'] ?? $companyName . ' Invoice Manager') ?>" style="flex:1">
                  <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;white-space:nowrap">
                    <input type="checkbox" id="f-show-generated" checked onchange="livePreview()" style="accent-color:var(--teal)"> Show in PDF
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Logo Options -->
          <div class="form-section">
            <div class="fs-title"><i class="fas fa-image"></i> Logo & Branding</div>
            <div class="form-grid g2">

              <!-- Company Logo -->
              <div class="field">
                <label>Company Logo</label>
                <div style="display:flex;gap:6px;align-items:stretch">
                  <input id="f-company-logo" placeholder="https://… or upload →" oninput="livePreview()" style="flex:1;min-width:0">
                  <label style="display:flex;align-items:center;gap:5px;padding:0 12px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);cursor:pointer;font-size:12px;font-weight:600;color:var(--muted);white-space:nowrap;transition:.2s" onmouseover="this.style.borderColor='var(--teal)';this.style.color='var(--teal)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
                    <i class="fas fa-upload"></i> Upload
                    <input type="file" accept="image/*" style="display:none" onchange="handleLogoUpload(this,'f-company-logo','f-logo-preview')">
                  </label>
                </div>
                <div id="f-logo-preview" style="margin-top:6px;min-height:0"></div>
              </div>

              <!-- Client Logo -->
              <div class="field">
                <label>Client Logo <span style="font-size:10px;color:var(--muted)">(optional)</span></label>
                <div style="display:flex;gap:6px;align-items:stretch">
                  <input id="f-client-logo" placeholder="https://… or upload →" oninput="livePreview()" style="flex:1;min-width:0">
                  <label style="display:flex;align-items:center;gap:5px;padding:0 12px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);cursor:pointer;font-size:12px;font-weight:600;color:var(--muted);white-space:nowrap;transition:.2s" onmouseover="this.style.borderColor='var(--teal)';this.style.color='var(--teal)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
                    <i class="fas fa-upload"></i> Upload
                    <input type="file" accept="image/*" style="display:none" onchange="handleLogoUpload(this,'f-client-logo','f-client-logo-preview')">
                  </label>
                </div>
                <div id="f-client-logo-preview" style="margin-top:6px;min-height:0"></div>
              </div>

              <!-- Signature -->
              <div class="field">
                <label>Authorised Signature</label>
                <div style="display:flex;gap:6px;align-items:stretch">
                  <input id="f-signature" placeholder="https://… or upload →" oninput="livePreview()" style="flex:1;min-width:0">
                  <label style="display:flex;align-items:center;gap:5px;padding:0 12px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);cursor:pointer;font-size:12px;font-weight:600;color:var(--muted);white-space:nowrap;transition:.2s" onmouseover="this.style.borderColor='var(--teal)';this.style.color='var(--teal)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
                    <i class="fas fa-pen-nib"></i> Upload Signature
                    <input type="file" accept="image/*" style="display:none" onchange="handleLogoUpload(this,'f-signature','f-sign-preview')">
                  </label>
                </div>
                <div id="f-sign-preview" style="margin-top:6px;min-height:0"></div>
                <div style="margin-top:6px;font-size:11px;color:var(--muted)">Upload a transparent PNG of your signature for PDF invoices</div>
              </div>

              <!-- QR Code -->
              <div class="field">
                <label>Payment QR Code <span style="font-size:10px;color:var(--muted)">(optional)</span></label>
                <div style="display:flex;gap:6px;align-items:stretch">
                  <input id="f-qr" placeholder="https://… or upload →" oninput="livePreview()" style="flex:1;min-width:0">
                  <label style="display:flex;align-items:center;gap:5px;padding:0 12px;border-radius:8px;border:1.5px solid var(--border);background:var(--bg);cursor:pointer;font-size:12px;font-weight:600;color:var(--muted);white-space:nowrap;transition:.2s" onmouseover="this.style.borderColor='var(--teal)';this.style.color='var(--teal)'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--muted)'">
                    <i class="fas fa-qrcode"></i> Upload QR
                    <input type="file" accept="image/*" style="display:none" onchange="handleLogoUpload(this,'f-qr','f-qr-preview')">
                  </label>
                </div>
                <div id="f-qr-preview" style="margin-top:6px;min-height:0"></div>
              </div>

            </div>
          </div>

          <!-- PDF Visibility Options -->
          <div class="form-section">
            <div class="fs-title"><i class="fas fa-eye"></i> PDF Show / Hide Options</div>
            <div class="pdf-opts-grid">
              <label class="pdf-opt"><input type="checkbox" id="popt-bank" checked onchange="savePoptPrefs();livePreview()"><span>Bank Details</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-qr" checked onchange="savePoptPrefs();livePreview()"><span>QR Code</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-sign" checked onchange="savePoptPrefs();livePreview()"><span>Signature</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-logo" checked onchange="savePoptPrefs();livePreview()"><span>Company Logo</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-client-logo" onchange="savePoptPrefs();livePreview()"><span>Client Logo</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-notes" checked onchange="savePoptPrefs();livePreview()"><span>Notes</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-tnc" checked onchange="savePoptPrefs();livePreview()"><span>Terms & Conditions</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-gst-col" checked onchange="savePoptPrefs();livePreview()"><span>GST Column</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-footer" checked onchange="savePoptPrefs();livePreview()"><span>Footer Bar</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-watermark" onchange="savePoptPrefs();livePreview()"><span>Paid Watermark</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-payment-block" checked onchange="savePoptPrefs();livePreview()"><span>Payment Details</span></label>
              <label class="pdf-opt"><input type="checkbox" id="popt-previous-due" onchange="savePoptPrefs();livePreview()"><span>Previous Due</span></label>
            </div>
          </div>

        </div>

        <!-- PREVIEW SIDE -->
        <div class="create-preview">
          <div class="preview-toolbar">
            <span class="preview-label"><i class="fas fa-eye"></i> Live Preview</span>
            <div style="display:flex;gap:8px">
              <select id="prevTplSelect" class="mini-select" onchange="document.getElementById('f-template').value=this.value;syncThemePicker();livePreview()">
                <option value="2">Colorful Matte</option>
                <option value="A">Clean Minimal</option>
                <option value="B">Corporate Split</option>
                <option value="E">Dark Header</option>
                  <option value="F">Formal Letterhead</option>
              </select>
              <button class="mini-btn" onclick="livePreview()"><i class="fas fa-sync"></i></button>
            </div>
          </div>
          <div class="preview-scroll">
            <div class="preview-scroll-inner">
              <div id="invoicePreviewWrap"></div>
            </div>
          </div>
          <div class="preview-actions">
            <button class="btn btn-success w100" onclick="saveInvoice()"><i class="fas fa-save"></i> Save Invoice</button>
            <button class="btn btn-outline w100" onclick="cancelInvoiceForm()" style="margin-top:6px"><i class="fas fa-times"></i> Cancel</button>
            <div class="btn-row-2">
              <button class="btn btn-primary" onclick="printCurrentInvoice()"><i class="fas fa-print"></i> Print / PDF</button>
              <button class="btn btn-whatsapp" onclick="sendWAFromForm()"><i class="fab fa-whatsapp"></i> WhatsApp</button>
            </div>
            <div class="btn-row-2">
              <button class="btn btn-email" onclick="sendEmailFromForm()"><i class="fas fa-envelope"></i> Email</button>
              <button class="btn btn-outline" onclick="markFormPaid()"><i class="fas fa-check"></i> Mark Paid</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ─────────── CLIENTS ─────────── -->
<?php include __DIR__ . '/../../includes/layout_footer.php'; ?>
