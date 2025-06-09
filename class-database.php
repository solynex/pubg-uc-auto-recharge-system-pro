<?php
if (!defined('ABSPATH')) exit;

class PUBG_Database {
    
    private $wpdb;
    private $codes_table;
    private $categories_table;
    private $log_table;
    private $pending_table;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->codes_table = $wpdb->prefix . 'pubg_recharge_codes';
        $this->categories_table = $wpdb->prefix . 'pubg_recharge_categories';
        $this->log_table = $wpdb->prefix . 'pubg_recharge_logs';
        $this->pending_table = $wpdb->prefix . 'pubg_pending_operations';
    }
    
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql_categories = "CREATE TABLE {$this->categories_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            uc_amount int NOT NULL,
            description text NULL,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uc_amount (uc_amount)
        ) $charset_collate;";
        
        $sql_codes = "CREATE TABLE {$this->codes_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            category_id mediumint(9) NOT NULL,
            code varchar(255) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'available',
            order_id bigint(20) NULL,
            player_id varchar(255) NULL,
            date_added datetime DEFAULT CURRENT_TIMESTAMP,
            date_used datetime NULL,
            retry_count int DEFAULT 0,
            last_error text NULL,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY category_id (category_id),
            KEY status (status),
            KEY order_id (order_id)
        ) $charset_collate;";
        
     $sql_log = "CREATE TABLE {$this->log_table} (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    code_id mediumint(9) NOT NULL,
    order_id bigint(20) NOT NULL,
    player_id varchar(255) NOT NULL,
    status varchar(50) NOT NULL,
    message text NULL,
    debug_data text NULL,
    date_created datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY code_id (code_id),
    KEY order_id (order_id),
    KEY status (status),
    KEY date_created (date_created)
) $charset_collate;";

$sql_pending = "CREATE TABLE {$this->pending_table} (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    order_id bigint(20) NOT NULL,
    item_id bigint(20) NOT NULL,
    product_id mediumint(9) NOT NULL,
    variation_id mediumint(9) NULL,
    player_id varchar(255) NOT NULL,
    category_id mediumint(9) NOT NULL,
    status varchar(50) NOT NULL DEFAULT 'pending',
    priority int NOT NULL DEFAULT 1,
    retry_count int DEFAULT 0,
    last_attempt datetime NULL,
    next_attempt datetime NULL,
    error_message text NULL,
    date_created datetime DEFAULT CURRENT_TIMESTAMP,
    date_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_order_item (order_id, item_id),
    KEY status (status),
    KEY priority (priority),
    KEY next_attempt (next_attempt)
) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_categories);
        dbDelta($sql_codes);
        dbDelta($sql_log);
        dbDelta($sql_pending);
        
        $this->create_default_categories();
    }
    
    public function drop_tables() {
        $this->wpdb->query("DROP TABLE IF EXISTS {$this->pending_table}");
        $this->wpdb->query("DROP TABLE IF EXISTS {$this->log_table}");
        $this->wpdb->query("DROP TABLE IF EXISTS {$this->codes_table}");
        $this->wpdb->query("DROP TABLE IF EXISTS {$this->categories_table}");
    }
    
    private function create_default_categories() {
        $default_categories = array(
            array('name' => 'PUBG Mobile 60 UC', 'uc_amount' => 60, 'description' => 'Basic UC package'),
            array('name' => 'PUBG Mobile 325 UC', 'uc_amount' => 325, 'description' => 'Popular UC package'),
            array('name' => 'PUBG Mobile 660 UC', 'uc_amount' => 660, 'description' => 'Premium UC package'),
            array('name' => 'PUBG Mobile 1800 UC', 'uc_amount' => 1800, 'description' => 'Elite UC package')
        );
        
        foreach ($default_categories as $category) {
            $existing = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$this->categories_table} WHERE uc_amount = %d",
                $category['uc_amount']
            ));
            
            if (!$existing) {
                $this->wpdb->insert($this->categories_table, $category);
            }
        }
    }
    
    public function allocate_code_safely($order_id, $product_id, $player_id, $variation_id = null) {
        $this->wpdb->query('START TRANSACTION');
        
        try {
            $category_id = $this->get_category_for_product($product_id, $variation_id);
            if (!$category_id) {
                throw new Exception('No category found for product');
            }
            
            $existing_log = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->log_table} WHERE order_id = %d AND player_id = %s AND status != 'failed'",
                $order_id, $player_id
            ));
            
            if ($existing_log) {
                throw new Exception('Order already processed');
            }
            
            $this->wpdb->query("LOCK TABLES {$this->codes_table} WRITE");
            
            $code = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->codes_table} WHERE category_id = %d AND status = 'available' ORDER BY id ASC LIMIT 1",
                $category_id
            ));
            
            if (!$code) {
                $this->wpdb->query("UNLOCK TABLES");
                $this->add_to_pending_queue($order_id, $product_id, $variation_id, $player_id, $category_id, 'No codes available');
                throw new Exception('No codes available - added to pending queue');
            }
            
            $updated = $this->wpdb->update(
                $this->codes_table,
                array(
                    'status' => 'reserved',
                    'order_id' => $order_id,
                    'player_id' => $player_id,
                    'date_used' => current_time('mysql')
                ),
                array('id' => $code->id),
                array('%s', '%d', '%s', '%s'),
                array('%d')
            );
            
            $this->wpdb->query("UNLOCK TABLES");
            
            if (!$updated) {
                throw new Exception('Failed to reserve code');
            }
            
            $this->wpdb->insert($this->log_table, array(
                'code_id' => $code->id,
                'order_id' => $order_id,
                'player_id' => $player_id,
                'status' => 'pending',
                'message' => 'Code allocated, processing...'
            ));
            
            $this->wpdb->query('COMMIT');
            
            if (get_option('pubg_enable_auto_recharge', 1)) {
                $api = new PUBG_API();
                $this->send_to_api($code->id, $code->code, $player_id, $order_id);
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->wpdb->query('ROLLBACK');
            $this->wpdb->query("UNLOCK TABLES");
            
            pubg_debug_log("Code allocation failed", array(
                'order_id' => $order_id,
                'player_id' => $player_id,
                'error' => $e->getMessage()
            ));
            
            return false;
        }
    }
    
    private function get_category_for_product($product_id, $variation_id = null) {
        if ($variation_id) {
            $category_id = get_post_meta($variation_id, 'pubg_category_id', true);
            if ($category_id) return $category_id;
        }
        
        return get_post_meta($product_id, 'pubg_category_id', true);
    }
    
    public function send_to_api($code_id, $code, $player_id, $order_id) {
        $api = new PUBG_API();
        $result = $api->activate_uc_code($player_id, $code);
        
        if ($result['success'] && isset($result['data']['status']) && $result['data']['status'] === 'success') {
            $this->update_code_status($code_id, 'used');
            $this->update_log_status($code_id, $order_id, 'success', 'UC activated successfully');
            
            $order = wc_get_order($order_id);
            if ($order) {
                $order->update_status('completed', 'PUBG UC activated successfully for Player ID: ' . $player_id);
            }
            
        } else {
            $error = isset($result['data']['message']) ? $result['data']['message'] : 'Unknown error';
            $this->update_code_status($code_id, 'failed', $error);
            $this->update_log_status($code_id, $order_id, 'failed', 'Activation failed: ' . $error);
            
            $this->add_to_pending_queue_from_failed($order_id, $code_id, $player_id, $error);
            
            $order = wc_get_order($order_id);
            if ($order) {
                $order->add_order_note('PUBG UC activation failed for Player ID: ' . $player_id . ' - Error: ' . $error);
            }
        }
    }
    
    public function update_code_status($code_id, $status, $error = null) {
        $update_data = array('status' => $status);
        if ($error) {
            $update_data['last_error'] = $error;
        }
        
        $this->wpdb->update($this->codes_table, $update_data, array('id' => $code_id));
    }
    
    public function update_log_status($code_id, $order_id, $status, $message) {
        $this->wpdb->update(
            $this->log_table,
            array('status' => $status, 'message' => $message),
            array('code_id' => $code_id, 'order_id' => $order_id)
        );
    }
    
    public function add_to_pending_queue($order_id, $product_id, $variation_id, $player_id, $category_id, $error = null) {
        $data = array(
            'order_id' => $order_id,
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'player_id' => $player_id,
            'category_id' => $category_id,
            'status' => 'pending',
            'priority' => 1,
            'error_message' => $error,
            'next_attempt' => date('Y-m-d H:i:s', strtotime('+5 minutes'))
        );
        
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->pending_table} WHERE order_id = %d AND player_id = %s",
            $order_id, $player_id
        ));
        
        if ($existing) {
            $this->wpdb->update($this->pending_table, $data, array('id' => $existing));
        } else {
            $this->wpdb->insert($this->pending_table, $data);
        }
    }
    
    public function add_to_pending_queue_from_failed($order_id, $code_id, $player_id, $error) {
        $code = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->codes_table} WHERE id = %d", $code_id
        ));
        
        if ($code) {
            $this->add_to_pending_queue(
                $order_id, 
                0, 
                null, 
                $player_id, 
                $code->category_id, 
                'Failed activation: ' . $error
            );
        }
    }
    
    public function get_pending_operations($limit = 50) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT p.*, cat.name as category_name, cat.uc_amount 
             FROM {$this->pending_table} p 
             LEFT JOIN {$this->categories_table} cat ON p.category_id = cat.id 
             WHERE p.status = 'pending' 
             ORDER BY p.priority DESC, p.date_created ASC 
             LIMIT %d",
            $limit
        ));
    }
    
    public function process_pending_operation($pending_id) {
        $pending = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->pending_table} WHERE id = %d", $pending_id
        ));
        
        if (!$pending) return false;
        
        $this->wpdb->update(
            $this->pending_table,
            array(
                'status' => 'processing',
                'last_attempt' => current_time('mysql'),
                'retry_count' => $pending->retry_count + 1
            ),
            array('id' => $pending_id)
        );
        
        $success = $this->allocate_code_safely(
            $pending->order_id,
            $pending->product_id,
            $pending->player_id,
            $pending->variation_id
        );
        
        if ($success) {
            $this->wpdb->delete($this->pending_table, array('id' => $pending_id));
        } else {
            $next_attempt = date('Y-m-d H:i:s', strtotime('+' . (5 * $pending->retry_count) . ' minutes'));
            $this->wpdb->update(
                $this->pending_table,
                array(
                    'status' => 'pending',
                    'next_attempt' => $next_attempt,
                    'error_message' => 'Retry failed - attempt ' . ($pending->retry_count + 1)
                ),
                array('id' => $pending_id)
            );
        }
        
        return $success;
    }
    
    public function delete_pending_operation($pending_id) {
        return $this->wpdb->delete($this->pending_table, array('id' => $pending_id));
    }
    
    public function get_codes($category_id = null, $status = null, $limit = 100, $offset = 0) {
        $where_conditions = array();
        $params = array();
        
        if ($category_id) {
            $where_conditions[] = "c.category_id = %d";
            $params[] = $category_id;
        }
        
        if ($status) {
            $where_conditions[] = "c.status = %s";
            $params[] = $status;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "SELECT c.*, cat.name as category_name, cat.uc_amount 
                FROM {$this->codes_table} c 
                JOIN {$this->categories_table} cat ON c.category_id = cat.id 
                $where_clause 
                ORDER BY c.id DESC LIMIT %d OFFSET %d";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $params));
    }
    
    public function add_codes($category_id, $codes_array) {
        $added_count = 0;
        $duplicate_count = 0;
        
        foreach ($codes_array as $code) {
            $code = trim($code);
            if (empty($code)) continue;
            
            $existing = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$this->codes_table} WHERE code = %s", $code
            ));
            
            if ($existing) {
                $duplicate_count++;
                continue;
            }
            
            $inserted = $this->wpdb->insert($this->codes_table, array(
                'category_id' => $category_id,
                'code' => $code,
                'status' => 'available'
            ));
            
            if ($inserted) {
                $added_count++;
            }
        }
        
        return array(
            'added' => $added_count,
            'duplicates' => $duplicate_count,
            'total_attempted' => count($codes_array)
        );
    }
    
    public function delete_code($code_id) {
        $code = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->codes_table} WHERE id = %d", $code_id
        ));
        
        if (!$code) return false;
        
        if ($code->status !== 'available') {
            return array('error' => 'Cannot delete non-available codes');
        }
        
        $deleted = $this->wpdb->delete($this->codes_table, array('id' => $code_id));
        return $deleted ? true : array('error' => 'Failed to delete code');
    }
    
    public function get_categories() {
        return $this->wpdb->get_results("SELECT * FROM {$this->categories_table} ORDER BY uc_amount ASC");
    }
    
    public function add_category($name, $uc_amount, $description = '') {
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->categories_table} WHERE uc_amount = %d", $uc_amount
        ));
        
        if ($existing) {
            return array('error' => 'Category with this UC amount already exists');
        }
        
        $inserted = $this->wpdb->insert($this->categories_table, array(
            'name' => $name,
            'uc_amount' => $uc_amount,
            'description' => $description
        ));
        
        return $inserted ? $this->wpdb->insert_id : array('error' => 'Failed to create category');
    }
    
    public function delete_category($category_id) {
        $codes_count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->codes_table} WHERE category_id = %d", $category_id
        ));
        
        if ($codes_count > 0) {
            return array('error' => 'Cannot delete category with existing codes');
        }
        
        $deleted = $this->wpdb->delete($this->categories_table, array('id' => $category_id));
        return $deleted ? true : array('error' => 'Failed to delete category');
    }
    
    public function get_logs($status = null, $days = 7, $limit = 200) {
        $where_conditions = array();
        $params = array();
        
        if ($status) {
            $where_conditions[] = "l.status = %s";
            $params[] = $status;
        }
        
        if ($days > 0) {
            $where_conditions[] = "l.date_created >= DATE_SUB(NOW(), INTERVAL %d DAY)";
            $params[] = $days;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $sql = "SELECT l.*, c.code, cat.name as category_name, cat.uc_amount 
                FROM {$this->log_table} l 
                LEFT JOIN {$this->codes_table} c ON l.code_id = c.id 
                LEFT JOIN {$this->categories_table} cat ON c.category_id = cat.id
                $where_clause 
                ORDER BY l.id DESC LIMIT %d";
        
        $params[] = $limit;
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $params));
    }
    
    public function export_data($type = 'all') {
        $data = array();
        
        if (in_array($type, array('all', 'categories'))) {
            $data['categories'] = $this->get_categories();
        }
        
        if (in_array($type, array('all', 'codes'))) {
            $data['codes'] = $this->get_codes(null, null, 10000);
        }
        
        if (in_array($type, array('all', 'settings'))) {
            $data['settings'] = array(
                'pubg_api_url' => get_option('pubg_api_url'),
                'pubg_enable_auto_recharge' => get_option('pubg_enable_auto_recharge'),
                'pubg_notification_email' => get_option('pubg_notification_email'),
                'pubg_low_stock_threshold' => get_option('pubg_low_stock_threshold'),
                'pubg_player_id_label' => get_option('pubg_player_id_label'),
                'pubg_success_message' => get_option('pubg_success_message'),
                'pubg_processing_message' => get_option('pubg_processing_message')
            );
        }
        
        return $data;
    }
    
    public function import_data($data) {
        $results = array('success' => 0, 'errors' => array());
        
        if (isset($data['categories'])) {
            foreach ($data['categories'] as $category) {
                $result = $this->add_category($category['name'], $category['uc_amount'], $category['description']);
                if (is_array($result) && isset($result['error'])) {
                    $results['errors'][] = 'Category: ' . $result['error'];
                } else {
                    $results['success']++;
                }
            }
        }
        
        if (isset($data['codes'])) {
            $codes_by_category = array();
            foreach ($data['codes'] as $code) {
                $codes_by_category[$code['category_id']][] = $code['code'];
            }
            
            foreach ($codes_by_category as $category_id => $codes) {
                $result = $this->add_codes($category_id, $codes);
                $results['success'] += $result['added'];
                if ($result['duplicates'] > 0) {
                    $results['errors'][] = 'Skipped ' . $result['duplicates'] . ' duplicate codes';
                }
            }
        }
        
        if (isset($data['settings'])) {
            foreach ($data['settings'] as $key => $value) {
                if ($key !== 'pubg_api_key') {
                    update_option($key, $value);
                    $results['success']++;
                }
            }
        }
        
        return $results;
    }
    
    public function get_statistics() {
        $stats = array();
        
        $stats['codes'] = array(
            'total' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->codes_table}"),
            'available' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->codes_table} WHERE status = 'available'"),
            'used' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->codes_table} WHERE status = 'used'"),
            'failed' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->codes_table} WHERE status = 'failed'"),
            'reserved' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->codes_table} WHERE status = 'reserved'")
        );
        
        $stats['operations'] = array(
            'success' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->log_table} WHERE status = 'success'"),
            'failed' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->log_table} WHERE status = 'failed'"),
            'pending' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->log_table} WHERE status = 'pending'")
        );
        
        $stats['pending_queue'] = array(
            'total' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->pending_table}"),
            'high_priority' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->pending_table} WHERE priority > 1"),
            'ready_for_retry' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->pending_table} WHERE next_attempt <= NOW()")
        );
        
        return $stats;
    }
    
    public function cleanup_old_data($days = 30) {
        $deleted_logs = $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->log_table} WHERE date_created < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        $deleted_pending = $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->pending_table} WHERE date_created < DATE_SUB(NOW(), INTERVAL %d DAY) AND status != 'pending'",
            $days
        ));
        
        return array(
            'deleted_logs' => $deleted_logs,
            'deleted_pending' => $deleted_pending
        );
    }
}
