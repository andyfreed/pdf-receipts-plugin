<?php

use Dompdf\Dompdf;
use Dompdf\Options;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BHFE_PDF_Receipts {

    /**
     * Singleton instance.
     *
     * @var BHFE_PDF_Receipts|null
     */
    protected static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return BHFE_PDF_Receipts
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    protected function __construct() {
        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ $this, 'register_actions' ] );
    }

    /**
     * Load plugin text domain.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'bhfe-pdf-receipts', false, dirname( plugin_basename( BHFE_PDF_RECEIPTS_PLUGIN_FILE ) ) . '/languages' );
    }

    /**
     * Register hooks and actions.
     */
    public function register_actions() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        add_filter( 'woocommerce_admin_order_actions', [ $this, 'add_admin_order_action' ], 20, 2 );
        add_action( 'admin_action_bhfe_generate_receipt', [ $this, 'handle_admin_generate_receipt' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_filter( 'woocommerce_order_actions', [ $this, 'add_order_view_action' ] );
        add_action( 'woocommerce_order_action_bhfe_generate_receipt', [ $this, 'handle_order_view_action' ] );
    }

    /**
     * Enqueue admin scripts/styles.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'woocommerce_page_wc-orders' !== $hook && 'post.php' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'bhfe-pdf-receipts-admin',
            BHFE_PDF_RECEIPTS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            BHFE_PDF_RECEIPTS_VERSION
        );
    }

    /**
     * Add custom order action button.
     */
    public function add_admin_order_action( $actions, $order ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return $actions;
        }

        $url = wp_nonce_url(
            add_query_arg(
                [
                    'action'    => 'bhfe_generate_receipt',
                    'order_id'  => $order->get_id(),
                ],
                admin_url( 'admin.php' )
            ),
            'bhfe_generate_receipt_' . $order->get_id()
        );

        $actions['bhfe_generate_receipt'] = [
            'url'       => $url,
            'name'      => __( 'PDF Receipt', 'bhfe-pdf-receipts' ),
            'action'    => 'view bhfe-pdf-receipt',
        ];

        return $actions;
    }

    /**
     * Add bulk order action in single order view dropdown.
     */
    public function add_order_view_action( $actions ) {
        $actions['bhfe_generate_receipt'] = __( 'Generate PDF Receipt', 'bhfe-pdf-receipts' );

        return $actions;
    }

    /**
     * Handle order view dropdown action.
     */
    public function handle_order_view_action( $order ) {
        $url = wp_nonce_url(
            add_query_arg(
                [
                    'action'   => 'bhfe_generate_receipt',
                    'order_id' => $order->get_id(),
                ],
                admin_url( 'admin.php' )
            ),
            'bhfe_generate_receipt_' . $order->get_id()
        );

        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Handle admin action to generate receipt.
     */
    public function handle_admin_generate_receipt() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Access denied.', 'bhfe-pdf-receipts' ) );
        }

        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

        if ( ! $order_id || ! check_admin_referer( 'bhfe_generate_receipt_' . $order_id ) ) {
            wp_die( __( 'Invalid request.', 'bhfe-pdf-receipts' ) );
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wp_die( __( 'Order not found.', 'bhfe-pdf-receipts' ) );
        }

        $pdf = $this->generate_receipt_pdf( $order );

        $filename = sprintf( 'bhfe-receipt-%d.pdf', $order->get_id() );

        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: inline; filename="' . $filename . '"' );
        echo $pdf;
        exit;
    }

    /**
     * Generate PDF binary string for order receipt.
     *
     * @param WC_Order $order Order object.
     *
     * @return string
     */
    protected function generate_receipt_pdf( $order ) {
        $html = $this->get_receipt_html( $order );

        $options = new Options();
        $options->set( 'isHtml5ParserEnabled', true );
        $options->set( 'isRemoteEnabled', true );
        $options->set( 'defaultFont', 'Helvetica' );

        $dompdf = new Dompdf( $options );
        $dompdf->setPaper( 'letter', 'portrait' );
        $dompdf->loadHtml( $html );
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Get HTML markup for the receipt.
     *
     * @param WC_Order $order Order object.
     *
     * @return string
     */
    protected function get_receipt_html( $order ) {
        $template = BHFE_PDF_RECEIPTS_PLUGIN_PATH . 'templates/receipt.php';

        if ( ! file_exists( $template ) ) {
            return __( 'Receipt template not found.', 'bhfe-pdf-receipts' );
        }

        ob_start();
        include $template;

        return ob_get_clean();
    }

    /**
     * Retrieve last four card digits from order meta.
     *
     * @param WC_Order $order Order object.
     *
     * @return string
     */
    public function get_card_last4( $order ) {
        $possible_keys = apply_filters(
            'bhfe_pdf_receipts_card_last4_meta_keys',
            [
                '_card_last4',
                '_stripe_last4',
                '_payment_method_last4',
                '_wc_authorize_net_cim_credit_card_last_four',
                '_wc_square_credit_card_last_4',
            ],
            $order
        );

        foreach ( $possible_keys as $key ) {
            $value = $order->get_meta( $key );

            if ( ! empty( $value ) ) {
                return sanitize_text_field( $value );
            }
        }

        return '';
    }
}

