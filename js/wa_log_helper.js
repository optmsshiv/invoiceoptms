// ================================================================
//  js/wa_log_helper.js — WhatsApp Message Log Frontend Helper
//  Handles: timezone formatting, optimistic updates, proper refresh
// ================================================================

const WA_LOG = {
    // Format timestamp to India Standard Time (IST)
    formatTime(ts) {
        if (!ts) return '';
        try {
            const date = new Date(ts);
            return date.toLocaleString('en-IN', {
                timeZone: 'Asia/Kolkata',
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
        } catch(e) {
            console.error('Time format error:', e);
            return ts;
        }
    },

    // Format time relative (e.g., "2 mins ago")
    formatTimeRelative(ts) {
        if (!ts) return '';
        try {
            const date = new Date(ts);
            const now = new Date();
            const diffMs = now - date;
            const diffSecs = Math.floor(diffMs / 1000);
            const diffMins = Math.floor(diffSecs / 60);
            const diffHours = Math.floor(diffMins / 60);
            const diffDays = Math.floor(diffHours / 24);

            if (diffSecs < 60) return 'just now';
            if (diffMins < 60) return diffMins + ' min' + (diffMins > 1 ? 's' : '') + ' ago';
            if (diffHours < 24) return diffHours + ' hour' + (diffHours > 1 ? 's' : '') + ' ago';
            if (diffDays < 7) return diffDays + ' day' + (diffDays > 1 ? 's' : '') + ' ago';
            
            return this.formatTime(ts);
        } catch(e) {
            return this.formatTime(ts);
        }
    },

    // Fetch WhatsApp log from server
    async fetchLog() {
        try {
            const response = await fetch('/api/wa_log.php', {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Failed to fetch log');
            }
            
            console.log(`[WA Log] Fetched ${data.count} messages | Timezone: ${data.timezone}`);
            return data.data || [];
        } catch(e) {
            console.error('[WA Log] Fetch error:', e);
            return [];
        }
    },

    // Send a message and log it (optimistic update)
    async sendMessage(message) {
        // ✅ Step 1: Create entry with unique ID
        const entryId = 'wa_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        const entry = {
            id: entryId,
            ts: new Date().toISOString(),
            type: message.type || 'unknown',
            status: 'sending',
            client: message.client || '',
            phone: message.phone || '',
            inv_id: message.inv_id || '',
            inv_num: message.inv_num || '',
            inv_amt: message.inv_amt || '',
            inv_status: message.inv_status || '',
            msg: message.msg || '',
            error: null
        };

        // ✅ Step 2: Add to UI immediately (optimistic update)
        this.addToTable(entry);
        
        // ✅ Step 3: Send to server
        try {
            const response = await fetch('/api/wa_log.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(entry)
            });

            const result = await response.json();

            if (result.success) {
                // ✅ Step 4: Update status to 'sent_web'
                this.updateEntryStatus(entryId, 'sent_web', null);
                console.log('[WA Log] Message sent:', entryId);
                
                // ✅ Step 5: Refresh from server after 1 second to sync
                setTimeout(() => this.refreshTable(), 1000);
                
                return { success: true, id: result.id };
            } else {
                throw new Error(result.error || 'Send failed');
            }
        } catch(e) {
            console.error('[WA Log] Send error:', e);
            // ✅ Step 6: Mark as failed
            this.updateEntryStatus(entryId, 'failed', e.message);
            
            // Still try to save the failure to server
            await this.logError(entryId, e.message);
            
            return { success: false, error: e.message };
        }
    },

    // Add entry to table immediately
    addToTable(entry) {
        const table = document.querySelector('#wa-log-table tbody');
        if (!table) return;

        const row = document.createElement('tr');
        row.id = 'wa-log-row-' + entry.id;
        row.dataset.entryId = entry.id;
        row.dataset.status = entry.status;
        row.innerHTML = `
            <td class="wa-log-ts">${this.formatTime(entry.ts)}</td>
            <td class="wa-log-type">${this.getTypeLabel(entry.type)}</td>
            <td class="wa-log-client">${entry.client}</td>
            <td class="wa-log-phone">${entry.phone}</td>
            <td class="wa-log-inv">${entry.inv_num}</td>
            <td class="wa-log-msg">${entry.msg.substring(0, 60)}...</td>
            <td class="wa-log-status">
                <span class="badge badge-${entry.status}">${entry.status}</span>
            </td>
        `;
        
        // Insert at TOP of table
        table.insertBefore(row, table.firstChild);
    },

    // Update entry status
    updateEntryStatus(entryId, newStatus, error) {
        const row = document.getElementById('wa-log-row-' + entryId);
        if (!row) return;

        row.dataset.status = newStatus;
        const statusEl = row.querySelector('.wa-log-status');
        if (statusEl) {
            statusEl.innerHTML = `<span class="badge badge-${newStatus}">${newStatus}</span>`;
        }

        if (error) {
            row.setAttribute('title', error);
        }
    },

    // Log error to server
    async logError(entryId, errorMsg) {
        try {
            await fetch('/api/wa_log.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: entryId,
                    status: 'failed',
                    error: errorMsg
                })
            });
        } catch(e) {
            console.error('[WA Log] Could not log error:', e);
        }
    },

    // Refresh entire table from server
    async refreshTable() {
        try {
            const logs = await this.fetchLog();
            const table = document.querySelector('#wa-log-table tbody');
            if (!table) return;

            // Clear table
            table.innerHTML = '';

            // Add all logs (newest first)
            logs.forEach(log => {
                const row = document.createElement('tr');
                row.id = 'wa-log-row-' + log.id;
                row.dataset.entryId = log.id;
                row.dataset.status = log.status;
                row.innerHTML = `
                    <td class="wa-log-ts" title="${log.ts}">${this.formatTimeRelative(log.ts)}</td>
                    <td class="wa-log-type">${this.getTypeLabel(log.type)}</td>
                    <td class="wa-log-client">${log.client || '-'}</td>
                    <td class="wa-log-phone">${log.phone || '-'}</td>
                    <td class="wa-log-inv">${log.inv_num || '-'}</td>
                    <td class="wa-log-msg">${(log.msg || '').substring(0, 60)}${(log.msg || '').length > 60 ? '...' : ''}</td>
                    <td class="wa-log-status">
                        <span class="badge badge-${log.status}">${log.status}</span>
                        ${log.error ? '<div class="text-danger small mt-1">' + log.error + '</div>' : ''}
                    </td>
                `;
                table.appendChild(row);
            });

            console.log('[WA Log] Table refreshed with ' + logs.length + ' entries');
        } catch(e) {
            console.error('[WA Log] Refresh error:', e);
        }
    },

    // Clear all logs with confirmation
    async clearLogs() {
        const confirmCode = 'CLEAR_WA_LOG_' + new Date().toISOString().split('T')[0];
        const confirmed = await Swal.fire({
            title: 'Clear WhatsApp Log?',
            text: 'All message logs will be permanently deleted. This cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#E53935',
            customClass: { popup: 'swal-compact' }
        });

        if (!confirmed.isConfirmed) return;

        try {
            const response = await fetch('/api/wa_log.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ confirm_code: confirmCode })
            });

            const result = await response.json();

            if (result.success) {
                toast('✓ WhatsApp log cleared', 'success');
                this.refreshTable();
            } else {
                throw new Error(result.error);
            }
        } catch(e) {
            toast('✗ Error clearing log: ' + e.message, 'error');
        }
    },

    // Get human-readable type label
    getTypeLabel(type) {
        const labels = {
            'invoice_created': '📄 Invoice Created',
            'estimate_created': '📋 Estimate Created',
            'payment_received': '✅ Payment Received',
            'partial_payment': '⚡ Partial Payment',
            'split_payment': '🔀 Split Payment',
            'payment_overdue': '⛔ Overdue',
            'payment_reminder': '🔔 Reminder',
            'invoice_followup': '📞 Follow-up',
            'festival': '🎉 Festival',
            'unknown': '❓ Unknown'
        };
        return labels[type] || type;
    }
};

// ================================================================
// Auto-initialize when page loads
// ================================================================
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('wa-log-table')) {
        WA_LOG.refreshTable();
        
        // Refresh every 10 seconds
        setInterval(() => WA_LOG.refreshTable(), 600000);
    }
});
