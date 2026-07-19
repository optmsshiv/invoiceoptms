      <!-- Add/Edit Client modal — shared: clients.php & invoices/create.php (via switchToSaveClient) -->
      <div class="modal-overlay" id="modal-addclient">
        <div class="modal modal-md">
          <div class="modal-header"><span>Add New Client</span><button class="modal-close" onclick="closeModal('modal-addclient')"><i class="fas fa-times"></i></button></div>
          <div class="modal-body" style="padding:24px">
            <!-- Logo Upload -->
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:18px;padding:14px;background:var(--surface2);border-radius:10px;border:1px solid var(--border)">
              <div id="nc-logo-preview" style="width:64px;height:64px;border-radius:50%;background:#00897B;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:#fff;overflow:hidden;flex-shrink:0;border:3px solid var(--border);transition:border-color .3s,box-shadow .3s">
                <span id="nc-logo-initials">?</span>
                <img id="nc-logo-img" src="" style="width:100%;height:100%;object-fit:cover;display:none">
              </div>
              <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:13px;margin-bottom:6px;color:var(--text)">Client Logo <span style="font-size:10px;color:var(--muted);font-weight:400">(optional)</span></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                  <label id="nc-logo-upload-btn" style="cursor:pointer;display:inline-flex;align-items:center;gap:6px;background:var(--teal);color:#fff;padding:5px 12px;border-radius:6px;font-size:12px;font-weight:600;position:relative;overflow:hidden;transition:background .2s">
                    <i class="fas fa-upload" id="nc-logo-upload-icon"></i>
                    <span id="nc-logo-upload-text">Upload</span>
                    <div id="nc-logo-progress-bar" style="position:absolute;left:0;bottom:0;height:3px;width:0%;background:rgba(255,255,255,.7);transition:width .05s linear;border-radius:0 0 6px 6px"></div>
                    <input type="file" id="nc-logo-file" accept="image/*" style="display:none" onchange="handleClientLogoUpload(this)">
                  </label>
                  <button class="btn btn-outline" style="font-size:12px;padding:5px 10px" onclick="document.getElementById('nc-logo-url-wrap').style.display=document.getElementById('nc-logo-url-wrap').style.display==='none'?'flex':'none'"><i class="fas fa-link"></i> URL</button>
                  <button class="btn btn-outline" style="font-size:12px;padding:5px 10px;color:var(--red)" onclick="clearClientLogo()" title="Remove logo"><i class="fas fa-times"></i></button>
                </div>
                <div id="nc-logo-url-wrap" style="display:none;margin-top:8px;gap:6px;align-items:center">
                  <input id="nc-logo-url" placeholder="https://…logo.png" style="flex:1;padding:5px 8px;border:1px solid var(--border);border-radius:6px;font-size:12px" oninput="previewClientLogoUrl(this.value)">
                </div>
              </div>
            </div>
            <div class="form-grid g2">
              <div class="field g-full"><label>Organization Name *</label><input id="nc-name" placeholder="Company or school name" oninput="updateClientLogoInitials()"></div>
              <div class="field"><label>Contact Person</label><input id="nc-person"></div>
              <div class="field"><label>WhatsApp</label><input id="nc-wa" placeholder="+91 XXXXX XXXXX"></div>
              <div class="field"><label>Email</label><input id="nc-email" type="email"></div>
              <div class="field"><label>GST Number</label><input id="nc-gst"></div>
              <div class="field"><label>Avatar Color</label><input type="color" id="nc-color" value="#00897B" oninput="updateClientLogoInitials()"></div>
              <div class="field g-full"><label>Address</label><textarea id="nc-addr"></textarea></div>
              <div class="field g-full"><label>Landmark <span style="font-size:10px;color:var(--muted)">(optional — nearby area or landmark)</span></label><input id="nc-landmark" placeholder="e.g. Near City Mall, Sector 12"></div>
            </div>

            <!-- Tags / Labels -->
            <div style="margin-top:18px">
              <label style="font-weight:600;font-size:13px;margin-bottom:6px;color:var(--text);display:block">Tags / Labels <span style="font-size:10px;color:var(--muted);font-weight:400">(optional — press Enter to add)</span></label>
              <div id="nc-tags-pills" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px"></div>
              <input id="nc-tag-input" placeholder="Type a tag and press Enter…" oninput="showTagSuggestions(this.value)" onkeydown="handleTagInput(event)" style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;font-family:var(--font)">
              <div id="nc-tag-suggestions" style="display:none;position:fixed;z-index:9999;background:var(--card);border:1px solid var(--border);border-radius:8px;box-shadow:var(--shadow-md);max-height:180px;overflow-y:auto;min-width:160px"></div>
            </div>

            <!-- Multiple Contacts -->
            <div style="margin-top:18px">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <label style="font-weight:600;font-size:13px;color:var(--text)">Additional Contacts <span style="font-size:10px;color:var(--muted);font-weight:400">(optional)</span></label>
                <button type="button" class="btn btn-outline" style="font-size:12px;padding:4px 10px" onclick="addExtraContactRow()"><i class="fas fa-plus"></i> Add Contact</button>
              </div>
              <div id="nc-extra-contacts" style="display:flex;flex-direction:column;gap:6px"></div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-primary" onclick="saveNewClient()">Add Client</button>
            <button class="btn btn-outline" onclick="closeModal('modal-addclient')">Cancel</button>
          </div>
        </div>
      </div>
