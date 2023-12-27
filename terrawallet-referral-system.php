<?php
/**
 * Plugin Name: TeraWallet Referral System
 * Description: Adds a referral system to TeraWallet with enhanced features including "Copy Invite Link" and "Copy Template Message" functionalities, a welcome bonus for new users, and a list of all successful invites with timestamps, optimized for both desktop and mobile viewing.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: terawallet-referral
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Add referral settings section and fields
add_filter('woo_wallet_settings_sections', 'tw_referral_settings_section');
add_filter('woo_wallet_settings_fields', 'tw_referral_settings_fields');
function tw_referral_settings_section($sections) {
    $sections[] = [
        'id' => '_wallet_settings_referral',
        'title' => __('Referral Options', 'terawallet-referral'),
        'icon' => 'dashicons-groups'
    ];
    return $sections;
}
function tw_referral_settings_fields($settings) {
    $settings['_wallet_settings_referral'] = [
        [
            'name' => 'referral_amount',
            'label' => __('Referral Amount', 'terawallet-referral'),
            'desc' => __('Amount to credit for each successful referral', 'terawallet-referral'),
            'type' => 'number',
            'default' => 10
        ]
    ];
    return $settings;
}

// Set a cookie for the referral ID
add_action('template_redirect', 'tw_set_referral_cookie');
function tw_set_referral_cookie() {
    if (isset($_GET['ref']) && !is_user_logged_in()) {
        $referrer_id = intval($_GET['ref']);
        setcookie('tw_referral', $referrer_id, time() + 432000, COOKIEPATH, COOKIE_DOMAIN); // Expires in 5 days
    }
}

// Handle referral on new user registration
add_action('user_register', 'tw_handle_user_referral');
function tw_handle_user_referral($user_id) {
    if (isset($_COOKIE['tw_referral']) || isset($_GET['ref'])) {
        $referrer_id = isset($_COOKIE['tw_referral']) ? intval($_COOKIE['tw_referral']) : intval($_GET['ref']);
        if ($referrer_id) {
            // Get the IP address and browser fingerprint of the new user
            $newUserIP = $_SERVER['REMOTE_ADDR'];
            $newUserBrowser = $_SERVER['HTTP_USER_AGENT'];

            // Retrieve the IP and browser fingerprint of the referrer
            $referrerIP = get_user_meta($referrer_id, 'last_ip', true);
            $referrerBrowser = get_user_meta($referrer_id, 'last_browser', true);

            // Check for self-referral: same IP and browser fingerprint
            if ($newUserIP === $referrerIP && $newUserBrowser === $referrerBrowser) {
                // Handle self-referral case (e.g., log, notify, or invalidate the referral)
                error_log("Possible self-referral detected for user ID: $user_id");
                return;
            }

            // Update the referrer's last IP and browser fingerprint
            update_user_meta($referrer_id, 'last_ip', $newUserIP);
            update_user_meta($referrer_id, 'last_browser', $newUserBrowser);

            // Proceed with referral credit logic
            update_user_meta($user_id, 'tw_referrer_id', $referrer_id);
            update_user_meta($user_id, 'tw_referral_signup_timestamp', current_time('mysql', true)); // Store UTC time
            $referral_amount = woo_wallet()->settings_api->get_option('referral_amount', '_wallet_settings_referral', 10);
            $welcome_bonus = 100; // PHP 100 welcome bonus for the new user

            // Credit referrer
            if ($referral_amount) {
                woo_wallet()->wallet->credit($referrer_id, $referral_amount, __('Referral bonus credited', 'terawallet-referral'));
                $current_count = (int) get_user_meta($referrer_id, 'tw_referral_count', true);
                update_user_meta($referrer_id, 'tw_referral_count', $current_count + 1);
                $total_incentive = (float) get_user_meta($referrer_id, 'tw_total_referral_incentive', true);
                update_user_meta($referrer_id, 'tw_total_referral_incentive', $total_incentive + $referral_amount);
            }

            // Credit new user
            woo_wallet()->wallet->credit($user_id, $welcome_bonus, __('Welcome Bonus', 'terawallet-referral'));
        }
    }
}

// Add a new endpoint for displaying referral content in My Account
add_action('init', 'tw_add_invite_endpoint');
add_filter('query_vars', 'tw_invite_query_vars', 0);
add_action('woocommerce_account_invite_endpoint', 'tw_invite_content');
function tw_add_invite_endpoint() {
    add_rewrite_endpoint('invite', EP_ROOT | EP_PAGES);
}
function tw_invite_query_vars($vars) {
    $vars[] = 'invite';
    return $vars;
}

function tw_invite_content() {
    $user_id = get_current_user_id();
    $invite_link = wc_get_page_permalink('myaccount') . 'invite/?ref=' . $user_id;
    $template_message = "Uy, friendship! 👋 Share ko lang 'tong magandang balita sa'yo! Ginagamit ko ngayon itong bonggang online piggy bank service na super helpful sa pag-iipon ko. Sobrang dali lang gamitin, promise! Eto pa, pag nag-sign up ka gamit ang Invitation Link ko, may instant PHP100 ka agad para sa simula ng iyong ePon journey! Tamang-tama 'to para maabot ang iyong mga financial goals ngayong 2024! Tara na, i-click mo lang 'to: " . $invite_link . ". Simulan natin together ang masayang pag-iipon now na! 😊";

    // Display title and referral link with enhanced copy button
    echo '<h3>My Invite Link</h3>';
    echo '<div id="invite-link-container" style="margin-bottom: 20px;">';
    echo '<input id="invite-link" value="' . esc_url($invite_link) . '" readonly style="width: 100%; margin-bottom: 10px;">';
    echo '<button onclick="copyInviteLink()" style="width: 100%; background-color: #FF6406; color: white; padding: 11px 0; font-size: 110%;">Copy Invite Link</button>';
    echo '</div>';

    // Display template message section
    echo '<h3>My Template Message</h3>';
    echo '<div id="template-message-container" style="margin-bottom: 10px;">';
    echo '<textarea id="template-message" readonly style="width: 100%; margin-bottom: 10px; height: 150px;">' . $template_message . '</textarea>';
    echo '<button onclick="copyTemplateMessage()" style="width: 100%; background-color: #FF6406; color: white; padding: 11px 0; font-size: 110%;">Copy Template Message</button>';
    echo '</div>';

    // Added wording and divider with spacing adjustments
    echo '<p style="margin-top: 18px; font-size: 90%;">NOTE: Once you copied your Invitation Link or Template Message Link, begin sharing it with your friends using your social media accounts. Please AVOID spamming to prevent sharing restrictions from your social media channels.</p>';
    echo '<hr style="height: 5px; background-color: #FF6406; border: none; margin-top: 10px; margin-bottom: 10px;">';

    // Display referral information in a table
    echo '<h3>Invites Summary Report</h3>';
    $referral_count = (int) get_user_meta($user_id, 'tw_referral_count', true);
    $total_incentive = (float) get_user_meta($user_id, 'tw_total_referral_incentive', true);
    echo '<table class="woocommerce-table woocommerce-table--invite-details shop_table invite_details">';
    echo '<tbody>';
    echo '<tr><th>' . __('Number of Successful Invites', 'terawallet-referral') . '</th><td>' . $referral_count . '</td></tr>';
    echo '<tr><th>' . __('Total Invite Rewards Earned', 'terawallet-referral') . '</th><td>' . wc_price($total_incentive) . '</td></tr>';
    echo '</tbody></table>';

    // Divider before the List of Successful Invites
    echo '<hr style="height: 5px; background-color: #FF6406; border: none; margin-top: 10px; margin-bottom: 10px;">';

    // Display List of Successful Invites in a table
    echo '<h3>' . __('List of Successful Invites', 'terawallet-referral') . '</h3>';
    $referred_users = tw_get_all_referred_users($user_id);
    if (!empty($referred_users)) {
        echo '<table class="woocommerce-table woocommerce-table--referrals shop_table referrals" style="width: 100%;">';
        echo '<tbody>';
        foreach ($referred_users as $referral) {
            $signup_timestamp = get_user_meta($referral->ID, 'tw_referral_signup_timestamp', true);
            $formatted_timestamp = date_i18n('F j, Y g:i a', strtotime($signup_timestamp) + (get_option('gmt_offset') * HOUR_IN_SECONDS));
            echo '<tr><td>' . esc_html($referral->user_login) . '</td><td>' . $formatted_timestamp . '</td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>' . __('No successful invites yet.', 'terawallet-referral') . '</p>';
    }
}

// Function to get all referred users
function tw_get_all_referred_users($referrer_id) {
    global $wpdb;
    $referred_users = $wpdb->get_results($wpdb->prepare(
        "SELECT u.ID, u.user_login FROM {$wpdb->users} u
         INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
         WHERE um.meta_key = 'tw_referrer_id' AND um.meta_value = %d",
        $referrer_id
    ));
    return $referred_users;
}

// Add JavaScript for copy-to-clipboard functionality
add_action('wp_footer', 'tw_add_copy_script');
function tw_add_copy_script() {
    echo '<script type="text/javascript">
    function copyInviteLink() {
        var copyText = document.getElementById("invite-link");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        document.execCommand("copy");
        alert("Invite Link successfully copied!");
    }
    function copyTemplateMessage() {
        var copyText = document.getElementById("template-message");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        document.execCommand("copy");
        alert("Template Message successfully copied!");
    }
    </script>';
}

// Add invite endpoint to My Account menu
add_filter('woocommerce_account_menu_items', 'tw_add_invite_link_my_account');
function tw_add_invite_link_my_account($items) {
    $items['invite'] = __('Invites', 'terawallet-referral');
    return $items;
}

// Add custom CSS for mobile view
add_action('wp_head', 'tw_custom_styles');
function tw_custom_styles() {
    echo '<style>
        @media only screen and (max-width: 768px) {
            .woocommerce-table--referrals td {
                font-size: 100%; /* Adjust username font size */
            }
            .woocommerce-table--referrals td:nth-child(2) {
                font-size: 80%; /* Adjust timestamp font size */
            }
        }
    </style>';
}
?>
