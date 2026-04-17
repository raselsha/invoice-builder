=== WP Invoice Manager ===
Contributors:      yourname
Tags:              invoice, billing, payment, PDF, client management
Requires at least: 5.8
Tested up to:      6.5
Requires PHP:      7.4
Stable tag:        1.0.0
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

= 1.0.0 =
* Initial release.
