<?php
/*
Plugin Name: Change Products in Processing Orders 2
Description: Allow customers to modify products in 'processing' orders by selecting a new product from the shop.
Version: 1.0.0
Author: Nikos Vougioukakis
*/

/*TODO: Add beforeunload script to cancel the order modification and make session forget,
*       once user clicks Change this product.
*       Should stop once a product is selected and run again only if another is selected to be changed.
*/

// TODO: If customer pays by card, add way to pay for the difference.


session_start(); //need at the start of every script
//error_log("Session order id:" . $_SESSION['current_order_id'] . " and replaced product: " . $_SESSION['replace_product']);

add_action( 'woocommerce_order_details_after_order_table', 'custom_change_order_products_button', 10, 1 );
/**
 * By hooking onto the woocommerce_order_details_after_order_table, this function will
 * put a 'Modify Order' button there which will show a form when clicked. The form has a button to replace
 * a product in the order under each product.
 * 
 * Buttons will include a query argument 'replace_product' with the order item id to be replaced as a value.
 * Order item id is different from woocommerce product id.
 */
function custom_change_order_products_button( $order ) {
    if ( $order->get_status() == 'processing' ) {
        echo '<button id="modify-order-button" class="button">Modify Order</button>';
        echo '<h2 id="modify-order-title" class="woocommerce-column__title" style="display:none;">Modify Order</h2>';
        echo '<div id="modify-order-form" style="display:none;">';

        // for each order item
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            echo '<p>Product: ' . $product->get_name() . ' - x' . $item->get_quantity() . '</p>';
            echo '<p><a href="' . esc_url( add_query_arg( 'replace_product', $item->get_id(), get_permalink( wc_get_page_id( 'shop' ) ) ) ) . '" class="button">Choose a new product</a></p>';
        }

        echo '</div>';

        // store the order id in session, couldnt retrieve when in shop page
        $_SESSION['current_order_id'] = $order->get_id();

    }
}

add_action( 'template_redirect', 'track_selected_product_for_replacement' );
/**
 * Pull the replaced product id from the query arg into the session.
 */
function track_selected_product_for_replacement() {
    if ( isset( $_GET['replace_product'] )) {
        $order_item_id = intval( $_GET['replace_product'] );
        $_SESSION['replace_product'] = $order_item_id;

        $order = wc_get_order($_SESSION['current_order_id']);
        $order_item = $order->get_item( $order_item_id );
        $product = $order_item->get_product();
        $product_id = $product->get_id();

        $_SESSION['replace_product_id'] = $product_id;
        error_log('  - -  -  - - - - replacing product with woocommerce id = '.$product_id);
    }
}

add_action( 'template_redirect', 'process_product_replacement' );

function process_product_replacement() {
    // check if 1. a product was selected for replacement and saved in session 
    // 2. if the new product id has been set as query arg, 3. if the order id is saved in session
    if ( isset( $_SESSION['replace_product'] ) && isset( $_GET['new_product_id'] ) && isset( $_SESSION['current_order_id'] ) ) {
        
        $order_id = $_SESSION['current_order_id'];
        $order = wc_get_order( $order_id );

        if (!$order) {
            //show an error and redirect
            wc_add_notice( 'Order not found, please try again.', 'error' );
            wp_redirect( wc_get_page_id( 'myaccount' ) );
            exit;
        }

        $replace_item_id_order = $_SESSION['replace_product'];
        $new_product_id = intval( $_GET['new_product_id'] );

        // replace the item with the new product
        foreach ( $order->get_items() as $item_id => $item ) {
            if ( $item->get_id() == $replace_item_id_order ) {
                $order->remove_item( $item_id );
                $order->add_product( wc_get_product( $new_product_id ), $item->get_quantity() );

                $order->calculate_totals();
                $order->save();
                // make session forget to avoid bugs
                session_unset();

                wc_add_notice( 'Your product has been changed successfully!', 'success' );
                wp_redirect( $order->get_view_order_url() );
                exit;
            }
        }
    }
}

add_action( 'wp_footer', 'enqueue_modify_order_script' );
/**
 * includes the script to show and hide the modify order form, the 'Modify Order' heading and the button.
 */
function enqueue_modify_order_script() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#modify-order-button').click(function() {
                $(this).hide();
                $('#modify-order-title').show();
                $('#modify-order-form').show();
            });
        });
    </script>
    <?php
}


add_action( 'woocommerce_after_shop_loop_item', 'add_select_product_button', 20 );
/**
 * Runs for every product in products to add a button next to 'add to cart'.
 * Only shows the button when theres a product we want to replace stored in the session.
 * The select button adds the query arg 'new_product_id'.
 */
function add_select_product_button() {
    if ( isset( $_SESSION['replace_product'] ) ) {
        $replace_item_id_order = $_SESSION['replace_product'];
        $replace_item_id = $_SESSION['replace_product_id'];

        error_log('product id in iter: ' . get_the_ID());
        // TESTING: Only enable this for products that have price >= than chosen.

        error_log('replace_item_id_order: '. $replace_item_id_order);
        error_log('replace_item_id: '. $replace_item_id);

        $product = wc_get_product( $replace_item_id );
        $current_product_price = $product->get_price();
        error_log('current_product_price:' . $current_product_price);
        $iteration_product_price = get_post_meta( get_the_ID(), '_price', true );
        error_log('iteration product price'. $iteration_product_price);

        if ($current_product_price <= $iteration_product_price) {
            echo '<a href="' . esc_url( add_query_arg( 'new_product_id', get_the_ID() ) ) . '" class="button">Select this product</a>';
        }


    } else {
        error_log('user is not replacing');
    }
}
