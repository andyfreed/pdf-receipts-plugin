<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$logo_url = apply_filters( 'bhfe_pdf_receipts_logo_url', BHFE_PDF_Receipts::instance()->get_default_logo_url(), $order );
$company_name = apply_filters( 'bhfe_pdf_receipts_company_name', get_bloginfo( 'name' ), $order );
$company_address = apply_filters( 'bhfe_pdf_receipts_company_address', '', $order );

$billing_address = $order->get_formatted_billing_address();
$shipping_address = $order->needs_shipping_address() ? $order->get_formatted_shipping_address() : '';

$payment_method = $order->get_payment_method_title();
$plugin = BHFE_PDF_Receipts::instance();
$card_last4 = $plugin->get_card_last4( $order );
$payment_date = $order->get_date_paid() ?: $order->get_date_created();
$currency_args = [
    'currency' => $order->get_currency(),
];

$reporting_fee_items = array_filter(
    $order->get_fees(),
    static function ( $fee ) {
        return false !== stripos( $fee->get_name(), 'reporting fee' );
    }
);

$receipt_css = '';
$receipt_css_path = BHFE_PDF_RECEIPTS_PLUGIN_PATH . 'assets/css/receipt.css';

if ( file_exists( $receipt_css_path ) ) {
    $receipt_css = file_get_contents( $receipt_css_path );
}

$has_shipping = ! empty( $shipping_address );

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html( $company_name ); ?> Receipt</title>
    <?php if ( $receipt_css ) : ?>
        <style>
            <?php echo $receipt_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </style>
    <?php endif; ?>
</head>
<body>
    <div class="bhfe-receipt">
        <header class="bhfe-receipt__header">
            <?php if ( $logo_url ) : ?>
                <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $company_name ); ?>" class="bhfe-receipt__logo" />
            <?php endif; ?>
            <div class="bhfe-receipt__company">
                <h2><?php echo esc_html( $company_name ); ?></h2>
                <?php if ( $company_address ) : ?>
                    <p><?php echo wp_kses_post( nl2br( $company_address ) ); ?></p>
                <?php endif; ?>
            </div>
        </header>

        <section class="bhfe-receipt__meta">
            <div class="bhfe-receipt__meta-column">
                <h3><?php esc_html_e( 'Receipt', 'bhfe-pdf-receipts' ); ?></h3>
                <p><?php echo wp_kses_post( $billing_address ); ?></p>
                <?php if ( $has_shipping ) : ?>
                    <h4><?php esc_html_e( 'Shipping Address', 'bhfe-pdf-receipts' ); ?></h4>
                    <p><?php echo wp_kses_post( $shipping_address ); ?></p>
                <?php endif; ?>
            </div>
            <div class="bhfe-receipt__meta-column">
                <p><strong><?php esc_html_e( 'Payment Date', 'bhfe-pdf-receipts' ); ?></strong><span><?php echo esc_html( wc_format_datetime( $payment_date, get_option( 'date_format' ) ) ); ?></span></p>
                <p><strong><?php esc_html_e( 'Payment Method', 'bhfe-pdf-receipts' ); ?></strong><span><?php echo esc_html( $payment_method . ( $card_last4 ? sprintf( ' ending in %s', $card_last4 ) : '' ) ); ?></span></p>
                <p><strong><?php esc_html_e( 'Order Number', 'bhfe-pdf-receipts' ); ?></strong><span><?php echo esc_html( $order->get_order_number() ); ?></span></p>
            </div>
        </section>

        <table class="bhfe-receipt__items">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Product', 'bhfe-pdf-receipts' ); ?></th>
                    <th><?php esc_html_e( 'Price', 'bhfe-pdf-receipts' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $order->get_items() as $item_id => $item ) : ?>
                    <tr>
                        <td><?php echo esc_html( $item->get_name() ); ?></td>
                        <td><?php echo wp_kses_post( wc_price( $item->get_total() + $item->get_total_tax(), $currency_args ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <table class="bhfe-receipt__totals">
            <colgroup>
                <col class="bhfe-receipt__totals-label-col" />
                <col class="bhfe-receipt__totals-amount-col" />
            </colgroup>
            <tbody>
                <tr>
                    <th><?php esc_html_e( 'Subtotal', 'bhfe-pdf-receipts' ); ?></th>
                    <td><?php echo wp_kses_post( wc_price( $order->get_subtotal(), $currency_args ) ); ?></td>
                </tr>
                <?php if ( $order->get_discount_total() > 0 ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Discounts', 'bhfe-pdf-receipts' ); ?></th>
                        <td><?php echo wp_kses_post( wc_price( $order->get_discount_total(), $currency_args ) ); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ( $order->get_shipping_total() > 0 ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Shipping', 'bhfe-pdf-receipts' ); ?></th>
                        <td><?php echo wp_kses_post( wc_price( $order->get_shipping_total(), $currency_args ) ); ?></td>
                    </tr>
                <?php endif; ?>
                <?php foreach ( $reporting_fee_items as $fee_item ) : ?>
                    <tr>
                        <th><?php echo esc_html( $fee_item->get_name() ); ?></th>
                        <td><?php echo wp_kses_post( wc_price( $fee_item->get_total() + $fee_item->get_total_tax(), $currency_args ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="bhfe-receipt__total">
                    <th><?php esc_html_e( 'Total', 'bhfe-pdf-receipts' ); ?></th>
                    <td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>

