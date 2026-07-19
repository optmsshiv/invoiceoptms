# AgriTrade Invoice Manager — Complete Project (Full MPA Cutover)

This is the **entire project**, ready to deploy wholesale — not a diff. It replaces your incremental deployment from before. `dashboard.php` stays at your project root, as requested.

## What changed from your original zip

- **`index.php`** — no longer renders the SPA. It now just checks login and redirects to `/dashboard.php`. The original 30,000-line SPA is preserved untouched as **`index_spa_backup.php`** (not linked anywhere, inert) in case you need to roll back — rename it to `index.php` to restore the old behavior.
- **`dashboard.php`** — fixed: its own includes were written as if it lived in `/pages/dashboard.php` (using `../` paths), but the file is at your root. This would have fatal-errored the moment anyone loaded it. Paths fixed to be root-relative. Also added `invoice-render-shared.js` to its scripts so the "click an invoice to preview" feature works from the dashboard.
- **`includes/auth.php`** — added `renderSessionTimeoutAssets()`, which was missing (only existed in the unused `includes/new_auth.php`) and was causing a fatal error on every single page. **This was your 500 error.**
- **`includes/layout_header.php` / `layout_footer.php`** — nav additions (Sales, Customers, Stock History), dashboard href fixed to `/dashboard.php`, +4 global modal includes.
- **`includes/modals/`** — 4 files that `layout_footer.php` already referenced but didn't exist (fatal error #2, now fixed).
- **`assets/css/app-core.css`** (new file) — 1,391 lines of CSS that were embedded inline in the old SPA's `<style>` tags and never made it into an external stylesheet. Without this, every new page's card/grid layouts fell back to unstyled stacking. Loaded after `app.css` in `layout_header.php`.
- **File organization**: JS files used by 2+ pages (`shared-data.js`, `wa-shared.js`, `edit-approval-shared.js`, `stock-shared.js`, `sales-shared.js`, `invoice-render-shared.js`) now live at `assets/js/` (matching where `common.js` and `dashboard.js` already were) instead of `assets/js/pages/`. Page-specific files stay in `assets/js/pages/`. All `<script>` tags updated to match.
- **`assets/js/suppliers.js`, `purchases.js`, `payments.js`** — replaced (confirmed stale against your current app — see full detail in the git history of our conversation, or ask me to re-explain).
- **36 new pages** in `pages/` covering Stock, Sales, Customers, Invoices, Payments, Products, Purchases, Suppliers, Finance, Comms, and Admin.

## What did NOT change (copied through as-is)

`api/` (all 39 endpoints), `config/`, `auth/`, `admin/` (super admin panel), `portal/` (public client portal), `.htaccess`, `composer.json/lock`.

## Critical: your `.env` file

**This package does not include `.env`** (it has your DB credentials and secrets — I don't want to risk you deploying a stale/wrong one over your working one). **Keep your existing `.env` on the server exactly as it is.** Everything else here is safe to overwrite.

## Changelog — round 3 fixes (after your syntax error report + DB dump)

- **12 functions across 4 files were missing `async`** despite using `await` — a gap in my earlier extraction script that a plain browser load wouldn't catch until that exact code path ran. Found via `node --check` on every file (real syntax validation, not pattern-matching) plus your two reported errors. Fixed in `sales-shared.js`, `edit-approval-shared.js`, `sale-new.js`, `customer-new.js`, `customers.js`.
- **`edit-approval-shared.js` had 11 leftover extraction debug comments** (`--- functionName ---`) that aren't valid JS syntax at all — this was your exact reported error. Stripped.
- **`create.js`'s `onServiceSelect()` and `onStatusChange()` were built from entirely commented-out dead code** in your source file — your SPA has both an active version and an earlier, fully-commented-out duplicate of each function, and my extraction script grabbed the wrong (commented) one both times, leaving an unclosed brace that broke the entire file's parsing. Replaced both with the real, active versions from your source. Audited all 706 functions I've ever extracted for this same pattern — only these two were affected.
- **Migration SQL was completely rewritten** after you shared your real master DB dump — the previous version would have failed outright (`permissions.label` is `NOT NULL` with no default, and I never provided one). Also corrected a wrong assumption: `menu.sales` and `menu.stock` already exist in your permissions table; only `menu.customers` and `menu.stock_history` are genuinely new. See `migrations/add_new_permission_keys.sql`.
- Every JS file now passes `node --check` (real syntax validation) and every PHP page has been re-verified for balanced markup.

## Deploy steps

1. **Back up your entire live site and DB first.** This is a full cutover, not an incremental patch.
2. Upload everything in this package to your server root, **except `.env`** — let it overwrite everything else, including your current `index.php`, `dashboard.php`, and everything under `assets/`, `includes/`, `pages/`.
3. Run `migrations/add_new_permission_keys.sql` against your **master** database.
4. Clear any PHP opcode cache if your host uses one (cPanel: usually not needed, but worth knowing).
5. Visit your site root. It should now redirect straight to `/dashboard.php`.

## Rollback plan

If something's badly broken and you need the SPA back immediately:
1. Rename `index_spa_backup.php` → `index.php` (overwriting the redirect version)
2. Your site is back to exactly how it was before this cutover — nothing else needs to change, since the SPA is self-contained and doesn't depend on any of the new `/pages/*.php` files.

## Testing checklist

- [ ] Site root redirects to `/dashboard.php` when logged in, to `/auth/login.php` when not
- [ ] Dashboard loads with real data (revenue chart, stats, recent activity)
- [ ] Every sidebar link works and shows correctly filtered data
- [ ] Sidebar shows/hides Sales, Customers, and the right Products/Payments view per your tenant's Business Type — now that the earlier 500 error is fixed, this should resolve on its own; check it explicitly
- [ ] Full checklist from the previous delivery still applies: create/edit flows in Stock, Sales, Invoices, Payments, Products, Purchases, Suppliers; Finance reports load; WhatsApp/Email send; Recurring schedules
- [ ] Browser console clean on every page — tell me about anything logged

## If something's still broken

Check the PHP error log first (cPanel → Metrics → Errors) — it'll tell us exactly what's failing, same as last time. I can't test this live, so precise error messages are the fastest path to a fix.
