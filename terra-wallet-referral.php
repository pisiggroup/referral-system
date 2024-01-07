<?php
/**
 * Plugin Name: TerraWallet Referral
 * Description: Referral system for TerraWallet.
 * Version: 1.0
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Activation hook for setting up the database table
register_activation_hook( __FILE__, 'twr_install' );

function twr_install() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'twr_referrals';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        referrer_id bigint(20) NOT NULL,
        first_topup_done boolean DEFAULT false NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

// Add new query var
function twr_add_query_vars( $vars ) {
    $vars[] = 'referrals';
    return $vars;
}

add_filter( 'query_vars', 'twr_add_query_vars', 0 );

// Add new endpoint to the My Account menu
function twr_add_endpoints() {
    add_rewrite_endpoint( 'referrals', EP_ROOT | EP_PAGES );
}

add_action( 'init', 'twr_add_endpoints' );

// Flush rewrite rules on plugin activation
function twr_flush_rewrite_rules() {
    twr_add_endpoints();
    flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'twr_flush_rewrite_rules' );

// Rename menu item to 'Invites'
function twr_add_menu_items( $items ) {
    $items['referrals'] = 'Invites';
    return $items;
}

add_filter( 'woocommerce_account_menu_items', 'twr_add_menu_items' );

// Content for the new endpoint
function twr_referrals_endpoint_content() {
    $user_id = get_current_user_id();
    global $wpdb;
    $referrals_table = $wpdb->prefix . 'twr_referrals';
    $users_table = $wpdb->prefix . 'users';

    // Fetch successful referrals with usernames
    $successful_referrals = $wpdb->get_results( $wpdb->prepare( 
        "SELECT u.user_login FROM $users_table u
         INNER JOIN $referrals_table r ON u.ID = r.user_id
         WHERE r.referrer_id = %d AND r.first_topup_done = 1", 
        $user_id 
    ));

    $referral_link = add_query_arg( 'invite', $user_id, 'https://tarasuki.xyz/epon/' );
    $template_message = "Uy, friend! 🌟 Alam mo ba, may exciting challenge ako na gusto kong i-share sa'yo! Sumali ka na sa aming 'Ipon Challenge' gamit itong astig na online piggy bank platform na super user-friendly at perfect sa pag-iipon. Sobrang dali lang, swear! 🐷✨\n\nPag nag-sign up ka gamit ang Invitation Link ko, may welcome gift ka agad na PHP100 para i-kickstart ang iyong ipon journey! Perfect 'to para sa mga financial goals mo ngayong 2024. Tara, sumali na at mag-ipon tayo! I-click mo lang ito: " . esc_url( $referral_link ) . " at simulan na natin ang ating masayang Ipon Challenge! 🚀🎉\n\nKita-kits sa challenge, bes! Let's achieve our financial dreams together! 💸😊";

    ?>
    <div class="invites-page">
        <h2>My Invitation Link</h2>
        <input type="text" value="<?php echo esc_url( $referral_link ); ?>" id="referralLink" readonly style="width: 100%; margin-bottom: 10px;">
        <button onclick="copyToClipboard('referralLink', 'Invitation Link copied successfully! Please AVOID spamming to prevent sharing restrictions from your social media channels. Share responsibly!')" class="button alt">Copy Invitation Link</button>

        <hr style="height: 5px; background-color: #yourColorCode; border: none; margin-top: 60px;">

        <h2>My Message Template</h2>
        <div style="border: 1px solid #ccc; padding: 10px; margin-top: 10px;">
            <p id="messageTemplate"><?php echo nl2br($template_message); ?></p>
        </div>
        <div style="margin-top: 10px;">
            <button onclick="copyToClipboard('messageTemplate', 'Message Template copied successfully! Please AVOID spamming to prevent sharing restrictions from your social media channels. Share responsibly')" class="button alt">Copy Message Template</button>
        </div>

        <hr style="height: 5px; background-color: #yourColorCode; border: none; margin-top: 60px;">

        <h2>My Successful Invites</h2>
        <table style="width:100%;">
            <tr><th>Referral Username</th></tr>
            <?php foreach ( $successful_referrals as $referral ) : ?>
                <tr><td><?php echo esc_html( $referral->user_login ); ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>

    <script>
        function copyToClipboard(elementId, successMessage) {
            var copyText = document.getElementById(elementId);
            var range = document.createRange();
            range.selectNode(copyText);
            window.getSelection().removeAllRanges(); // Clear current selection
            window.getSelection().addRange(range); // Select the text
            document.execCommand("copy");
            window.getSelection().removeAllRanges(); // Clear selection

            alert(successMessage);
        }
    </script>
    <?php
}

add_action( 'woocommerce_account_referrals_endpoint', 'twr_referrals_endpoint_content' );

// Handle referral link and set cookie
function twr_handle_referral_link() {
    if ( isset( $_GET['invite'] ) ) {
        $referrer_id = intval( $_GET['invite'] );
        setcookie( 'twr_referrer_id', $referrer_id, time() + (5 * 24 * 60 * 60), COOKIEPATH, COOKIE_DOMAIN );
    }
}

add_action( 'init', 'twr_handle_referral_link' );

// Handle referral signup and credit new user
function twr_handle_referral_signup( $new_user_id ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'twr_referrals';

    $wallet = new Woo_Wallet_Wallet();
    $wallet->credit( $new_user_id, 100, 'Welcome Bonus' );

    if ( isset( $_COOKIE['twr_referrer_id'] ) ) {
        $referrer_id = intval( $_COOKIE['twr_referrer_id'] );

        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $new_user_id,
                'referrer_id' => $referrer_id,
                'first_topup_done' => false
            ),
            array( '%d', '%d', '%d' )
        );
    }
}

add_action( 'user_register', 'twr_handle_referral_signup' );

// Handle wallet credit purchase
function twr_wallet_credit_purchase( $order_id ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'twr_referrals';

    $order = wc_get_order( $order_id );
    $user_id = $order->get_user_id();

    $referral_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE user_id = %d", $user_id ) );

    if ( $referral_data && !$referral_data->first_topup_done ) {
        $wallet = new Woo_Wallet_Wallet();
        $wallet->credit( $referral_data->referrer_id, 20, 'New Signup Reward!' ); // Updated reward to PHP20

        $wpdb->update(
            $table_name,
            array( 'first_topup_done' => true ),
            array( 'user_id' => $user_id ),
            array( '%d' ),
            array( '%d' )
        );
    }
}

add_action( 'woocommerce_order_status_completed', 'twr_wallet_credit_purchase' );

function tw_referral_settings_fields($settings) {
    $settings['_wallet_settings_referral'] = [
        [
            'name' => 'referral_amount',
            'label' => __('Referral Amount', 'terawallet-referral'),
            'desc' => __('Amount to credit for each successful referral', 'terawallet-referral'),
            'type' => 'number',
            'default' => 20 // Updated referral amount
        ]
    ];
    return $settings;
}


function tw_handle_user_referral($user_id) {
    if (isset($_COOKIE['tw_referral']) || isset($_GET['ref'])) {
        $referrer_id = isset($_COOKIE['tw_referral']) ? intval($_COOKIE['tw_referral']) : intval($_GET['ref']);
        if ($referrer_id) {
            // Existing logic for checking self-referral and updating metadata

            $referral_amount = woo_wallet()->settings_api->get_option('referral_amount', '_wallet_settings_referral', 20);
            $welcome_bonus = 100; // PHP 100 welcome bonus for the new user

            if ($referral_amount) {
                woo_wallet()->wallet->credit($referrer_id, $referral_amount, __('Invitation Incentive', 'terawallet-referral')); // Updated description
                // Logic for updating referral count and total incentive
            }

            // Award welcome bonus only if signed up using a referral link
            woo_wallet()->wallet->credit($user_id, $welcome_bonus, __('Welcome Bonus', 'terawallet-referral'));
        }
    }
}
