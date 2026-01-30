<?php
/*
Plugin Name: Minimum Order Restriction for WooCommerce
Plugin URI: https://github.com/deveguru
Description: جلوگیری از خرید زیر ۲۰۰ هزار تومان، غیرفعال‌سازی دکمه تسویه حساب و نمایش پیام زمان ارسال در جزئیات سفارش
Version: 1.1.0
Author: devguru
Author URI: https://github.com/deveguru
License: GPLv2 or later
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DEVGURU_MIN_ORDER_AMOUNT', 190000 );

add_action( 'admin_menu', 'devguru_add_settings_page' );
function devguru_add_settings_page() {
    add_options_page(
        'تنظیمات سفارش',
        'تنظیمات سفارش',
        'manage_options',
        'devguru-order-settings',
        'devguru_settings_page_html'
    );
}

function devguru_settings_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_POST['devguru_settings_submit'] ) ) {
        check_admin_referer( 'devguru_settings_action', 'devguru_settings_nonce' );
        update_option( 'devguru_packaging_fee', sanitize_text_field( $_POST['devguru_packaging_fee'] ) );
        update_option( 'devguru_packaging_label', sanitize_text_field( $_POST['devguru_packaging_label'] ) );
        echo '<div class="updated"><p>تنظیمات ذخیره شد.</p></div>';
    }

    $packaging_fee = get_option( 'devguru_packaging_fee', '0' );
    $packaging_label = get_option( 'devguru_packaging_label', 'هزینه بسته بندی' );
    ?>
    <div class="wrap">
        <h1>تنظیمات سفارش</h1>
        <form method="post" action="">
            <?php wp_nonce_field( 'devguru_settings_action', 'devguru_settings_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="devguru_packaging_label">عنوان هزینه بسته بندی</label></th>
                    <td><input type="text" id="devguru_packaging_label" name="devguru_packaging_label" value="<?php echo esc_attr( $packaging_label ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="devguru_packaging_fee">مبلغ هزینه بسته بندی (تومان)</label></th>
                    <td><input type="number" id="devguru_packaging_fee" name="devguru_packaging_fee" value="<?php echo esc_attr( $packaging_fee ); ?>" class="regular-text" step="1000"></td>
                </tr>
            </table>
            <?php submit_button( 'ذخیره تنظیمات', 'primary', 'devguru_settings_submit' ); ?>
        </form>
    </div>
    <?php
}

add_action( 'woocommerce_cart_calculate_fees', 'devguru_add_packaging_fee' );
function devguru_add_packaging_fee() {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    $packaging_fee = floatval( get_option( 'devguru_packaging_fee', '0' ) );
    $packaging_label = get_option( 'devguru_packaging_label', 'هزینه بسته بندی' );

    if ( $packaging_fee > 0 ) {
        WC()->cart->add_fee( $packaging_label, $packaging_fee );
    }
}

add_action( 'woocommerce_check_cart_items', 'devguru_check_minimum_order_amount' );
function devguru_check_minimum_order_amount() {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    $cart_total = WC()->cart->get_total( 'edit' );

    if ( $cart_total < DEVGURU_MIN_ORDER_AMOUNT ) {
        wc_add_notice(
            sprintf(
                'حداقل مبلغ خرید %s تومان می‌باشد. لطفاً محصولات بیشتری به سبد خرید اضافه کنید.',
                number_format( DEVGURU_MIN_ORDER_AMOUNT )
            ),
            'error'
        );
    }
}

add_action( 'wp_footer', 'devguru_disable_checkout_button_if_needed' );
function devguru_disable_checkout_button_if_needed() {
    if ( ! is_cart() || ! WC()->cart ) {
        return;
    }

    $cart_total = WC()->cart->get_total( 'edit' );

    if ( $cart_total < DEVGURU_MIN_ORDER_AMOUNT ) :
        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
                const checkoutButton = document.querySelector('.checkout-button');
                if (checkoutButton) {
                    checkoutButton.classList.add('disabled');
                    checkoutButton.style.pointerEvents = 'none';
                    checkoutButton.style.opacity = '0.5';
                }
            });
        </script>
        <?php
    endif;
}

add_action( 'woocommerce_checkout_process', 'devguru_prevent_checkout_below_minimum' );
function devguru_prevent_checkout_below_minimum() {
    $cart_total = WC()->cart->get_total( 'edit' );

    if ( $cart_total < DEVGURU_MIN_ORDER_AMOUNT ) {
        wc_add_notice(
            sprintf(
                'حداقل مبلغ سفارش %s تومان است. امکان ثبت سفارش وجود ندارد.',
                number_format( DEVGURU_MIN_ORDER_AMOUNT )
            ),
            'error'
        );
    }
}

add_action( 'woocommerce_order_details_after_order_table', 'devguru_show_shipping_notice_on_order_details', 10 );
function devguru_show_shipping_notice_on_order_details( $order ) {
    ?>
    <div class="devguru-shipping-notice" style="margin-top:20px;padding:15px;background:#f9f9f9;border-right:4px solid #2271b1;font-size:14px;line-height:2;">
        <strong>اطلاعیه ارسال سفارش:</strong><br>
        سفارشات تا 3 روز کاری زمان میبرد بسته بندی شود<br>
        بعد از 3 روز کاری حدودا 4 الی 8 روز کاری زمان میبرد برسد به دستتان
    </div>
    <?php
}
