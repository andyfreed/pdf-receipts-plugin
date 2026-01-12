<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$logo_url = apply_filters( 'bhfe_pdf_receipts_logo_url', BHFE_PDF_Receipts::instance()->get_default_logo_url(), $order );
$company_name = apply_filters( 'bhfe_pdf_receipts_company_name', get_bloginfo( 'name' ), $order );
$company_address = apply_filters(
    'bhfe_pdf_receipts_company_address',
    "Beacon Hill Financial Educators, Inc.\n51A Middle Street\nNewburyport, MA 01950\n1-800-588-7039",
    $order
);

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

if ( ! function_exists( 'bhfe_pdf_receipts_normalize_course_number' ) ) {
    function bhfe_pdf_receipts_normalize_course_number( $value ) {
        if ( is_bool( $value ) ) {
            return '';
        }

        if ( is_scalar( $value ) ) {
            $candidate = trim( (string) $value );

            if ( '' !== $candidate && strlen( $candidate ) >= 3 && preg_match( '/\d/', $candidate ) ) {
                return $candidate;
            }
        }

        return '';
    }
}

if ( ! function_exists( 'bhfe_pdf_receipts_extract_global_course_number' ) ) {
    function bhfe_pdf_receipts_extract_global_course_number( $value ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $key => $nested_value ) {
                if ( is_string( $key ) && 'course_numbers' === strtolower( $key ) ) {
                    $candidate = bhfe_pdf_receipts_extract_global_course_number( $nested_value );

                    if ( '' !== $candidate ) {
                        return $candidate;
                    }
                }
            }

            foreach ( $value as $key => $nested_value ) {
                if ( is_string( $key ) && 'global' === strtolower( $key ) ) {
                    $candidate = bhfe_pdf_receipts_normalize_course_number( $nested_value );

                    if ( '' !== $candidate ) {
                        return $candidate;
                    }
                }
            }

            foreach ( $value as $nested_value ) {
                if ( is_array( $nested_value ) || is_object( $nested_value ) ) {
                    $candidate = bhfe_pdf_receipts_extract_global_course_number( $nested_value );

                    if ( '' !== $candidate ) {
                        return $candidate;
                    }
                }
            }
        } elseif ( is_object( $value ) ) {
            return bhfe_pdf_receipts_extract_global_course_number( (array) $value );
        }

        return bhfe_pdf_receipts_normalize_course_number( $value );
    }
}

if ( ! function_exists( 'bhfe_pdf_receipts_collect_course_numbers' ) ) {
    function bhfe_pdf_receipts_collect_course_numbers( WC_Order_Item_Product $item ) {
        $collected_numbers = [];

        $course_number_module_available = function_exists( 'flms_is_module_active' ) && flms_is_module_active( 'course_numbers' ) && class_exists( 'FLMS_Module_Course_Numbers' );

        if ( $course_number_module_available ) {
            $module = new FLMS_Module_Course_Numbers();
            $course_refs = [];

            $variation_id = $item->get_variation_id();

            if ( $variation_id ) {
                $variation_courses = get_post_meta( $variation_id, 'flms_woocommerce_variable_course_ids', true );

                if ( ! empty( $variation_courses ) ) {
                    $course_refs = array_merge( $course_refs, is_array( $variation_courses ) ? $variation_courses : [ $variation_courses ] );
                }
            }

            $product = $item->get_product();

            if ( $product ) {
                $product_id = $product->get_id();

                $simple_courses = get_post_meta( $product_id, 'flms_woocommerce_simple_course_ids', true );
                if ( ! empty( $simple_courses ) ) {
                    $course_refs = array_merge( $course_refs, is_array( $simple_courses ) ? $simple_courses : [ $simple_courses ] );
                }

                $primary_course_ids = get_post_meta( $product_id, 'flms_woocommerce_product_id', true );

                if ( ! empty( $primary_course_ids ) ) {
                    if ( is_array( $primary_course_ids ) ) {
                        $course_refs = array_merge( $course_refs, $primary_course_ids );
                    } else {
                        $course_refs[] = $primary_course_ids;
                    }
                }
            }

            foreach ( $course_refs as $course_ref ) {
                if ( empty( $course_ref ) ) {
                    continue;
                }

                $course_id = '';
                $course_version = '';

                if ( is_scalar( $course_ref ) ) {
                    $course_ref = trim( (string) $course_ref );

                    if ( '' === $course_ref ) {
                        continue;
                    }

                    $parts = explode( ':', $course_ref );
                    $course_id = trim( $parts[0] ?? '' );
                    $course_version = isset( $parts[1] ) ? trim( $parts[1] ) : '';
                } elseif ( is_array( $course_ref ) ) {
                    $course_id = isset( $course_ref['course_id'] ) ? $course_ref['course_id'] : ( $course_ref[0] ?? '' );
                    $course_version = isset( $course_ref['course_version'] ) ? $course_ref['course_version'] : ( $course_ref[1] ?? '' );
                } elseif ( is_object( $course_ref ) ) {
                    $course_id = isset( $course_ref->course_id ) ? $course_ref->course_id : '';
                    $course_version = isset( $course_ref->course_version ) ? $course_ref->course_version : '';
                }

                if ( ! $course_id ) {
                    continue;
                }

                $raw_number = $module->get_course_number( $course_id, $course_version );
                $normalized = bhfe_pdf_receipts_normalize_course_number( $raw_number );

                if ( '' !== $normalized ) {
                    $collected_numbers[] = $normalized;
                }
            }
        }

        if ( empty( $collected_numbers ) ) {
            $course_number_sources = [
                $item->get_meta( 'course_numbers', true ),
                $item->get_meta( '_course_numbers', true ),
                $item->get_meta( '_sfwd-courses', true ),
                $item->get_meta( '_sfwd_course', true ),
                $item->get_meta( '_ld_course', true ),
                $item->get_meta( '_ld_course_info', true ),
            ];

            foreach ( $item->get_meta_data() as $meta_data ) {
                $value = $meta_data->get_data()['value'];

                if ( is_array( $value ) || is_object( $value ) ) {
                    $course_number_sources[] = $value;
                }
            }

            $product = $item->get_product();

            if ( $product ) {
                $course_number_sources[] = $product->get_meta( 'course_numbers', true );
                $course_number_sources[] = get_post_meta( $product->get_id(), 'course_numbers', true );
                $course_number_sources[] = get_post_meta( $product->get_id(), '_sfwd-courses', true );
                $course_number_sources[] = $product->get_meta( '_sfwd-courses', true );
                $course_number_sources[] = $product->get_meta( '_sfwd_course', true );
                $course_number_sources[] = $product->get_meta( '_ld_course', true );
                $course_number_sources[] = $product->get_meta( '_ld_course_info', true );

                foreach ( $product->get_meta_data() as $meta_data ) {
                    $value = $meta_data->get_data()['value'];

                    if ( is_array( $value ) || is_object( $value ) ) {
                        $course_number_sources[] = $value;
                    }
                }
            }

            foreach ( $course_number_sources as $source ) {
                if ( null === $source || '' === $source ) {
                    continue;
                }

                $candidate = bhfe_pdf_receipts_extract_global_course_number( $source );

                if ( '' !== $candidate ) {
                    $collected_numbers[] = $candidate;
                }
            }
        }

        if ( empty( $collected_numbers ) ) {
            return [];
        }

        $collected_numbers = array_map( 'trim', $collected_numbers );
        $collected_numbers = array_filter( $collected_numbers, static function ( $number ) {
            return '' !== $number;
        } );

        return array_values( array_unique( $collected_numbers ) );
    }
}

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
            <?php if ( $company_address ) : ?>
                <div class="bhfe-receipt__company">
                    <p><?php echo wp_kses_post( nl2br( $company_address ) ); ?></p>
                </div>
            <?php endif; ?>
        </header>

        <section class="bhfe-receipt__meta">
            <h3><?php esc_html_e( 'Receipt', 'bhfe-pdf-receipts' ); ?></h3>
            <table class="bhfe-receipt__meta-table">
                <tr>
                    <td class="bhfe-receipt__meta-cell">
                        <?php if ( $billing_address ) : ?>
                            <h4><?php esc_html_e( 'Billing Address', 'bhfe-pdf-receipts' ); ?></h4>
                            <p><?php echo wp_kses_post( $billing_address ); ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="bhfe-receipt__meta-cell">
                        <?php if ( $has_shipping ) : ?>
                            <h4><?php esc_html_e( 'Shipping Address', 'bhfe-pdf-receipts' ); ?></h4>
                            <p><?php echo wp_kses_post( $shipping_address ); ?></p>
                        <?php else : ?>
                            <h4><?php esc_html_e( 'Shipping Address', 'bhfe-pdf-receipts' ); ?></h4>
                            <p><?php esc_html_e( 'N/A', 'bhfe-pdf-receipts' ); ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="bhfe-receipt__meta-cell bhfe-receipt__meta-cell--details">
                        <table class="bhfe-receipt__meta-details">
                            <tr>
                                <th><?php esc_html_e( 'Payment Date', 'bhfe-pdf-receipts' ); ?></th>
                                <td><?php echo esc_html( wc_format_datetime( $payment_date, get_option( 'date_format' ) ) ); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Payment Method', 'bhfe-pdf-receipts' ); ?></th>
                                <td>
                                    <?php
                                    if ( $card_last4 ) {
                                        echo esc_html( sprintf( '%s XX%s', $payment_method, $card_last4 ) );
                                    } else {
                                        echo esc_html( $payment_method );
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e( 'Order Number', 'bhfe-pdf-receipts' ); ?></th>
                                <td><?php echo esc_html( $order->get_order_number() ); ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
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
                    <?php
                    $collected_course_numbers = bhfe_pdf_receipts_collect_course_numbers( $item );
                    $course_number = $collected_course_numbers ? reset( $collected_course_numbers ) : '';
                    ?>
                    <tr>
                        <td>
                            <?php echo esc_html( $item->get_name() ); ?>
                            <?php if ( $course_number ) : ?>
                                <div class="bhfe-receipt__course-number">
                                    <?php printf( esc_html__( 'Course #: %s', 'bhfe-pdf-receipts' ), esc_html( $course_number ) ); ?>
                                </div>
                            <?php endif; ?>
                        </td>
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

