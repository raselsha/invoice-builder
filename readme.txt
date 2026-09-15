=== WP Invoice Manager ===
Contributors:      yourname
Tags:              invoice, billing, payment, PDF, client management
Requires at least: 5.8
Tested up to:      6.5
Requires PHP:      7.4
Stable tag:        1.2.2
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

A professional OOP-based invoice management plugin for WordPress.

== Description ==

**WP Invoice Manager** lets you create, manage, send, and print professional invoices directly from your WordPress admin dashboard.

= Key Features =

* ✅ Create & edit invoices with dynamic line items
* ✅ Auto-generated sequential invoice numbers (configurable prefix)
* ✅ Multiple invoice statuses: Draft, Sent, Paid, Overdue, Cancelled
* ✅ Client & biller information management
* ✅ Per-item tax rates + flat discount
* ✅ Live total calculation (JS)
* ✅ Printable / PDF-ready invoice template
* ✅ One-click email invoice to client
* ✅ AJAX inline status update
* ✅ Settings page: company info, default currency, default tax, number prefix
* ✅ 8 currencies: USD, EUR, GBP, BDT, JPY, CAD, AUD, INR
* ✅ Fully OOP architecture following WordPress coding standards
* ✅ Custom DB table for line items (via dbDelta)
* ✅ Nonce-protected all forms
* ✅ No external dependencies

= Plugin Architecture =

```
wp-invoice-manager/
├── wp-invoice-manager.php          ← Main bootstrap (Singleton)
├── includes/
│   ├── class-wp-im-activator.php   ← DB setup on activation
│   ├── class-wp-im-deactivator.php ← Flush rewrite on deactivation
│   ├── class-wp-im-post-type.php   ← Custom Post Type: wp_invoice
│   ├── class-wp-im-invoice.php     ← Invoice model (CRUD + totals)
│   └── class-wp-im-pdf-generator.php ← Printable invoice renderer
├── admin/
│   ├── class-wp-im-admin.php       ← Controller (menus, handlers, AJAX)
│   ├── css/admin.css               ← Admin UI styles
│   ├── js/admin.js                 ← Dynamic line items + AJAX
│   └── views/
│       ├── list.php                ← Invoice list with stats & filters
│       ├── form.php                ← Create / Edit form
│       └── settings.php           ← Settings page
└── templates/
    └── invoice-print.php           ← Printable HTML invoice

```

== Installation ==

1. Upload the `wp-invoice-manager` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. Navigate to **Invoices → Settings** to configure your company details.
4. Click **Invoices → New Invoice** to create your first invoice.

== Frequently Asked Questions ==

= Can I export to PDF? =
The print template is browser-print-ready. Use **Ctrl+P → Save as PDF** in Chrome/Firefox. To add server-side PDF generation, extend the `WP_IM_PDF_Generator` class with TCPDF or Dompdf.

= Where is invoice data stored? =
Invoice meta is stored as WordPress post meta on the `wp_invoice` custom post type. Line items are stored in the custom DB table `{prefix}_invoice_items`.

= Is it translation-ready? =
Yes – all strings use `__()` / `_e()` with the `wp-invoice-manager` text domain.

== Changelog ==

= 1.2.2 =
* Fix: on the printed invoice, the "Bill To" block showed a stray gap between the client name
  and the phone/address lines — caused by the template's own indentation whitespace being
  rendered as a blank line (`.party-detail` uses `white-space: pre-line`). The client's contact
  lines are now built and printed in a single pass so no incidental whitespace leaks in.

= 1.2.1 =
* Invoice form: Status and Currency are now the same custom dropdown used in Settings
  (fixes the native `<select>`'s text not sitting vertically centered).
* Refactor: shared view helpers (`wim_val()`, `wim_render_select()`) moved into
  `admin/view-helpers.php` so both the Settings and Invoice form templates use one
  implementation instead of duplicating it.

= 1.2.0 =
* New: Customers — a reusable client list (new `wim_customer` post type, no extra DB tables).
* Invoice form: search customers by name/email/phone and auto-fill client details, or arrive
  prefilled via a customer's "New Invoice" quick-link.
* Invoices are "auto-filed": creating/updating an invoice automatically creates or updates a
  matching customer record (matched by email, falling back to name).
* Customers page: Add/Edit happens in a modal, no separate page navigation.
* Fix: printed invoices no longer show a stray blank line when the client email is empty, and
  addresses with stray blank lines are trimmed/collapsed on the printed invoice.

= 1.1.0 =
* Settings: custom Terms & Conditions repeater (card style, view/edit toggle, drag-to-reorder).
* Invoice form: per-invoice checklist to choose which saved Terms & Conditions print with that invoice.
* Settings: configurable invoice colors — Primary/Accent, Header, and Date & Status Bar (background + text separately), a custom saturation/hue color picker, and 8 quick color themes.
* Public, tokenised "Share" link per invoice — view/print without logging in, with copy/open/regenerate controls (list page and edit screen).
* Invoice list: new "View" action, right-aligned Actions column.
* Settings: custom-styled dropdown (replaces the native currency `<select>`), consistent field heights.
* Settings: configurable invoice footer text with `{site_name}` / `{date}` placeholders.
* Drag-to-reorder for invoice line items and Terms & Conditions.
* Fix: a line item with quantity/price but a blank description was silently dropped on save, causing the saved total to differ from what the form showed.
* Fix: admin CSS/JS now cache-bust on file change (was pinned to a static version string).

= 1.0.0 =
* Initial release.
