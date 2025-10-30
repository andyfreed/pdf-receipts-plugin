<?php
/**
 * Plugin Name: BHFE PDF Receipts
 * Description: Generate branded PDF receipts for WooCommerce orders from the admin dashboard.
 * Version: 0.1.0
 * Author: Skynet
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BHFE_PDF_RECEIPTS_PLUGIN_FILE', __FILE__ );
define( 'BHFE_PDF_RECEIPTS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'BHFE_PDF_RECEIPTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BHFE_PDF_RECEIPTS_VERSION', '0.1.0' );

require_once BHFE_PDF_RECEIPTS_PLUGIN_PATH . 'vendor/autoload.php';

require_once BHFE_PDF_RECEIPTS_PLUGIN_PATH . 'includes/class-bhfe-pdf-receipts.php';

BHFE_PDF_Receipts::instance();

