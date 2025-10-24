<?php
/**
 * Plugin Name: Brother Data Importer PRO Enhanced Complete
 * Description: Brother ürünlerini otomatik import eden gelişmiş sistem
 * Version: 5.4.0
 * Author: BDI Team
 * License: GPL v2 or later
 * Text Domain: bdi-pro
 * 
 * Bu dosya tüm BDI bileşenlerini içerir:
 * - Ana BDI_Pro_Enhanced sınıfı
 * - Yardımcı sınıflar (Cache, Logger, Rate Limiter, vb.)
 * - Admin arayüzü ve işleme metodları
 * - API entegrasyonları
 * - Frontend görünüm ve shortcode'lar
 */

if (!defined("ABSPATH")) exit;

/**
 * Plugin Name: Brother Data Importer PRO Enhanced Complete
 * Description: Brother ürünlerini otomatik import eden gelişmiş sistem - Tüm parçalar birleştirilmiş
 * Version: 5.4.0
 * Author: BDI Team
 */

if (!defined("ABSPATH")) exit;


// ============================================
// PART 1 - MAIN STRUCTURE & INITIALIZATION
// ============================================
/*
Plugin Name: Brother Detail Importer PRO - Enhanced & Secured
Description: Brother TÃ¼rkiye Ã¼rÃ¼n detay sayfalarÄ±ndan gÃ¼venli WooCommerce Ã¼rÃ¼n aktarÄ±mÄ± - API Entegrasyonlu
Version: 5.4.0
Author: MYS TasarÄ±mm
*/

if (!defined('ABSPATH')) exit;

// ============================================
// CACHE MANAGER SINIFI
// ============================================
if (!class_exists('BDI_Cache_Manager')) :
class BDI_Cache_Manager {
    private $cache_prefix = 'bdi_cache_';
    private $default_expiration = 3600;

    public function get($key) {
        return get_transient($this->cache_prefix . $key);
    }

    public function set($key, $value, $expiration = null) {
        if ($expiration === null) {
            $expiration = $this->default_expiration;
        }
        return set_transient($this->cache_prefix . $key, $value, $expiration);
    }

    public function delete($key) {
        return delete_transient($this->cache_prefix . $key);
    }

    public function get_or_set($key, $callback, $expiration = null) {
        $cached = $this->get($key);
        if ($cached !== false) {
            return $cached;
        }

        $result = call_user_func($callback);
        $this->set($key, $result, $expiration);
        return $result;
    }

    public function flush_all() {
        global $wpdb;
        $like = $wpdb->esc_like('_transient_' . $this->cache_prefix) . '%';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $like
        ));

        $like_timeout = $wpdb->esc_like('_transient_timeout_' . $this->cache_prefix) . '%';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $like_timeout
        ));
    }
}
endif;

// ============================================
// RATE LIMITER SINIFI
// ============================================
if (!class_exists('BDI_Rate_Limiter')) :

class BDI_Rate_Limiter {
    private $cache_manager;

    public function __construct(BDI_Cache_Manager $cache_manager) {
        $this->cache_manager = $cache_manager;
    }

    public function check($key, $max_requests = 10, $window_seconds = 60) {
        $rate_key = 'rate_limit_' . $key;
        $current = $this->cache_manager->get($rate_key);

        if ($current === false) {
            $current = 0;
        }

        if ($current >= $max_requests) {
            return false;
        }

        $this->cache_manager->set($rate_key, $current + 1, $window_seconds);
        return true;
    }

    public function reset($key) {
        $rate_key = 'rate_limit_' . $key;
        $this->cache_manager->delete($rate_key);
    }
}
endif;

// ============================================
// LOGGER SINIFI
// ============================================
if (!class_exists('BDI_Logger')) :

class BDI_Logger {
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    private $log_option = 'bdi_logs';
    private $max_logs = 500;
    private $log_file_enabled = false;
    private $log_file_path;

    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file_path = $upload_dir['basedir'] . '/bdi-logs/';

        if (!file_exists($this->log_file_path)) {
            wp_mkdir_p($this->log_file_path);
        }
    }

    public function log($message, $level = self::LEVEL_INFO, $context = array()) {
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        $message = wp_strip_all_tags((string)$message);

        $valid_levels = array(
            self::LEVEL_DEBUG,
            self::LEVEL_INFO,
            self::LEVEL_WARNING,
            self::LEVEL_ERROR,
            self::LEVEL_CRITICAL
        );

        if (!in_array($level, $valid_levels, true)) {
            $level = self::LEVEL_INFO;
        }

        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'message' => substr($message, 0, 1000),
            'context' => $context
        );

        $this->save_to_database($log_entry);

        if ($this->log_file_enabled) {
            $this->save_to_file($log_entry);
        }
    }

    private function save_to_database($entry) {
        $logs = get_option($this->log_option, array());

        if (!is_array($logs)) {
            $logs = array();
        }

        $logs[] = $entry;

        if (count($logs) > $this->max_logs) {
            $logs = array_slice($logs, -$this->max_logs);
        }

        update_option($this->log_option, $logs, false);
    }

    private function save_to_file($entry) {
        $filename = $this->log_file_path . 'bdi-' . date('Y-m-d') . '.log';
        $log_line = sprintf(
            "[%s] [%s] %s %s\n",
            $entry['timestamp'],
            strtoupper($entry['level']),
            $entry['message'],
            !empty($entry['context']) ? json_encode($entry['context']) : ''
        );

        if (file_exists($filename) && filesize($filename) > 5 * 1024 * 1024) {
            rename($filename, $filename . '.' . time() . '.old');
        }

        error_log($log_line, 3, $filename);
    }

    public function get_logs($limit = 200, $level = null) {
        $logs = get_option($this->log_option, array());

        if (!is_array($logs)) {
            return array();
        }

        if ($level) {
            $logs = array_filter($logs, function($log) use ($level) {
                return isset($log['level']) && $log['level'] === $level;
            });
        }

        return array_slice(array_reverse($logs), 0, $limit);
    }

    public function clear_logs() {
        update_option($this->log_option, array());

        if ($this->log_file_enabled) {
            $files = glob($this->log_file_path . '*.log*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
endif;

// ============================================
// KATEGORÄ° IMPORTER SINIFI
// ============================================
if (!class_exists('BDI_Category_Importer')) :

class BDI_Category_Importer {

    private $base_url = 'https://www.brother.com.tr';
    private $cache_manager;
    private $logger;

    private $category_structure = array(
        'yazicilar' => array(
            'name' => 'YazÄ±cÄ±lar ve Ã‡ok Fonksiyonlu YazÄ±cÄ±lar',
            'url' => '/printers/all-printers',
            'children' => array(
                array('name' => 'Renkli MÃ¼rekkep PÃ¼skÃ¼rtmeli YazÄ±cÄ±lar', 'slug' => 'inkjet-printers'),
                array('name' => 'Siyah-Beyaz Lazer YazÄ±cÄ±lar', 'slug' => 'mono-laser-printers'),
                array('name' => 'Renkli Lazer YazÄ±cÄ±lar', 'slug' => 'color-laser-printers'),
                array('name' => 'A3 YazÄ±cÄ±lar', 'slug' => 'a3-printers'),
                array('name' => 'Ev iÃ§in YazÄ±cÄ±lar', 'slug' => 'home-printers'),
                array('name' => 'Ä°ÅŸ iÃ§in YazÄ±cÄ±lar', 'slug' => 'business-printers'),
                array('name' => 'MÃ¼rekkep TanklÄ± YazÄ±cÄ±lar', 'slug' => 'ink-tank-printers'),
                array('name' => 'Ã‡ok Fonksiyonlu YazÄ±cÄ±lar', 'slug' => 'multifunction-printers')
            )
        ),
        'tarayicilar' => array(
            'name' => 'TarayÄ±cÄ±lar',
            'url' => '/scanners',
            'children' => array(
                array('name' => 'DokÃ¼man TarayÄ±cÄ±lar', 'slug' => 'document-scanners'),
                array('name' => 'Mobil TarayÄ±cÄ±lar', 'slug' => 'mobile-scanners'),
                array('name' => 'MasaÃ¼stÃ¼ TarayÄ±cÄ±lar', 'slug' => 'desktop-scanners')
            )
        ),
        'mobil-yazicilar' => array(
            'name' => 'Mobil YazÄ±cÄ±lar',
            'url' => '/mobile-printers',
            'children' => array(
                array('name' => 'PJ Serisi', 'slug' => 'pj-series'),
                array('name' => 'RJ Serisi', 'slug' => 'rj-series'),
                array('name' => 'MW Serisi', 'slug' => 'mw-series')
            )
        ),
        'etiket-yazicilar' => array(
            'name' => 'Etiket YazÄ±cÄ±lar',
            'url' => '/label-printers',
            'children' => array(
                array('name' => 'Ev ve KÃ¼Ã§Ã¼k Ofis', 'slug' => 'home-office-label'),
                array('name' => 'EndÃ¼striyel KullanÄ±m', 'slug' => 'industrial-label'),
                array('name' => 'Ä°ÅŸ AmaÃ§lÄ± KullanÄ±m', 'slug' => 'business-label'),
                array('name' => 'P-touch Serisi', 'slug' => 'p-touch'),
                array('name' => 'QL Serisi', 'slug' => 'ql-series'),
                array('name' => 'TD Serisi', 'slug' => 'td-series')
            )
        ),
        'dikis-makineleri' => array(
            'name' => 'Ev Tipi DikiÅŸ Makineleri',
            'url' => '/sewing-machines',
            'children' => array(
                array('name' => 'Elektronik DikiÅŸ Makineleri', 'slug' => 'electronic-sewing'),
                array('name' => 'Mekanik DikiÅŸ Makineleri', 'slug' => 'mechanical-sewing'),
                array('name' => 'Overlok Makineleri', 'slug' => 'overlock'),
                array('name' => 'NakÄ±ÅŸ Makineleri', 'slug' => 'embroidery'),
                array('name' => 'DikiÅŸ ve NakÄ±ÅŸ Kombinasyon', 'slug' => 'sewing-embroidery-combo'),
                array('name' => 'Kapitone Makineleri', 'slug' => 'quilting')
            )
        )
    );

    private $model_category_mapping = array(
        'MFC-J' => array('YazÄ±cÄ±lar', 'MÃ¼rekkep PÃ¼skÃ¼rtmeli YazÄ±cÄ±lar', 'Ã‡ok Fonksiyonlu'),
        'MFC-L' => array('YazÄ±cÄ±lar', 'Lazer YazÄ±cÄ±lar', 'Ã‡ok Fonksiyonlu'),
        'MFC-T' => array('YazÄ±cÄ±lar', 'MÃ¼rekkep TanklÄ± YazÄ±cÄ±lar', 'Ã‡ok Fonksiyonlu'),
        'DCP-J' => array('YazÄ±cÄ±lar', 'MÃ¼rekkep PÃ¼skÃ¼rtmeli YazÄ±cÄ±lar', 'Ã‡ok Fonksiyonlu'),
        'DCP-L' => array('YazÄ±cÄ±lar', 'Lazer YazÄ±cÄ±lar', 'Ã‡ok Fonksiyonlu'),
        'DCP-T' => array('YazÄ±cÄ±lar', 'MÃ¼rekkep TanklÄ± YazÄ±cÄ±lar', 'Ã‡ok Fonksiyonlu'),
        'HL-L' => array('YazÄ±cÄ±lar', 'Lazer YazÄ±cÄ±lar'),
        'HL-' => array('YazÄ±cÄ±lar', 'Lazer YazÄ±cÄ±lar'),
        'ADS-' => array('TarayÄ±cÄ±lar', 'DokÃ¼man TarayÄ±cÄ±lar'),
        'DS-' => array('TarayÄ±cÄ±lar', 'Mobil TarayÄ±cÄ±lar'),
        'PT-' => array('Etiket YazÄ±cÄ±lar', 'P-touch Serisi'),
        'QL-' => array('Etiket YazÄ±cÄ±lar', 'QL Serisi'),
        'TD-' => array('Etiket YazÄ±cÄ±lar', 'TD Serisi'),
        'PJ-' => array('Mobil YazÄ±cÄ±lar', 'PJ Serisi'),
        'RJ-' => array('Mobil YazÄ±cÄ±lar', 'RJ Serisi'),
        'MW-' => array('Mobil YazÄ±cÄ±lar', 'MW Serisi')
    );

    public function __construct(BDI_Cache_Manager $cache_manager, BDI_Logger $logger) {
        $this->cache_manager = $cache_manager;
        $this->logger = $logger;
    }

    public function fetch_category_structure() {
        return $this->cache_manager->get_or_set('category_structure_v2', function() {
            return $this->get_default_structure();
        }, DAY_IN_SECONDS);
    }

    private function get_default_structure() {
        $structured = array();

        foreach ($this->category_structure as $key => $cat_data) {
            $structured[] = array(
                'name' => $cat_data['name'],
                'slug' => $key,
                'url' => $cat_data['url'],
                'children' => $cat_data['children']
            );
        }

        return $structured;
    }

    public function create_wc_categories($structure = null, $parent_id = 0) {
        if ($structure === null) {
            $structure = $this->get_default_structure();
        }

        $created = 0;

        foreach ($structure as $cat_data) {
            if (!isset($cat_data['name'])) continue;

            try {
                $term = term_exists($cat_data['name'], 'product_cat', $parent_id);

                if (!$term) {
                    $slug = isset($cat_data['slug']) ? $cat_data['slug'] : sanitize_title($cat_data['name']);

                    $term = wp_insert_term(
                        $cat_data['name'],
                        'product_cat',
                        array(
                            'parent' => $parent_id,
                            'slug' => $slug,
                            'description' => 'Brother ' . $cat_data['name']
                        )
                    );

                    if (!is_wp_error($term)) {
                        $created++;
                        $this->logger->log('Kategori oluÅŸturuldu: ' . $cat_data['name'], BDI_Logger::LEVEL_INFO);

                        if (isset($term['term_id'])) {
                            update_term_meta($term['term_id'], 'brother_category', true);
                            if (isset($cat_data['url'])) {
                                update_term_meta($term['term_id'], 'brother_url', $cat_data['url']);
                            }
                        }
                    }
                }

                if (!is_wp_error($term) && !empty($cat_data['children'])) {
                    $term_id = is_array($term) ? $term['term_id'] : $term;
                    $created += $this->create_wc_categories($cat_data['children'], $term_id);
                }

            } catch (Exception $e) {
                $this->logger->log('Kategori oluÅŸturma hatasÄ±: ' . $e->getMessage(), BDI_Logger::LEVEL_ERROR);
            }
        }

        return $created;
    }

    public function match_product_to_categories($url, $title, $html = '') {
        $categories = array();

        $url_mappings = array(
            '/printers/' => 'YazÄ±cÄ±lar',
            '/inkjet/' => 'MÃ¼rekkep PÃ¼skÃ¼rtmeli YazÄ±cÄ±lar',
            '/laser/' => 'Lazer YazÄ±cÄ±lar',
            '/mobile/' => 'Mobil YazÄ±cÄ±lar',
            '/label/' => 'Etiket YazÄ±cÄ±lar',
            '/scanner' => 'TarayÄ±cÄ±lar',
            '/sewing' => 'DikiÅŸ Makineleri'
        );

        foreach ($url_mappings as $url_part => $category) {
            if (strpos($url, $url_part) !== false) {
                $categories[] = $category;
            }
        }

        foreach ($this->model_category_mapping as $prefix => $cats) {
            if (stripos($title, $prefix) !== false) {
                $categories = array_merge($categories, $cats);
                break;
            }
        }

        if ($html) {
            $breadcrumbs = $this->extract_breadcrumbs_from_html($html);
            if (!empty($breadcrumbs)) {
                $categories = array_merge($categories, $breadcrumbs);
            }
        }

        $categories = array_unique($categories);
        $categories = array_filter($categories, function($cat) {
            return !preg_match('/^[A-Z]{2,4}-[A-Z0-9]+/', $cat);
        });

        return array_values($categories);
    }

    private function extract_breadcrumbs_from_html($html) {
        $breadcrumbs = array();

        try {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
            $xpath = new DOMXPath($dom);

            $queries = array(
                '//nav[@aria-label="breadcrumb"]//a',
                '//ol[@class="breadcrumb"]//a',
                '//div[contains(@class,"breadcrumb")]//a',
                '//ul[contains(@class,"breadcrumb")]//a'
            );

            foreach ($queries as $query) {
                $nodes = $xpath->query($query);

                if ($nodes && $nodes->length > 0) {
                    foreach ($nodes as $node) {
                        $text = trim($node->textContent);
                        $skip_terms = array('Ana Sayfa', 'Home', 'Anasayfa', 'Brother', 'TR');

                        $skip = false;
                        foreach ($skip_terms as $term) {
                            if (strcasecmp($text, $term) === 0) {
                                $skip = true;
                                break;
                            }
                        }

                        if (!$skip && $text) {
                            $breadcrumbs[] = $this->normalize_category_name($text);
                        }
                    }

                    if (!empty($breadcrumbs)) {
                        break;
                    }
                }
            }

        } catch (Exception $e) {
            $this->logger->log('Breadcrumb Ã§Ä±karma hatasÄ±: ' . $e->getMessage(), BDI_Logger::LEVEL_WARNING);
        }

        return $breadcrumbs;
    }

    private function normalize_category_name($name) {
        $translations = array(
            'Printers' => 'YazÄ±cÄ±lar',
            'All Printers' => 'TÃ¼m YazÄ±cÄ±lar',
            'Scanners' => 'TarayÄ±cÄ±lar',
            'Label Printers' => 'Etiket YazÄ±cÄ±lar',
            'Mobile Printers' => 'Mobil YazÄ±cÄ±lar',
            'Sewing Machines' => 'DikiÅŸ Makineleri'
        );

        foreach ($translations as $eng => $tr) {
            if (strcasecmp($name, $eng) === 0) {
                return $tr ?: '';
            }
        }

        $name = trim($name);
        $name = str_replace('&amp;', '&', $name);

        return $name;
    }

    public function get_category_tree() {
        $tree = array();

        $terms = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'meta_key' => 'brother_category',
            'meta_value' => true,
            'hierarchical' => true
        ));

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $tree[] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'parent' => $term->parent,
                    'count' => $term->count,
                    'brother_url' => get_term_meta($term->term_id, 'brother_url', true)
                );
            }
        }

        return $tree;
    }
}
endif;

// ============================================
// ATTRIBUTE HANDLER SINIFI
// ============================================
if (!class_exists('BDI_Attribute_Handler')) :

class BDI_Attribute_Handler {

    private $logger;

    private $default_attribute_mapping = array(
        'BaskÄ± Teknolojisi' => 'print-technology',
        'BaskÄ± HÄ±zÄ±' => 'print-speed',
        'Maksimum KaÄŸÄ±t Boyutu' => 'paper-size',
        'BaÄŸlantÄ±' => 'connectivity',
        'Ã‡ift TaraflÄ± BaskÄ±' => 'duplex',
        'Ekran' => 'display',
        'Renk/Monokrom' => 'color-type',
        'AÄŸ BaÄŸlantÄ±sÄ±' => 'network',
        'WiFi' => 'wifi',
        'Tarama Ã‡Ã¶zÃ¼nÃ¼rlÃ¼ÄŸÃ¼' => 'scan-resolution',
        'KaÄŸÄ±t Kapasitesi' => 'paper-capacity'
    );

    private $value_normalization = array(
        'boolean' => array(
            'true_values' => array('var', 'evet', 'yes', 'mevcut', 'destekler', 'supported', 'vardÄ±r'),
            'false_values' => array('yok', 'hayÄ±r', 'no', 'mevcut deÄŸil', 'desteklemiyor', 'not supported', 'yoktur'),
            'normalized_true' => 'Var',
            'normalized_false' => 'Yok'
        )
    );

    public function __construct(BDI_Logger $logger) {
        $this->logger = $logger;
    }

    public function extract_product_attributes($html) {
        $attributes = array();

        if (empty($html)) {
            return $attributes;
        }

        try {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);

            if (!@$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html)) {
                $this->logger->log('HTML parse hatasÄ±', BDI_Logger::LEVEL_WARNING);
                return $attributes;
            }

            $xpath = new DOMXPath($dom);

            $spec_queries = array(
                '//div[@id="specifications"]//table//tr',
                '//table[@class="specs-table"]//tr',
                '//div[contains(@class,"specifications")]//tr',
                '//div[contains(@class,"product-specs")]//tr',
                '//table[contains(@class,"tech-specs")]//tr'
            );

            foreach ($spec_queries as $query) {
                $elements = $xpath->query($query);

                if ($elements && $elements->length > 0) {
                    if (strpos($query, '//tr') !== false) {
                        $parsed = $this->parse_table_rows($elements, $xpath);
                        $attributes = array_merge($attributes, $parsed);
                    }
                }
            }

            $attributes = $this->normalize_attribute_values($attributes);
            $attributes = $this->deduplicate_attributes($attributes);
            $attributes = $this->filter_mapped_attributes($attributes);

            unset($dom, $xpath);

        } catch (Exception $e) {
            $this->logger->log('Attribute Ã§Ä±karma hatasÄ±: ' . $e->getMessage(), BDI_Logger::LEVEL_ERROR);
        }

        return $attributes;
    }

    private function parse_table_rows($rows, $xpath) {
        $results = array();

        foreach ($rows as $row) {
            $cells = $xpath->query('.//td | .//th', $row);

            if ($cells && $cells->length >= 2) {
                $label = trim($cells->item(0)->textContent);
                $value = trim($cells->item(1)->textContent);

                $label = $this->clean_label($label);
                $value = $this->clean_value_initial($value);

                if ($label && $value && $value !== '-' && $value !== 'N/A' && strlen($value) > 1) {
                    $results[$label] = $value;
                }
            }
        }

        return $results;
    }

    private function clean_label($label) {
        $label = wp_strip_all_tags($label);
        $label = preg_replace('/\s+/', ' ', $label);
        $label = trim($label);
        $label = rtrim($label, ':');
        return $label;
    }

    private function clean_value_initial($value) {
        $value = wp_strip_all_tags($value);
        $value = preg_replace('/\s+/', ' ', $value);
        $value = trim($value);
        return $value;
    }

    private function normalize_attribute_values($attributes) {
        $normalized = array();

        foreach ($attributes as $label => $value) {
            $normalized_value = $this->normalize_boolean_value($value);

            if ($normalized_value === $value) {
                $normalized_value = $this->parse_numeric_value($value);
            }

            $normalized_value = $this->clean_value_final($normalized_value);

            if ($normalized_value && strlen($normalized_value) > 0) {
                $normalized[$label] = $normalized_value;
            }
        }

        return $normalized;
    }

    private function normalize_boolean_value($value) {
        $value_lower = mb_strtolower(trim($value), 'UTF-8');

        foreach ($this->value_normalization['boolean']['true_values'] as $true_val) {
            if ($value_lower === $true_val || strpos($value_lower, $true_val) === 0) {
                return $this->value_normalization['boolean']['normalized_true'];
            }
        }

        foreach ($this->value_normalization['boolean']['false_values'] as $false_val) {
            if ($value_lower === $false_val || strpos($value_lower, $false_val) === 0) {
                return $this->value_normalization['boolean']['normalized_false'];
            }
        }

        return $value;
    }

    private function parse_numeric_value($value) {
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*([a-zA-ZÃ‡Ã§ÄžÄŸÄ°Ä±Ã–Ã¶ÅžÅŸÃœÃ¼\/]+)$/u', trim($value), $matches)) {
            $number = str_replace(',', '.', $matches[1]);
            $unit = strtolower($matches[2]);

            $standard_unit = $this->standardize_unit($unit);

            return $number . ' ' . $standard_unit;
        }

        if (preg_match('/^(\d+)\s*x\s*(\d+)\s*([a-zA-Z]+)?$/i', trim($value), $matches)) {
            $num1 = $matches[1];
            $num2 = $matches[2];
            $unit = isset($matches[3]) ? strtolower($matches[3]) : '';

            if ($unit) {
                $standard_unit = $this->standardize_unit($unit);
                return $num1 . ' x ' . $num2 . ' ' . $standard_unit;
            }

            return $num1 . ' x ' . $num2;
        }

        return $value;
    }

    private function standardize_unit($unit) {
        $unit = strtolower(trim($unit));

        $unit_mappings = array(
            'sayfa/dakika' => 'ppm',
            'sayfa/dk' => 'ppm',
            'pages per minute' => 'ppm',
            'page/min' => 'ppm',
            'dots per inch' => 'dpi',
            'megabyte' => 'MB',
            'gigabyte' => 'GB',
            'kilobyte' => 'KB',
            'milimetre' => 'mm',
            'santimetre' => 'cm',
            'inÃ§' => 'inch',
            'kilogram' => 'kg',
            'gram' => 'g',
            'watt' => 'W'
        );

        return isset($unit_mappings[$unit]) ? $unit_mappings[$unit] : strtoupper($unit);
    }

    private function clean_value_final($value) {
        $value = wp_strip_all_tags($value);
        $value = preg_replace('/\s+/', ' ', $value);

        if ($value === mb_strtoupper($value, 'UTF-8') && strlen($value) > 3) {
            $value = mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        }

        $value = trim($value);

        if (strlen($value) > 200) {
            $value = substr($value, 0, 200) . '...';
        }

        return sanitize_text_field($value);
    }

    private function deduplicate_attributes($attributes) {
        $seen_values = array();
        $unique = array();

        foreach ($attributes as $label => $value) {
            $value_key = mb_strtolower($value, 'UTF-8');

            if (!isset($seen_values[$value_key])) {
                $seen_values[$value_key] = $label;
                $unique[$label] = $value;
            } else {
                $existing_label = $seen_values[$value_key];

                $prefer_new = false;

                if ($this->is_mapped_label($label) && !$this->is_mapped_label($existing_label)) {
                    $prefer_new = true;
                } elseif (strlen($label) < strlen($existing_label)) {
                    $prefer_new = true;
                }

                if ($prefer_new) {
                    unset($unique[$existing_label]);
                    $unique[$label] = $value;
                    $seen_values[$value_key] = $label;
                }
            }
        }

        return $unique;
    }

    private function filter_mapped_attributes($attributes) {
        $mapping = $this->get_attribute_mapping();
        $filtered = array();

        foreach ($attributes as $label => $value) {
            if ($this->get_mapped_slug($label, $mapping)) {
                $filtered[$label] = $value;
            }
        }

        if (count($filtered) < 3 && count($attributes) > 3) {
            return $attributes;
        }

        return $filtered;
    }

    private function is_mapped_label($label) {
        $mapping = $this->get_attribute_mapping();
        return $this->get_mapped_slug($label, $mapping) !== null;
    }

    private function get_mapped_slug($label, $mapping = null) {
        if ($mapping === null) {
            $mapping = $this->get_attribute_mapping();
        }

        if (isset($mapping[$label])) {
            return $mapping[$label];
        }

        $label_lower = mb_strtolower($label, 'UTF-8');
        foreach ($mapping as $mapped_label => $slug) {
            if (mb_strtolower($mapped_label, 'UTF-8') === $label_lower) {
                return $slug;
            }
        }

        foreach ($mapping as $mapped_label => $slug) {
            if (strpos($label_lower, mb_strtolower($mapped_label, 'UTF-8')) !== false) {
                return $slug;
            }
        }

        return null;
    }

    public function ensure_attribute_taxonomy($slug, $label) {
        $taxonomy = 'pa_' . $slug;

        if (taxonomy_exists($taxonomy)) {
            return true;
        }

        if (!function_exists('wc_create_attribute')) {
            return false;
        }

        $result = wc_create_attribute(array(
            'slug' => $slug,
            'name' => $label,
            'type' => 'select',
            'orderby' => 'menu_order',
            'has_archives' => false
        ));

        if (!is_wp_error($result)) {
            register_taxonomy($taxonomy, 'product', array(
                'hierarchical' => false,
                'label' => $label,
                'query_var' => true,
                'show_in_nav_menus' => false,
                'show_ui' => false
            ));

            delete_transient('wc_attribute_taxonomies');

            return true;
        }

        return false;
    }

    public function create_wc_attributes($attributes, $product_id) {
        if (empty($attributes) || !$product_id) {
            return false;
        }

        try {
            $product_attributes = array();
            $position = 0;
            $created_taxonomies = array();

            $mapping = $this->get_attribute_mapping();

            foreach ($attributes as $label => $value) {
                $slug = $this->get_mapped_slug($label, $mapping);

                if (!$slug) {
                    $slug = sanitize_title($label);
                }

                $this->ensure_attribute_taxonomy($slug, $label);

                $taxonomy = 'pa_' . $slug;
                $created_taxonomies[] = $taxonomy;

                $term = $this->ensure_attribute_term($taxonomy, $value);

                if ($term && !is_wp_error($term)) {
                    $term_id = is_array($term) ? $term['term_id'] : $term;

                    $product_attributes[$taxonomy] = array(
                        'name' => $taxonomy,
                        'value' => '',
                        'position' => $position++,
                        'is_visible' => 1,
                        'is_variation' => 0,
                        'is_taxonomy' => 1
                    );

                    wp_set_object_terms($product_id, intval($term_id), $taxonomy, true);
                }
            }

            if (!empty($product_attributes)) {
                $existing = get_post_meta($product_id, '_product_attributes', true);
                if (is_array($existing)) {
                    $product_attributes = array_merge($existing, $product_attributes);
                }

                update_post_meta($product_id, '_product_attributes', $product_attributes);

                $this->clear_attribute_cache($created_taxonomies);

                $this->logger->log(
                    count($product_attributes) . ' attribute eklendi (Ã¼rÃ¼n #' . $product_id . ')',
                    BDI_Logger::LEVEL_INFO
                );

                return true;
            }

        } catch (Exception $e) {
            $this->logger->log('Attribute ekleme hatasÄ±: ' . $e->getMessage(), BDI_Logger::LEVEL_ERROR);
        }

        return false;
    }

    public function get_attribute_mapping() {
        $custom_mapping = get_option('bdi_attribute_mapping', array());

        if (empty($custom_mapping) || !is_array($custom_mapping)) {
            return $this->default_attribute_mapping;
        }

        return array_merge($this->default_attribute_mapping, $custom_mapping);
    }

    public function get_default_mapping() {
        return $this->default_attribute_mapping;
    }

    private function ensure_attribute_term($taxonomy, $value) {
        if (!taxonomy_exists($taxonomy)) {
            return false;
        }

        $term = term_exists($value, $taxonomy);

        if (!$term) {
            $term = wp_insert_term(
                $value,
                $taxonomy,
                array('slug' => sanitize_title($value))
            );
        }

        return $term;
    }

    private function clear_attribute_cache($taxonomies) {
        if (empty($taxonomies)) {
            return;
        }

        delete_transient('wc_attribute_taxonomies');

        if (class_exists('WC_Cache_Helper')) {
            WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');
        }
    }

    public function get_all_attributes() {
        return $this->get_attribute_mapping();
    }
}
endif;

// ============================================
// GLOBAL CALLBACK FONKSÄ°YONLARI
// ============================================

function bdi_render_specs_tab() {
    global $post;
    if (!isset($GLOBALS['bdi_pro_enhanced'])) {
        echo '<p>Brother Importer not initialized</p>';
        return;
    }
    $GLOBALS['bdi_pro_enhanced']->render_specs_tab();
}

function bdi_render_support_tab() {
    global $post;
    if (!isset($GLOBALS['bdi_pro_enhanced'])) {
        echo '<p>Brother Importer not initialized</p>';
        return;
    }
    $GLOBALS['bdi_pro_enhanced']->render_support_tab();
}

function bdi_render_supplies_tab() {
    global $post;
    if (!isset($GLOBALS['bdi_pro_enhanced'])) {
        echo '<p>Brother Importer not initialized</p>';
        return;
    }
    $GLOBALS['bdi_pro_enhanced']->render_supplies_tab();
}

function bdi_render_accessories_tab() {
    global $post;
    if (!isset($GLOBALS['bdi_pro_enhanced'])) {
        echo '<p>Brother Importer not initialized</p>';
        return;
    }
    $GLOBALS['bdi_pro_enhanced']->render_accessories_tab();
}

// ============================================
// ANA EKLENTÄ° SINIFI BAÅžLIYOR
// ============================================
if (!class_exists('BDI_Pro_Enhanced')) :


class BDI_Pro_Enhanced {
    const OPT_URLS         = 'bdi_urls';
    const OPT_SETTINGS     = 'bdi_settings';
    const META_SOURCE_URL  = '_bdi_source_url';
    const META_HASH        = '_bdi_content_hash';
    const META_WEBP_ALT    = '_bdi_webp_alt_id';
    const CRON_HOOK        = 'bdi_enhanced_cron';
    const META_SPECS       = '_bdi_specs_html';
    const META_SUPPORT     = '_bdi_support_html';
    const META_SUPPLIES    = '_bdi_supplies_data';
    const META_SUPPLY_CODES = '_bdi_supply_codes';
    const META_ACCESSORIES = '_bdi_accessories_data';
    const META_ACCESSORY_CODES = '_bdi_accessory_codes';

    private $sizes_filters_applied = false;
    private $processed_count = 0;
    private $error_count = 0;
    private $category_importer;
    private $attribute_handler;
    private $cache_manager;
    private $logger;
    private $rate_limiter;

    public function __construct() {
        $this->cache_manager = new BDI_Cache_Manager();
        $this->logger = new BDI_Logger();
        $this->rate_limiter = new BDI_Rate_Limiter($this->cache_manager);
        $this->category_importer = new BDI_Category_Importer($this->cache_manager, $this->logger);
        $this->attribute_handler = new BDI_Attribute_Handler($this->logger);

        add_action('wp', array($this, 'register_wc_hooks'));
        add_action('init', array($this, 'setup_handlers'), 1);
        add_filter('wp_image_editors', array($this, 'prefer_gd_editor'), 5);
        add_action('admin_menu', array($this, 'menu'));
        add_action('admin_post_bdi_save', array($this, 'save'));
        add_action('admin_post_bdi_save_attribute_mapping', array($this, 'save_attribute_mapping'));
        add_action('admin_post_bdi_run', array($this, 'run_now'));
        add_action('admin_post_bdi_sync_categories', array($this, 'sync_categories'));
        add_action('admin_post_bdi_batch_categories', array($this, 'handle_batch_categories'));
        add_action('admin_post_bdi_download_log', array($this, 'download_log'));
        add_action('admin_post_bdi_delete_log', array($this, 'delete_log'));
        add_action('admin_post_bdi_clear_cache', array($this, 'clear_cache'));
        add_action(self::CRON_HOOK, array($this, 'cron'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles_scripts'));
        add_action('wp_ajax_bdi_import_supplies', array($this, 'ajax_import_supplies_enhanced'));
        add_action('wp_ajax_bdi_import_accessories', array($this, 'ajax_import_accessories_enhanced'));
        add_action('template_redirect', array($this, 'check_supply_detail_request'));
        add_action('wp_ajax_bdi_scan_sitemap', array($this, 'ajax_scan_sitemap'));
        add_action('wp_ajax_bdi_add_url_to_list', array($this, 'ajax_add_url_to_list'));
        add_action('wp_ajax_bdi_add_bulk_urls', array($this, 'ajax_add_bulk_urls'));
        add_action('admin_post_bdi_create_attributes', array($this, 'handle_create_attributes'));
        add_action('admin_post_bdi_batch_attributes', array($this, 'handle_batch_attributes'));

        add_shortcode('brother_supply_detail', array($this, 'supply_detail_shortcode'));
        add_shortcode('brother_accessory_detail', array($this, 'accessory_detail_shortcode'));

        if (function_exists('as_schedule_single_action')) {
            add_action('bdi_process_single_url', array($this, 'process_single_url_async'), 10, 2);
        }
    }

    public function register_wc_hooks() {
        if (is_admin()) { return; }
        if (!class_exists('WooCommerce')) { return; }
        if (!$this->is_main_query_ready()) { return; }

        add_filter('woocommerce_product_tabs', array($this, 'add_brother_tabs'));
    }

    private function is_main_query_ready() {
        global $wp_query;
        if (!isset($wp_query) || !is_a($wp_query, 'WP_Query')) { return false; }
        return isset($wp_query->query_vars) && is_array($wp_query->query_vars);
    }

    public function setup_handlers() {
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            if (error_reporting() === 0) return false;
            $this->logger->log("PHP Error [$errno] $errstr in $errfile:$errline", BDI_Logger::LEVEL_ERROR);
            return false;
        });
    }

    public function prefer_gd_editor($editors) {
        return array('WP_Image_Editor_GD', 'WP_Image_Editor_Imagick');
    }

    public static function activate() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CRON_HOOK);
        }
        self::ensure_brand_attribute_static();
    }

    public static function deactivate() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public function menu() {
        add_menu_page(
            'Brother Importer PRO (Secured)',
            'Brother Importer',
            'manage_options',
            'bdi-pro-enhanced',
            array($this, 'page'),
            'dashicons-database-import',
            58
        );
    }

// ============================================
// PART 2 BURAYA EKLENECEK
// ============================================

// ============================================
// PART 2 - ADMIN INTERFACE
// ============================================
// BU KISIM BDI_Pro_Enhanced CLASS Ä°Ã‡Ä°NE EKLENECEK
// PART 1'den sonra devam ediyor
// ============================================

public function page() {
    if (!current_user_can('manage_options')) return;

    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'main';

    $urls_opt = get_option(self::OPT_URLS, "");
    if (is_array($urls_opt)) {
        $urls = implode("\n", array_filter(array_map('strval', $urls_opt)));
    } else {
        $urls = (string)$urls_opt;
    }

    $s = wp_parse_args(get_option(self::OPT_SETTINGS, array()), array(
        'timeout' => 30,
        'status' => 'publish',
        'img_quality' => 85,
        'img_webp' => 1,
        'img_width' => 800,
        'img_height' => 600,
        'crop_images' => 0,
        'auto_categories' => 0,
        'create_attributes' => 0,
        'use_async' => 0,
        'rate_limit' => 10,
        'rate_window' => 60,
        'marker_symbol' => 'âœ“',
        'marker_color' => '#27ae60',
        'text_color' => '#333333',
        'strong_color' => '#2c3e50',
        'font_family' => 'Arial, sans-serif',
        'font_size' => '14px',
        'line_height' => '1.6'
    ));

    $ran = isset($_GET['ran']) ? intval($_GET['ran']) : 0;
    $synced = isset($_GET['synced']) ? intval($_GET['synced']) : 0;
    $batch_updated = isset($_GET['batch_updated']) ? intval($_GET['batch_updated']) : 0;
    $cache_cleared = isset($_GET['cache_cleared']) ? 1 : 0;
    $attr_saved = isset($_GET['attr_saved']) ? 1 : 0;
    $attrs_created = isset($_GET['attrs_created']) ? intval($_GET['attrs_created']) : 0;
    $attrs_existing = isset($_GET['attrs_existing']) ? intval($_GET['attrs_existing']) : 0;
    ?>
    <div class="wrap">
        <h1>Brother Detail Importer PRO - Secured <small>v5.4.0</small></h1>

        <?php if ($ran): ?>
            <div class="notice notice-success"><p>Ä°ÅŸlem tamamlandÄ±. Log'u kontrol edin.</p></div>
        <?php endif; ?>
        <?php if ($synced): ?>
            <div class="notice notice-success"><p><?php echo esc_html($synced); ?> kategori senkronize edildi.</p></div>
        <?php endif; ?>
        <?php if ($batch_updated): ?>
            <div class="notice notice-success"><p><?php echo esc_html($batch_updated); ?> Ã¼rÃ¼nÃ¼nÃ¼n kategorisi gÃ¼ncellendi.</p></div>
        <?php endif; ?>
        <?php if ($cache_cleared): ?>
            <div class="notice notice-success"><p>Cache temizlendi.</p></div>
        <?php endif; ?>
        <?php if ($attr_saved): ?>
            <div class="notice notice-success"><p>Attribute mapping kaydedildi.</p></div>
        <?php endif; ?>
        <?php if ($attrs_created || $attrs_existing): ?>
            <div class="notice notice-success">
                <p>
                    âœ“ Attribute kontrolÃ¼ tamamlandÄ±:
                    <?php echo $attrs_created; ?> yeni oluÅŸturuldu,
                    <?php echo $attrs_existing; ?> zaten mevcut.
                </p>
            </div>
        <?php endif; ?>

        <h2 class="nav-tab-wrapper">
            <a href="?page=bdi-pro-enhanced&tab=main" class="nav-tab <?php echo $active_tab == 'main' ? 'nav-tab-active' : ''; ?>">Ana Ayarlar</a>
            <a href="?page=bdi-pro-enhanced&tab=attributes" class="nav-tab <?php echo $active_tab == 'attributes' ? 'nav-tab-active' : ''; ?>">Attribute YÃ¶netimi</a>
            <a href="?page=bdi-pro-enhanced&tab=supplies" class="nav-tab <?php echo $active_tab == 'supplies' ? 'nav-tab-active' : ''; ?>">Sarf & Aksesuar</a>
            <a href="?page=bdi-pro-enhanced&tab=sitemap" class="nav-tab <?php echo $active_tab == 'sitemap' ? 'nav-tab-active' : ''; ?>">Sitemap KarÅŸÄ±laÅŸtÄ±rma</a>
            <a href="?page=bdi-pro-enhanced&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>">GÃ¼nlÃ¼k</a>
        </h2>

        <?php if ($active_tab == 'main'): ?>
            <h2 class="title">URL Listesi</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('bdi_save'); ?>
                <input type="hidden" name="action" value="bdi_save" />
                <p>
                    <textarea name="bdi_urls" rows="8" style="width:100%" placeholder="Her satÄ±ra bir Brother Ã¼rÃ¼n detay URL'si&#10;Ã–rnek: https://www.brother.com.tr/printers/all-printers/dcp-t530dw"><?php echo esc_textarea($urls); ?></textarea>
                </p>

                <h2 class="title">Ayarlar</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label>Timeout (saniye)</label></th>
                        <td><input type="number" name="bdi_settings[timeout]" min="10" max="120" value="<?php echo esc_attr($s['timeout']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label>YayÄ±n Durumu</label></th>
                        <td>
                            <select name="bdi_settings[status]">
                                <option value="publish" <?php selected($s['status'], 'publish'); ?>>YayÄ±nla</option>
                                <option value="draft" <?php selected($s['status'], 'draft'); ?>>Taslak</option>
                                <option value="pending" <?php selected($s['status'], 'pending'); ?>>Ä°nceleme Bekliyor</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Rate Limiting</label></th>
                        <td>
                            <input type="number" name="bdi_settings[rate_limit]" min="1" max="100" value="<?php echo esc_attr($s['rate_limit']); ?>" style="width:80px">
                            istek /
                            <input type="number" name="bdi_settings[rate_window]" min="10" max="3600" value="<?php echo esc_attr($s['rate_window']); ?>" style="width:80px">
                            saniye
                        </td>
                    </tr>
                    <?php if (function_exists('as_schedule_single_action')): ?>
                    <tr style="background:#e8f5e9;">
                        <th scope="row"><label style="color:#2e7d32;">Async Ä°ÅŸleme</label></th>
                        <td>
                            <label><input type="checkbox" name="bdi_settings[use_async]" value="1" <?php checked(1, intval($s['use_async'])); ?>>
                            <strong>Action Scheduler ile asenkron iÅŸle</strong></label>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr style="background:#f0f8ff;">
                        <th scope="row"><label style="color:#0073aa;">Otomatik Kategori</label></th>
                        <td>
                            <label><input type="checkbox" name="bdi_settings[auto_categories]" value="1" <?php checked(1, intval($s['auto_categories'])); ?>>
                            <strong>Kategorileri otomatik eÅŸleÅŸtir</strong></label>
                        </td>
                    </tr>
                    <tr style="background:#f0f8ff;">
                        <th scope="row"><label style="color:#0073aa;">Attribute OluÅŸtur</label></th>
                        <td>
                            <label><input type="checkbox" name="bdi_settings[create_attributes]" value="1" <?php checked(1, intval($s['create_attributes'])); ?>>
                            <strong>ÃœrÃ¼n Ã¶zelliklerini attribute olarak ekle</strong></label>
                            <p class="description">Teknik Ã¶zellikler sekmesinden otomatik attribute oluÅŸturur</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Resim Kalitesi</label></th>
                        <td>
                            <input type="number" name="bdi_settings[img_quality]" min="60" max="95" value="<?php echo esc_attr($s['img_quality']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>WebP OluÅŸtur</label></th>
                        <td>
                            <label><input type="checkbox" name="bdi_settings[img_webp]" value="1" <?php checked(1, intval($s['img_webp'])); ?>> WebP formatÄ±nda da kaydet</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Resim BoyutlarÄ±</label></th>
                        <td>
                            <input type="number" name="bdi_settings[img_width]" value="<?php echo esc_attr($s['img_width']); ?>" min="400" max="1600"> Ã—
                            <input type="number" name="bdi_settings[img_height]" value="<?php echo esc_attr($s['img_height']); ?>" min="300" max="1200">
                            <br>
                            <label><input type="checkbox" name="bdi_settings[crop_images]" value="1" <?php checked(1, intval($s['crop_images'])); ?>> Resimleri KÄ±rp</label>
                        </td>
                    </tr>
                </table>

                <h3>KÄ±sa AÃ§Ä±klama Stil AyarlarÄ±</h3>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label>Liste Marker Åžekli</label></th>
                        <td>
                            <select name="bdi_settings[marker_symbol]">
                                <option value="âœ“" <?php selected($s['marker_symbol'], 'âœ“'); ?>>âœ“ Checkmark</option>
                                <option value="â—" <?php selected($s['marker_symbol'], 'â—'); ?>>â— Bullet</option>
                                <option value="â–¶" <?php selected($s['marker_symbol'], 'â–¶'); ?>>â–¶ Arrow</option>
                                <option value="â˜…" <?php selected($s['marker_symbol'], 'â˜…'); ?>>â˜… Star</option>
                                <option value="âœ¦" <?php selected($s['marker_symbol'], 'âœ¦'); ?>>âœ¦ Diamond</option>
                                <option value="â†’" <?php selected($s['marker_symbol'], 'â†’'); ?>>â†’ Right Arrow</option>
                                <option value="â€¢" <?php selected($s['marker_symbol'], 'â€¢'); ?>>â€¢ Small Bullet</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Marker Rengi</label></th>
                        <td>
                            <input type="color" name="bdi_settings[marker_color]" value="<?php echo esc_attr($s['marker_color']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Metin Rengi</label></th>
                        <td>
                            <input type="color" name="bdi_settings[text_color]" value="<?php echo esc_attr($s['text_color']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>KalÄ±n Metin Rengi</label></th>
                        <td>
                            <input type="color" name="bdi_settings[strong_color]" value="<?php echo esc_attr($s['strong_color']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Font Ailesi</label></th>
                        <td>
                            <select name="bdi_settings[font_family]">
                                <option value="Arial, sans-serif" <?php selected($s['font_family'], 'Arial, sans-serif'); ?>>Arial</option>
                                <option value="'Segoe UI', Tahoma, sans-serif" <?php selected($s['font_family'], "'Segoe UI', Tahoma, sans-serif"); ?>>Segoe UI</option>
                                <option value="'Helvetica Neue', Helvetica, sans-serif" <?php selected($s['font_family'], "'Helvetica Neue', Helvetica, sans-serif"); ?>>Helvetica</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Font Boyutu</label></th>
                        <td>
                            <select name="bdi_settings[font_size]">
                                <option value="12px" <?php selected($s['font_size'], '12px'); ?>>12px</option>
                                <option value="14px" <?php selected($s['font_size'], '14px'); ?>>14px</option>
                                <option value="16px" <?php selected($s['font_size'], '16px'); ?>>16px</option>
                                <option value="18px" <?php selected($s['font_size'], '18px'); ?>>18px</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p><button type="submit" class="button button-primary">AyarlarÄ± Kaydet</button></p>
            </form>

            <hr/>

            <h2 style="color:#0073aa;">Kategori YÃ¶netimi</h2>
            <div style="background:#f0f8ff;padding:15px;border-radius:5px;margin:10px 0;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('bdi_sync_categories'); ?>
                    <input type="hidden" name="action" value="bdi_sync_categories" />
                    <p>Brother sitesindeki kategori yapÄ±sÄ±nÄ± Ã§eker ve WooCommerce'e aktarÄ±r.</p>

                    <h4>Kategori Senkronizasyon SeÃ§enekleri:</h4>
                    <p>
                        <label>
                            <input type="checkbox" name="sync_deep" value="1">
                            Derin Kategori Tarama (Alt kategorileri de Ã§eker)
                        </label>
                    </p>
                    <p>
                        <label>
                            <input type="checkbox" name="delete_orphans" value="1">
                            Brother'da olmayan kategorileri sil
                        </label>
                    </p>

                    <button class="button button-primary">Kategorileri Senkronize Et</button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px;">
                    <?php wp_nonce_field('bdi_batch_categories'); ?>
                    <input type="hidden" name="action" value="bdi_batch_categories" />
                    <button class="button" onclick="return confirm('TÃ¼m Ã¼rÃ¼nlerin kategorileri gÃ¼ncellenecek. Bu iÅŸlem uzun sÃ¼rebilir. Devam?')">
                        TÃ¼m ÃœrÃ¼nlerin Kategorilerini GÃ¼ncelle
                    </button>
                </form>

                <div style="margin-top:20px;">
                    <h4>Mevcut Brother Kategorileri:</h4>
                    $tree = $this->category_importer->get_category_tree();
                    if (!empty($tree)) {
                        echo '<ul style="list-style:disc;margin-left:20px;">';
                        foreach ($tree as $cat) {
                            echo '<li>';
                            echo esc_html($cat['name']) . ' (' . $cat['count'] . ' Ã¼rÃ¼n)';
                            if ($cat['brother_url']) {
                                echo ' <small>[' . esc_html($cat['brother_url']) . ']</small>';
                            }
                            echo '</li>';
                        }
                        echo '</ul>';
                    } else {
                        echo '<p style="color:#666;">HenÃ¼z Brother kategorisi oluÅŸturulmamÄ±ÅŸ.</p>';
                    }
                    ?>
                </div>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:10px;">
                    <?php wp_nonce_field('bdi_clear_cache'); ?>
                    <input type="hidden" name="action" value="bdi_clear_cache" />
                    <button class="button">Cache'i Temizle</button>
                </form>
            </div>

            <hr/>

            <h2>Ã‡alÄ±ÅŸtÄ±r</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('bdi_run'); ?>
                <input type="hidden" name="action" value="bdi_run" />
                <p><button class="button button-primary button-large">Listede yer alan URL'leri iÅŸle</button></p>
            </form>

        <?php elseif ($active_tab == 'attributes'): ?>
            <h2>Attribute YÃ¶netimi</h2>
            <p class="description">Brother sitesindeki Ã¶zellik adlarÄ±nÄ± WooCommerce attribute slug'larÄ±na eÅŸleÅŸtirin.</p>

            <div style="background:#e8f4f8;padding:20px;border-radius:5px;margin:20px 0;border-left:4px solid #0d2ea0;">
                <h3>ðŸ”§ Attribute Sistem KontrolÃ¼</h3>
                <p>Sistem genelinde attribute yÃ¶netimini kontrol edin ve gÃ¼ncelleyin.</p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:15px;">
                    <?php wp_nonce_field('bdi_create_attributes'); ?>
                    <input type="hidden" name="action" value="bdi_create_attributes" />
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-admin-tools" style="vertical-align:middle;"></span>
                        TÃ¼m Attribute'larÄ± OluÅŸtur/Kontrol Et
                    </button>
                    <span class="description" style="margin-left:10px;">
                        Gerekli tÃ¼m WooCommerce attribute'larÄ±nÄ± oluÅŸturur
                    </span>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                      onsubmit="return confirm('Bu iÅŸlem tÃ¼m Ã¼rÃ¼nlere attribute ekleyecek. Uzun sÃ¼rebilir. Devam?')">
                    <?php wp_nonce_field('bdi_batch_attributes'); ?>
                    <input type="hidden" name="action" value="bdi_batch_attributes" />
                    <button type="submit" class="button button-secondary">
                        <span class="dashicons dashicons-update" style="vertical-align:middle;"></span>
                        TÃ¼m ÃœrÃ¼nlere Attribute Uygula
                    </button>
                    <span class="description" style="margin-left:10px;">
                        Mevcut tÃ¼m Ã¼rÃ¼nlere specs'ten attribute ekler
                    </span>
                </form>

                if (function_exists('wc_get_attribute_taxonomies')) {
                    $existing_attrs = wc_get_attribute_taxonomies();
                    if (!empty($existing_attrs)) {
                        echo '<div style="margin-top:15px;padding:15px;background:white;border-radius:5px;">';
                        echo '<h4 style="margin:0 0 10px;">ðŸ“Š Mevcut Attribute\'lar:</h4>';
                        echo '<ul style="columns:2;column-gap:20px;margin:0;">';
                        foreach ($existing_attrs as $attr) {
                            echo '<li><strong>' . esc_html($attr->attribute_label) . '</strong> ';
                            echo '<code>pa_' . esc_html($attr->attribute_name) . '</code></li>';
                        }
                        echo '</ul>';
                        echo '</div>';
                    }
                }
                ?>
            </div>

            <h3>Attribute Mapping YÃ¶netimi</h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="attribute-mapping-form">
                <?php wp_nonce_field('bdi_save_attribute_mapping'); ?>
                <input type="hidden" name="action" value="bdi_save_attribute_mapping" />

                <table class="widefat" id="attribute-mapping-table">
                    <thead>
                        <tr>
                            <th style="width:35%;">Brother Ã–zellik AdÄ±</th>
                            <th style="width:30%;">WooCommerce Slug</th>
                            <th style="width:30%;">WooCommerce Label</th>
                            <th style="width:5%;">Ä°ÅŸlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        $current_mapping = $this->attribute_handler->get_attribute_mapping();
                        $row_index = 0;

                        foreach ($current_mapping as $brother_name => $wc_slug):
                            $row_index++;
                        ?>
                        <tr data-row="<?php echo $row_index; ?>">
                            <td>
                                <input type="text"
                                       name="attribute_mapping[<?php echo $row_index; ?>][label]"
                                       value="<?php echo esc_attr($brother_name); ?>"
                                       style="width:100%;"
                                       placeholder="BaskÄ± HÄ±zÄ±">
                            </td>
                            <td>
                                <input type="text"
                                       name="attribute_mapping[<?php echo $row_index; ?>][slug]"
                                       value="<?php echo esc_attr($wc_slug); ?>"
                                       style="width:100%;"
                                       placeholder="print-speed">
                            </td>
                            <td>
                                <input type="text"
                                       name="attribute_mapping[<?php echo $row_index; ?>][display_label]"
                                       value="<?php echo esc_attr(ucwords(str_replace('-', ' ', $wc_slug))); ?>"
                                       style="width:100%;"
                                       placeholder="Print Speed">
                            </td>
                            <td>
                                <button type="button" class="button remove-mapping-row">Sil</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p style="margin-top:10px;">
                    <button type="button" class="button" id="add-mapping-row">Yeni SatÄ±r Ekle</button>
                    <button type="submit" class="button button-primary">Mapping'i Kaydet</button>
                </p>
            </form>

            <script>
            jQuery(document).ready(function($) {
                var rowIndex = <?php echo $row_index; ?>;

                $('#add-mapping-row').on('click', function() {
                    rowIndex++;
                    var newRow = '<tr data-row="' + rowIndex + '">' +
                        '<td><input type="text" name="attribute_mapping[' + rowIndex + '][label]" style="width:100%;" placeholder="BaskÄ± HÄ±zÄ±"></td>' +
                        '<td><input type="text" name="attribute_mapping[' + rowIndex + '][slug]" style="width:100%;" placeholder="print-speed"></td>' +
                        '<td><input type="text" name="attribute_mapping[' + rowIndex + '][display_label]" style="width:100%;" placeholder="Print Speed"></td>' +
                        '<td><button type="button" class="button remove-mapping-row">Sil</button></td>' +
                        '</tr>';

                    $('#attribute-mapping-table tbody').append(newRow);
                });

                $(document).on('click', '.remove-mapping-row', function() {
                    if (confirm('Bu satÄ±rÄ± silmek istediÄŸinizden emin misiniz?')) {
                        $(this).closest('tr').remove();
                    }
                });
            });
            </script>

        <?php elseif ($active_tab == 'supplies'): ?>
            <?php $this->render_supplies_admin_tab(); ?>

        <?php elseif ($active_tab == 'sitemap'): ?>
            <?php $this->render_sitemap_comparison_tab(); ?>

        <?php elseif ($active_tab == 'logs'): ?>
            <?php $this->render_logs_tab(); ?>
        <?php endif; ?>
    </div>
}

// Devam edecek - PART 3'te tamamlanacak

// ============================================
// PART 3 - ADMIN TABS
// ============================================
// BU KISIM BDI_Pro_Enhanced CLASS Ä°Ã‡Ä°NE EKLENECEK
// PART 2'den sonra devam ediyor - SON BÃ–LÃœM
// ============================================

private function render_supplies_admin_tab() {
    echo '<h2>Sarf Malzemesi ve Aksesuar YÃ¶netimi</h2>';
    echo '<p class="description">ÃœrÃ¼nler iÃ§in toplanan sarf malzemesi ve aksesuar bilgileri</p>';

    $products_with_supplies = new WP_Query(array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => self::META_SUPPLIES,
                'compare' => 'EXISTS'
            )
        ),
        'fields' => 'ids'
    ));

    $products_with_accessories = new WP_Query(array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => self::META_ACCESSORIES,
                'compare' => 'EXISTS'
            )
        ),
        'fields' => 'ids'
    ));

    $all_product_ids = array_unique(array_merge(
        $products_with_supplies->posts,
        $products_with_accessories->posts
    ));

    if (!empty($all_product_ids)) {
        echo '<table class="widefat" style="margin-top:20px;">';
        echo '<thead><tr>';
        echo '<th style="width:25%;">ÃœrÃ¼n</th>';
        echo '<th style="width:10%;">Model</th>';
        echo '<th style="width:25%;">Uyumlu Sarf Malzemeleri</th>';
        echo '<th style="width:25%;">Uyumlu Aksesuarlar</th>';
        echo '<th style="width:15%;">Ä°ÅŸlem</th>';
        echo '</tr></thead><tbody>';

        foreach ($all_product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;

            $supplies_json = get_post_meta($product_id, self::META_SUPPLIES, true);
            $supplies = json_decode($supplies_json, true);

            $accessories_json = get_post_meta($product_id, self::META_ACCESSORIES, true);
            $accessories = json_decode($accessories_json, true);

            if (empty($supplies) && empty($accessories)) continue;

            echo '<tr>';
            echo '<td><strong>' . esc_html($product->get_name()) . '</strong><br>';
            echo '<small><a href="' . get_edit_post_link($product_id) . '" target="_blank">DÃ¼zenle</a></small></td>';
            echo '<td>' . esc_html($product->get_sku()) . '</td>';

            // Sarf malzemeleri
            echo '<td>';
            if (!empty($supplies) && is_array($supplies)) {
                echo '<ul style="margin:0;padding-left:20px;">';
                foreach ($supplies as $supply) {
                    echo '<li><strong>' . esc_html($supply['code']) . '</strong>';
                    if (!empty($supply['name']) && $supply['name'] !== $supply['code']) {
                        echo ' - ' . esc_html($supply['name']);
                    }
                    if (!empty($supply['type'])) {
                        echo ' <span style="background:#0d2ea0;color:white;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:5px;">';
                        echo esc_html($supply['type']) . '</span>';
                    }
                    echo '</li>';
                }
                echo '</ul>';
            } else {
                echo '<span style="color:#999;">-</span>';
            }
            echo '</td>';

            // Aksesuarlar
            echo '<td>';
            if (!empty($accessories) && is_array($accessories)) {
                echo '<ul style="margin:0;padding-left:20px;">';
                foreach ($accessories as $accessory) {
                    echo '<li><strong>' . esc_html($accessory['code']) . '</strong>';
                    if (!empty($accessory['name']) && $accessory['name'] !== $accessory['code']) {
                        echo ' - ' . esc_html($accessory['name']);
                    }
                    if (!empty($accessory['type'])) {
                        echo ' <span style="background:#28a745;color:white;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:5px;">';
                        echo esc_html($accessory['type']) . '</span>';
                    }
                    echo '</li>';
                }
                echo '</ul>';
            } else {
                echo '<span style="color:#999;">-</span>';
            }
            echo '</td>';

            echo '<td>';
            echo '<p style="margin:0;font-size:11px;color:#666;">ÃœrÃ¼n bilgileri saklÄ± - Shortcode kullanarak gÃ¶sterebilirsiniz</p>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    } else {
        echo '<div style="padding:40px;text-align:center;background:#fff;border:1px solid #ddd;border-radius:5px;margin-top:20px;">';
        echo '<p style="font-size:16px;color:#666;">ðŸ“¦ HenÃ¼z sarf malzemesi veya aksesuar verisi bulunan Ã¼rÃ¼n yok.</p>';
        echo '<p>ÃœrÃ¼nleri iÃ§e aktardÄ±ÄŸÄ±nÄ±zda, Brother API\'den tedarik bilgileri otomatik olarak Ã§ekilecektir.</p>';
        echo '</div>';
    }
}

private function render_sitemap_comparison_tab() {
    ?>
    <div class="wrap">
        <h2>Brother Sitemap KarÅŸÄ±laÅŸtÄ±rma</h2>
        <p class="description">Brother.com.tr sitemap'lerini tarayarak eksik Ã¼rÃ¼nleri tespit edin.</p>

        <div style="background:#fff;padding:20px;border:1px solid #ddd;border-radius:5px;margin:20px 0;">
            <h3>ðŸ” Sitemap KontrolÃ¼</h3>
            <p>AÅŸaÄŸÄ±daki butonlara tÄ±klayarak Brother'Ä±n sitemap'lerini tarayÄ±n ve eksik Ã¼rÃ¼nleri gÃ¶rÃ¼n.</p>

            <button type="button" class="button button-primary button-large" id="scan-product-sitemap" style="margin-right:10px;">
                <span class="dashicons dashicons-search" style="vertical-align:middle;"></span>
                ÃœrÃ¼n Sitemap'ini Tara
            </button>

            <div id="sitemap-scan-result" style="margin-top:20px;"></div>
        </div>

        <div id="missing-products-container" style="display:none;"></div>

        <script>
        jQuery(document).ready(function($) {
            $('#scan-product-sitemap').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> TaranÄ±yor...');

                $('#sitemap-scan-result').html('<div class="notice notice-info"><p>Sitemap taranÄ±yor, lÃ¼tfen bekleyin...</p></div>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bdi_scan_sitemap',
                        sitemap_type: 'product',
                        nonce: '<?php echo wp_create_nonce('bdi_sitemap_scan'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#missing-products-container').html(response.data.html).slideDown();
                            $('#sitemap-scan-result').html(
                                '<div class="notice notice-success"><p>âœ“ Tarama tamamlandÄ±. ' +
                                response.data.missing_count + ' eksik Ã¼rÃ¼n bulundu.</p></div>'
                            );
                        } else {
                            $('#sitemap-scan-result').html(
                                '<div class="notice notice-error"><p>Hata: ' + response.data + '</p></div>'
                            );
                        }
                        btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> ÃœrÃ¼n Sitemap\'ini Tara');
                    }
                });
            });

            $(document).on('click', '.bdi-add-to-list', function() {
                var btn = $(this);
                var url = btn.data('url');

                btn.prop('disabled', true).html('Ekleniyor...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bdi_add_url_to_list',
                        url: url,
                        nonce: '<?php echo wp_create_nonce('bdi_add_url'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            btn.html('âœ“ Eklendi').css('background', '#28a745');
                            setTimeout(function() {
                                btn.closest('tr').fadeOut();
                            }, 1000);
                        }
                    }
                });
            });
        });
        </script>
    </div>
}

private function render_logs_tab() {
    ?>
    <h2>GÃ¼nlÃ¼k</h2>
    <p>
        <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=bdi_download_log'), 'bdi_download_log')); ?>">Log'u Ä°ndir</a>
        <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=bdi_delete_log'), 'bdi_delete_log')); ?>">Log'u Temizle</a>
    </p>

    <div style="margin-bottom:15px;">
        <label>
            <input type="checkbox" id="show-debug-logs" checked> DEBUG seviyesi loglarÄ± gÃ¶ster
        </label>
    </div>

    <div style="max-height:600px;overflow:auto;background:#fff;border:1px solid #ddd;padding:10px;">
        $logs = $this->logger->get_logs(500);
        foreach ($logs as $log) {
            $level = isset($log['level']) ? $log['level'] : 'info';
            $level_class = 'bdi-log-' . $level;
            $timestamp = isset($log['timestamp']) ? $log['timestamp'] : '';
            $message = isset($log['message']) ? $log['message'] : '';

            $data_level = ($level === 'debug') ? 'data-level="debug"' : '';

            echo '<div class="bdi-log-entry ' . esc_attr($level_class) . '" ' . $data_level . ' style="font-family:monospace;margin-bottom:2px;font-size:12px;">';
            echo '<strong>' . esc_html($timestamp) . '</strong> â€” ';
            echo '<span class="log-level-badge" style="display:inline-block;padding:2px 6px;border-radius:3px;font-size:10px;margin-right:5px;">' . strtoupper($level) . '</span>';
            echo esc_html($message);
            echo '</div>' . "\n";
        }
        ?>
    </div>

    <style>
    .bdi-log-error, .bdi-log-critical { color: #d63638; }
    .bdi-log-warning { color: #dba617; }
    .bdi-log-info { color: #2271b1; }
    .bdi-log-debug { color: #666; }
    .bdi-log-error .log-level-badge { background: #d63638; color: white; }
    .bdi-log-critical .log-level-badge { background: #8b0000; color: white; }
    .bdi-log-warning .log-level-badge { background: #dba617; color: white; }
    .bdi-log-info .log-level-badge { background: #2271b1; color: white; }
    .bdi-log-debug .log-level-badge { background: #999; color: white; }
    </style>

    <script>
    jQuery(document).ready(function($) {
        $('#show-debug-logs').on('change', function() {
            if ($(this).is(':checked')) {
                $('.bdi-log-entry[data-level="debug"]').show();
            } else {
                $('.bdi-log-entry[data-level="debug"]').hide();
            }
        });
    });
    </script>
}

public function save() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('bdi_save');

    $urls = isset($_POST['bdi_urls']) ? wp_unslash($_POST['bdi_urls']) : "";

    $url_lines = preg_split('/\r\n|\r|\n/', $urls);
    $sanitized_urls = array();

    foreach ($url_lines as $url) {
        $url = trim($url);
        if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
            $sanitized_urls[] = esc_url_raw($url);
        }
    }

    update_option(self::OPT_URLS, implode("\n", $sanitized_urls));

    $in = isset($_POST['bdi_settings']) && is_array($_POST['bdi_settings']) ? (array)$_POST['bdi_settings'] : array();
    $s = wp_parse_args($in, array(
        'timeout' => 30,
        'status' => 'publish',
        'img_quality' => 85,
        'img_webp' => 1,
        'img_width' => 800,
        'img_height' => 600,
        'crop_images' => 0,
        'auto_categories' => 0,
        'create_attributes' => 0,
        'use_async' => 0,
        'rate_limit' => 10,
        'rate_window' => 60,
        'marker_symbol' => 'âœ“',
        'marker_color' => '#27ae60',
        'text_color' => '#333333',
        'strong_color' => '#2c3e50',
        'font_family' => 'Arial, sans-serif',
        'font_size' => '14px',
        'line_height' => '1.6'
    ));

    $s['timeout'] = max(10, min(120, intval($s['timeout'])));
    $s['img_quality'] = max(60, min(95, intval($s['img_quality'])));
    $s['img_width'] = max(400, min(1600, intval($s['img_width'])));
    $s['img_height'] = max(300, min(1200, intval($s['img_height'])));
    $s['rate_limit'] = max(1, min(100, intval($s['rate_limit'])));
    $s['rate_window'] = max(10, min(3600, intval($s['rate_window'])));
    $s['img_webp'] = !empty($s['img_webp']) ? 1 : 0;
    $s['crop_images'] = !empty($s['crop_images']) ? 1 : 0;
    $s['auto_categories'] = !empty($s['auto_categories']) ? 1 : 0;
    $s['create_attributes'] = !empty($s['create_attributes']) ? 1 : 0;
    $s['use_async'] = !empty($s['use_async']) ? 1 : 0;

    $allowed_markers = array('âœ“', 'â—', 'â–¶', 'â˜…', 'âœ¦', 'â†’', 'â€¢');
    if (!in_array($s['marker_symbol'], $allowed_markers)) $s['marker_symbol'] = 'âœ“';

    $allowed_fonts = array('Arial, sans-serif', "'Segoe UI', Tahoma, sans-serif", "'Helvetica Neue', Helvetica, sans-serif");
    if (!in_array($s['font_family'], $allowed_fonts)) $s['font_family'] = 'Arial, sans-serif';

    $allowed_sizes = array('12px', '14px', '16px', '18px');
    if (!in_array($s['font_size'], $allowed_sizes)) $s['font_size'] = '14px';

    if (!preg_match('/^#[a-fA-F0-9]{6}$/', $s['marker_color'])) $s['marker_color'] = '#27ae60';
    if (!preg_match('/^#[a-fA-F0-9]{6}$/', $s['text_color'])) $s['text_color'] = '#333333';
    if (!preg_match('/^#[a-fA-F0-9]{6}$/', $s['strong_color'])) $s['strong_color'] = '#2c3e50';

    if (!in_array($s['status'], array('publish', 'draft', 'pending'), true)) {
        $s['status'] = 'publish';
    }

    update_option(self::OPT_SETTINGS, $s);

    wp_redirect(admin_url('admin.php?page=bdi-pro-enhanced'));
    exit;
}

public function save_attribute_mapping() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('bdi_save_attribute_mapping');

    $mapping_input = isset($_POST['attribute_mapping']) ? (array)$_POST['attribute_mapping'] : array();

    $final_mapping = array();

    foreach ($mapping_input as $row) {
        if (!isset($row['label']) || !isset($row['slug'])) {
            continue;
        }

        $label = sanitize_text_field($row['label']);
        $slug = sanitize_title($row['slug']);

        if ($label && $slug) {
            $final_mapping[$label] = $slug;
        }
    }

    update_option('bdi_attribute_mapping', $final_mapping);

    $this->logger->log(count($final_mapping) . ' attribute mapping kaydedildi', BDI_Logger::LEVEL_INFO);

    wp_redirect(admin_url('admin.php?page=bdi-pro-enhanced&tab=attributes&attr_saved=1'));
    exit;
}

public function handle_create_attributes() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('bdi_create_attributes');

    $result = $this->ensure_all_product_attributes();

    wp_redirect(admin_url('admin.php?page=bdi-pro-enhanced&tab=attributes&attrs_created=' . $result['created'] . '&attrs_existing=' . $result['existing']));
    exit;
}

public function handle_batch_attributes() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('bdi_batch_attributes');

    @set_time_limit(300);
    @ini_set('max_execution_time', '300');

    $updated = $this->batch_apply_attributes_to_all_products();

    wp_redirect(admin_url('admin.php?page=bdi-pro-enhanced&tab=attributes&batch_updated=' . $updated));
    exit;
}

public function ensure_all_product_attributes() {
    $attributes_to_create = array(
        'brand' => array('name' => 'Marka', 'type' => 'select'),
        'tip' => array('name' => 'Tip', 'type' => 'select'),
        'sayfa_verimi' => array('name' => 'Sayfa Verimi', 'type' => 'text'),
        'aksesuar_tipi' => array('name' => 'Aksesuar Tipi', 'type' => 'select'),
        'baski-teknolojisi' => array('name' => 'BaskÄ± Teknolojisi', 'type' => 'select'),
        'baski-hizi' => array('name' => 'BaskÄ± HÄ±zÄ±', 'type' => 'text'),
        'kagit-boyutu' => array('name' => 'Maksimum KaÄŸÄ±t Boyutu', 'type' => 'select'),
        'baglanti' => array('name' => 'BaÄŸlantÄ±', 'type' => 'select'),
        'duplex' => array('name' => 'Ã‡ift TaraflÄ± BaskÄ±', 'type' => 'select'),
        'ekran' => array('name' => 'Ekran', 'type' => 'select'),
        'renk-tip' => array('name' => 'Renk Tipi', 'type' => 'select'),
        'wifi' => array('name' => 'WiFi', 'type' => 'select'),
        'tarama-cozunurlugu' => array('name' => 'Tarama Ã‡Ã¶zÃ¼nÃ¼rlÃ¼ÄŸÃ¼', 'type' => 'text'),
        'kagit-kapasitesi' => array('name' => 'KaÄŸÄ±t Kapasitesi', 'type' => 'text')
    );

    $created = 0;
    $existing = 0;

    foreach ($attributes_to_create as $slug => $config) {
        $taxonomy = 'pa_' . $slug;

        if (taxonomy_exists($taxonomy)) {
            $existing++;
            continue;
        }

        if (function_exists('wc_create_attribute')) {
            $result = wc_create_attribute(array(
                'slug' => $slug,
                'name' => $config['name'],
                'type' => $config['type'],
                'orderby' => 'menu_order',
                'has_archives' => false
            ));

            if (!is_wp_error($result)) {
                register_taxonomy($taxonomy, 'product', array(
                    'hierarchical' => false,
                    'label' => $config['name'],
                    'query_var' => true,
                    'rewrite' => array('slug' => $slug),
                    'show_in_nav_menus' => false,
                    'show_ui' => false
                ));

                $created++;
                $this->logger->log('Attribute oluÅŸturuldu: ' . $config['name'], BDI_Logger::LEVEL_INFO);
            }
        }
    }

    delete_transient('wc_attribute_taxonomies');

    if (class_exists('WC_Cache_Helper')) {
        WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');
    }

    $this->logger->log(
        sprintf('Attribute kontrolÃ¼: %d yeni oluÅŸturuldu, %d zaten mevcut', $created, $existing),
        BDI_Logger::LEVEL_INFO
    );

    return array('created' => $created, 'existing' => $existing);
}

public function batch_apply_attributes_to_all_products() {
    $this->ensure_all_product_attributes();

    $products = new WP_Query(array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => array('publish', 'draft'),
        'fields' => 'ids',
        'no_found_rows' => true
    ));

    if (empty($products->posts)) {
        $this->logger->log('Attribute eklenecek Ã¼rÃ¼n bulunamadÄ±', BDI_Logger::LEVEL_WARNING);
        return 0;
    }

    $processed = 0;
    $updated = 0;

    foreach ($products->posts as $product_id) {
        $processed++;

        $specs_html = get_post_meta($product_id, self::META_SPECS, true);

        if ($specs_html) {
            $attributes = $this->attribute_handler->extract_product_attributes($specs_html);

            if (!empty($attributes)) {
                $result = $this->attribute_handler->create_wc_attributes($attributes, $product_id);
                if ($result) {
                    $updated++;
                }
            }
        }

        $this->ensure_brand_attribute();
        if (taxonomy_exists('pa_brand')) {
            wp_set_object_terms($product_id, 'Brother', 'pa_brand', false);
        }

        if ($processed % 50 == 0) {
            $this->logger->log(
                sprintf('Ä°lerleme: %d/%d Ã¼rÃ¼n iÅŸlendi', $processed, count($products->posts)),
                BDI_Logger::LEVEL_INFO
            );
        }
    }

    $this->logger->log(
        sprintf('Toplu attribute ekleme tamamlandÄ±: %d Ã¼rÃ¼n iÅŸlendi, %d gÃ¼ncellendi', $processed, $updated),
        BDI_Logger::LEVEL_INFO
    );

    return $updated;
}

private static function ensure_brand_attribute_static() {
    if (!class_exists('WooCommerce')) return;

    $tax_id = 0;
    if (function_exists('wc_get_attribute_taxonomy_id_by_name')) {
        $tax_id = wc_get_attribute_taxonomy_id_by_name('brand');
    } elseif (function_exists('wc_attribute_taxonomy_id_by_name')) {
        $tax_id = wc_attribute_taxonomy_id_by_name('brand');
    }

    if (!$tax_id && function_exists('wc_create_attribute')) {
        $args = array(
            'slug' => 'brand',
            'name' => 'Brand',
            'type' => 'select',
            'orderby' => 'menu_order',
            'has_archives' => false
        );
        wc_create_attribute($args);
    }

    if (!taxonomy_exists('pa_brand')) {
        register_taxonomy('pa_brand', 'product', array(
            'hierarchical' => false,
            'label' => 'Brand',
            'query_var' => true,
            'rewrite' => array('slug' => 'brand')
        ));
        flush_rewrite_rules();
    }
}

private function ensure_brand_attribute() {
    self::ensure_brand_attribute_static();
}

public function run_now() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('bdi_run');

    try {
        $this->process(false);
    } catch (Throwable $e) {
        $this->logger->log('Process Throwable: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), BDI_Logger::LEVEL_CRITICAL);
    }

    wp_redirect(admin_url('admin.php?page=bdi-pro-enhanced&ran=1'));
    exit;
}

public function download_log() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('bdi_download_log');

    $logs = $this->logger->get_logs(1000);

    $txt = "";
    foreach ($logs as $log) {
        $level = isset($log['level']) ? '[' . strtoupper($log['level']) . '] ' : '';
        $time = isset($log['timestamp']) ? $log['timestamp'] : '';
        $message = isset($log['message']) ? $log['message'] : '';
        $txt .= $time . " â€” " . $level . $message . "\n";
    }

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="bdi_log_' . date('Y-m-d') . '.txt"');
    echo $txt;
    exit;
}

public function delete_log() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('bdi_delete_log');

    $this->logger->clear_logs();
    wp_redirect(admin_url('admin.php?page=bdi-pro-enhanced&tab=logs'));
    exit;
}

public function sync_categories() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('bdi_sync_categories');

    try {
        $deep_sync = !empty($_POST['sync_deep']);
        $delete_orphans = !empty($_POST['delete_orphans']);

        if ($delete_orphans) {
            $existing = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
                'meta_key' => 'brother_category',
                'meta_value' => true,
                'fields' => 'ids'
            ));
            $this->logger->log('Mevcut Brother kategorileri yedeklendi: ' . count($existing), BDI_Logger::LEVEL_INFO);
        }

        if ($deep_sync) {
            $this->cache_manager->delete('category_structure_v2');
        }

        $created = $this->category_importer->create_wc_categories();

        if ($delete_orphans && isset($existing)) {
            $new_cats = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
                'meta_key' => 'brother_category',
                'meta_value' => true,
                'fields' => 'ids'
            ));

            $orphans = array_diff($existing, $new_cats);
            foreach ($orphans as $orphan_id) {
                wp_delete_term($orphan_id, 'product_cat');
                $this->logger->log('Orphan kategori silindi: #' . $orphan_id, BDI_Logger::LEVEL_INFO);
            }
        }

        $this->logger->log('Kategori senkronizasyonu tamamlandÄ±. OluÅŸturulan: ' . $created, BDI_Logger::LEVEL_INFO);

        wp_redirect(admin_url('admin.php?page=bdi-pro-enhanced&synced=' . $created));
    } catch (Exception $e) {
        $this->logger->log('Kategori senkronizasyon hatasÄ±: ' . $e->getMessage(), BDI_Logger::LEVEL_ERROR);
        wp_redirect(admin_url('admin.php?page=bdi-pro-enhanced&error=1'));
    }
    exit;
}

public function handle_batch_categories() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('bdi_batch_categories');

    $updated = $this->batch_update_product_categories();

    wp_redirect(admin_url('admin.php?page=bdi-pro-enhanced&batch_updated=' . $updated));
    exit;
}

public function clear_cache() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized');
    check_admin_referer('bdi_clear_cache');

    $this->cache_manager->flush_all();
    $this->logger->log('Cache temizlendi', BDI_Logger::LEVEL_INFO);

    wp_redirect(admin_url('admin.php?page=bdi-pro-enhanced&cache_cleared=1'));
    exit;
}


// Burada class BDI_Pro_Enhanced kapanÄ±yor
}
endif;

// Plugin baÅŸlatma
register_activation_hook(__FILE__, array('BDI_Pro_Enhanced', 'activate'));
register_deactivation_hook(__FILE__, array('BDI_Pro_Enhanced', 'deactivate'));

if (class_exists('BDI_Pro_Enhanced') && !isset($GLOBALS['bdi_pro_enhanced'])) {
    $GLOBALS['bdi_pro_enhanced'] = new BDI_Pro_Enhanced();
}

// ============================================
// PART 4 - PROCESSING & PARSING
// ============================================
// PART 4 - Ä°ÅžLEME VE PARSE METODLARI
// BDI_Pro_Enhanced class iÃ§ine eklenecek
// ============================================

public function cron() {
    try {
        $this->process(false);
    } catch (Throwable $e) {
        $this->logger->log('Cron Throwable: ' . $e->getMessage(), BDI_Logger::LEVEL_ERROR);
    }
}

public function process($dry = false) {
    if (!class_exists('WooCommerce')) {
        $this->logger->log('WooCommerce aktif deÄŸil.', BDI_Logger::LEVEL_ERROR);
        return;
    }

    if (!class_exists('DOMDocument')) {
        $this->logger->log('PHP DOM eklentisi yok.', BDI_Logger::LEVEL_ERROR);
        return;
    }

    @ini_set('max_execution_time', '300');
    @set_time_limit(300);

    $this->apply_image_size_filters();

    $urls_raw = get_option(self::OPT_URLS, '');
    if (!$urls_raw) {
        $this->logger->log('URL listesi boÅŸ.', BDI_Logger::LEVEL_WARNING);
        $this->remove_image_size_filters();
        return;
    }

    $urls = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $urls_raw))));
    $total = count($urls);

    if ($total == 0) {
        $this->logger->log('URL listesi boÅŸ veya geÃ§ersiz.', BDI_Logger::LEVEL_WARNING);
        $this->remove_image_size_filters();
        return;
    }

    $s = wp_parse_args(get_option(self::OPT_SETTINGS, array()), array(
        'timeout' => 30,
        'status' => 'publish',
        'img_quality' => 85,
        'img_webp' => 1,
        'img_width' => 800,
        'img_height' => 600,
        'crop_images' => 0,
        'auto_categories' => 0,
        'create_attributes' => 0,
        'use_async' => 0,
        'rate_limit' => 10,
        'rate_window' => 60,
        'mapping' => '{}',
        'retry_count' => 3,
        'min_delay' => 1,
        'max_delay' => 3
    ));

    $map = json_decode($s['mapping'], true);
    if (!is_array($map)) $map = array();

    $this->logger->log('BaÅŸladÄ±: ' . $total . ' URL' . ($dry ? ' (DRY-RUN)' : ''), BDI_Logger::LEVEL_INFO);

    if (!empty($s['use_async']) && function_exists('as_schedule_single_action') && !$dry) {
        foreach ($urls as $index => $url) {
            as_schedule_single_action(time() + ($index * 5), 'bdi_process_single_url', array($url, $s));
        }

        $this->logger->log('URL\'ler async iÅŸleme kuyruÄŸuna eklendi', BDI_Logger::LEVEL_INFO);
        $this->remove_image_size_filters();
        return;
    }

    $imported_or_updated = array();
    $this->processed_count = 0;
    $this->error_count = 0;

    foreach ($urls as $index => $u) {
        if (!$dry && !$this->rate_limiter->check($u, intval($s['rate_limit']), intval($s['rate_window']))) {
            $this->logger->log('Rate limit aÅŸÄ±ldÄ±, bekleniyor: ' . $u, BDI_Logger::LEVEL_WARNING);
            sleep(intval($s['rate_window']));
            $this->rate_limiter->reset($u);
        }

        $this->logger->log('Ä°ÅŸleniyor (' . ($index + 1) . '/' . $total . '): ' . $u, BDI_Logger::LEVEL_INFO);

        try {
            $ok = $this->handle_single($u, $s, $map, $dry);
            if ($ok && !$dry) {
                $imported_or_updated[] = $ok;
                $this->create_attributes_from_specs($ok);
            }
            $this->processed_count++;
        } catch (Throwable $e) {
            $this->logger->log('Tekil URL hatasÄ±: ' . $u . ' â€” ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), BDI_Logger::LEVEL_ERROR);
            $this->error_count++;
        }

        if (!$dry) {
            $delay = rand(intval($s['min_delay']), intval($s['max_delay']));
            if ($delay > 0) sleep($delay);
        }

        $this->logger->log('Ä°lerleme: ' . ($index + 1) . '/' . $total . ' tamamlandÄ±', BDI_Logger::LEVEL_INFO);
    }

    $this->remove_image_size_filters();
    $this->logger->log('TamamlandÄ±. Ä°ÅŸlenen: ' . $this->processed_count . ', Hata: ' . $this->error_count . ' (Toplam: ' . $total . ')', BDI_Logger::LEVEL_INFO);
}

public function process_single_url_async($url, $settings) {
    try {
        $this->apply_image_size_filters();
        $map = json_decode($settings['mapping'], true);
        if (!is_array($map)) $map = array();

        $result = $this->handle_single($url, $settings, $map, false);

        if ($result) {
            $this->create_attributes_from_specs($result);
            $this->logger->log('Async iÅŸlem baÅŸarÄ±lÄ±: ' . $url, BDI_Logger::LEVEL_INFO);
        }

        $this->remove_image_size_filters();
    } catch (Exception $e) {
        $this->logger->log('Async iÅŸlem hatasÄ±: ' . $url . ' - ' . $e->getMessage(), BDI_Logger::LEVEL_ERROR);
    }
}

private function handle_single($url, $s, $map, $dry = false) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $this->logger->log('GeÃ§ersiz URL: ' . $url, BDI_Logger::LEVEL_ERROR);
        return 0;
    }

    $parsed = parse_url($url);
    if (!isset($parsed['host']) || !in_array($parsed['host'], array('www.brother.com.tr', 'brother.com.tr'))) {
        $this->logger->log('GÃ¼venlik: YalnÄ±zca brother.com.tr URL\'leri kabul edilir: ' . $url, BDI_Logger::LEVEL_ERROR);
        return 0;
    }

    $html = $this->fetch($url, intval($s['timeout']));
    if (!$html) {
        $this->logger->log('Ä°ndirilemedi: ' . $url, BDI_Logger::LEVEL_ERROR);
        return 0;
    }

    try {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);

        if (!@$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new Exception('HTML parse hatasÄ±');
        }

        $xp = new DOMXPath($doc);

        $title = $this->xp_text($xp, '//h1');
        if (!$title) $title = $this->xp_text($xp, '//title');

        $overview_html = $this->extract_overview($doc, $xp);
        $overview_html = $this->absolutize_urls($overview_html, $url);

        $specifications_html = $this->extract_specifications($doc, $xp);
        if (!empty($specifications_html)) {
            $specifications_html = $this->absolutize_urls($specifications_html, $url);
        }

        $product_item_id = $this->extract_product_item_id($html);

        $supplies_data = array();
        $accessories_data = array();

        if ($product_item_id) {
            $supplies_data = $this->fetch_supplies_from_api($product_item_id);
            $accessories_data = $this->fetch_accessories_from_api($product_item_id);
        } else {
            $this->logger->log('ProductItemId bulunamadÄ±, API Ã§aÄŸrÄ±sÄ± yapÄ±lamÄ±yor', BDI_Logger::LEVEL_WARNING);
        }

        $downloads_html = $this->extract_support_content($doc, $xp, $url, $supplies_data, $accessories_data);

        $extra_functions = $this->extract_product_extra_functions($doc, $xp);
        $extra_functions = $this->absolutize_urls($extra_functions, $url);

        if (!empty($s['auto_categories'])) {
            $categories = $this->category_importer->match_product_to_categories($url, $title, $html);
            $this->logger->log('Otomatik kategori eÅŸleÅŸtirme: ' . implode(', ', $categories), BDI_Logger::LEVEL_INFO);
        } else {
            $categories = $this->apply_mapping_and_cleanup($this->extract_categories($xp), $map);
        }

        $attributes = array();
        if (!empty($s['create_attributes'])) {
            $attributes = $this->attribute_handler->extract_product_attributes($html);
            if (!empty($attributes)) {
                $this->logger->log('Bulunan Ã¶zellik sayÄ±sÄ±: ' . count($attributes), BDI_Logger::LEVEL_INFO);
            }
        }

        $sku = $this->extract_model_from_title($title);
        $images = $this->extract_product_visuals($url, $xp, $doc, $sku);

        $content = '<div class="bro-overview">' . $overview_html . '</div>';

        $pid = $this->find_product_by_source($url);
        if (!$pid && $title) $pid = $this->find_product_by_title($title);

        $hash = md5(wp_json_encode(array($title, $overview_html, $images, $categories, $sku, $extra_functions, $attributes)));

        if ($dry) {
            $info = '[DRY] ' . $title . ' â€” iÃ§erik: ' . strlen($overview_html) . ' karakter, kÄ±sa aÃ§Ä±klama: ' . strlen($extra_functions) . ' karakter, resim: ' . count($images) . ', kategori: ' . implode(' â€º ', $categories);
            if (!empty($attributes)) {
                $info .= ', Ã¶zellik: ' . count($attributes);
            }
            if (!empty($supplies_data)) {
                $info .= ', sarf malzemesi: ' . count($supplies_data);
            }
            if (!empty($accessories_data)) {
                $info .= ', aksesuar: ' . count($accessories_data);
            }
            $this->logger->log($info, BDI_Logger::LEVEL_INFO);
            return 0;
        }

        if ($pid) {
            $prev = get_post_meta($pid, self::META_HASH, true);
            $clean_excerpt = $extra_functions ? $this->clean_short_description($extra_functions) : '';
            wp_update_post(array(
                'ID' => $pid,
                'post_title' => wp_strip_all_tags($title),
                'post_content' => $content,
                'post_excerpt' => $clean_excerpt,
                'post_status' => $s['status'],
            ));
            update_post_meta($pid, self::META_HASH, $hash);
            $this->logger->log(($prev === $hash ? 'GÃ¼ncelleme (deÄŸiÅŸim yok)' : 'GÃ¼ncellendi') . ': #' . $pid . ' â€” ' . $title, BDI_Logger::LEVEL_INFO);
        } else {
            $clean_excerpt = $extra_functions ? $this->clean_short_description($extra_functions) : '';
            $pid = wp_insert_post(array(
                'post_title' => wp_strip_all_tags($title),
                'post_content' => $content,
                'post_excerpt' => $clean_excerpt,
                'post_status' => $s['status'],
                'post_type' => 'product',
            ));

            if (!is_wp_error($pid) && $pid) {
                add_post_meta($pid, self::META_SOURCE_URL, esc_url_raw($url), true);
                update_post_meta($pid, self::META_HASH, $hash);
                $this->logger->log('OluÅŸturuldu: #' . $pid . ' â€” ' . $title, BDI_Logger::LEVEL_INFO);
            } else {
                $this->logger->log('OluÅŸturma hatasÄ±: ' . $title, BDI_Logger::LEVEL_ERROR);
                return 0;
            }
        }

        if ($sku) {
            update_post_meta($pid, '_sku', function_exists('wc_clean') ? wc_clean($sku) : sanitize_text_field($sku));
        }

        if (!empty($specifications_html)) {
            update_post_meta($pid, self::META_SPECS, $specifications_html);

            if (!empty($s['create_attributes'])) {
                $attributes = $this->attribute_handler->extract_product_attributes($specifications_html);
                if (!empty($attributes)) {
                    $this->attribute_handler->create_wc_attributes($attributes, $pid);
                    $this->logger->log('Attribute eklendi: ' . count($attributes), BDI_Logger::LEVEL_INFO);
                }
            }
        } else {
            delete_post_meta($pid, self::META_SPECS);
        }

        if (!empty($downloads_html)) {
            update_post_meta($pid, self::META_SUPPORT, $downloads_html);
        } else {
            delete_post_meta($pid, self::META_SUPPORT);
        }

        if (!empty($supplies_data)) {
            update_post_meta($pid, self::META_SUPPLIES, wp_json_encode($supplies_data));

            $supply_codes = array();
            foreach ($supplies_data as $supply) {
                if (!empty($supply['code'])) {
                    $supply_codes[] = $supply['code'];
                }
            }
            if (!empty($supply_codes)) {
                update_post_meta($pid, self::META_SUPPLY_CODES, implode(',', $supply_codes));
                $this->logger->log(
                    sprintf('ÃœrÃ¼n #%d iÃ§in %d sarf malzemesi kaydedildi: %s',
                        $pid,
                        count($supply_codes),
                        implode(', ', $supply_codes)
                    ),
                    BDI_Logger::LEVEL_INFO
                );
            }
        } else {
            delete_post_meta($pid, self::META_SUPPLIES);
            delete_post_meta($pid, self::META_SUPPLY_CODES);
        }

        if (!empty($accessories_data)) {
            update_post_meta($pid, self::META_ACCESSORIES, wp_json_encode($accessories_data));

            $accessory_codes = array();
            foreach ($accessories_data as $accessory) {
                if (!empty($accessory['code'])) {
                    $accessory_codes[] = $accessory['code'];
                }
            }
            if (!empty($accessory_codes)) {
                update_post_meta($pid, self::META_ACCESSORY_CODES, implode(',', $accessory_codes));
                $this->logger->log(
                    sprintf('ÃœrÃ¼n #%d iÃ§in %d aksesuar kaydedildi: %s',
                        $pid,
                        count($accessory_codes),
                        implode(', ', $accessory_codes)
                    ),
                    BDI_Logger::LEVEL_INFO
                );
            }
        } else {
            delete_post_meta($pid, self::META_ACCESSORIES);
            delete_post_meta($pid, self::META_ACCESSORY_CODES);
        }

        $this->ensure_brand_attribute();
        if (taxonomy_exists('pa_brand')) {
            wp_set_object_terms($pid, 'Brother', 'pa_brand', true);
        }

        if ($categories) {
            $term_ids = $this->ensure_categories($categories);
            if ($term_ids) wp_set_object_terms($pid, $term_ids, 'product_cat');
        }

        if (!empty($attributes) && !empty($s['create_attributes'])) {
            $this->attribute_handler->create_wc_attributes($attributes, $pid);
            $this->logger->log('Ã–zellikler eklendi: ' . count($attributes), BDI_Logger::LEVEL_INFO);
        }

        if ($images) {
            $this->import_images_optimized(
                $pid,
                $title,
                $sku,
                $images,
                intval($s['img_quality']),
                !empty($s['img_webp']),
                intval($s['img_width']),
                intval($s['img_height']),
                !empty($s['crop_images'])
            );
        }

        unset($doc, $xp);

        return $pid;

    } catch (Exception $e) {
        $this->logger->log('Handle single hatasÄ±: ' . $e->getMessage(), BDI_Logger::LEVEL_ERROR);
        if (isset($doc)) unset($doc);
        if (isset($xp)) unset($xp);
        return 0;
    }
}

private function fetch($url, $timeout = 30) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $this->logger->log('GeÃ§ersiz URL: ' . $url, BDI_Logger::LEVEL_ERROR);
        return '';
    }

    $resp = wp_remote_get($url, array(
        'timeout' => max(10, intval($timeout)),
        'user-agent' => 'Mozilla/5.0 (compatible; WP-BDI/5.4.0)',
        'headers' => array('Accept' => 'text/html,application/xhtml+xml'),
        'sslverify' => true,
        'redirection' => 5,
        'httpversion' => '1.1'
    ));

    if (is_wp_error($resp)) {
        $this->logger->log('HTTP hatasÄ±: ' . $url . ' â€” ' . $resp->get_error_message(), BDI_Logger::LEVEL_ERROR);
        return '';
    }

    $code = wp_remote_retrieve_response_code($resp);
    if ($code !== 200) {
        $this->logger->log('HTTP ' . $code . ' hatasÄ±: ' . $url, BDI_Logger::LEVEL_ERROR);
        return '';
    }

    return (string)wp_remote_retrieve_body($resp);
}

private function extract_overview($doc, $xp) {
    $tab = null;
    $nodes = $xp->query('//*[self::a or self::button][@aria-controls or starts-with(@href, "#")]');
    if ($nodes) {
        foreach ($nodes as $el) {
            $txt = mb_strtolower(trim($el->textContent), 'UTF-8');
            if ($txt !== '' && strpos($txt, 'genel bakÄ±ÅŸ') !== false) {
                $tab = $el;
                break;
            }
        }
    }

    if ($tab) {
        $panelId = trim($tab->getAttribute('aria-controls'));
        if ($panelId === '' && $tab->hasAttribute('href')) {
            $href = trim($tab->getAttribute('href'));
            if ($href !== '' && $href[0] === '#') $panelId = substr($href, 1);
        }
        if ($panelId !== '') {
            $nl = $xp->query('//*[@id="' . $panelId . '"]');
            if ($nl && $nl->length) {
                $node = $nl->item(0);
                $out = '';
                foreach ($node->childNodes as $child) $out .= $doc->saveHTML($child);
                if (trim($out) !== '') return $out;
            }
        }
    }

    $hdr = $this->find_heading_contains($xp);
    if ($hdr) {
        $parts = array();
        for ($sib = $hdr->nextSibling, $limit = 0; $sib && $limit < 300; $sib = $sib->nextSibling, $limit++) {
            if ($sib->nodeType === XML_ELEMENT_NODE && preg_match('/^h[2-4]$/i', $sib->nodeName)) {
                $txt = mb_strtolower(trim($sib->textContent), 'UTF-8');
                if ($txt === '' || strpos($txt, 'Ã¶zellik') !== false || strpos($txt, 'technical') !== false || strpos($txt, 'destek') !== false || strpos($txt, 'tedarik') !== false) break;
            }
            $html = trim($doc->saveHTML($sib));
            if($html !== '') $parts[] = $html;
        }
        if (!empty($parts)) return implode("\n", $parts);
    }

    $cands = $xp->query('//*[@id="overview" or @data-tab="overview" or contains(concat(" ", normalize-space(@class), " "), " overview ") or contains(@class,"product-overview") or contains(@class,"tabs__panel")]');
    if ($cands && $cands->length) {
        foreach ($cands as $cand) {
            $txt = mb_strtolower(trim($cand->textContent), 'UTF-8');
            if (strpos($txt, 'Ã¶zellik') !== false || strpos($txt, 'technical') !== false) continue;
            $out = '';
            foreach ($cand->childNodes as $ch) $out .= $doc->saveHTML($ch);
            if (trim($out) !== '') return $out;
        }
    }

    $meta = $this->xp_attr($xp, '//meta[@name="description"]', 'content');
    if ($meta) return esc_html($meta);
    return $this->xp_html($xp, '//*[contains(@class,"brother-product-hero__description")]');
}

private function find_heading_contains($xp) {
    $q = '//*[self::h2 or self::h3 or self::h4][contains(translate(normalize-space(.),
        "ÄžÃœÅžÄ°Ã–Ã‡Ã‚ÃŠÃŽÃ”Ã›ABCDEFGHIJKLMNOPQRSTUVWXYZ",
        "ÄŸÃ¼ÅŸiÃ¶Ã§Ã¢ÃªÃ®Ã´Ã»abcdefghijklmnopqrstuvwxyz"
    ), "genel bakÄ±ÅŸ")]';
    $n = $xp->query($q);
    return ($n && $n->length) ? $n->item(0) : null;
}

private function extract_specifications($doc, $xp) {
    $selectors = array(
        '//*[@id="specifications"]',
        '//*[@data-tab-content="specifications"]',
        '//*[contains(@class,"specifications-tab")]',
        '//div[@role="tabpanel"][contains(@aria-labelledby,"specifications")]'
    );

    foreach ($selectors as $selector) {
        $nodes = $xp->query($selector);
        if ($nodes && $nodes->length > 0) {
            $node = $nodes->item(0);
            $html = '';
            foreach ($node->childNodes as $child) {
                $html .= $doc->saveHTML($child);
            }
            if (trim($html) !== '') {
                return $html;
            }
        }
    }

    return '';
}

private function extract_support_content($doc, $xp, $url, $supplies_data = array(), $accessories_data = array()) {
    $support_link = 'https://support.brother.com/g/b/countrytop.aspx?c=tr&lang=tr';

    $output = '<div class="bro-support">';
    $output .= '<h2>Destek</h2>';

    $output .= '<div class="support-cards">';

    $output .= '
    <div class="support-card">
        <a href="' . esc_url($support_link) . '" target="_blank" rel="noopener" class="support-card-link-wrapper">
            <div class="support-card-icon icon-faq"></div>
            <h3 class="support-card-title">SSS ve Sorun Giderme</h3>
            <p class="support-card-description">En sÄ±k sorulan sorularÄ± ve yanÄ±tlarÄ±nÄ± burada bulabilirsiniz</p>
        </a>
    </div>';

    $output .= '
    <div class="support-card">
        <a href="' . esc_url($support_link) . '" target="_blank" rel="noopener" class="support-card-link-wrapper">
            <div class="support-card-icon icon-downloads"></div>
            <h3 class="support-card-title">Ä°ndirmeler & SÃ¼rÃ¼cÃ¼ler</h3>
            <p class="support-card-description">Brother Ã¼rÃ¼nleriniz iÃ§in en son sÃ¼rÃ¼cÃ¼leri ve yazÄ±lÄ±mÄ± indirip yÃ¼kleyin</p>
        </a>
    </div>';

    $output .= '
    <div class="support-card">
        <a href="' . esc_url($support_link) . '" target="_blank" rel="noopener" class="support-card-link-wrapper">
            <div class="support-card-icon icon-manuals"></div>
            <h3 class="support-card-title">KÄ±lavuzlar</h3>
            <p class="support-card-description">Brother Ã¼rÃ¼nleriniz iÃ§in en son kullanÄ±m kÄ±lavuzlarÄ±nÄ± indirin</p>
        </a>
    </div>';

    $output .= '</div>';
    $output .= '</div>';

    return $output;
}

private function extract_product_extra_functions($doc, $xp) {
    $selectors = array(
        'product-extra-functions',
        'product-short-description',
        'short-description',
        'product-summary',
        'product-highlights'
    );

    foreach ($selectors as $class) {
        $node = $this->xp_first($xp, '//*[contains(@class,"' . $class . '")]');

        if ($node) {
            $html_content = '';
            foreach ($node->childNodes as $child) {
                $html_content .= $doc->saveHTML($child);
            }
            $html_content = trim($html_content);

            if ($html_content !== '' && strlen($html_content) > 10) {
                return $html_content;
            }
        }
    }

    $meta_desc = $this->xp_attr($xp, '//meta[@name="description"]', 'content');
    if ($meta_desc && strlen($meta_desc) > 10) {
        return $meta_desc;
    }

    return '';
}

private function clean_short_description($html) {
    if (!$html) return '';

    $this->logger->log('KÄ±sa aÃ§Ä±klama temizleme baÅŸlÄ±yor: ' . strlen($html) . ' karakter', BDI_Logger::LEVEL_DEBUG);

    $settings = wp_parse_args(get_option(self::OPT_SETTINGS, array()), array(
        'marker_symbol' => 'âœ“',
        'marker_color' => '#27ae60',
        'text_color' => '#333333',
        'strong_color' => '#2c3e50',
        'font_family' => 'Arial, sans-serif',
        'font_size' => '14px',
        'line_height' => '1.6'
    ));

    if (empty($settings['marker_symbol']) || !in_array($settings['marker_symbol'], array('âœ“', 'â—', 'â–¶', 'â˜…', 'âœ¦', 'â†’', 'â€¢'))) {
        $settings['marker_symbol'] = 'âœ“';
    }

    $html = $this->absolutize_urls($html, 'https://www.brother.com.tr');

    $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
    $html = preg_replace('/<iframe[^>]*>.*?<\/iframe>/is', '', $html);
    $html = preg_replace('/\son\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);

    $html = preg_replace('/\s+(class|id)="[^"]*"/i', '', $html);

    $allowed_tags = '<p><br><strong><b><em><i><ul><ol><li><h1><h2><h3><h4><h5><h6><span><div><a>';
    $html = strip_tags($html, $allowed_tags);

    $html = preg_replace('/<([^>]+)>\s*<\/\1>/i', '', $html);
    $html = preg_replace('/\s+/', ' ', $html);
    $html = trim($html);

    $css_content = '
    .bdi-short-desc {
        font-family: ' . $settings['font_family'] . ';
        line-height: ' . $settings['line_height'] . ';
        color: ' . $settings['text_color'] . ';
        font-size: ' . $settings['font_size'] . ';
    }
    .bdi-short-desc ul {
        padding-left: 20px;
        margin: 10px 0;
    }
    .bdi-short-desc ul li, .bdi-short-desc > li {
        margin: 5px 0;
        list-style-type: none;
        position: relative;
        padding-left: 20px;
    }
    .bdi-short-desc ul li::before, .bdi-short-desc > li::before {
        content: "' . esc_js($settings['marker_symbol']) . '";
        position: absolute;
        left: 0;
        top: 0;
        color: ' . $settings['marker_color'] . ';
        font-weight: bold;
        font-size: ' . $settings['font_size'] . ';
    }
    .bdi-short-desc ol {
        padding-left: 20px;
        margin: 10px 0;
    }
    .bdi-short-desc ol li {
        margin: 5px 0;
        color: ' . $settings['text_color'] . ';
    }
    .bdi-short-desc p {
        margin: 8px 0;
    }
    .bdi-short-desc strong, .bdi-short-desc b {
        color: ' . $settings['strong_color'] . ';
        font-weight: bold;
    }';

    $final_html = '<style>' . $css_content . '</style>';
    $final_html .= '<div class="bdi-short-desc">' . $html . '</div>';

    $this->logger->log('KÄ±sa aÃ§Ä±klama Ã¶zelleÅŸtirildi: Marker=' . $settings['marker_symbol'] . ', Renk=' . $settings['marker_color'], BDI_Logger::LEVEL_DEBUG);

    return $final_html;
}

public function create_attributes_from_specs($product_id) {
    $specs_html = get_post_meta($product_id, self::META_SPECS, true);
    if (!$specs_html) return;

    try {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $specs_html);
        $xp = new DOMXPath($doc);

        $pairs = array();

        $container = null;
        $nodes = $xp->query('//*[@id="specifications"]');
        if ($nodes && $nodes->length) {
            $container = $nodes->item(0);
        }

        $norm = function($s) {
            $s = trim(preg_replace('/\s+/u', ' ', (string)$s));
            return sanitize_text_field($s);
        };

        $ctx = $container ?: $doc;

        foreach ($xp->query('.//tr', $ctx) as $tr) {
            $tds = $tr->getElementsByTagName('td');
            $key = '';
            $val = '';

            if ($tds->length >= 2) {
                $key = $norm($tds->item(0)->textContent);
                $val = $norm($tds->item(1)->textContent);
            }

            if ($key !== '' && $val !== '') {
                $pairs[] = array($key, $val);
            }
        }

        if (empty($pairs)) {
            return;
        }

        $seen = array();
        $unique = array();
        foreach ($pairs as $kv) {
            $k = $norm($kv[0]);
            $v = $norm($kv[1]);
            if ($k === '' || $v === '') continue;
            $sig = strtolower($k . '|' . $v);
            if (!isset($seen[$sig])) {
                $seen[$sig] = true;
                $unique[] = array($k, $v);
            }
        }

        $existing = get_post_meta($product_id, '_product_attributes', true);
        if (!is_array($existing)) $existing = array();
        $attributes = $existing;
        $pos = count($attributes);

        foreach ($unique as $kv) {
            list($name, $value) = $kv;
            $slug = sanitize_title($name);
            if (isset($attributes[$slug])) {
                $old = $attributes[$slug]['value'];
                if (stripos($old, $value) === false) {
                    $attributes[$slug]['value'] = $old . ' | ' . $value;
                }
            } else {
                $attributes[$slug] = array(
                    'name' => $name,
                    'value' => $value,
                    'position' => $pos++,
                    'is_visible' => 1,
                    'is_variation' => 0,
                    'is_taxonomy' => 0,
                );
            }
        }

        update_post_meta($product_id, '_product_attributes', $attributes);

        unset($doc, $xp);

    } catch (Exception $e) {
        $this->logger->log('Attributes oluÅŸturma hatasÄ±: ' . $e->getMessage(), BDI_Logger::LEVEL_ERROR);
    }
}

// PART 5'te devam edecek...

// ============================================
// PART 5 - API & SUPPLIES
// ============================================
// PART 5 - API VE SARF/AKSESUAR METODLARI
// BDI_Pro_Enhanced class iÃ§ine eklenecek
// ============================================

private function extract_product_item_id($html) {
    if (empty($html)) {
        return null;
    }

    if (preg_match('/(?:var|const|let)\s+productItemId\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
        $this->logger->log('ProductItemId bulundu (JS variable): ' . $matches[1], BDI_Logger::LEVEL_INFO);
        return $matches[1];
    }

    if (preg_match('/"productItemId"\s*:\s*"([^"]+)"/i', $html, $matches)) {
        $this->logger->log('ProductItemId bulundu (JSON): ' . $matches[1], BDI_Logger::LEVEL_INFO);
        return $matches[1];
    }

    if (preg_match('/data-product-item-id=["\']([^"\']+)["\']/i', $html, $matches)) {
        $this->logger->log('ProductItemId bulundu (data-attribute): ' . $matches[1], BDI_Logger::LEVEL_INFO);
        return $matches[1];
    }

    preg_match_all('/\{[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}\}/i', $html, $all_guids, PREG_OFFSET_CAPTURE);

    if (!empty($all_guids[0])) {
        $this->logger->log('Bulunan GUID sayÄ±sÄ±: ' . count($all_guids[0]), BDI_Logger::LEVEL_DEBUG);

        $best_guid = null;
        $best_score = -1;

        foreach ($all_guids[0] as $idx => $match) {
            $guid = $match[0];
            $position = $match[1];

            $context_start = max(0, $position - 250);
            $context_end = min(strlen($html), $position + 250);
            $context = substr($html, $context_start, $context_end - $context_start);

            $score = 0;

            if (stripos($context, 'supplie') !== false) $score += 10;
            if (stripos($context, 'accessori') !== false) $score += 10;
            if (stripos($context, 'productItemId') !== false) $score += 20;
            if (stripos($context, 'ProductItemId') !== false) $score += 20;
            if (stripos($context, 'catalog') !== false) $score += 5;
            if (stripos($context, 'api') !== false) $score += 5;

            $this->logger->log(sprintf('GUID #%d: %s | Score: %d', $idx + 1, $guid, $score), BDI_Logger::LEVEL_DEBUG);

            if ($score > $best_score) {
                $best_score = $score;
                $best_guid = $guid;
            }
        }

        if ($best_guid && $best_score > 0) {
            $this->logger->log('En uygun ProductItemId seÃ§ildi (score: ' . $best_score . '): ' . $best_guid, BDI_Logger::LEVEL_INFO);
            return $best_guid;
        }

        if (count($all_guids[0]) > 1) {
            $this->logger->log('Ä°kinci GUID deneniyor: ' . $all_guids[0][1][0], BDI_Logger::LEVEL_WARNING);
            return $all_guids[0][1][0];
        }

        $this->logger->log('Son Ã§are - ilk GUID: ' . $all_guids[0][0][0], BDI_Logger::LEVEL_WARNING);
        return $all_guids[0][0][0];
    }

    $this->logger->log('ProductItemId bulunamadÄ±', BDI_Logger::LEVEL_WARNING);
    return null;
}

private function fetch_supplies_from_api($product_item_id) {
    if (empty($product_item_id)) {
        return array();
    }

    $api_url = 'https://www.brother.com.tr/brotherapi/cxa/catalog/suppliestab';

    $request_body = array(
        'ProductItemId' => $product_item_id,
        'CategoryType' => 'SUPPLY_CATEGORY',
        'PageNumber' => ''
    );

    $this->logger->log('Sarf API isteÄŸi gÃ¶nderiliyor: ' . $api_url . ' | Body: ' . wp_json_encode($request_body), BDI_Logger::LEVEL_INFO);

    $response = wp_remote_post($api_url, array(
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ),
        'body' => wp_json_encode($request_body)
    ));

    if (is_wp_error($response)) {
        $this->logger->log('Sarf malzemesi API hatasÄ±: ' . $response->get_error_message(), BDI_Logger::LEVEL_ERROR);
        return array();
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    $this->logger->log('Sarf API yanÄ±t kodu: ' . $status_code . ' | YanÄ±t uzunluÄŸu: ' . strlen($body) . ' karakter', BDI_Logger::LEVEL_INFO);

    if (strlen($body) <= 10) {
        $this->logger->log('UYARI: API Ã§ok kÄ±sa yanÄ±t dÃ¶ndÃ¼, muhtemelen boÅŸ', BDI_Logger::LEVEL_WARNING);
        return array();
    }

    $preview = substr($body, 0, 200);
    $is_html = (stripos($preview, '<') !== false && stripos($preview, '>') !== false);
    $is_json = (substr(trim($body), 0, 1) === '{' || substr(trim($body), 0, 1) === '[');

    $this->logger->log('API yanÄ±t tipi: ' . ($is_html ? 'HTML' : ($is_json ? 'JSON' : 'UNKNOWN')), BDI_Logger::LEVEL_INFO);

    if ($is_html) {
        return $this->parse_supplies_html($body, 'supply');
    } elseif ($is_json) {
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->log('Sarf API JSON parse hatasÄ±: ' . json_last_error_msg(), BDI_Logger::LEVEL_ERROR);
            return array();
        }

        if (empty($data)) {
            $this->logger->log('Sarf malzemesi API boÅŸ yanÄ±t dÃ¶ndÃ¼ (JSON decode sonrasÄ±)', BDI_Logger::LEVEL_WARNING);
            return array();
        }

        $this->logger->log('Sarf API data keys: ' . implode(', ', array_keys($data)), BDI_Logger::LEVEL_DEBUG);
        return $this->parse_api_products($data, 'supply');
    } else {
        $this->logger->log('Bilinmeyen API yanÄ±t formatÄ±', BDI_Logger::LEVEL_ERROR);
        return array();
    }
}

private function fetch_accessories_from_api($product_item_id) {
    if (empty($product_item_id)) {
        return array();
    }

    $api_url = 'https://www.brother.com.tr/brotherapi/cxa/catalog/associatedtab';

    $request_body = array(
        'ProductItemId' => $product_item_id,
        'CategoryType' => 'ACCESSORY_CATEGORY',
        'PageNumber' => ''
    );

    $this->logger->log('Aksesuar API isteÄŸi gÃ¶nderiliyor: ' . $api_url . ' | Body: ' . wp_json_encode($request_body), BDI_Logger::LEVEL_INFO);

    $response = wp_remote_post($api_url, array(
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ),
        'body' => wp_json_encode($request_body)
    ));

    if (is_wp_error($response)) {
        $this->logger->log('Aksesuar API hatasÄ±: ' . $response->get_error_message(), BDI_Logger::LEVEL_ERROR);
        return array();
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    $this->logger->log('Aksesuar API yanÄ±t kodu: ' . $status_code . ' | YanÄ±t uzunluÄŸu: ' . strlen($body) . ' karakter', BDI_Logger::LEVEL_INFO);

    if (strlen($body) <= 10) {
        $this->logger->log('Aksesuar API boÅŸ yanÄ±t dÃ¶ndÃ¼ - Ã¼rÃ¼nde aksesuar olmayabilir', BDI_Logger::LEVEL_WARNING);
        return array();
    }

    $preview = substr($body, 0, 200);
    $is_html = (stripos($preview, '<') !== false && stripos($preview, '>') !== false);
    $is_json = (substr(trim($body), 0, 1) === '{' || substr(trim($body), 0, 1) === '[');

    $this->logger->log('Aksesuar API yanÄ±t tipi: ' . ($is_html ? 'HTML' : ($is_json ? 'JSON' : 'UNKNOWN')), BDI_Logger::LEVEL_INFO);

    if ($is_html) {
        return $this->parse_supplies_html($body, 'accessory');
    } elseif ($is_json) {
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->log('Aksesuar API JSON parse hatasÄ±: ' . json_last_error_msg(), BDI_Logger::LEVEL_ERROR);
            return array();
        }

        if (empty($data)) {
            $this->logger->log('Aksesuar API boÅŸ yanÄ±t dÃ¶ndÃ¼ (JSON decode sonrasÄ±)', BDI_Logger::LEVEL_WARNING);
            return array();
        }

        $this->logger->log('Aksesuar API data keys: ' . implode(', ', array_keys($data)), BDI_Logger::LEVEL_DEBUG);
        return $this->parse_api_products($data, 'accessory');
    } else {
        $this->logger->log('Bilinmeyen aksesuar API yanÄ±t formatÄ±', BDI_Logger::LEVEL_ERROR);
        return array();
    }
}

private function parse_api_products($data, $type = 'supply') {
    $products = array();

    $this->logger->log('Parse baÅŸlÄ±yor: ' . $type . ' | Data tipi: ' . gettype($data), BDI_Logger::LEVEL_DEBUG);

    $items = array();
    if (isset($data['products']) && is_array($data['products'])) {
        $items = $data['products'];
        $this->logger->log('API yapÄ±sÄ±: data.products | Item sayÄ±sÄ±: ' . count($items), BDI_Logger::LEVEL_INFO);
    } elseif (isset($data['items']) && is_array($data['items'])) {
        $items = $data['items'];
        $this->logger->log('API yapÄ±sÄ±: data.items | Item sayÄ±sÄ±: ' . count($items), BDI_Logger::LEVEL_INFO);
    } elseif (isset($data['data']) && is_array($data['data'])) {
        $items = $data['data'];
        $this->logger->log('API yapÄ±sÄ±: data.data | Item sayÄ±sÄ±: ' . count($items), BDI_Logger::LEVEL_INFO);
    } elseif (isset($data['result']) && is_array($data['result'])) {
        $items = $data['result'];
        $this->logger->log('API yapÄ±sÄ±: data.result | Item sayÄ±sÄ±: ' . count($items), BDI_Logger::LEVEL_INFO);
    } elseif (isset($data['Results']) && is_array($data['Results'])) {
        $items = $data['Results'];
        $this->logger->log('API yapÄ±sÄ±: data.Results | Item sayÄ±sÄ±: ' . count($items), BDI_Logger::LEVEL_INFO);
    } elseif (is_array($data)) {
        $items = $data;
        $this->logger->log('API yapÄ±sÄ±: direkt array | Item sayÄ±sÄ±: ' . count($items), BDI_Logger::LEVEL_INFO);
    }

    if (empty($items)) {
        $this->logger->log('Parse edilecek item bulunamadÄ±. Mevcut data keys: ' . implode(', ', array_keys($data)), BDI_Logger::LEVEL_WARNING);

        $data_preview = print_r($data, true);
        $this->logger->log('Data preview: ' . substr($data_preview, 0, 1000), BDI_Logger::LEVEL_DEBUG);
        return array();
    }

    $item_count = 0;
    foreach ($items as $item) {
        $item_count++;

        if ($item_count <= 3) {
            $item_keys = is_array($item) ? array_keys($item) : 'not_array';
            $this->logger->log('Item #' . $item_count . ' keys: ' . (is_array($item_keys) ? implode(', ', $item_keys) : $item_keys), BDI_Logger::LEVEL_DEBUG);
        }

        $product = array(
            'code' => '',
            'name' => '',
            'image' => '',
            'yield' => '',
            'type' => '',
            'url' => '',
            'description' => ''
        );

        $code_fields = array('code', 'Code', 'modelCode', 'ModelCode', 'sku', 'SKU', 'productCode', 'ProductCode', 'ModelNumber', 'modelNumber');
        foreach ($code_fields as $field) {
            if (!empty($item[$field])) {
                $product['code'] = sanitize_text_field($item[$field]);
                break;
            }
        }

        $name_fields = array('name', 'Name', 'title', 'Title', 'productName', 'ProductName', 'DisplayName', 'displayName');
        foreach ($name_fields as $field) {
            if (!empty($item[$field])) {
                $product['name'] = sanitize_text_field($item[$field]);
                break;
            }
        }

        if (empty($product['name']) && !empty($product['code'])) {
            $product['name'] = $product['code'];
        }

        $image_fields = array('image', 'Image', 'imageUrl', 'ImageUrl', 'ImageURL', 'thumbnail', 'Thumbnail', 'thumbUrl', 'ThumbUrl');
        foreach ($image_fields as $field) {
            if (!empty($item[$field])) {
                $product['image'] = esc_url_raw($item[$field]);
                break;
            }
        }

        if ($type === 'supply') {
            $yield_fields = array('yield', 'Yield', 'pageYield', 'PageYield', 'capacity', 'Capacity', 'PageCapacity', 'pageCapacity');
            foreach ($yield_fields as $field) {
                if (!empty($item[$field])) {
                    $product['yield'] = sanitize_text_field($item[$field]);
                    break;
                }
            }
        }

        $type_fields = array('type', 'Type', 'category', 'Category', 'productType', 'ProductType', 'CategoryName', 'categoryName');
        foreach ($type_fields as $field) {
            if (!empty($item[$field])) {
                $product['type'] = sanitize_text_field($item[$field]);
                break;
            }
        }

        $url_fields = array('url', 'Url', 'URL', 'link', 'Link', 'detailUrl', 'DetailUrl', 'ProductUrl', 'productUrl');
        foreach ($url_fields as $field) {
            if (!empty($item[$field])) {
                $product['url'] = esc_url_raw($item[$field]);
                break;
            }
        }

        $desc_fields = array('description', 'Description', 'desc', 'Desc', 'ShortDescription', 'shortDescription');
        foreach ($desc_fields as $field) {
            if (!empty($item[$field])) {
                $product['description'] = wp_kses_post($item[$field]);
                break;
            }
        }

        if (!empty($product['code']) || !empty($product['name'])) {
            $products[] = $product;

            $this->logger->log(
                sprintf('%s parse edildi: [%s] %s',
                    ucfirst($type),
                    $product['code'],
                    $product['name']
                ),
                BDI_Logger::LEVEL_DEBUG
            );
        } else {
            if ($item_count <= 3) {
                $item_preview = print_r($item, true);
                $this->logger->log('Parse edilemedi - Item #' . $item_count . ': ' . substr($item_preview, 0, 300), BDI_Logger::LEVEL_WARNING);
            }
        }
    }

    $this->logger->log(
        sprintf('API\'den %d adet %s Ã§ekildi (Toplam %d item iÅŸlendi)', count($products), $type, $item_count),
        BDI_Logger::LEVEL_INFO
    );

    return $products;
}

private function parse_supplies_html($html, $type = 'supply') {
    if (empty($html)) {
        return array();
    }

    $products = array();
    $seen_codes = array();

    try {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new DOMXPath($dom);

        $selectors = array(
            '//div[contains(@class, "product-card")]',
            '//div[contains(@class, "cxa-product-card")]',
            '//div[contains(@class, "product-tile")]',
            '//div[contains(@class, "product-item")]',
            '//article[contains(@class, "product")]',
        );

        $product_nodes = null;
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes && $nodes->length > 0) {
                $product_nodes = $nodes;
                $this->logger->log('HTML parse: ' . $nodes->length . ' Ã¼rÃ¼n bulundu (selector: ' . $selector . ')', BDI_Logger::LEVEL_INFO);
                break;
            }
        }

        if (!$product_nodes || $product_nodes->length === 0) {
            $this->logger->log('HTML parse: HiÃ§ Ã¼rÃ¼n kartÄ± bulunamadÄ±', BDI_Logger::LEVEL_WARNING);
            return array();
        }

        $parsed_count = 0;
        foreach ($product_nodes as $idx => $node) {

            $product = array(
                'code' => '',
                'name' => '',
                'image' => '',
                'yield' => '',
                'type' => '',
                'url' => '',
                'description' => ''
            );

            $full_text = trim($node->textContent);

            $code_patterns = array(
                '/\b([A-Z]{2,4}[-]?[0-9]{3,4}[A-Z]{0,3})\b/',
                '/\b(LC[-]?[0-9]{4}[A-Z]{1,2})\b/',
                '/\b(DR[-]?[0-9]{4})\b/',
                '/\b(TN[-]?[0-9]{4})\b/',
            );

            foreach ($code_patterns as $pattern) {
                if (preg_match($pattern, $full_text, $matches)) {
                    $product['code'] = strtoupper($matches[1]);
                    $product['name'] = $product['code'];
                    break;
                }
            }

            if (empty($product['code'])) {
                $text_queries = array(
                    './/h3',
                    './/h4',
                    './/h5',
                    './/strong',
                    './/b',
                    './/*[contains(@class, "name")]',
                    './/*[contains(@class, "title")]',
                    './/*[contains(@class, "model")]',
                    './/*[contains(@class, "code")]',
                );

                foreach ($text_queries as $query) {
                    $text_nodes = $xpath->query($query, $node);
                    if ($text_nodes && $text_nodes->length > 0) {
                        $text = trim($text_nodes->item(0)->textContent);

                        foreach ($code_patterns as $pattern) {
                            if (preg_match($pattern, $text, $matches)) {
                                $product['code'] = strtoupper($matches[1]);
                                $product['name'] = $product['code'];
                                break 2;
                            }
                        }
                    }
                }
            }

            if (!empty($product['code']) && in_array($product['code'], $seen_codes)) {
                $this->logger->log('TekilleÅŸtirme: ' . $product['code'] . ' atlandÄ± (zaten mevcut)', BDI_Logger::LEVEL_DEBUG);
                continue;
            }

            $seen_codes[] = $product['code'];

            $img_nodes = $xpath->query('.//img', $node);
            if ($img_nodes && $img_nodes->length > 0) {
                $img = $img_nodes->item(0);
                foreach (array('src', 'data-src', 'data-lazy-src', 'data-original') as $attr) {
                    if ($img->hasAttribute($attr)) {
                        $src = trim($img->getAttribute($attr));
                        if ($src && (strpos($src, 'http') === 0 || strpos($src, '/') === 0)) {
                            if (strpos($src, '/') === 0) {
                                $src = 'https://www.brother.com.tr' . $src;
                            }
                            $product['image'] = $src;
                            break;
                        }
                    }
                }
            }

            if ($type === 'supply') {
                if (preg_match('/yaklaÅŸÄ±k\s+([0-9.,]+)\s*sayfa/i', $full_text, $matches)) {
                    $number = str_replace(array('.', ','), '', $matches[1]);
                    $product['yield'] = number_format((int)$number) . ' sayfa';
                }
            }

            $type_keywords = array(
                'mÃ¼rekkep' => 'MÃ¼rekkep',
                'toner' => 'Toner',
                'drum' => 'Drum',
                'ink' => 'MÃ¼rekkep',
                'cartridge' => 'KartuÅŸ',
            );

            foreach ($type_keywords as $keyword => $label) {
                if (stripos($full_text, $keyword) !== false) {
                    $product['type'] = $label;
                    break;
                }
            }

            $link_nodes = $xpath->query('.//a[@href]', $node);
            if ($link_nodes && $link_nodes->length > 0) {
                $href = trim($link_nodes->item(0)->getAttribute('href'));
                if ($href) {
                    if (strpos($href, '/') === 0) {
                        $href = 'https://www.brother.com.tr' . $href;
                    }
                    $product['url'] = $href;
                }
            }

            $desc = preg_replace('/\b[A-Z]{2,4}[-]?[0-9]{3,4}[A-Z]{0,3}\b/', '', $full_text);
            $desc = preg_replace('/yaklaÅŸÄ±k\s+[0-9.,]+\s*sayfa/i', '', $desc);
            $desc = trim(preg_replace('/\s+/', ' ', $desc));
            if (strlen($desc) > 10) {
                $product['description'] = substr($desc, 0, 200);
            }

            if (!empty($product['code']) || !empty($product['name'])) {
                $products[] = $product;
                $parsed_count++;

                $this->logger->log(
                    sprintf('HTML parse baÅŸarÄ±lÄ± #%d: [%s] %s | Tip: %s | Verim: %s',
                        $parsed_count,
                        $product['code'],
                        $product['name'],
                        $product['type'],
                        $product['yield']
                    ),
                    BDI_Logger::LEVEL_INFO
                );
            }
        }

        unset($dom, $xpath);

    } catch (Exception $e) {
        $this->logger->log('HTML parse hatasÄ±: ' . $e->getMessage(), BDI_Logger::LEVEL_ERROR);
    }

    $this->logger->log(
        sprintf('HTML parse tamamlandÄ±: Toplam %d adet %s Ã§ekildi (TekilleÅŸtirilmiÅŸ)', count($products), $type),
        BDI_Logger::LEVEL_INFO
    );

    return $products;
}

public function ajax_import_supplies_enhanced() {
    check_ajax_referer('bdi_import_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Yetkisiz eriÅŸim');
    }

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if (!$product_id) {
        wp_send_json_error('GeÃ§ersiz Ã¼rÃ¼n ID');
    }

    $supplies_json = get_post_meta($product_id, self::META_SUPPLIES, true);
    $supplies = json_decode($supplies_json, true);

    if (empty($supplies)) {
        wp_send_json_error('Sarf malzemesi bulunamadÄ±');
    }

    $imported = array();
    $updated = array();
    $skipped = array();

    foreach ($supplies as $supply) {
        $existing = wc_get_product_id_by_sku($supply['code']);

        if ($existing) {
            $result = $this->import_supply_as_wc_product_enhanced($supply, $product_id);
            if ($result) {
                $updated[] = $supply['code'];
            }
        } else {
            $result = $this->import_supply_as_wc_product_enhanced($supply, $product_id);
            if ($result) {
                $imported[] = $supply['code'];
            } else {
                $skipped[] = $supply['code'];
            }
        }
    }

    wp_send_json_success(array(
        'imported' => count($imported),
        'updated' => count($updated),
        'skipped' => count($skipped),
        'message' => sprintf(
            '%d yeni eklendi, %d gÃ¼ncellendi, %d atlandÄ±',
            count($imported),
            count($updated),
            count($skipped)
        )
    ));
}

public function ajax_import_accessories_enhanced() {
    check_ajax_referer('bdi_import_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Yetkisiz eriÅŸim');
    }

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if (!$product_id) {
        wp_send_json_error('GeÃ§ersiz Ã¼rÃ¼n ID');
    }

    $accessories_json = get_post_meta($product_id, self::META_ACCESSORIES, true);
    $accessories = json_decode($accessories_json, true);

    if (empty($accessories)) {
        wp_send_json_error('Aksesuar bulunamadÄ±');
    }

    $imported = array();
    $updated = array();
    $skipped = array();

    foreach ($accessories as $accessory) {
        $existing = wc_get_product_id_by_sku($accessory['code']);

        if ($existing) {
            $result = $this->import_accessory_as_wc_product_enhanced($accessory, $product_id);
            if ($result) {
                $updated[] = $accessory['code'];
            }
        } else {
            $result = $this->import_accessory_as_wc_product_enhanced($accessory, $product_id);
            if ($result) {
                $imported[] = $accessory['code'];
            } else {
                $skipped[] = $accessory['code'];
            }
        }
    }

    wp_send_json_success(array(
        'imported' => count($imported),
        'updated' => count($updated),
        'skipped' => count($skipped),
        'message' => sprintf(
            '%d yeni eklendi, %d gÃ¼ncellendi, %d atlandÄ±',
            count($imported),
            count($updated),
            count($skipped)
        )
    ));
}

// PART 6'da devam edecek...

// ============================================
// PART 6 - FRONTEND & SHORTCODES
// ============================================
// ============================================
// PART 6 - FRONTEND RENDER VE SHORTCODE METODLARI
// BDI_Pro_Enhanced class içine eklenecek
// ============================================

// SHORTCODE METODLARI
public function supply_detail_shortcode($atts) {
    $atts = shortcode_atts(array(
        'supply_code' => '',
        'parent_id' => 0
    ), $atts);

    if (!$atts['supply_code']) {
        return '<p>Sarf kodu belirtilmemiş.</p>';
    }

    ob_start();
    $this->render_supply_detail_page($atts['supply_code'], $atts['parent_id']);
    return ob_get_clean();
}

public function accessory_detail_shortcode($atts) {
    $atts = shortcode_atts(array(
        'accessory_code' => '',
        'parent_id' => 0
    ), $atts);

    if (!$atts['accessory_code']) {
        return '<p>Aksesuar kodu belirtilmemiş.</p>';
    }

    ob_start();
    $this->render_accessory_detail_page($atts['accessory_code'], $atts['parent_id']);
    return ob_get_clean();
}

// TEMPLATE REDIRECT
public function check_supply_detail_request() {
    if (!isset($_GET['supply_code'])) { return; }

    $supply_code = sanitize_text_field($_GET['supply_code']);
    $parent_id = isset($_GET['parent_id']) ? intval($_GET['parent_id']) : 0;

    // Önce WooCommerce ürün olarak arama
    $product_id = wc_get_product_id_by_sku($supply_code);
    if ($product_id) {
        wp_redirect(get_permalink($product_id));
        exit;
    }

    // Shortcode ile render et
    $content = do_shortcode('[brother_supply_detail supply_code="' . esc_attr($supply_code) . '" parent_id="' . $parent_id . '"]');

    if ($content) {
        // Basit bir sayfa şablonu
        get_header();
        echo '<div class="container" style="max-width:1200px;margin:0 auto;padding:20px;">';
        echo $content;
        echo '</div>';
        get_footer();
        exit;
    }
}

// RENDER METODLARI
public function render_supply_detail_page($supply_code, $parent_id = 0) {
    if (!$supply_code) return;

    // Önce WooCommerce ürün olarak kontrol et
    $product_id = wc_get_product_id_by_sku($supply_code);

    if ($product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            echo '<div class="supply-detail-wrapper">';
            echo '<h1>' . esc_html($product->get_name()) . '</h1>';
            echo '<div class="supply-image">' . get_the_post_thumbnail($product_id, 'medium') . '</div>';
            echo '<div class="supply-description">' . wp_kses_post($product->get_description()) . '</div>';
            echo '<div class="supply-price">Fiyat: ' . $product->get_price_html() . '</div>';
            echo '<div class="supply-stock">Stok Durumu: ' . ($product->is_in_stock() ? 'Stokta' : 'Stok Yok') . '</div>';
            echo '<a href="' . get_permalink($product_id) . '" class="button">Ürün Sayfasına Git</a>';
            echo '</div>';
            return;
        }
    }

    // Parent ürünün sarf bilgilerinden bul
    if ($parent_id) {
        $supplies_json = get_post_meta($parent_id, self::META_SUPPLIES, true);
        $supplies = json_decode($supplies_json, true);

        if ($supplies) {
            foreach ($supplies as $supply) {
                if ($supply['code'] === $supply_code) {
                    echo '<div class="supply-detail-wrapper">';
                    echo '<h1>Sarf Malzemesi: ' . esc_html($supply['name']) . '</h1>';
                    echo '<div class="supply-code">Ürün Kodu: ' . esc_html($supply['code']) . '</div>';
                    if (!empty($supply['type'])) {
                        echo '<div class="supply-type">Tür: ' . esc_html($supply['type']) . '</div>';
                    }
                    if (!empty($supply['yield'])) {
                        echo '<div class="supply-yield">Verim: ' . esc_html($supply['yield']) . '</div>';
                    }
                    echo '<p>Bu sarf malzemesi henüz sisteme ürün olarak eklenmemiş.</p>';
                    echo '</div>';
                    return;
                }
            }
        }
    }

    // Tüm ürünlerde ara
    $products = $this->find_products_with_supply($supply_code);
    if (!empty($products)) {
        echo '<div class="supply-detail-wrapper">';
        echo '<h1>Sarf Malzemesi: ' . esc_html($supply_code) . '</h1>';
        echo '<h3>Bu sarf malzemesini kullanan ürünler:</h3>';
        echo '<ul class="compatible-products">';
        foreach ($products as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                echo '<li><a href="' . get_permalink($product_id) . '">' . esc_html($product->get_name()) . '</a></li>';
            }
        }
        echo '</ul>';
        echo '</div>';
        return;
    }

    echo '<p>Sarf malzemesi bulunamadı: ' . esc_html($supply_code) . '</p>';
}

public function render_accessory_detail_page($accessory_code, $parent_id = 0) {
    if (!$accessory_code) return;

    // Önce WooCommerce ürün olarak kontrol et
    $product_id = wc_get_product_id_by_sku($accessory_code);

    if ($product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            echo '<div class="accessory-detail-wrapper">';
            echo '<h1>' . esc_html($product->get_name()) . '</h1>';
            echo '<div class="accessory-image">' . get_the_post_thumbnail($product_id, 'medium') . '</div>';
            echo '<div class="accessory-description">' . wp_kses_post($product->get_description()) . '</div>';
            echo '<div class="accessory-price">Fiyat: ' . $product->get_price_html() . '</div>';
            echo '<div class="accessory-stock">Stok Durumu: ' . ($product->is_in_stock() ? 'Stokta' : 'Stok Yok') . '</div>';
            echo '<a href="' . get_permalink($product_id) . '" class="button">Ürün Sayfasına Git</a>';
            echo '</div>';
            return;
        }
    }

    // Parent ürünün aksesuar bilgilerinden bul
    if ($parent_id) {
        $accessories_json = get_post_meta($parent_id, self::META_ACCESSORIES, true);
        $accessories = json_decode($accessories_json, true);

        if ($accessories) {
            foreach ($accessories as $accessory) {
                if ($accessory['code'] === $accessory_code) {
                    echo '<div class="accessory-detail-wrapper">';
                    echo '<h1>Aksesuar: ' . esc_html($accessory['name']) . '</h1>';
                    echo '<div class="accessory-code">Ürün Kodu: ' . esc_html($accessory['code']) . '</div>';
                    if (!empty($accessory['type'])) {
                        echo '<div class="accessory-type">Tür: ' . esc_html($accessory['type']) . '</div>';
                    }
                    echo '<p>Bu aksesuar henüz sisteme ürün olarak eklenmemiş.</p>';
                    echo '</div>';
                    return;
                }
            }
        }
    }

    echo '<p>Aksesuar bulunamadı: ' . esc_html($accessory_code) . '</p>';
}

// HTML RENDER METODLARI
public function render_supplies_html($supplies, $product_id) {
    if (empty($supplies) || !is_array($supplies)) {
        return '<p>Bu ürün için sarf malzemesi bilgisi bulunmamaktadır.</p>';
    }

    $html = '<div class="brother-supplies-list">';
    $html .= '<h3 style="margin-bottom:15px;">Uyumlu Sarf Malzemeleri</h3>';
    $html .= '<div class="supplies-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:15px;">';

    foreach ($supplies as $supply) {
        $supply_product_id = wc_get_product_id_by_sku($supply['code']);
        $has_product = $supply_product_id ? true : false;

        $html .= '<div class="supply-item" style="border:1px solid #e0e0e0;padding:15px;border-radius:5px;background:#fff;">';

        if ($has_product) {
            $supply_product = wc_get_product($supply_product_id);
            $html .= '<div class="supply-image" style="text-align:center;margin-bottom:10px;">';
            $html .= get_the_post_thumbnail($supply_product_id, array(100, 100));
            $html .= '</div>';
        }

        $html .= '<h4 style="margin:0 0 8px 0;font-size:14px;font-weight:600;">';
        $html .= esc_html($supply['code']);
        $html .= '</h4>';

        if (!empty($supply['name']) && $supply['name'] !== $supply['code']) {
            $html .= '<p style="margin:0 0 5px 0;font-size:13px;color:#666;">' . esc_html($supply['name']) . '</p>';
        }

        if (!empty($supply['type'])) {
            $type_color = '#0d2ea0';
            if (strpos(strtolower($supply['type']), 'toner') !== false) {
                $type_color = '#dc3545';
            } elseif (strpos(strtolower($supply['type']), 'drum') !== false) {
                $type_color = '#28a745';
            }

            $html .= '<span style="display:inline-block;background:' . $type_color . ';color:white;padding:3px 10px;border-radius:12px;font-size:11px;margin:5px 0;">';
            $html .= esc_html($supply['type']);
            $html .= '</span>';
        }

        if (!empty($supply['yield'])) {
            $html .= '<p style="margin:5px 0;font-size:12px;color:#888;">Verim: ' . esc_html($supply['yield']) . '</p>';
        }

        if ($has_product) {
            $html .= '<div style="margin-top:10px;padding-top:10px;border-top:1px solid #eee;">';
            $html .= '<div style="font-size:14px;font-weight:600;color:#0d2ea0;">' . $supply_product->get_price_html() . '</div>';
            $html .= '<a href="' . get_permalink($supply_product_id) . '" class="button button-small" style="margin-top:5px;">Detayları Gör</a>';
            $html .= '</div>';
        } else {
            $detail_url = add_query_arg(array(
                'supply_code' => $supply['code'],
                'parent_id' => $product_id
            ), home_url('/supply-detail/'));

            $html .= '<a href="' . esc_url($detail_url) . '" style="display:inline-block;margin-top:8px;font-size:12px;color:#0d2ea0;">Detaylar →</a>';
        }

        $html .= '</div>';
    }

    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

public function render_accessories_html($accessories, $product_id) {
    if (empty($accessories) || !is_array($accessories)) {
        return '<p>Bu ürün için aksesuar bilgisi bulunmamaktadır.</p>';
    }

    $html = '<div class="brother-accessories-list">';
    $html .= '<h3 style="margin-bottom:15px;">Uyumlu Aksesuarlar</h3>';
    $html .= '<div class="accessories-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:15px;">';

    foreach ($accessories as $accessory) {
        $accessory_product_id = wc_get_product_id_by_sku($accessory['code']);
        $has_product = $accessory_product_id ? true : false;

        $html .= '<div class="accessory-item" style="border:1px solid #e0e0e0;padding:15px;border-radius:5px;background:#fff;">';

        if ($has_product) {
            $accessory_product = wc_get_product($accessory_product_id);
            $html .= '<div class="accessory-image" style="text-align:center;margin-bottom:10px;">';
            $html .= get_the_post_thumbnail($accessory_product_id, array(100, 100));
            $html .= '</div>';
        }

        $html .= '<h4 style="margin:0 0 8px 0;font-size:14px;font-weight:600;">';
        $html .= esc_html($accessory['code']);
        $html .= '</h4>';

        if (!empty($accessory['name']) && $accessory['name'] !== $accessory['code']) {
            $html .= '<p style="margin:0 0 5px 0;font-size:13px;color:#666;">' . esc_html($accessory['name']) . '</p>';
        }

        if (!empty($accessory['type'])) {
            $html .= '<span style="display:inline-block;background:#28a745;color:white;padding:3px 10px;border-radius:12px;font-size:11px;margin:5px 0;">';
            $html .= esc_html($accessory['type']);
            $html .= '</span>';
        }

        if ($has_product) {
            $html .= '<div style="margin-top:10px;padding-top:10px;border-top:1px solid #eee;">';
            $html .= '<div style="font-size:14px;font-weight:600;color:#0d2ea0;">' . $accessory_product->get_price_html() . '</div>';
            $html .= '<a href="' . get_permalink($accessory_product_id) . '" class="button button-small" style="margin-top:5px;">Detayları Gör</a>';
            $html .= '</div>';
        } else {
            $detail_url = add_query_arg(array(
                'accessory_code' => $accessory['code'],
                'parent_id' => $product_id
            ), home_url('/accessory-detail/'));

            $html .= '<a href="' . esc_url($detail_url) . '" style="display:inline-block;margin-top:8px;font-size:12px;color:#28a745;">Detaylar →</a>';
        }

        $html .= '</div>';
    }

    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

// YARDIMCI METODLAR
public function find_products_with_supply($supply_code) {
    global $wpdb;

    $query = "
        SELECT DISTINCT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
        AND pm.meta_key = %s
        AND pm.meta_value LIKE %s
    ";

    $like_pattern = '%"code":"' . $wpdb->esc_like($supply_code) . '"%';

    $results = $wpdb->get_col($wpdb->prepare(
        $query,
        self::META_SUPPLIES,
        $like_pattern
    ));

    return $results ? $results : array();
}

public function find_products_with_accessory($accessory_code) {
    global $wpdb;

    $query = "
        SELECT DISTINCT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
        AND pm.meta_key = %s
        AND pm.meta_value LIKE %s
    ";

    $like_pattern = '%"code":"' . $wpdb->esc_like($accessory_code) . '"%';

    $results = $wpdb->get_col($wpdb->prepare(
        $query,
        self::META_ACCESSORIES,
        $like_pattern
    ));

    return $results ? $results : array();
}

// FRONTEND STILLER VE SCRIPTLER - PART 9'da tamamlanacak
// ============================================
// PART 7 - AJAX & SITEMAP
// ============================================
// ============================================
// PART 7 - AJAX VE SITEMAP METODLARI
// BDI_Pro_Enhanced class içine eklenecek
// ============================================

// AJAX HANDLERS - SITEMAP
public function ajax_scan_sitemap() {
    check_ajax_referer('bdi_scan_sitemap', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Yetkisiz erişim');
    }

    $this->logger->log('Sitemap taraması başlatıldı', BDI_Logger::LEVEL_INFO);

    try {
        // Brother sitemap URL'leri
        $sitemap_urls = array(
            'https://www.brother.com.tr/sitemap_index.xml',
            'https://www.brother.com.tr/product-sitemap.xml',
            'https://www.brother.com.tr/printer-sitemap.xml'
        );

        $all_products = array();
        $sitemap_found = false;

        foreach ($sitemap_urls as $sitemap_url) {
            $response = wp_remote_get($sitemap_url, array(
                'timeout' => 30,
                'user-agent' => 'Mozilla/5.0 (compatible; WP-BDI/5.4.0)'
            ));

            if (is_wp_error($response)) {
                $this->logger->log('Sitemap erişim hatası: ' . $sitemap_url, BDI_Logger::LEVEL_ERROR);
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            if (empty($body)) continue;

            // XML parse et
            $xml = @simplexml_load_string($body);
            if (!$xml) continue;

            $sitemap_found = true;

            // Namespace'leri kontrol et
            $namespaces = $xml->getNamespaces(true);

            // URL'leri topla
            foreach ($xml->url as $url_node) {
                $loc = (string)$url_node->loc;

                // Ürün URL'lerini filtrele
                if ($this->is_product_url($loc)) {
                    $all_products[] = $loc;
                }
            }

            // Eğer bu bir sitemap index ise, alt sitemapleri de tara
            if ($xml->getName() === 'sitemapindex') {
                foreach ($xml->sitemap as $sitemap) {
                    $sub_sitemap_url = (string)$sitemap->loc;

                    $sub_response = wp_remote_get($sub_sitemap_url, array(
                        'timeout' => 30
                    ));

                    if (!is_wp_error($sub_response)) {
                        $sub_body = wp_remote_retrieve_body($sub_response);
                        $sub_xml = @simplexml_load_string($sub_body);

                        if ($sub_xml) {
                            foreach ($sub_xml->url as $url_node) {
                                $loc = (string)$url_node->loc;
                                if ($this->is_product_url($loc)) {
                                    $all_products[] = $loc;
                                }
                            }
                        }
                    }
                }
            }
        }

        if (!$sitemap_found) {
            wp_send_json_error('Sitemap dosyası bulunamadı');
        }

        // Benzersiz URL'ler
        $all_products = array_unique($all_products);

        // Mevcut ürünleri kontrol et
        $existing_urls = $this->get_existing_source_urls();
        $missing_products = array_diff($all_products, $existing_urls);

        // Sonuçları hazırla
        $result_html = $this->generate_missing_products_table($missing_products);

        $this->logger->log(sprintf(
            'Sitemap taraması tamamlandı. Toplam: %d, Mevcut: %d, Eksik: %d',
            count($all_products),
            count($existing_urls),
            count($missing_products)
        ), BDI_Logger::LEVEL_INFO);

        wp_send_json_success(array(
            'html' => $result_html,
            'total_count' => count($all_products),
            'existing_count' => count($existing_urls),
            'missing_count' => count($missing_products)
        ));

    } catch (Exception $e) {
        $this->logger->log('Sitemap tarama hatası: ' . $e->getMessage(), BDI_Logger::LEVEL_ERROR);
        wp_send_json_error('Tarama sırasında hata oluştu: ' . $e->getMessage());
    }
}

public function ajax_add_url_to_list() {
    check_ajax_referer('bdi_add_url', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Yetkisiz erişim');
    }

    $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';

    if (!$url) {
        wp_send_json_error('Geçersiz URL');
    }

    // Mevcut URL listesini al
    $current_urls = get_option(self::OPT_URLS, '');
    $urls_array = array_filter(array_map('trim', explode("\n", $current_urls)));

    // URL zaten varsa ekleme
    if (in_array($url, $urls_array)) {
        wp_send_json_success(array('message' => 'URL zaten listede'));
    }

    // Yeni URL'yi ekle
    $urls_array[] = $url;
    $new_urls = implode("\n", $urls_array);

    update_option(self::OPT_URLS, $new_urls);

    $this->logger->log('URL listeye eklendi: ' . $url, BDI_Logger::LEVEL_INFO);

    wp_send_json_success(array(
        'message' => 'URL başarıyla eklendi',
        'total' => count($urls_array)
    ));
}

public function ajax_add_bulk_urls() {
    check_ajax_referer('bdi_add_bulk', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Yetkisiz erişim');
    }

    $urls = isset($_POST['urls']) ? (array)$_POST['urls'] : array();

    if (empty($urls)) {
        wp_send_json_error('URL listesi boş');
    }

    // Mevcut URL listesini al
    $current_urls = get_option(self::OPT_URLS, '');
    $urls_array = array_filter(array_map('trim', explode("\n", $current_urls)));

    $added_count = 0;
    $skipped_count = 0;

    foreach ($urls as $url) {
        $url = esc_url_raw($url);
        if ($url && !in_array($url, $urls_array)) {
            $urls_array[] = $url;
            $added_count++;
        } else {
            $skipped_count++;
        }
    }

    // Güncellenmiş listeyi kaydet
    $new_urls = implode("\n", $urls_array);
    update_option(self::OPT_URLS, $new_urls);

    $this->logger->log(sprintf(
        'Toplu URL ekleme: %d eklendi, %d atlandı',
        $added_count,
        $skipped_count
    ), BDI_Logger::LEVEL_INFO);

    wp_send_json_success(array(
        'message' => sprintf('%d URL eklendi, %d atlandı', $added_count, $skipped_count),
        'added' => $added_count,
        'skipped' => $skipped_count,
        'total' => count($urls_array)
    ));
}

// YARDIMCI METODLAR - SITEMAP
private function is_product_url($url) {
    // Brother ürün URL'lerini kontrol et
    $product_patterns = array(
        '/\/[a-z]{2}-[a-z]{2}\/[^\/]+$/',  // /tr-tr/mfc-l2750dw gibi
        '/\/products?\//i',
        '/\/printer/i',
        '/\/scanner/i',
        '/\/fax/i',
        '/\/label/i',
        '/\/sewing/i',
        '/\/(mfc|dcp|hl|ads|pt|ql|td|pj|rj)-/i'  // Model kodları
    );

    foreach ($product_patterns as $pattern) {
        if (preg_match($pattern, $url)) {
            return true;
        }
    }

    return false;
}

private function generate_missing_products_table($missing_products) {
    if (empty($missing_products)) {
        return '<div class="notice notice-success"><p>Tebrikler! Tüm ürünler sisteme eklenmiş.</p></div>';
    }

    $html = '<div style="background:#fff;padding:20px;border:1px solid #ddd;border-radius:5px;margin-top:20px;">';
    $html .= '<h3>Eksik Ürünler (' . count($missing_products) . ')</h3>';

    // Toplu ekleme butonu
    $html .= '<p>';
    $html .= '<button type="button" class="button button-primary" id="add-all-missing">Tümünü Listeye Ekle</button>';
    $html .= ' <span class="spinner" style="float:none;margin-top:0;"></span>';
    $html .= '</p>';

    $html .= '<table class="widefat striped" id="missing-products-table">';
    $html .= '<thead><tr>';
    $html .= '<th style="width:50px;"><input type="checkbox" id="select-all-missing" checked></th>';
    $html .= '<th>URL</th>';
    $html .= '<th style="width:150px;">Model</th>';
    $html .= '<th style="width:100px;">İşlem</th>';
    $html .= '</tr></thead><tbody>';

    foreach ($missing_products as $url) {
        // URL'den model kodunu çıkarmaya çalış
        $model = $this->extract_model_from_url($url);

        $html .= '<tr>';
        $html .= '<td><input type="checkbox" class="missing-url-checkbox" value="' . esc_attr($url) . '" checked></td>';
        $html .= '<td><a href="' . esc_url($url) . '" target="_blank">' . esc_html($url) . '</a></td>';
        $html .= '<td>' . esc_html($model) . '</td>';
        $html .= '<td>';
        $html .= '<button type="button" class="button button-small bdi-add-to-list" data-url="' . esc_attr($url) . '">Ekle</button>';
        $html .= '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    // JavaScript
    $html .= '<script>
    jQuery(document).ready(function($) {
        $("#select-all-missing").on("change", function() {
            $(".missing-url-checkbox").prop("checked", $(this).prop("checked"));
        });

        $("#add-all-missing").on("click", function() {
            var btn = $(this);
            var spinner = btn.next(".spinner");
            var selectedUrls = [];

            $(".missing-url-checkbox:checked").each(function() {
                selectedUrls.push($(this).val());
            });

            if (selectedUrls.length === 0) {
                alert("Lütfen en az bir URL seçin");
                return;
            }

            btn.prop("disabled", true);
            spinner.addClass("is-active");

            $.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "bdi_add_bulk_urls",
                    urls: selectedUrls,
                    nonce: "' . wp_create_nonce('bdi_add_bulk') . '"
                },
                success: function(response) {
                    spinner.removeClass("is-active");
                    btn.prop("disabled", false);

                    if (response.success) {
                        alert(response.data.message);
                        $(".missing-url-checkbox:checked").closest("tr").fadeOut();
                    } else {
                        alert("Hata: " + response.data);
                    }
                }
            });
        });
    });
    </script>';

    $html .= '</div>';

    return $html;
}

private function extract_model_from_url($url) {
    // URL'den model kodunu çıkarma
    if (preg_match('/\/(mfc|dcp|hl|ads|pt|ql|td|pj|rj)-([a-z0-9]+)/i', $url, $matches)) {
        return strtoupper($matches[1] . '-' . $matches[2]);
    }

    // URL'nin son kısmını al
    $parts = explode('/', trim($url, '/'));
    $last = end($parts);

    // tr-tr gibi dil kodunu temizle
    $last = preg_replace('/^[a-z]{2}-[a-z]{2}-/', '', $last);

    return strtoupper($last);
}

public function get_existing_source_urls() {
    global $wpdb;

    $query = "
        SELECT DISTINCT meta_value
        FROM {$wpdb->postmeta}
        WHERE meta_key = %s
        AND meta_value != ''
    ";

    $results = $wpdb->get_col($wpdb->prepare($query, self::META_SOURCE));

    return $results ? $results : array();
}

// ASYNC PROCESSING
public function process_single_url_async($url, $settings) {
    try {
        $this->logger->log('Async işleme başladı: ' . $url, BDI_Logger::LEVEL_INFO);

        // Rate limiting kontrolü
        if (isset($settings['rate_limit']) && $settings['rate_limit'] > 0) {
            $this->apply_rate_limit($settings['rate_limit'], $settings['rate_window']);
        }

        // URL'yi işle
        $result = $this->handle_single($url, false);

        if ($result > 0) {
            $this->logger->log('Async işleme tamamlandı: ' . $url . ' (Ürün #' . $result . ')', BDI_Logger::LEVEL_INFO);
        } else {
            $this->logger->log('Async işleme başarısız: ' . $url, BDI_Logger::LEVEL_WARNING);
        }

        return $result;

    } catch (Exception $e) {
        $this->logger->log('Async işleme hatası: ' . $e->getMessage(), BDI_Logger::LEVEL_ERROR);
        return 0;
    }
}

private function apply_rate_limit($limit, $window) {
    $transient_key = 'bdi_rate_limit_' . md5(current_time('mysql'));
    $current_count = get_transient($transient_key);

    if ($current_count === false) {
        set_transient($transient_key, 1, $window);
    } else {
        if ($current_count >= $limit) {
            // Rate limit aşıldı, bekle
            $wait_time = $window - (time() % $window);
            sleep($wait_time);
            set_transient($transient_key, 1, $window);
        } else {
            set_transient($transient_key, $current_count + 1, $window);
        }
    }
}
// ============================================
// PART 8 - HELPERS & UTILITIES
// ============================================
// ============================================
// PART 8 - HELPER VE UTILITY METODLARI
// BDI_Pro_Enhanced class içine eklenecek
// ============================================

// TOPLU KATEGORİ İŞLEMLERİ
public function batch_update_product_categories($limit = 50) {
    if (!$this->settings['auto_categories']) {
        return array('processed' => 0, 'message' => 'Otomatik kategori özelliği devre dışı');
    }

    $args = array(
        'post_type' => 'product',
        'posts_per_page' => $limit,
        'post_status' => 'any',
        'meta_query' => array(
            array(
                'key' => '_bdi_categories_processed',
                'compare' => 'NOT EXISTS'
            )
        ),
        'fields' => 'ids'
    );

    $products = get_posts($args);
    $processed = 0;

    foreach ($products as $product_id) {
        $source_url = get_post_meta($product_id, self::META_SOURCE, true);

        if ($source_url) {
            $categories = $this->find_best_categories_for_product($product_id, $source_url);

            if (!empty($categories)) {
                wp_set_object_terms($product_id, $categories, 'product_cat');
                $this->logger->log('Kategoriler güncellendi: Ürün #' . $product_id, BDI_Logger::LEVEL_DEBUG);
            }
        }

        update_post_meta($product_id, '_bdi_categories_processed', current_time('mysql'));
        $processed++;
    }

    return array(
        'processed' => $processed,
        'remaining' => $this->get_uncategorized_product_count(),
        'message' => $processed . ' ürün işlendi'
    );
}

public function find_best_categories_for_product($product_id, $source_url = '') {
    $product = wc_get_product($product_id);
    if (!$product) return array();

    $title = $product->get_name();
    $sku = $product->get_sku();

    $category_ids = array();

    // Model bazlı kategori eşleştirme
    $model_mappings = array(
        'MFC' => array('yazicilar', 'cok-fonksiyonlu-yazicilar'),
        'DCP' => array('yazicilar', 'cok-fonksiyonlu-yazicilar'),
        'HL' => array('yazicilar', 'lazer-yazicilar'),
        'ADS' => array('tarayicilar', 'dokuman-tarayicilar'),
        'PT' => array('etiket-yazicilar', 'p-touch-etiket'),
        'QL' => array('etiket-yazicilar', 'profesyonel-etiket'),
        'TD' => array('etiket-yazicilar', 'mobil-yazicilar'),
        'PJ' => array('mobil-yazicilar'),
        'RJ' => array('mobil-yazicilar', 'makbuz-yazicilar')
    );

    foreach ($model_mappings as $prefix => $cat_slugs) {
        if (stripos($sku, $prefix . '-') !== false || stripos($title, $prefix . '-') !== false) {
            foreach ($cat_slugs as $slug) {
                $term = get_term_by('slug', $slug, 'product_cat');
                if ($term) {
                    $category_ids[] = $term->term_id;
                }
            }
            break;
        }
    }

    // URL bazlı kategori tespiti
    if (empty($category_ids) && $source_url) {
        if (strpos($source_url, '/printer') !== false) {
            $term = get_term_by('slug', 'yazicilar', 'product_cat');
            if ($term) $category_ids[] = $term->term_id;
        } elseif (strpos($source_url, '/scanner') !== false) {
            $term = get_term_by('slug', 'tarayicilar', 'product_cat');
            if ($term) $category_ids[] = $term->term_id;
        } elseif (strpos($source_url, '/label') !== false) {
            $term = get_term_by('slug', 'etiket-yazicilar', 'product_cat');
            if ($term) $category_ids[] = $term->term_id;
        }
    }

    // Varsayılan kategori
    if (empty($category_ids)) {
        $default_term = get_term_by('slug', 'diger-urunler', 'product_cat');
        if (!$default_term) {
            $default_term = wp_insert_term('Diğer Ürünler', 'product_cat', array('slug' => 'diger-urunler'));
            if (!is_wp_error($default_term)) {
                $category_ids[] = $default_term['term_id'];
            }
        } else {
            $category_ids[] = $default_term->term_id;
        }
    }

    return array_unique($category_ids);
}

private function get_uncategorized_product_count() {
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'meta_query' => array(
            array(
                'key' => '_bdi_categories_processed',
                'compare' => 'NOT EXISTS'
            )
        ),
        'fields' => 'ids'
    );

    $query = new WP_Query($args);
    return $query->found_posts;
}

// KATEGORİ OLUŞTURMA
public function ensure_categories() {
    $categories = array(
        'yazicilar' => array(
            'name' => 'Yazıcılar',
            'children' => array(
                'lazer-yazicilar' => 'Lazer Yazıcılar',
                'inkjet-yazicilar' => 'Inkjet Yazıcılar',
                'cok-fonksiyonlu-yazicilar' => 'Çok Fonksiyonlu Yazıcılar',
                'mobil-yazicilar' => 'Mobil Yazıcılar'
            )
        ),
        'tarayicilar' => array(
            'name' => 'Tarayıcılar',
            'children' => array(
                'dokuman-tarayicilar' => 'Döküman Tarayıcılar',
                'mobil-tarayicilar' => 'Mobil Tarayıcılar'
            )
        ),
        'etiket-yazicilar' => array(
            'name' => 'Etiket Yazıcılar',
            'children' => array(
                'p-touch-etiket' => 'P-Touch Etiket',
                'profesyonel-etiket' => 'Profesyonel Etiket'
            )
        ),
        'sarf-malzemeleri' => array(
            'name' => 'Sarf Malzemeleri',
            'children' => array(
                'tonerler' => 'Tonerler',
                'murekkep-kartuslari' => 'Mürekkep Kartuşları',
                'drum-uniteleri' => 'Drum Üniteleri',
                'etiket-seritler' => 'Etiket Şeritleri'
            )
        ),
        'aksesuarlar' => array(
            'name' => 'Aksesuarlar',
            'children' => array()
        )
    );

    $created = 0;

    foreach ($categories as $slug => $data) {
        $parent_term = get_term_by('slug', $slug, 'product_cat');

        if (!$parent_term) {
            $result = wp_insert_term($data['name'], 'product_cat', array('slug' => $slug));
            if (!is_wp_error($result)) {
                $parent_id = $result['term_id'];
                $created++;
                $this->logger->log('Ana kategori oluşturuldu: ' . $data['name'], BDI_Logger::LEVEL_INFO);
            } else {
                continue;
            }
        } else {
            $parent_id = $parent_term->term_id;
        }

        // Alt kategorileri oluştur
        if (!empty($data['children'])) {
            foreach ($data['children'] as $child_slug => $child_name) {
                $child_term = get_term_by('slug', $child_slug, 'product_cat');

                if (!$child_term) {
                    $result = wp_insert_term($child_name, 'product_cat', array(
                        'slug' => $child_slug,
                        'parent' => $parent_id
                    ));

                    if (!is_wp_error($result)) {
                        $created++;
                        $this->logger->log('Alt kategori oluşturuldu: ' . $child_name, BDI_Logger::LEVEL_DEBUG);
                    }
                }
            }
        }
    }

    return $created;
}

// MAPPING VE CLEANUP
public function apply_mapping_and_cleanup($raw_value, $attribute_slug = '') {
    // Boş değer kontrolü
    if (empty($raw_value)) return '';

    // HTML temizleme
    $value = wp_strip_all_tags($raw_value);
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Fazla boşlukları temizle
    $value = preg_replace('/\s+/', ' ', $value);
    $value = trim($value);

    // Özel karakter temizleme
    $value = str_replace(array('™', '®', '©'), '', $value);

    // Attribute'a özel temizleme
    switch ($attribute_slug) {
        case 'pa_connection':
            // Bağlantı tipleri için standardizasyon
            $mappings = array(
                'usb 2.0' => 'USB 2.0',
                'usb2' => 'USB 2.0',
                'usb 3.0' => 'USB 3.0',
                'usb3' => 'USB 3.0',
                'wi-fi' => 'Wi-Fi',
                'wifi' => 'Wi-Fi',
                'ethernet' => 'Ethernet',
                'lan' => 'Ethernet',
                'bluetooth' => 'Bluetooth',
                'nfc' => 'NFC'
            );
            $value_lower = strtolower($value);
            if (isset($mappings[$value_lower])) {
                $value = $mappings[$value_lower];
            }
            break;

        case 'pa_print-speed':
            // Hız değerlerini standardize et
            $value = preg_replace('/\s*(ppm|sayfa\/dk|sayfa\/dakika)$/i', ' ppm', $value);
            break;

        case 'pa_capacity':
            // Kapasite değerlerini standardize et
            $value = preg_replace('/\s*(sayfa|yaprak|sheet)s?$/i', ' sayfa', $value);
            break;
    }

    return $value;
}

// RESİM İŞLEME
public function is_allowed_image_url($url) {
    if (empty($url)) return false;

    // Güvenlik kontrolleri
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['host'])) return false;

    // İzin verilen domain'ler
    $allowed_domains = array(
        'brother.com',
        'brother.com.tr',
        'brothercdn.com',
        'brother-usa.com'
    );

    $host = strtolower($parsed['host']);
    $allowed = false;

    foreach ($allowed_domains as $domain) {
        if ($host === $domain || substr($host, -(strlen($domain) + 1)) === '.' . $domain) {
            $allowed = true;
            break;
        }
    }

    if (!$allowed) {
        $this->logger->log('İzin verilmeyen resim domain\'i: ' . $host, BDI_Logger::LEVEL_WARNING);
        return false;
    }

    // Dosya uzantısı kontrolü
    $path = isset($parsed['path']) ? $parsed['path'] : '';
    if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $path)) {
        $this->logger->log('Geçersiz resim uzantısı: ' . $path, BDI_Logger::LEVEL_WARNING);
        return false;
    }

    return true;
}

public function import_images_optimized($product_id, $title, $sku, $image_urls, $quality = 85, $convert_webp = false, $max_width = 800, $max_height = 600, $crop = false) {
    if (empty($image_urls)) return;

    $attachment_ids = array();
    $processed = 0;
    $max_images = 10; // Maksimum resim sayısı

    foreach ($image_urls as $index => $url) {
        if ($processed >= $max_images) break;

        if (!$this->is_allowed_image_url($url)) continue;

        $attachment_id = $this->download_and_attach_image(
            $url,
            $product_id,
            $title . ' ' . ($index + 1),
            $sku,
            $quality,
            $convert_webp,
            $max_width,
            $max_height,
            $crop
        );

        if ($attachment_id) {
            $attachment_ids[] = $attachment_id;
            $processed++;

            // İlk resmi öne çıkan görsel yap
            if ($index === 0) {
                set_post_thumbnail($product_id, $attachment_id);
                $this->logger->log('Öne çıkan görsel ayarlandı: ' . $attachment_id, BDI_Logger::LEVEL_DEBUG);
            }
        }
    }

    // Galeri resimlerini ayarla
    if (count($attachment_ids) > 1) {
        $gallery_ids = array_slice($attachment_ids, 1); // İlki hariç
        update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
        $this->logger->log('Galeri resimleri ayarlandı: ' . count($gallery_ids) . ' adet', BDI_Logger::LEVEL_DEBUG);
    }

    return $attachment_ids;
}

private function download_and_attach_image($url, $product_id, $title, $sku, $quality, $convert_webp, $max_width, $max_height, $crop) {
    // Önce cache'i kontrol et
    $cache_key = 'bdi_img_' . md5($url);
    $cached_id = get_transient($cache_key);

    if ($cached_id && wp_attachment_is_image($cached_id)) {
        return $cached_id;
    }

    // Resmi indir
    $response = wp_remote_get($url, array(
        'timeout' => 30,
        'user-agent' => 'Mozilla/5.0 (compatible; WP-BDI/5.4.0)'
    ));

    if (is_wp_error($response)) {
        $this->logger->log('Resim indirme hatası: ' . $url, BDI_Logger::LEVEL_ERROR);
        return 0;
    }

    $image_data = wp_remote_retrieve_body($response);
    if (empty($image_data)) return 0;

    // Dosya adı oluştur
    $filename = sanitize_file_name($sku . '-' . md5($url) . '.jpg');

    // Upload dizinine kaydet
    $upload_dir = wp_upload_dir();
    $upload_path = $upload_dir['path'] . '/' . $filename;
    $upload_url = $upload_dir['url'] . '/' . $filename;

    file_put_contents($upload_path, $image_data);

    // WordPress media library'ye ekle
    $attachment = array(
        'post_mime_type' => 'image/jpeg',
        'post_title' => $title,
        'post_content' => '',
        'post_status' => 'inherit',
        'post_parent' => $product_id
    );

    $attachment_id = wp_insert_attachment($attachment, $upload_path, $product_id);

    if (!is_wp_error($attachment_id)) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Metadata oluştur
        $attach_data = wp_generate_attachment_metadata($attachment_id, $upload_path);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        // Alt text ekle
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $title);

        // Cache'e kaydet
        set_transient($cache_key, $attachment_id, DAY_IN_SECONDS * 7);

        $this->logger->log('Resim eklendi: ' . $filename, BDI_Logger::LEVEL_DEBUG);

        return $attachment_id;
    }

    return 0;
}

// FILTER METODLARI
public function apply_image_size_filters() {
    add_filter('intermediate_image_sizes_advanced', array($this, 'disable_intermediate_sizes'), 10, 2);
    add_filter('big_image_size_threshold', array($this, 'return_zero'));
}

public function remove_image_size_filters() {
    remove_filter('intermediate_image_sizes_advanced', array($this, 'disable_intermediate_sizes'), 10);
    remove_filter('big_image_size_threshold', array($this, 'return_zero'));
}

public function return_zero() {
    return 0;
}

public function disable_intermediate_sizes($sizes, $metadata) {
    // Sadece zorunlu boyutları tut
    $allowed = array('thumbnail', 'woocommerce_thumbnail', 'woocommerce_single');

    foreach ($sizes as $size => $params) {
        if (!in_array($size, $allowed)) {
            unset($sizes[$size]);
        }
    }

    return $sizes;
}

// ÜRÜN BULMA METODLARI
public function find_product_by_source($url) {
    global $wpdb;

    $query = $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta}
         WHERE meta_key = %s AND meta_value = %s LIMIT 1",
        self::META_SOURCE,
        $url
    );

    $result = $wpdb->get_var($query);
    return $result ? intval($result) : 0;
}

public function find_product_by_title($title) {
    global $wpdb;

    $query = $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type = 'product'
         AND post_status IN ('publish', 'draft', 'pending')
         AND post_title = %s LIMIT 1",
        $title
    );

    $result = $wpdb->get_var($query);
    return $result ? intval($result) : 0;
}

// XPATH HELPER METODLARI
private function abs($node, $url) {
    if (!$node || !$url) return '';

    $href = trim($node->getAttribute('href'));
    if ($href === '') return '';

    if (preg_match('/^https?:\/\//i', $href)) {
        return $href;
    }

    $parsed = parse_url($url);
    $scheme = isset($parsed['scheme']) ? $parsed['scheme'] : 'http';
    $host = isset($parsed['host']) ? $parsed['host'] : '';

    if ($href[0] === '/') {
        return $scheme . '://' . $host . $href;
    }

    $path = isset($parsed['path']) ? $parsed['path'] : '/';
    $path = substr($path, 0, strrpos($path, '/') + 1);

    return $scheme . '://' . $host . $path . $href;
}

private function xp_first($xp, $query, $context = null) {
    $nodes = $context ? $xp->query($query, $context) : $xp->query($query);
    return ($nodes && $nodes->length > 0) ? $nodes->item(0) : null;
}

private function xp_text($xp, $query, $context = null, $default = '') {
    $node = $this->xp_first($xp, $query, $context);
    return $node ? trim($node->textContent) : $default;
}

private function xp_html($xp, $query, $context = null, $default = '') {
    $node = $this->xp_first($xp, $query, $context);
    if (!$node) return $default;

    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument->saveHTML($child);
    }

    return trim($html);
}

private function xp_attr($xp, $query, $attr, $context = null, $default = '') {
    $node = $this->xp_first($xp, $query, $context);
    return $node ? trim($node->getAttribute($attr)) : $default;
}
// ============================================
// PART 9 - CSS/JS & TABS
// ============================================
// ============================================
// PART 9 - CSS/JS VE WOOCOMMERCE TAB METODLARI
// BDI_Pro_Enhanced class içine eklenecek
// ============================================

// CSS VE JS EKLEME
public function enqueue_styles_scripts() {
    if (!is_product()) return;

    global $post;

    // Sadece Brother ürünlerinde yükle
    $source = get_post_meta($post->ID, self::META_SOURCE, true);
    if (!$source || strpos($source, 'brother.com') === false) return;

    // Inline CSS
    $css = '
    <style>
    /* Brother Importer Custom Styles */
    .brother-tabs-content {
        padding: 20px 0;
    }

    .brother-tabs-content h3 {
        color: #0d2ea0;
        margin-bottom: 20px;
        font-size: 24px;
        border-bottom: 2px solid #0d2ea0;
        padding-bottom: 10px;
    }

    /* Specifications Table */
    .brother-specs-table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .brother-specs-table th,
    .brother-specs-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #e0e0e0;
    }

    .brother-specs-table th {
        background: #f5f5f5;
        font-weight: 600;
        color: #333;
        width: 35%;
    }

    .brother-specs-table tr:hover {
        background: #f9f9f9;
    }

    .brother-specs-table tr:last-child td,
    .brother-specs-table tr:last-child th {
        border-bottom: none;
    }

    /* Support Content */
    .brother-support-content {
        line-height: 1.8;
        color: #333;
    }

    .brother-support-content ul {
        list-style: none;
        padding: 0;
        margin: 20px 0;
    }

    .brother-support-content ul li {
        position: relative;
        padding-left: 30px;
        margin-bottom: 12px;
        line-height: 1.6;
    }

    .brother-support-content ul li:before {
        content: "✓";
        position: absolute;
        left: 0;
        color: #27ae60;
        font-weight: bold;
        font-size: 18px;
    }

    .brother-support-content strong {
        color: #2c3e50;
        font-weight: 600;
    }

    /* Supplies and Accessories */
    .brother-supplies-list,
    .brother-accessories-list {
        margin: 20px 0;
    }

    .supplies-grid,
    .accessories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .supply-item,
    .accessory-item {
        border: 1px solid #e0e0e0;
        padding: 15px;
        border-radius: 8px;
        background: #fff;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .supply-item:hover,
    .accessory-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .supply-item h4,
    .accessory-item h4 {
        margin: 0 0 10px 0;
        font-size: 16px;
        font-weight: 600;
        color: #333;
    }

    .supply-item .supply-image,
    .accessory-item .accessory-image {
        text-align: center;
        margin-bottom: 12px;
        min-height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .supply-item img,
    .accessory-item img {
        max-width: 100%;
        height: auto;
        max-height: 100px;
    }

    .supply-type,
    .accessory-type {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 11px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 5px 0;
    }

    .supply-type {
        background: #0d2ea0;
        color: white;
    }

    .accessory-type {
        background: #28a745;
        color: white;
    }

    .supply-yield,
    .accessory-info {
        font-size: 13px;
        color: #666;
        margin: 8px 0;
    }

    .supply-item .button,
    .accessory-item .button {
        display: inline-block;
        margin-top: 10px;
        padding: 8px 16px;
        background: #0d2ea0;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        font-size: 13px;
        transition: background 0.3s ease;
    }

    .supply-item .button:hover,
    .accessory-item .button:hover {
        background: #0a2380;
    }

    /* No Data Messages */
    .no-supplies-message,
    .no-accessories-message {
        padding: 30px;
        text-align: center;
        background: #f8f9fa;
        border: 1px dashed #dee2e6;
        border-radius: 8px;
        color: #6c757d;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .supplies-grid,
        .accessories-grid {
            grid-template-columns: 1fr;
        }

        .brother-specs-table {
            font-size: 14px;
        }

        .brother-specs-table th,
        .brother-specs-table td {
            padding: 10px;
        }
    }

    /* Loading State */
    .brother-loading {
        text-align: center;
        padding: 40px;
        color: #999;
    }

    .brother-loading:after {
        content: "...";
        animation: dots 1.5s steps(4, end) infinite;
    }

    @keyframes dots {
        0%, 20% { content: "."; }
        40% { content: ".."; }
        60% { content: "..."; }
        80%, 100% { content: ""; }
    }
    </style>
    ';

    echo $css;

    // JavaScript
    $js = '
    <script>
    jQuery(document).ready(function($) {
        // Tab aktivasyon animasyonu
        $(".woocommerce-tabs .tabs a").on("click", function(e) {
            var target = $(this).attr("href");
            if (target && target.indexOf("#tab-") === 0) {
                $(target).hide().fadeIn(300);
            }
        });

        // Sarf malzemesi lazy loading
        var suppliesLoaded = false;
        $("#tab-brother_supplies").on("click", function() {
            if (!suppliesLoaded) {
                // AJAX ile sarf malzemelerini yükle
                suppliesLoaded = true;
            }
        });

        // Aksesuar lazy loading
        var accessoriesLoaded = false;
        $("#tab-brother_accessories").on("click", function() {
            if (!accessoriesLoaded) {
                // AJAX ile aksesuarları yükle
                accessoriesLoaded = true;
            }
        });
    });
    </script>
    ';

    echo $js;
}

// WOOCOMMERCE TAB METODLARI
public function add_brother_tabs($tabs) {
    global $post;

    if (!$post || !is_object($post)) return $tabs;

    // Teknik özellikler varsa ekle
    $specs = get_post_meta($post->ID, self::META_SPECS, true);
    if (!empty($specs)) {
        $tabs['brother_specifications'] = array(
            'title' => 'Teknik Özellikler',
            'priority' => 15,
            'callback' => 'bdi_render_specs_tab'
        );
    }

    // Destek içeriği varsa ekle
    $support = get_post_meta($post->ID, self::META_SUPPORT, true);
    if (!empty($support)) {
        $tabs['brother_support'] = array(
            'title' => 'Destek',
            'priority' => 25,
            'callback' => 'bdi_render_support_tab'
        );
    }

    // Sarf malzemeleri varsa ekle
    $supplies_json = get_post_meta($post->ID, self::META_SUPPLIES, true);
    $supplies = json_decode($supplies_json, true);
    if (!empty($supplies)) {
        $tabs['brother_supplies'] = array(
            'title' => 'Sarf Malzemeleri',
            'priority' => 30,
            'callback' => 'bdi_render_supplies_tab'
        );
    }

    // Aksesuarlar varsa ekle
    $accessories_json = get_post_meta($post->ID, self::META_ACCESSORIES, true);
    $accessories = json_decode($accessories_json, true);
    if (!empty($accessories)) {
        $tabs['brother_accessories'] = array(
            'title' => 'Aksesuarlar',
            'priority' => 35,
            'callback' => 'bdi_render_accessories_tab'
        );
    }

    return $tabs;
}

// TAB RENDER METODLARI
public function render_specs_tab() {
    global $post;

    $specs = get_post_meta($post->ID, self::META_SPECS, true);

    if (empty($specs)) {
        echo '<div class="brother-tabs-content"><p>Teknik özellik bilgisi bulunmamaktadır.</p></div>';
        return;
    }

    echo '<div class="brother-tabs-content">';
    echo '<h3>Teknik Özellikler</h3>';

    // Özellikleri kategorilere ayır
    $categorized_specs = $this->categorize_specifications($specs);

    foreach ($categorized_specs as $category => $items) {
        if (empty($items)) continue;

        echo '<h4 style="margin-top:30px;margin-bottom:15px;color:#555;">' . esc_html($category) . '</h4>';
        echo '<table class="brother-specs-table">';

        foreach ($items as $label => $value) {
            echo '<tr>';
            echo '<th>' . esc_html($label) . '</th>';
            echo '<td>' . wp_kses_post($value) . '</td>';
            echo '</tr>';
        }

        echo '</table>';
    }

    echo '</div>';
}

public function render_support_tab() {
    global $post;

    $support = get_post_meta($post->ID, self::META_SUPPORT, true);

    if (empty($support)) {
        echo '<div class="brother-tabs-content"><p>Destek bilgisi bulunmamaktadır.</p></div>';
        return;
    }

    $settings = get_option(self::OPT_SETTINGS, array());

    // Stil ayarlarını al
    $marker = isset($settings['marker_symbol']) ? $settings['marker_symbol'] : '✓';
    $marker_color = isset($settings['marker_color']) ? $settings['marker_color'] : '#27ae60';
    $text_color = isset($settings['text_color']) ? $settings['text_color'] : '#333333';
    $strong_color = isset($settings['strong_color']) ? $settings['strong_color'] : '#2c3e50';
    $font_family = isset($settings['font_family']) ? $settings['font_family'] : 'Arial, sans-serif';
    $font_size = isset($settings['font_size']) ? $settings['font_size'] : '14px';
    $line_height = isset($settings['line_height']) ? $settings['line_height'] : '1.6';

    // Özel stil ekle
    echo '<style>
    .brother-support-content {
        font-family: ' . esc_attr($font_family) . ';
        font-size: ' . esc_attr($font_size) . ';
        line-height: ' . esc_attr($line_height) . ';
        color: ' . esc_attr($text_color) . ';
    }
    .brother-support-content ul li:before {
        content: "' . esc_attr($marker) . '";
        color: ' . esc_attr($marker_color) . ';
    }
    .brother-support-content strong {
        color: ' . esc_attr($strong_color) . ';
    }
    </style>';

    echo '<div class="brother-tabs-content">';
    echo '<h3>Destek ve Özellikler</h3>';
    echo '<div class="brother-support-content">';
    echo wp_kses_post($support);
    echo '</div>';
    echo '</div>';
}

public function render_supplies_tab() {
    global $post;

    $supplies_json = get_post_meta($post->ID, self::META_SUPPLIES, true);
    $supplies = json_decode($supplies_json, true);

    if (empty($supplies)) {
        echo '<div class="brother-tabs-content">';
        echo '<div class="no-supplies-message">Bu ürün için sarf malzemesi bilgisi bulunmamaktadır.</div>';
        echo '</div>';
        return;
    }

    echo '<div class="brother-tabs-content">';
    echo $this->render_supplies_html($supplies, $post->ID);
    echo '</div>';
}

public function render_accessories_tab() {
    global $post;

    $accessories_json = get_post_meta($post->ID, self::META_ACCESSORIES, true);
    $accessories = json_decode($accessories_json, true);

    if (empty($accessories)) {
        echo '<div class="brother-tabs-content">';
        echo '<div class="no-accessories-message">Bu ürün için aksesuar bilgisi bulunmamaktadır.</div>';
        echo '</div>';
        return;
    }

    echo '<div class="brother-tabs-content">';
    echo $this->render_accessories_html($accessories, $post->ID);
    echo '</div>';
}

// YARDIMCI METODLAR
private function categorize_specifications($specs) {
    $categories = array(
        'Genel Özellikler' => array(),
        'Yazdırma' => array(),
        'Tarama' => array(),
        'Kopyalama' => array(),
        'Faks' => array(),
        'Kağıt Yönetimi' => array(),
        'Bağlantı' => array(),
        'Fiziksel Özellikler' => array(),
        'Diğer' => array()
    );

    // Kategori anahtar kelimeleri
    $keywords = array(
        'Yazdırma' => array('yazdırma', 'print', 'hız', 'speed', 'çözünürlük', 'resolution', 'dpi'),
        'Tarama' => array('tarama', 'scan', 'tarayıcı', 'scanner', 'adf', 'otomatik'),
        'Kopyalama' => array('kopyalama', 'copy', 'fotokopi'),
        'Faks' => array('faks', 'fax', 'modem'),
        'Kağıt Yönetimi' => array('kağıt', 'paper', 'tepsi', 'tray', 'kapasite', 'capacity'),
        'Bağlantı' => array('bağlantı', 'connection', 'usb', 'ethernet', 'wi-fi', 'wifi', 'network', 'ağ'),
        'Fiziksel Özellikler' => array('boyut', 'size', 'ağırlık', 'weight', 'ebat', 'dimension')
    );

    // Özellikleri kategorilere ayır
    foreach ($specs as $label => $value) {
        $assigned = false;
        $label_lower = mb_strtolower($label, 'UTF-8');

        foreach ($keywords as $category => $category_keywords) {
            foreach ($category_keywords as $keyword) {
                if (strpos($label_lower, $keyword) !== false) {
                    $categories[$category][$label] = $value;
                    $assigned = true;
                    break 2;
                }
            }
        }

        if (!$assigned) {
            $categories['Diğer'][$label] = $value;
        }
    }

    // Boş kategorileri kaldır
    foreach ($categories as $category => $items) {
        if (empty($items)) {
            unset($categories[$category]);
        }
    }

    return $categories;
}

// ATTRIBUTE OLUŞTURMA
public function create_attributes_from_specs($product_id, $specs) {
    if (empty($specs) || !is_array($specs)) return 0;

    $attributes = array();
    $attribute_mapping = $this->attribute_handler->get_attribute_mapping();
    $created_count = 0;

    foreach ($specs as $label => $value) {
        // Mapping'de varsa kullan
        if (isset($attribute_mapping[$label])) {
            $attribute_slug = 'pa_' . $attribute_mapping[$label];

            // Değeri temizle ve standardize et
            $clean_value = $this->apply_mapping_and_cleanup($value, $attribute_slug);

            if (!empty($clean_value)) {
                $attributes[$attribute_slug] = $clean_value;
                $created_count++;
            }
        }
    }

    // Attribute'ları ürüne ekle
    if (!empty($attributes)) {
        $result = $this->attribute_handler->add_product_attributes($product_id, $attributes);

        if ($result) {
            $this->logger->log(
                'Teknik özelliklerden ' . $created_count . ' attribute oluşturuldu (Ürün #' . $product_id . ')',
                BDI_Logger::LEVEL_INFO
            );
        }
    }

    return $created_count;
}

// SON KAPANIŞ METODLARI
public function __destruct() {
    // Cleanup işlemleri
    $this->remove_image_size_filters();
}

// CLASS SONU
}
// Plugin initialization
if (!isset($GLOBALS["bdi_pro_enhanced"])) {
    $GLOBALS["bdi_pro_enhanced"] = new BDI_Pro_Enhanced();
}

// Activation/Deactivation hooks
register_activation_hook(__FILE__, array('BDI_Pro_Enhanced', 'activate'));
register_deactivation_hook(__FILE__, array('BDI_Pro_Enhanced', 'deactivate'));

    // ============================================
    // ABSOLUTIZE URLs METHODS (Missing methods fix)
    // ============================================
    
    /**
     * HTML içindeki tüm URL'leri absolute hale getirir
     */
    private function absolutize_urls($html, $base_url) {
        if (empty($html) || empty($base_url)) {
            return $html;
        }
        
        try {
            $doc = new DOMDocument();
            libxml_use_internal_errors(true);
            
            $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
            @$doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            
            $xpath = new DOMXPath($doc);
            
            // href attributes
            $links = $xpath->query('//*[@href]');
            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                if ($href) {
                    $absolute_url = $this->make_absolute_url($href, $base_url);
                    $link->setAttribute('href', $absolute_url);
                }
            }
            
            // src attributes
            $images = $xpath->query('//*[@src]');
            foreach ($images as $img) {
                $src = $img->getAttribute('src');
                if ($src) {
                    $absolute_url = $this->make_absolute_url($src, $base_url);
                    $img->setAttribute('src', $absolute_url);
                }
            }
            
            $html = $doc->saveHTML();
            $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            return $html;
            
        } catch (Exception $e) {
            $this->logger->log('URL absolutize hatası: ' . $e->getMessage(), BDI_Logger::LEVEL_WARNING);
            return $html;
        }
    }
    
    /**
     * Tek bir URL'yi absolute hale getirir
     */
    private function make_absolute_url($url, $base) {
        if (empty($url)) return $url;
        
        if (preg_match('/^(data:|mailto:|javascript:|#)/i', $url)) {
            return $url;
        }
        
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }
        
        if (substr($url, 0, 2) === '//') {
            $parsed_base = parse_url($base);
            $scheme = isset($parsed_base['scheme']) ? $parsed_base['scheme'] : 'https';
            return $scheme . ':' . $url;
        }
        
        $parsed = parse_url($base);
        if (!$parsed) return $url;
        
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] : 'https';
        $host = isset($parsed['host']) ? $parsed['host'] : '';
        
        if (empty($host)) return $url;
        
        if ($url[0] === '/') {
            return $scheme . '://' . $host . $url;
        }
        
        $path = isset($parsed['path']) ? $parsed['path'] : '/';
        
        if (substr($path, -1) !== '/') {
            $path = dirname($path);
            if ($path === '.' || $path === '\\') {
                $path = '/';
            } elseif (substr($path, -1) !== '/') {
                $path .= '/';
            }
        }
        
        return $scheme . '://' . $host . $path . $url;
    }

} // BDI_Pro_Enhanced class end
endif;

// ============================================
// PLUGIN INITIALIZATION
// ============================================
if (!isset($GLOBALS["bdi_pro_enhanced"])) {
    $GLOBALS["bdi_pro_enhanced"] = new BDI_Pro_Enhanced();
}

// Register activation/deactivation hooks
register_activation_hook(__FILE__, array("BDI_Pro_Enhanced", "activate"));
register_deactivation_hook(__FILE__, array("BDI_Pro_Enhanced", "deactivate"));
    // ============================================
    // CSS/JS VE WOOCOMMERCE TAB METODLARI
    // ============================================
    public function enqueue_styles_scripts() {
        if (!is_product()) return;
        
        global $post;
        
        $source = get_post_meta($post->ID, self::META_SOURCE, true);
        if (!$source || strpos($source, 'brother.com') === false) return;
        
        $css = '
        <style>
        /* Brother Importer Custom Styles */
        .brother-tabs-content {
            padding: 20px 0;
        }
        
        .brother-tabs-content h3 {
            color: #0d2ea0;
            margin-bottom: 20px;
            font-size: 24px;
            border-bottom: 2px solid #0d2ea0;
            padding-bottom: 10px;
        }
        
        /* Specifications Table */
        .brother-specs-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .brother-specs-table th,
        .brother-specs-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .brother-specs-table th {
            background: #f5f5f5;
            font-weight: 600;
            color: #333;
            width: 35%;
        }
        
        .brother-specs-table tr:hover {
            background: #f9f9f9;
        }
        
        .brother-specs-table tr:last-child td,
        .brother-specs-table tr:last-child th {
            border-bottom: none;
        }
        
        /* Support Content */
        .brother-support-content {
            line-height: 1.8;
            color: #333;
        }
        
        .brother-support-content ul {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        
        .brother-support-content ul li {
            position: relative;
            padding-left: 30px;
            margin-bottom: 12px;
            line-height: 1.6;
        }
        
        .brother-support-content ul li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #27ae60;
            font-weight: bold;
            font-size: 18px;
        }
        
        .brother-support-content strong {
            color: #2c3e50;
            font-weight: 600;
        }
        
        /* Supplies and Accessories */
        .brother-supplies-list,
        .brother-accessories-list {
            margin: 20px 0;
        }
        
        .supplies-grid,
        .accessories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .supply-item,
        .accessory-item {
            border: 1px solid #e0e0e0;
            padding: 15px;
            border-radius: 8px;
            background: #fff;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .supply-item:hover,
        .accessory-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .supply-item h4,
        .accessory-item h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .supply-item .supply-image,
        .accessory-item .accessory-image {
            text-align: center;
            margin-bottom: 12px;
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .supply-item img,
        .accessory-item img {
            max-width: 100%;
            height: auto;
            max-height: 100px;
        }
        
        .supply-type,
        .accessory-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 5px 0;
        }
        
        .supply-type {
            background: #0d2ea0;
            color: white;
        }
        
        .accessory-type {
            background: #28a745;
            color: white;
        }
        
        .supply-yield,
        .accessory-info {
            font-size: 13px;
            color: #666;
            margin: 8px 0;
        }
        
        .supply-item .button,
        .accessory-item .button {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 16px;
            background: #0d2ea0;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
            transition: background 0.3s ease;
        }
        
        .supply-item .button:hover,
        .accessory-item .button:hover {
            background: #0a2380;
        }
        
        /* No Data Messages */
        .no-supplies-message,
        .no-accessories-message {
            padding: 30px;
            text-align: center;
            background: #f8f9fa;
            border: 1px dashed #dee2e6;
            border-radius: 8px;
            color: #6c757d;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .supplies-grid,
            .accessories-grid {
                grid-template-columns: 1fr;
            }
            
            .brother-specs-table {
                font-size: 14px;
            }
            
            .brother-specs-table th,
            .brother-specs-table td {
                padding: 10px;
            }
        }
        
        /* Loading State */
        .brother-loading {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .brother-loading:after {
            content: "...";
            animation: dots 1.5s steps(4, end) infinite;
        }
        
        @keyframes dots {
            0%, 20% { content: "."; }
            40% { content: ".."; }
            60% { content: "..."; }
            80%, 100% { content: ""; }
        }
        </style>
        ';
        
        echo $css;
        
        $js = '
        <script>
        jQuery(document).ready(function($) {
            // Tab aktivasyon animasyonu
            $(".woocommerce-tabs .tabs a").on("click", function(e) {
                var target = $(this).attr("href");
                if (target && target.indexOf("#tab-") === 0) {
                    $(target).hide().fadeIn(300);
                }
            });
            
            // Sarf malzemesi lazy loading
            var suppliesLoaded = false;
            $("#tab-brother_supplies").on("click", function() {
                if (!suppliesLoaded) {
                    // AJAX ile sarf malzemelerini yükle
                    suppliesLoaded = true;
                }
            });
            
            // Aksesuar lazy loading
            var accessoriesLoaded = false;
            $("#tab-brother_accessories").on("click", function() {
                if (!accessoriesLoaded) {
                    // AJAX ile aksesuarları yükle
                    accessoriesLoaded = true;
                }
            });
        });
        </script>
        ';
        
        echo $js;
    }

    public function add_brother_tabs($tabs) {
        global $post;
        
        if (!$post || !is_object($post)) return $tabs;
        
        $specs = get_post_meta($post->ID, self::META_SPECS, true);
        if (!empty($specs)) {
            $tabs['brother_specifications'] = array(
                'title' => 'Teknik Özellikler',
                'priority' => 15,
                'callback' => 'bdi_render_specs_tab'
            );
        }
        
        $support = get_post_meta($post->ID, self::META_SUPPORT, true);
        if (!empty($support)) {
            $tabs['brother_support'] = array(
                'title' => 'Destek',
                'priority' => 25,
                'callback' => 'bdi_render_support_tab'
            );
        }
        
        $supplies_json = get_post_meta($post->ID, self::META_SUPPLIES, true);
        $supplies = json_decode($supplies_json, true);
        if (!empty($supplies)) {
            $tabs['brother_supplies'] = array(
                'title' => 'Sarf Malzemeleri',
                'priority' => 30,
                'callback' => 'bdi_render_supplies_tab'
            );
        }
        
        $accessories_json = get_post_meta($post->ID, self::META_ACCESSORIES, true);
        $accessories = json_decode($accessories_json, true);
        if (!empty($accessories)) {
            $tabs['brother_accessories'] = array(
                'title' => 'Aksesuarlar',
                'priority' => 35,
                'callback' => 'bdi_render_accessories_tab'
            );
        }
        
        return $tabs;
    }

    public function render_specs_tab() {
        global $post;
        
        $specs = get_post_meta($post->ID, self::META_SPECS, true);
        
        if (empty($specs)) {
            echo '<div class="brother-tabs-content"><p>Teknik özellik bilgisi bulunmamaktadır.</p></div>';
            return;
        }
        
        echo '<div class="brother-tabs-content">';
        echo '<h3>Teknik Özellikler</h3>';
        
        $categorized_specs = $this->categorize_specifications($specs);
        
        foreach ($categorized_specs as $category => $items) {
            if (empty($items)) continue;
            
            echo '<h4 style="margin-top:30px;margin-bottom:15px;color:#555;">' . esc_html($category) . '</h4>';
            echo '<table class="brother-specs-table">';
            
            foreach ($items as $label => $value) {
                echo '<tr>';
                echo '<th>' . esc_html($label) . '</th>';
                echo '<td>' . wp_kses_post($value) . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
        }
        
        echo '</div>';
    }

    public function render_support_tab() {
        global $post;
        
        $support = get_post_meta($post->ID, self::META_SUPPORT, true);
        
        if (empty($support)) {
            echo '<div class="brother-tabs-content"><p>Destek bilgisi bulunmamaktadır.</p></div>';
            return;
        }
        
        $settings = get_option(self::OPT_SETTINGS, array());
        
        $marker = isset($settings['marker_symbol']) ? $settings['marker_symbol'] : '✓';
        $marker_color = isset($settings['marker_color']) ? $settings['marker_color'] : '#27ae60';
        $text_color = isset($settings['text_color']) ? $settings['text_color'] : '#333333';
        $strong_color = isset($settings['strong_color']) ? $settings['strong_color'] : '#2c3e50';
        $font_family = isset($settings['font_family']) ? $settings['font_family'] : 'Arial, sans-serif';
        $font_size = isset($settings['font_size']) ? $settings['font_size'] : '14px';
        $line_height = isset($settings['line_height']) ? $settings['line_height'] : '1.6';
        
        echo '<style>
        .brother-support-content {
            font-family: ' . esc_attr($font_family) . ';
            font-size: ' . esc_attr($font_size) . ';
            line-height: ' . esc_attr($line_height) . ';
            color: ' . esc_attr($text_color) . ';
        }
        .brother-support-content ul li:before {
            content: "' . esc_attr($marker) . '";
            color: ' . esc_attr($marker_color) . ';
        }
        .brother-support-content strong {
            color: ' . esc_attr($strong_color) . ';
        }
        </style>';
        
        echo '<div class="brother-tabs-content">';
        echo '<h3>Destek ve Özellikler</h3>';
        echo '<div class="brother-support-content">';
        echo wp_kses_post($support);
        echo '</div>';
        echo '</div>';
    }

    public function render_supplies_tab() {
        global $post;
        
        $supplies_json = get_post_meta($post->ID, self::META_SUPPLIES, true);
        $supplies = json_decode($supplies_json, true);
        
        if (empty($supplies)) {
            echo '<div class="brother-tabs-content">';
            echo '<div class="no-supplies-message">Bu ürün için sarf malzemesi bilgisi bulunmamaktadır.</div>';
            echo '</div>';
            return;
        }
        
        echo '<div class="brother-tabs-content">';
        echo $this->render_supplies_html($supplies, $post->ID);
        echo '</div>';
    }

    public function render_accessories_tab() {
        global $post;
        
        $accessories_json = get_post_meta($post->ID, self::META_ACCESSORIES, true);
        $accessories = json_decode($accessories_json, true);
        
        if (empty($accessories)) {
            echo '<div class="brother-tabs-content">';
            echo '<div class="no-accessories-message">Bu ürün için aksesuar bilgisi bulunmamaktadır.</div>';
            echo '</div>';
            return;
        }
        
        echo '<div class="brother-tabs-content">';
        echo $this->render_accessories_html($accessories, $post->ID);
        echo '</div>';
    }

    private function categorize_specifications($specs) {
        $categories = array(
            'Genel Özellikler' => array(),
            'Yazdırma' => array(),
            'Tarama' => array(),
            'Kopyalama' => array(),
            'Faks' => array(),
            'Kağıt Yönetimi' => array(),
            'Bağlantı' => array(),
            'Fiziksel Özellikler' => array(),
            'Diğer' => array()
        );
        
        $keywords = array(
            'Yazdırma' => array('yazdırma', 'print', 'hız', 'speed', 'çözünürlük', 'resolution', 'dpi'),
            'Tarama' => array('tarama', 'scan', 'tarayıcı', 'scanner', 'adf', 'otomatik'),
            'Kopyalama' => array('kopyalama', 'copy', 'fotokopi'),
            'Faks' => array('faks', 'fax', 'modem'),
            'Kağıt Yönetimi' => array('kağıt', 'paper', 'tepsi', 'tray', 'kapasite', 'capacity'),
            'Bağlantı' => array('bağlantı', 'connection', 'usb', 'ethernet', 'wi-fi', 'wifi', 'network', 'ağ'),
            'Fiziksel Özellikler' => array('boyut', 'size', 'ağırlık', 'weight', 'ebat', 'dimension')
        );
        
        // Parse HTML specs
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $specs);
        $xp = new DOMXPath($doc);
        
        // Tablo satırlarından veri al
        $rows = $xp->query('//tr');
        foreach ($rows as $row) {
            $cols = $xp->query('.//td', $row);
            if ($cols->length >= 2) {
                $label = trim($cols->item(0)->textContent);
                $value = trim($cols->item(1)->textContent);
                
                if ($label && $value) {
                    $assigned = false;
                    $label_lower = mb_strtolower($label, 'UTF-8');
                    
                    foreach ($keywords as $category => $category_keywords) {
                        foreach ($category_keywords as $keyword) {
                            if (strpos($label_lower, $keyword) !== false) {
                                $categories[$category][$label] = $value;
                                $assigned = true;
                                break 2;
                            }
                        }
                    }
                    
                    if (!$assigned) {
                        $categories['Diğer'][$label] = $value;
                    }
                }
            }
        }
        
        // Boş kategorileri kaldır
        foreach ($categories as $category => $items) {
            if (empty($items)) {
                unset($categories[$category]);
            }
        }
        
        return $categories;
    }

    // ============================================
    // STATIC METODLAR
    // ============================================
    public static function ensure_brand_attribute_static() {
        if (!taxonomy_exists('pa_brand')) {
            $args = array(
                'slug' => 'brand',
                'name' => 'Marka',
                'type' => 'text',
                'order_by' => 'menu_order',
                'has_archives' => false
            );
            
            wc_create_attribute($args);
            
            register_taxonomy(
                'pa_brand',
                'product',
                array(
                    'labels' => array(
                        'name' => 'Markalar',
                        'singular_name' => 'Marka'
                    ),
                    'hierarchical' => false,
                    'show_ui' => true,
                    'query_var' => true,
                    'rewrite' => array('slug' => 'brand')
                )
            );
            
            $term_exists = term_exists('Brother', 'pa_brand');
            if (!$term_exists) {
                wp_insert_term('Brother', 'pa_brand');
            }
        }
    }

    public function ensure_all_product_attributes() {
        $mapping = $this->attribute_handler->get_attribute_mapping();
        $created = 0;
        $existing = 0;
        
        foreach ($mapping as $label => $slug) {
            $taxonomy = 'pa_' . $slug;
            
            if (!taxonomy_exists($taxonomy)) {
                $args = array(
                    'slug' => $slug,
                    'name' => $label,
                    'type' => 'text',
                    'order_by' => 'menu_order',
                    'has_archives' => false
                );
                
                $result = wc_create_attribute($args);
                
                if ($result && !is_wp_error($result)) {
                    $created++;
                    $this->logger->log('Attribute oluşturuldu: ' . $label . ' (' . $slug . ')', BDI_Logger::LEVEL_INFO);
                    
                    register_taxonomy(
                        $taxonomy,
                        'product',
                        array(
                            'labels' => array(
                                'name' => $label,
                                'singular_name' => $label
                            ),
                            'hierarchical' => false,
                            'show_ui' => true,
                            'query_var' => true,
                            'rewrite' => array('slug' => $slug)
                        )
                    );
                }
            } else {
                $existing++;
            }
        }
        
        delete_transient('wc_attribute_taxonomies');
        
        return array(
            'created' => $created,
            'existing' => $existing
        );
    }

    public function batch_create_attributes_from_specs($limit = 50) {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $limit,
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => self::META_SPECS,
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => '_bdi_attributes_created',
                    'compare' => 'NOT EXISTS'
                )
            ),
            'fields' => 'ids'
        );
        
        $products = get_posts($args);
        $processed = 0;
        
        foreach ($products as $product_id) {
            $this->create_attributes_from_specs($product_id);
            update_post_meta($product_id, '_bdi_attributes_created', current_time('mysql'));
            $processed++;
        }
        
        return array(
            'processed' => $processed,
            'remaining' => $this->get_products_without_attributes_count()
        );
    }

    private function get_products_without_attributes_count() {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => self::META_SPECS,
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => '_bdi_attributes_created',
                    'compare' => 'NOT EXISTS'
                )
            ),
            'fields' => 'ids'
        );
        
        $query = new WP_Query($args);
        return $query->found_posts;
    }

    public function __destruct() {
        $this->remove_image_size_filters();
    }
}
endif;

<?php
// ============================================
// YARDIMCI SINIFLAR - DEVAM
// ============================================

// ============================================
// CACHE MANAGER SINIFI
// ============================================
if (!class_exists('BDI_Cache_Manager')) :
class BDI_Cache_Manager {
    
    private $cache_prefix = 'bdi_cache_';
    private $cache_group = 'bdi_importer';
    
    public function get($key) {
        if (wp_using_ext_object_cache()) {
            return wp_cache_get($this->cache_prefix . $key, $this->cache_group);
        } else {
            return get_transient($this->cache_prefix . $key);
        }
    }
    
    public function set($key, $value, $expiration = 3600) {
        if (wp_using_ext_object_cache()) {
            return wp_cache_set($this->cache_prefix . $key, $value, $this->cache_group, $expiration);
        } else {
            return set_transient($this->cache_prefix . $key, $value, $expiration);
        }
    }
    
    public function delete($key) {
        if (wp_using_ext_object_cache()) {
            return wp_cache_delete($this->cache_prefix . $key, $this->cache_group);
        } else {
            return delete_transient($this->cache_prefix . $key);
        }
    }
    
    public function clear() {
        global $wpdb;
        
        if (wp_using_ext_object_cache()) {
            wp_cache_flush();
        } else {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_' . $this->cache_prefix . '%'
                )
            );
            
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_timeout_' . $this->cache_prefix . '%'
                )
            );
        }
    }
}
endif;

// ============================================
// RATE LIMITER SINIFI
// ============================================
if (!class_exists('BDI_Rate_Limiter')) :
class BDI_Rate_Limiter {
    
    private $cache_manager;
    
    public function __construct($cache_manager) {
        $this->cache_manager = $cache_manager;
    }
    
    public function check($key, $max_requests = 10, $window_seconds = 60) {
        $rate_key = 'rate_limit_' . $key;
        $current = $this->cache_manager->get($rate_key);
        
        if ($current === false) {
            $current = 0;
        }
        
        if ($current >= $max_requests) {
            return false;
        }
        
        $this->cache_manager->set($rate_key, $current + 1, $window_seconds);
        return true;
    }
    
    public function reset($key) {
        $rate_key = 'rate_limit_' . $key;
        $this->cache_manager->delete($rate_key);
    }
}
endif;

// ============================================
// LOGGER SINIFI
// ============================================
if (!class_exists('BDI_Logger')) :
class BDI_Logger {
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';
    
    private $log_option = 'bdi_logs';
    private $max_logs = 500;
    private $log_file_enabled = false;
    private $log_file_path;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file_path = $upload_dir['basedir'] . '/bdi-logs/';
        
        if (!file_exists($this->log_file_path)) {
            wp_mkdir_p($this->log_file_path);
        }
    }
    
    public function log($message, $level = self::LEVEL_INFO, $context = array()) {
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        $message = wp_strip_all_tags((string)$message);
        
        $valid_levels = array(
            self::LEVEL_DEBUG,
            self::LEVEL_INFO,
            self::LEVEL_WARNING,
            self::LEVEL_ERROR,
            self::LEVEL_CRITICAL
        );
        
        if (!in_array($level, $valid_levels, true)) {
            $level = self::LEVEL_INFO;
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'message' => substr($message, 0, 1000),
            'context' => $context
        );
        
        $this->save_to_database($log_entry);
        
        if ($this->log_file_enabled) {
            $this->save_to_file($log_entry);
        }
    }
    
    private function save_to_database($entry) {
        $logs = get_option($this->log_option, array());
        
        if (!is_array($logs)) {
            $logs = array();
        }
        
        $logs[] = $entry;
        
        if (count($logs) > $this->max_logs) {
            $logs = array_slice($logs, -$this->max_logs);
        }
        
        update_option($this->log_option, $logs, false);
    }
    
    private function save_to_file($entry) {
        $filename = $this->log_file_path . 'bdi-' . date('Y-m-d') . '.log';
        $log_line = sprintf(
            "[%s] [%s] %s %s\n",
            $entry['timestamp'],
            strtoupper($entry['level']),
            $entry['message'],
            !empty($entry['context']) ? json_encode($entry['context']) : ''
        );
        
        if (file_exists($filename) && filesize($filename) > 5 * 1024 * 1024) {
            rename($filename, $filename . '.' . time() . '.old');
        }
        
        error_log($log_line, 3, $filename);
    }
    
    public function get_logs($limit = 200, $level = null) {
        $logs = get_option($this->log_option, array());
        
        if (!is_array($logs)) {
            return array();
        }
        
        if ($level) {
            $logs = array_filter($logs, function($log) use ($level) {
                return isset($log['level']) && $log['level'] === $level;
            });
        }
        
        return array_slice(array_reverse($logs), 0, $limit);
    }
    
    public function clear_logs() {
        update_option($this->log_option, array());
        
        if ($this->log_file_enabled) {
            $files = glob($this->log_file_path . '*.log*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
endif;

// ============================================
// KATEGORİ IMPORTER SINIFI
// ============================================
if (!class_exists('BDI_Category_Importer')) :
class BDI_Category_Importer {
    
    private $base_url = 'https://www.brother.com.tr';
    private $cache_manager;
    private $logger;
    
    private $category_structure = array(
        'yazicilar' => array(
            'name' => 'Yazıcılar ve Çok Fonksiyonlu Yazıcılar',
            'url' => '/printers/all-printers',
            'children' => array(
                array('name' => 'Renkli Mürekkep Püskürtmeli Yazıcılar', 'slug' => 'inkjet-printers'),
                array('name' => 'Siyah-Beyaz Lazer Yazıcılar', 'slug' => 'mono-laser-printers'),
                array('name' => 'Renkli Lazer Yazıcılar', 'slug' => 'color-laser-printers'),
                array('name' => 'A3 Yazıcılar', 'slug' => 'a3-printers'),
                array('name' => 'Ev için Yazıcılar', 'slug' => 'home-printers'),
                array('name' => 'İş için Yazıcılar', 'slug' => 'business-printers'),
                array('name' => 'Mürekkep Tanklı Yazıcılar', 'slug' => 'ink-tank-printers'),
                array('name' => 'Çok Fonksiyonlu Yazıcılar', 'slug' => 'multifunction-printers')
            )
        ),
        'tarayicilar' => array(
            'name' => 'Tarayıcılar',
            'url' => '/scanners/all-scanners',
            'children' => array(
                array('name' => 'Döküman Tarayıcılar', 'slug' => 'document-scanners'),
                array('name' => 'Mobil Tarayıcılar', 'slug' => 'mobile-scanners'),
                array('name' => 'Masaüstü Tarayıcılar', 'slug' => 'desktop-scanners')
            )
        ),
        'etiket-yazicilar' => array(
            'name' => 'Etiket Yazıcılar',
            'url' => '/labellers/all-labellers',
            'children' => array(
                array('name' => 'P-Touch Etiket Makineleri', 'slug' => 'p-touch-labellers'),
                array('name' => 'QL Profesyonel Etiket Yazıcılar', 'slug' => 'ql-labellers'),
                array('name' => 'TD Mobil Etiket Yazıcılar', 'slug' => 'td-labellers')
            )
        )
    );
    
    public function __construct($cache_manager, $logger) {
        $this->cache_manager = $cache_manager;
        $this->logger = $logger;
    }
    
    public function import_categories() {
        $created_count = 0;
        
        foreach ($this->category_structure as $parent_slug => $parent_data) {
            $parent_term = get_term_by('slug', $parent_slug, 'product_cat');
            
            if (!$parent_term) {
                $parent_result = wp_insert_term(
                    $parent_data['name'],
                    'product_cat',
                    array('slug' => $parent_slug)
                );
                
                if (!is_wp_error($parent_result)) {
                    $parent_id = $parent_result['term_id'];
                    $created_count++;
                    $this->logger->log('Kategori oluşturuldu: ' . $parent_data['name'], BDI_Logger::LEVEL_INFO);
                } else {
                    $this->logger->log('Kategori oluşturma hatası: ' . $parent_data['name'], BDI_Logger::LEVEL_ERROR);
                    continue;
                }
            } else {
                $parent_id = $parent_term->term_id;
            }
            
            if (!empty($parent_data['children'])) {
                foreach ($parent_data['children'] as $child_data) {
                    $child_term = get_term_by('slug', $child_data['slug'], 'product_cat');
                    
                    if (!$child_term) {
                        $child_result = wp_insert_term(
                            $child_data['name'],
                            'product_cat',
                            array(
                                'slug' => $child_data['slug'],
                                'parent' => $parent_id
                            )
                        );
                        
                        if (!is_wp_error($child_result)) {
                            $created_count++;
                            $this->logger->log('Alt kategori oluşturuldu: ' . $child_data['name'], BDI_Logger::LEVEL_INFO);
                        }
                    }
                }
            }
        }
        
        return $created_count;
    }
    
    public function match_product_to_categories($url, $title, $html) {
        $categories = array();
        
        $model_patterns = array(
            'mfc' => 'cok-fonksiyonlu-yazicilar',
            'dcp' => 'cok-fonksiyonlu-yazicilar',
            'hl' => 'lazer-yazicilar',
            'ads' => 'document-scanners',
            'pt' => 'p-touch-labellers',
            'ql' => 'ql-labellers',
            'td' => 'td-labellers',
            'pj' => 'mobile-printers',
            'rj' => 'mobile-printers'
        );
        
        foreach ($model_patterns as $prefix => $category_slug) {
            if (stripos($title, $prefix . '-') !== false || stripos($url, '/' . $prefix . '-') !== false) {
                $term = get_term_by('slug', $category_slug, 'product_cat');
                if ($term) {
                    $categories[] = $term->term_id;
                    
                    if ($term->parent) {
                        $categories[] = $term->parent;
                    }
                }
                break;
            }
        }
        
        return array_unique($categories);
    }
}
endif;

// ============================================
// ATTRIBUTE HANDLER SINIFI
// ============================================
if (!class_exists('BDI_Attribute_Handler')) :
class BDI_Attribute_Handler {
    
    private $logger;
    
    private $default_attribute_mapping = array(
        'Yazdırma Hızı' => 'print-speed',
        'Yazdırma Çözünürlüğü' => 'print-resolution',
        'Tarama Hızı' => 'scan-speed',
        'Tarama Çözünürlüğü' => 'scan-resolution',
        'Kopyalama Hızı' => 'copy-speed',
        'Faks Hızı' => 'fax-speed',
        'Kağıt Kapasitesi' => 'paper-capacity',
        'Kağıt Boyutu' => 'paper-size',
        'Bağlantı' => 'connection',
        'Ağırlık' => 'weight',
        'Boyutlar' => 'dimensions',
        'Güç Tüketimi' => 'power-consumption',
        'Ses Seviyesi' => 'noise-level',
        'Bellek' => 'memory',
        'İşlemci' => 'processor',
        'Ekran' => 'display',
        'Aylık Görev Döngüsü' => 'duty-cycle',
        'Önerilen Aylık Sayfa Hacmi' => 'recommended-volume'
    );
    
    public function __construct($logger) {
        $this->logger = $logger;
    }
    
    public function extract_product_attributes($html) {
        $attributes = array();
        
        if (empty($html)) return $attributes;
        
        try {
            $doc = new DOMDocument();
            libxml_use_internal_errors(true);
            @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
            $xpath = new DOMXPath($doc);
            
            $spec_tables = $xpath->query('//table[contains(@class, "spec") or contains(@class, "feature")]');
            
            foreach ($spec_tables as $table) {
                $rows = $xpath->query('.//tr', $table);
                
                foreach ($rows as $row) {
                    $cells = $xpath->query('.//td', $row);
                    
                    if ($cells->length >= 2) {
                        $label = trim($cells->item(0)->textContent);
                        $value = trim($cells->item(1)->textContent);
                        
                        if ($label && $value && $value !== '-' && $value !== 'N/A') {
                            $attributes[$label] = $value;
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->logger->log('Attribute çıkarma hatası: ' . $e->getMessage(), BDI_Logger::LEVEL_ERROR);
        }
        
        return $attributes;
    }
    
    public function create_wc_attributes($attributes, $product_id) {
        if (empty($attributes) || !$product_id) return false;
        
        $product = wc_get_product($product_id);
        if (!$product) return false;
        
        $product_attributes = array();
        $mapping = $this->get_attribute_mapping();
        
        foreach ($attributes as $label => $value) {
            $slug = isset($mapping[$label]) ? $mapping[$label] : sanitize_title($label);
            $taxonomy = 'pa_' . $slug;
            
            if (!taxonomy_exists($taxonomy)) {
                $this->create_product_attribute($label, $slug);
            }
            
            $term = term_exists($value, $taxonomy);
            
            if (!$term) {
                $term = wp_insert_term($value, $taxonomy);
            }
            
            if (!is_wp_error($term)) {
                $term_id = is_array($term) ? $term['term_id'] : $term;
                
                $product_attributes[$taxonomy] = array(
                    'name' => $taxonomy,
                    'value' => '',
                    'position' => 0,
                    'is_visible' => 1,
                    'is_variation' => 0,
                    'is_taxonomy' => 1
                );
                
                wp_set_object_terms($product_id, $term_id, $taxonomy, true);
            }
        }
        
        if (!empty($product_attributes)) {
            update_post_meta($product_id, '_product_attributes', $product_attributes);
            $this->logger->log('Attribute eklendi: ' . count($product_attributes) . ' adet (Ürün #' . $product_id . ')', BDI_Logger::LEVEL_INFO);
            return true;
        }
        
        return false;
    }
    
    private function create_product_attribute($label, $slug) {
        $args = array(
            'slug' => $slug,
            'name' => $label,
            'type' => 'text',
            'order_by' => 'menu_order',
            'has_archives' => false
        );
        
        $result = wc_create_attribute($args);
        
        if ($result && !is_wp_error($result)) {
            register_taxonomy(
                'pa_' . $slug,
                'product',
                array(
                    'labels' => array(
                        'name' => $label,
                        'singular_name' => $label
                    ),
                    'hierarchical' => false,
                    'show_ui' => true,
                    'query_var' => true,
                    'rewrite' => array('slug' => $slug)
                )
            );
            
            $this->logger->log('Attribute oluşturuldu: ' . $label, BDI_Logger::LEVEL_INFO);
        }
    }
    
    public function add_product_attributes($product_id, $attributes) {
        if (empty($attributes) || !$product_id) return false;
        
        try {
            $product = wc_get_product($product_id);
            if (!$product) return false;
            
            $existing_attributes = $product->get_attributes();
            $mapping = $this->get_attribute_mapping();
            
            foreach ($attributes as $label => $value) {
                if (empty($value)) continue;
                
                $slug = isset($mapping[$label]) ? $mapping[$label] : sanitize_title($label);
                $taxonomy = 'pa_' . $slug;
                
                if (!taxonomy_exists($taxonomy)) {
                    $this->create_product_attribute($label, $slug);
                }
                
                $this->ensure_attribute_term($taxonomy, $value);
                
                $attribute = new WC_Product_Attribute();
                $attribute->set_id(wc_attribute_taxonomy_id_by_name($taxonomy));
                $attribute->set_name($taxonomy);
                $attribute->set_options(array($value));
                $attribute->set_position(count($existing_attributes));
                $attribute->set_visible(true);
                $attribute->set_variation(false);
                
                $existing_attributes[$taxonomy] = $attribute;
            }
            
            $product->set_attributes($existing_attributes);
            $product->save();
            
            $this->logger->log(
                count($attributes) . ' attribute eklendi (Ürün #' . $product_id . ')',
                BDI_Logger::LEVEL_INFO
            );
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->log('Attribute ekleme hatası: ' . $e->getMessage(), BDI_Logger::LEVEL_ERROR);
        }
        
        return false;
    }
    
    public function get_attribute_mapping() {
        $custom_mapping = get_option('bdi_attribute_mapping', array());
        
        if (empty($custom_mapping) || !is_array($custom_mapping)) {
            return $this->default_attribute_mapping;
        }
        
        return array_merge($this->default_attribute_mapping, $custom_mapping);
    }
    
    public function get_default_mapping() {
        return $this->default_attribute_mapping;
    }
    
    private function ensure_attribute_term($taxonomy, $value) {
        if (!taxonomy_exists($taxonomy)) {
            return false;
        }
        
        $term = term_exists($value, $taxonomy);
        
        if (!$term) {
            $term = wp_insert_term(
                $value,
                $taxonomy,
                array('slug' => sanitize_title($value))
            );
        }
        
        return $term;
    }
    
    private function clear_attribute_cache($taxonomies) {
        if (empty($taxonomies)) {
            return;
        }
        
        delete_transient('wc_attribute_taxonomies');
        
        if (class_exists('WC_Cache_Helper')) {
            WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');
        }
    }
    
    public function get_all_attributes() {
        return $this->get_attribute_mapping();
    }
}
endif;

// ============================================
// GLOBAL CALLBACK FONKSİYONLARI
// ============================================

function bdi_render_specs_tab() {
    global $post;
    if (!isset($GLOBALS['bdi_pro_enhanced'])) {
        echo '<p>Brother Importer not initialized</p>';
        return;
    }
    $GLOBALS['bdi_pro_enhanced']->render_specs_tab();
}

function bdi_render_support_tab() {
    global $post;
    if (!isset($GLOBALS['bdi_pro_enhanced'])) {
        echo '<p>Brother Importer not initialized</p>';
        return;
    }
    $GLOBALS['bdi_pro_enhanced']->render_support_tab();
}

function bdi_render_supplies_tab() {
    global $post;
    if (!isset($GLOBALS['bdi_pro_enhanced'])) {
        echo '<p>Brother Importer not initialized</p>';
        return;
    }
    $GLOBALS['bdi_pro_enhanced']->render_supplies_tab();
}

function bdi_render_accessories_tab() {
    global $post;
    if (!isset($GLOBALS['bdi_pro_enhanced'])) {
        echo '<p>Brother Importer not initialized</p>';
        return;
    }
    $GLOBALS['bdi_pro_enhanced']->render_accessories_tab();
}

// ============================================
// PLUGIN AKTİVASYON VE İNİT
// ============================================

register_activation_hook(__FILE__, array('BDI_Pro_Enhanced', 'activate'));
register_deactivation_hook(__FILE__, array('BDI_Pro_Enhanced', 'deactivate'));

add_action('init', function() {
    $GLOBALS['bdi_pro_enhanced'] = new BDI_Pro_Enhanced();
});

// Plugin tamam!