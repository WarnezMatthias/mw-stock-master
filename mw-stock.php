<?php

/**
 * Plugin Name: Stock
 * Plugin URI:
 * Description: Creates a extra tab in my account for changing the stock of products.
 * Version: 1.0
 * Author: Matthias Warnez
 * Author URI:
 */

if (!defined('ABSPATH')) {
	die;
}

include(plugin_dir_path(__FILE__) . 'includes/stock.php');

if ((string) get_option('my_licence_key_for_git_hub') !== '') {
	include_once(plugin_dir_path(__FILE__) . 'git-updater.php');
	$updater = new PDUpdater(__FILE__);
	$updater->set_username('mwarnez');
	$updater->set_repository('mw-stock-master');
	$updater->authorize(get_option('my_licence_key_for_git_hub'));
	$updater->initialize();
}


/** Include sub-file*/
class StockPlugin {
	protected static $_instance = null;

	public static function instance() {
		if (is_null(self::$_instance)) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}


	function on_activate() {
		add_role(
			'mw_stock', //  System name of the role.
			__('Bepaal vooraad'), // Display name of the role.
			array()
		);

		if (!get_option('mw_stock_flush_rewrite_rules_flag')) {
			add_option('mw_stock_flush_rewrite_rules_flag', true);
		}
	}

	function on_deactivate() {
		flush_rewrite_rules(); // for permalinks
	}

	function plugin_url() {
		return untrailingslashit(plugins_url('/', __FILE__));
	}
}

if (class_exists('StockPlugin')) {
	$stockPlugin = new StockPlugin();
}

function stockPlugin() {
	return StockPlugin::instance();
}


/**activation and deactivation hook*/
register_activation_hook(__File__, array($stockPlugin, 'on_activate'));
register_deactivation_hook(__File__, array($stockPlugin, 'on_deactivate'));
