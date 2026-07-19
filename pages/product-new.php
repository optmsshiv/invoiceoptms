<?php
// ================================================================
//  pages/products.php
// ================================================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requirePermission('menu.products');
$user = currentUser();

$activePage = 'products';
$pageTitle  = 'New Product';
require_once __DIR__ . '/../includes/layout_header.php';
?>



      <div class="pne-topbar">
        <div>
          <div class="pne-title" id="pnp-title">New Product</div>
          <div class="pne-subtitle" id="pnp-subtitle">Add a product to your catalog</div>
        </div>
        <div class="pne-actions">
          <button class="btn btn-outline" id="pp-btn-cancel" onclick="cancelProductEntry()">Cancel</button>
          <button class="btn pne-btn-savenew" id="pp-btn-savenew" onclick="saveProductEntry('new')">Save &amp; New</button>
          <button class="btn pne-btn-save" id="pp-btn-save" onclick="saveProductEntry('stay')"><i class="fas fa-check"></i> Save Product</button>
        </div>
      </div>
      <div id="pp-loading-bar" style="display:none;height:3px;background:var(--border);border-radius:99px;overflow:hidden;margin:-6px 0 12px">
        <div style="height:100%;width:35%;background:#00897B;border-radius:99px;animation:pp-slide 1.1s ease-in-out infinite"></div>
      </div>

      <div class="pne-layout">
        <div class="pne-main">

          <!-- 1. Product Information -->
          <div class="pne-card">
            <div class="pne-card-head"><span class="pne-num"><i class="fas fa-box-open"></i></span> Product Information</div>
            <div class="pne-grid4">
              <div class="field"><label>Product Name *</label><input id="pp-name" placeholder="e.g. Makhana (Foxnut)"></div>
              <div class="field"><label>Product Code / SKU *</label><input id="pp-sku" placeholder="e.g. MKH-PREM-A01"></div>
              <div class="field"><label>Unit *</label>
                <select id="pp-unit" onchange="pnpSyncUnits()"><option>Kg</option><option>g</option><option>Ltr</option><option>ml</option><option>Pcs</option><option>Box</option><option>Dozen</option></select>
              </div>
              <div class="field"><label>Brand</label><input id="pp-brand" placeholder="e.g. AgriTrade"></div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>Category *</label>
                <select id="pp-category"></select>
              </div>
              <div class="field"><label>HSN Code</label><input id="pp-hsn" placeholder="e.g. 07134000" list="hsn-suggestions"></div>
              <div class="field"><label>Base Unit</label><input id="pp-baseunit" readonly></div>
              <div class="field"><label>Shelf Life (Months)</label><input type="number" id="pp-shelflife" min="0" placeholder="12"></div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>Variety</label>
                <select id="pp-variety" onchange="onPPVarietyChange()"><option value="">—</option><option>Premium</option><option>SBD</option><option>BD</option><option>CD</option><option>RBD</option></select>
              </div>
              <div class="field"><label>Barcode</label>
                <div style="display:flex;gap:6px">
                  <input id="pp-barcode" style="flex:1" placeholder="Scan or type">
                  <button type="button" class="btn btn-outline" style="padding:0 12px" title="Scan barcode" onclick="toast('📷 Barcode scanning needs a camera-enabled device — coming soon','info')"><i class="fas fa-barcode"></i></button>
                </div>
              </div>
              <div class="field"><label>Sale Unit</label><input id="pp-saleunit" readonly></div>
              <div class="field"><label>Storage Type</label>
                <select id="pp-storagetype"><option>Dry</option><option>Cold Storage</option><option>Frozen</option><option>Ambient</option></select>
              </div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>Grade</label>
                <select id="pp-grade" onchange="onPPGradeChange()"><option value="">—</option><option>Grade-1</option><option>Grade-2</option><option>Grade-3</option><option>Grade-4</option><option>Grade-5</option></select>
              </div>
              <div class="field"><label>QR Code</label>
                <div id="pp-qr-preview" class="pp-qr-box"><i class="fas fa-qrcode"></i></div>
              </div>
              <div class="field"><label>Purchase Unit</label><input id="pp-purchaseunit" readonly></div>
              <div class="field"><label>Min Order Qty</label><input type="number" id="pp-minorderqty" min="0" step="0.01" value="0"></div>
            </div>
          </div>

          <!-- 2. Product Specifications -->
          <div class="pne-card">
            <div class="pne-card-head pne-head-blue"><span class="pne-num"><i class="fas fa-flask"></i></span> Product Specifications</div>
            <div class="pne-grid4">
              <div class="field"><label>Moisture Limit (%) *</label><input type="number" id="pp-moisture" min="0" max="100" step="0.01" placeholder="12.00"></div>
              <div class="field"><label>Foreign Matter Limit (%)</label><input type="number" id="pp-foreignmatter" min="0" max="100" step="0.01" placeholder="2.00"></div>
              <div class="field"><label>Broken / Damage Limit (%)</label><input type="number" id="pp-brokendamage" min="0" max="100" step="0.01" placeholder="5.00"></div>
              <div class="field"><label>Oil Content (%)</label><input type="number" id="pp-oilcontent" min="0" max="100" step="0.01" placeholder="Enter oil content"></div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>Admixture Limit (%)</label><input type="number" id="pp-admixture" min="0" max="100" step="0.01" placeholder="0.50"></div>
              <div class="field"><label>Color</label><input id="pp-color" placeholder="e.g. White"></div>
              <div class="field"><label>Aroma</label><input id="pp-aroma" placeholder="e.g. Natural"></div>
              <div class="field"><label>Shape / Size</label><input id="pp-shapesize" placeholder="e.g. Medium"></div>
            </div>
            <div class="pne-grid4">
              <div class="field"><label>Packing Type</label>
                <select id="pp-packingtype"><option>PP Bag</option><option>Jute Bag</option><option>Carton</option><option>Pouch</option><option>Loose</option></select>
              </div>
              <div class="field"><label>Packing Size</label><input id="pp-packingsize" placeholder="e.g. 25 Kg"></div>
              <div></div><div></div>
            </div>
          </div>

          <!-- 3. Pricing & Tax Information -->
          <div class="pne-card">
            <div class="pne-card-head pne-head-green"><span class="pne-num"><i class="fas fa-tags"></i></span> Pricing &amp; Tax Information</div>
            <div class="pne-grid5">
              <div class="field"><label>Purchase Rate (₹/Kg)</label><input type="number" id="pp-purchaserate" min="0" step="0.01" value="0"></div>
              <div class="field"><label>Default Sale Rate (₹/Kg)</label><input type="number" id="pp-salerate" min="0" step="0.01" value="0"></div>
              <div class="field"><label>MRP (₹/Kg)</label><input type="number" id="pp-mrp" min="0" step="0.01" value="0"></div>
              <div class="field"><label>GST % *</label>
                <select id="pp-gst"><option value="0">0%</option><option value="5">5%</option><option value="12">12%</option><option value="18" selected>18%</option><option value="28">28%</option></select>
              </div>
              <div class="field"><label>Tax Type</label>
                <select id="pp-taxtype"><option>Intra-State (CGST+SGST)</option><option>Inter-State (IGST)</option></select>
              </div>
            </div>
          </div>

          <!-- 4. Inventory Information -->
          <div class="pne-card">
            <div class="pne-card-head pne-head-amber"><span class="pne-num"><i class="fas fa-warehouse"></i></span> Inventory Information</div>
            <div class="pne-grid4">
              <div class="field"><label>Opening Stock (Kg)</label><input type="number" id="pp-openingstock" min="0" step="0.01" value="0"></div>
              <div class="field"><label>Reorder Level (Kg)</label><input type="number" id="pp-reorderlevel" min="0" step="0.01" value="0"></div>
              <div class="field"><label>Maximum Stock (Kg)</label><input type="number" id="pp-maxstock" min="0" step="0.01" value="0"></div>
              <div class="field"><label>Default Warehouse</label><select id="pp-warehouse"><option>Main Warehouse</option></select></div>
            </div>
            <div style="display:flex;gap:28px;margin-top:4px">
              <div style="display:flex;align-items:center;gap:8px"><span style="font-size:12.5px;font-weight:600">Track Batch</span><div class="tog" id="pp-trackbatch" onclick="this.classList.toggle('on')"></div></div>
              <div style="display:flex;align-items:center;gap:8px"><span style="font-size:12.5px;font-weight:600">Track Serial No.</span><div class="tog" id="pp-trackserial" onclick="this.classList.toggle('on')"></div></div>
            </div>
          </div>

          <!-- 5. Product Description -->
          <div class="pne-card">
            <div class="pne-card-head pne-head-purple"><span class="pne-num"><i class="fas fa-align-left"></i></span> Product Description</div>
            <div class="pne-grid2">
              <div class="field">
                <label>Short Description</label>
                <textarea id="pp-shortdesc" maxlength="200" style="min-height:90px" oninput="pnpCharCount('pp-shortdesc','pp-shortdesc-count',200)" placeholder="One-line summary shown in listings"></textarea>
                <div style="text-align:right;font-size:10px;color:var(--muted)"><span id="pp-shortdesc-count">0</span>/200</div>
              </div>
              <div class="field">
                <label>Detailed Description</label>
                <textarea id="pp-detaildesc" maxlength="500" style="min-height:90px" oninput="pnpCharCount('pp-detaildesc','pp-detaildesc-count',500)" placeholder="Full description for the product page / export documents"></textarea>
                <div style="text-align:right;font-size:10px;color:var(--muted)"><span id="pp-detaildesc-count">0</span>/500</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right sidebar -->
        <div class="pne-sidebar">
          <div class="pne-card">
            <div class="pne-card-head"><i class="fas fa-image"></i> Product Images</div>
            <label class="pp-dropzone" for="pp-images-input">
              <i class="fas fa-cloud-upload-alt"></i>
              <div>Drag &amp; drop images here<br>or click to upload</div>
            </label>
            <input type="file" id="pp-images-input" accept="image/png,image/jpeg,image/webp" multiple style="display:none" onchange="pnpAddImages(this.files)">
            <div style="font-size:10px;color:var(--muted);margin-top:6px">Recommended size: 800x800px (Max 5MB each)</div>
            <div id="pp-images-preview" class="pp-thumb-grid"></div>
          </div>

          <div class="pne-card">
            <div class="pne-card-head"><i class="fas fa-circle-info"></i> Additional Information</div>
            <div class="field"><label>Country of Origin</label><input id="pp-country" value="India"></div>
            <div class="field"><label>Manufacturer / Producer</label><input id="pp-manufacturer" placeholder="Optional"></div>
            <div class="field"><label>FSSAI License No.</label><input id="pp-fssai" placeholder="Optional"></div>
            <div class="field"><label>IEC Code</label><input id="pp-iec" placeholder="Optional"></div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px">
              <span style="font-size:12.5px;font-weight:600">Product Status</span>
              <div style="display:flex;align-items:center;gap:8px">
                <div class="tog on" id="pp-status"></div><span id="pp-status-label" style="font-size:12px;color:var(--muted)">Active</span>
              </div>
            </div>
          </div>

          <div class="pne-card">
            <div class="pne-card-head"><i class="fas fa-tags"></i> Tags</div>
            <input id="pp-tags-input" placeholder="Add tags and press enter" onkeydown="pnpTagKeydown(event)">
            <div id="pp-tags-chips" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px"></div>
          </div>

          <div class="pne-card">
            <div class="pne-card-head"><i class="fas fa-paperclip"></i> Attachments</div>
            <label class="pp-dropzone" for="pp-attachments-input">
              <i class="fas fa-cloud-upload-alt"></i>
              <div>Drag &amp; drop files here<br>or click to upload</div>
            </label>
            <input type="file" id="pp-attachments-input" accept="application/pdf,image/png,image/jpeg" multiple style="display:none" onchange="pnpAddAttachments(this.files)">
            <div style="font-size:10px;color:var(--muted);margin-top:6px">Supported formats: PDF, JPG, PNG (Max 5MB)</div>
            <div id="pp-attachments-list" style="margin-top:8px;display:flex;flex-direction:column;gap:6px"></div>
          </div>
        </div>
      </div>


<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
<script src="/assets/js/shared-data.js"></script>
<script src="/assets/js/pages/product-new.js"></script>
