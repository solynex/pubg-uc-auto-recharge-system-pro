<?php
if (!defined('ABSPATH')) exit;

class PUBG_Bundle {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('wp_ajax_pubg_toggle_bundle_mode', array($this, 'ajax_toggle_bundle_mode'));
        add_filter('pubg_category_form_fields', array($this, 'add_bundle_fields'), 10, 1);
        add_action('pubg_save_category', array($this, 'save_bundle_settings'), 10, 2);
        add_action('pubg_process_codes', array($this, 'process_bundle_codes'), 10, 3);
        add_filter('pubg_display_log_entry', array($this, 'display_bundle_log'), 10, 2);
        add_filter('pubg_allocate_code', array($this, 'handle_bundle_allocation'), 10, 5);
    }
    
    public function add_bundle_fields($category_id = null) {
        $is_bundle = false;
        if ($category_id) {
            $is_bundle = get_option("pubg_category_{$category_id}_is_bundle", false);
        }
        ?>
        <tr>
            <th scope="row">Bundle Category</th>
            <td>
                <label>
                    <input type="checkbox" name="is_bundle_category" value="1" <?php checked($is_bundle, 1); ?> id="bundle-checkbox">
                    Enable Bundle Mode (كودين في سطر واحد)
                </label>
                <p class="description">في الـ Bundle Mode: كل سطر يحتوي على كودين مفصولين بفاصلة</p>
            </td>
        </tr>
        
        <script>
        jQuery(document).ready(function($) {
            function toggleBundleMode() {
                var isBundle = $('#bundle-checkbox').is(':checked');
                var $textarea = $('textarea[name="codes"]');
                
                if (isBundle) {
                    $textarea.attr('placeholder', 'كود1,كود2\nكود3,كود4\nكود5,كود6\n\nمثال:\nABC123-DEF456,GHI789-JKL012\nMNO345-PQR678,STU901-VWX234');
                    $textarea.closest('tr').find('.description').html('أدخل كودين في كل سطر مفصولين بفاصلة. الكودين سيتم إرسالهم معاً كـ bundle.');
                } else {
                    $textarea.attr('placeholder', 'كود واحد في كل سطر\nABC123-DEF456\nGHI789-JKL012\nMNO345-PQR678');
                    $textarea.closest('tr').find('.description').html('أدخل كود واحد في كل سطر. سيتم تجاهل الأسطر المكررة.');
                }
            }
            
            $('#bundle-checkbox').change(toggleBundleMode);
            toggleBundleMode();
        });
        </script>
        <?php
    }
    
    public function save_bundle_settings($category_id, $data) {
        $is_bundle = isset($data['is_bundle_category']) ? 1 : 0;
        update_option("pubg_category_{$category_id}_is_bundle", $is_bundle);
    }
    
    public function process_bundle_codes($category_id, $codes_array, $is_bundle) {
        if (!$is_bundle) {
            return $this->process_normal_codes($category_id, $codes_array);
        }
        
        global $wpdb;
        $codes_table = $wpdb->prefix . 'pubg_recharge_codes';
        $added_count = 0;
        $error_count = 0;
        
        foreach ($codes_array as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $bundle_codes = array_map('trim', explode(',', $line));
            
            if (count($bundle_codes) != 2) {
                $error_count++;
                continue;
            }
            
            $code1 = $bundle_codes[0];
            $code2 = $bundle_codes[1];
            
            if (empty($code1) || empty($code2)) {
                $error_count++;
                continue;
            }
            
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $codes_table WHERE code = %s OR code LIKE %s OR code LIKE %s",
                $code1 . '|' . $code2,
                $code1 . '|%',
                '%|' . $code1
            ));
            
            if ($existing) {
                $error_count++;
                continue;
            }
            
            $inserted = $wpdb->insert($codes_table, array(
                'category_id' => $category_id,
                'code' => $code1 . '|' . $code2,
                'is_bundle' => 1,
                'status' => 'available'
            ));
            
            if ($inserted) {
                $added_count++;
            } else {
                $error_count++;
            }
        }
        
        return array(
            'added' => $added_count,
            'errors' => $error_count,
            'type' => 'bundle'
        );
    }
    
    private function process_normal_codes($category_id, $codes_array) {
        global $wpdb;
        $codes_table = $wpdb->prefix . 'pubg_recharge_codes';
        $added_count = 0;
        $duplicate_count = 0;
        
        foreach ($codes_array as $code) {
            $code = trim($code);
            if (empty($code)) continue;
            
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $codes_table WHERE code = %s", $code
            ));
            
            if ($existing) {
                $duplicate_count++;
                continue;
            }
            
            $inserted = $wpdb->insert($codes_table, array(
                'category_id' => $category_id,
                'code' => $code,
                'is_bundle' => 0,
                'status' => 'available'
            ));
            
            if ($inserted) {
                $added_count++;
            }
        }
        
        return array(
            'added' => $added_count,
            'duplicates' => $duplicate_count,
            'type' => 'normal'
        );
    }
    
    public function handle_bundle_allocation($result, $order_id, $product_id, $variation_id, $player_id, $item_id) {
        global $wpdb;
        
        $codes_table = $wpdb->prefix . 'pubg_recharge_codes';
        $log_table = $wpdb->prefix . 'pubg_recharge_logs';
        
        $category_id = $this->get_category_id($product_id, $variation_id);
        if (!$category_id) {
            return false;
        }
        
        $is_bundle_category = get_option("pubg_category_{$category_id}_is_bundle", false);
        
        if (!$is_bundle_category) {
            return $result;
        }
        
        $existing_log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $log_table WHERE order_id = %d AND player_id = %s AND status != 'failed'",
            $order_id, $player_id
        ));
        
        if ($existing_log) {
            return true;
        }
        
        $wpdb->query('START TRANSACTION');
        
        try {
            $wpdb->query("LOCK TABLES $codes_table WRITE");
            
            $bundle_code = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $codes_table WHERE category_id = %d AND is_bundle = 1 AND status = 'available' ORDER BY id ASC LIMIT 1",
                $category_id
            ));
            
            if (!$bundle_code) {
                $wpdb->query("UNLOCK TABLES");
                $wpdb->query('ROLLBACK');
                
                $wpdb->insert($log_table, array(
                    'code_id' => 0,
                    'order_id' => $order_id,
                    'player_id' => $player_id,
                    'is_bundle' => 1,
                    'status' => 'failed',
                    'message' => 'No bundle codes available for this category'
                ));
                return false;
            }
            
            $updated = $wpdb->update($codes_table, array(
                'status' => 'used',
                'order_id' => $order_id,
                'player_id' => $player_id,
                'date_used' => current_time('mysql')
            ), array('id' => $bundle_code->id));
            
            $wpdb->query("UNLOCK TABLES");
            
            if ($updated) {
                $wpdb->query('COMMIT');
                
                $this->process_bundle_api($bundle_code->id, $bundle_code->code, $player_id, $order_id);
                return true;
            } else {
                $wpdb->query('ROLLBACK');
                return false;
            }
            
        } catch (Exception $e) {
            $wpdb->query("UNLOCK TABLES");
            $wpdb->query('ROLLBACK');
            return false;
        }
    }
    
    private function process_bundle_api($code_id, $bundle_codes, $player_id, $order_id) {
        global $wpdb;
        $log_table = $wpdb->prefix . 'pubg_recharge_logs';
        
        $codes = explode('|', $bundle_codes);
        $results = array();
        $success_count = 0;
        
        foreach ($codes as $code) {
            $result = pubg_activate_uc_code($player_id, trim($code));
            
            if ($result['success'] && isset($result['data']['status']) && $result['data']['status'] === 'success') {
                $results[] = 'success';
                $success_count++;
            } else {
                $results[] = 'failed';
            }
        }
        
        $total_codes = count($codes);
        
        if ($success_count == $total_codes) {
            $overall_status = 'success';
            $message = 'Bundle activated successfully';
        } elseif ($success_count == 0) {
            $overall_status = 'failed';
            $message = 'Bundle activation failed completely';
        } else {
            $overall_status = 'partial';
            $message = "Bundle partially successful: {$success_count}/{$total_codes} codes activated";
        }
        
        $wpdb->insert($log_table, array(
            'code_id' => $code_id,
            'order_id' => $order_id,
            'player_id' => $player_id,
            'code' => $bundle_codes,
            'is_bundle' => 1,
            'codes_status' => implode('|', $results),
            'status' => $overall_status,
            'message' => $message
        ));
        
        $order = wc_get_order($order_id);
        if ($order) {
            if ($overall_status === 'success') {
                $order->update_status('completed', 'PUBG Bundle activated successfully for Player ID: ' . $player_id);
            } else {
                $order->add_order_note('PUBG Bundle processing completed for Player ID: ' . $player_id . ' - ' . $message);
            }
        }
    }
    
    public function display_bundle_log($html, $log) {
        if (!$log->is_bundle) {
            return $html;
        }
        
        $codes = explode('|', $log->code);
        $statuses = explode('|', $log->codes_status);
        
        $html = '<div class="bundle-log-display">';
        
        foreach ($codes as $index => $code) {
            $status = $statuses[$index] ?? 'unknown';
            $icon = $status === 'success' ? '✅' : ($status === 'failed' ? '❌' : '⏳');
            $class = $status === 'success' ? 'success' : ($status === 'failed' ? 'failed' : 'pending');
            
            $html .= '<div class="bundle-code-line">';
            $html .= '<code class="bundle-code">' . esc_html($code) . '</code>';
            $html .= '<span class="bundle-status ' . $class . '">' . $icon . ' ' . ucfirst($status) . '</span>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        $html .= '<style>
        .bundle-log-display {
            display: flex;
            flex-direction: column;
            gap: 4px;
            max-width: 300px;
        }
        .bundle-code-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 8px;
            background: #f8f9fa;
            border-radius: 3px;
            border-left: 3px solid #dee2e6;
        }
        .bundle-code {
            font-family: monospace;
            font-size: 11px;
            font-weight: bold;
            background: #e9ecef;
            padding: 2px 4px;
            border-radius: 2px;
        }
        .bundle-status {
            font-size: 11px;
            font-weight: 600;
        }
        .bundle-status.success { color: #28a745; }
        .bundle-status.failed { color: #dc3545; }
        .bundle-status.pending { color: #ffc107; }
        .bundle-code-line:has(.success) { border-left-color: #28a745; }
        .bundle-code-line:has(.failed) { border-left-color: #dc3545; }
        .bundle-code-line:has(.pending) { border-left-color: #ffc107; }
        </style>';
        
        return $html;
    }
    
    private function get_category_id($product_id, $variation_id) {
        $is_multi_variation = get_post_meta($product_id, 'is_pubg_multi_variation', true);
        
        if ($is_multi_variation == 'yes' && $variation_id) {
            $is_variation_enabled = get_post_meta($variation_id, 'is_pubg_variation_enabled', true);
            if ($is_variation_enabled == 'yes') {
                return get_post_meta($variation_id, 'pubg_variation_category_id', true);
            }
        } else {
            return get_post_meta($product_id, 'pubg_category_id', true);
        }
        
        return false;
    }
    
    public function ajax_toggle_bundle_mode() {
        check_ajax_referer('pubg-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }
        
        $category_id = intval($_POST['category_id']);
        $is_bundle = isset($_POST['is_bundle']) ? 1 : 0;
        
        update_option("pubg_category_{$category_id}_is_bundle", $is_bundle);
        
        wp_send_json_success(array(
            'message' => 'Bundle mode updated successfully',
            'is_bundle' => $is_bundle
        ));
    }
    
    public function upgrade_database() {
        global $wpdb;
        $codes_table = $wpdb->prefix . 'pubg_recharge_codes';
        $log_table = $wpdb->prefix . 'pubg_recharge_logs';
        
        $codes_columns = $wpdb->get_col("DESCRIBE $codes_table");
        if (!in_array('is_bundle', $codes_columns)) {
            $wpdb->query("ALTER TABLE $codes_table ADD COLUMN is_bundle TINYINT(1) DEFAULT 0 AFTER code");
        }
        
        $log_columns = $wpdb->get_col("DESCRIBE $log_table");
        if (!in_array('is_bundle', $log_columns)) {
            $wpdb->query("ALTER TABLE $log_table ADD COLUMN is_bundle TINYINT(1) DEFAULT 0 AFTER player_id");
        }
        if (!in_array('codes_status', $log_columns)) {
            $wpdb->query("ALTER TABLE $log_table ADD COLUMN codes_status TEXT NULL AFTER is_bundle");
        }
    }
}

new PUBG_Bundle();
