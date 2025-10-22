# BHFE PDF Receipts

A lightweight WooCommerce extension that lets Beacon Hill Financial Educators admins generate branded PDF receipts for customer orders.

## Features

- Adds a “PDF Receipt” action button to WooCommerce order lists and single order screens.
- Streams a Dompdf-generated PDF that includes logo, billing/shipping details, course line items, reporting fees, discounts, shipping, totals, and masked payment info.
- Uses the site’s custom logo or WooCommerce email header image automatically.
- Designed for admin-only usage; access is limited by `manage_woocommerce` capability and protected by nonces.

## Installation

1. Upload the plugin directory to `wp-content/plugins/` or install via WP-CLI.
2. Run `composer install` inside the plugin folder if the `vendor/` directory is missing.
3. Activate **BHFE PDF Receipts** from the WordPress admin Plugins screen.

## Usage

1. Go to **WooCommerce → Orders**.
2. Hover an order and click the black “PDF Receipt” icon, or open an order and use the button beneath the billing details.
3. A new browser tab will open with the generated PDF, ready to download or print.

## Customization

- Filter the logo, company name, and address with:
  - `bhfe_pdf_receipts_logo_url`
  - `bhfe_pdf_receipts_company_name`
  - `bhfe_pdf_receipts_company_address`
- Override the template by copying `templates/receipt.php` into your theme and hooking via `bhfe_pdf_receipts_template_path` (filter can be added as needed).
- Adjust styles by editing `assets/css/receipt.css`.

## Development

- Requires PHP 7.4+ and WooCommerce 8+.
- Run `composer install` to pull Dompdf.
- Use `npm`/`yarn` tooling as desired for Sass or build steps (not included by default).

## License

This project is proprietary to Beacon Hill Financial Educators. All rights reserved.

