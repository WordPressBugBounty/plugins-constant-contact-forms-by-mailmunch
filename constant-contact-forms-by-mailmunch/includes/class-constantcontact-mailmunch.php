<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://www.mailmunch.com
 * @since      2.0.0
 *
 * @package    Constantcontact_Mailmunch
 * @subpackage Constantcontact_Mailmunch/includes
 */

// Define some class constants.
define( 'CONSTANTCONTACT_MAILMUNCH_URL', "http://wordpress.mailmunch.com" );
define( 'CONSTANTCONTACT_MAILMUNCH_URL_SECURED', "https://wordpress.mailmunch.com" );
define( 'CONSTANTCONTACT_MAILMUNCH_HOME_URL', "https://app.mailmunch.com" );
define( 'CONSTANTCONTACT_MAILMUNCH_LANDING_PAGE_URL', "https://wordpress.mailmunch.com" );
define( 'CONSTANTCONTACT_MAILMUNCH_PAGE_SERVICE_URL', "http://wordpress.page.co" );
define( 'CONSTANTCONTACT_MAILMUNCH_SLUG', "constantcontact-mailmunch" );
define( 'CONSTANTCONTACT_MAILMUNCH_PREFIX', 'cc_mm' );
define( 'CONSTANTCONTACT_MAILMUNCH_POST_TYPE', 'mailmunch_page' );
define( 'CONSTANTCONTACT_MAILMUNCH_PLUGIN_DIRECTORY', 'constant-contact-forms-by-mailmunch' );
define( 'CONSTANTCONTACT_MAILMUNCH_VERSION', '2.1.2' );

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      2.0.0
 * @package    Constantcontact_Mailmunch
 * @subpackage Constantcontact_Mailmunch/includes
 * @author     MailMunch <info@mailmunch.com>
 */
class Constantcontact_Mailmunch {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      Constantcontact_Mailmunch_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	* The unique identifier for the plugin's intended 3rd party integration
	*
	* @since    2.0.0
	* @access   protected
	* @var      string    $integration_name    The string used to uniquely identify the integration.
	*/
	protected $integration_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * The MailMunch api.
	 *
	 * @since    2.0.0
	 * @access   protected
	 * @var      string    $mailmunch_api    MailMunch API
	 */
	protected $mailmunch_api;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    2.0.0
	 */
	public function __construct() {

		$this->plugin_name = 'Constant Contact Forms by MailMunch';
		$this->integration_name = 'Constant Contact';
		$this->version = '2.0.1';

		$this->load_dependencies();
		$this->set_locale();
		if (is_admin()) {
			$this->define_admin_hooks();
		}
		$this->define_public_hooks();
		$landingPagesEnabled = get_option(CONSTANTCONTACT_MAILMUNCH_PREFIX. '_landing_pages_enabled');
		if (empty($landingPagesEnabled) || $landingPagesEnabled == 'yes') {
			$this->define_post_type();
		}
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Constantcontact_Mailmunch_Loader. Orchestrates the hooks of the plugin.
	 * - Constantcontact_Mailmunch_i18n. Defines internationalization functionality.
	 * - Constantcontact_Mailmunch_Admin. Defines all hooks for the admin area.
	 * - Constantcontact_Mailmunch_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for communicating with MailMunch's Public API
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-mailmunch-api.php';

		/**
		 * The class responsible for adding the sidebar widget
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-constantcontact-mailmunch-sidebar-widget.php';

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-constantcontact-mailmunch-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-constantcontact-mailmunch-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-constantcontact-mailmunch-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-constantcontact-mailmunch-public.php';
		
		/**
		* The class responsible for defining landing page post type
		*/
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-constantcontact-mailmunch-post-type.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-constantcontact-mailmunch-rewrite.php';

		$this->loader = new Constantcontact_Mailmunch_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Constantcontact_Mailmunch_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Constantcontact_Mailmunch_i18n();
		$plugin_i18n->set_domain( $this->get_plugin_name() );

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Constantcontact_Mailmunch_Admin( $this->get_plugin_name(), $this->get_integration_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'menu' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'activation_redirect' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'check_installation_date' );

		// Review Notice
		$this->loader->add_action( 'admin_init', $plugin_admin, 'dismiss_review_notice' );
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'review_us_notice' );

		// Ajax calls
		$this->loader->add_action( 'wp_ajax_sign_up', $plugin_admin, 'sign_up' );
		$this->loader->add_action( 'wp_ajax_sign_in', $plugin_admin, 'sign_in' );
		$this->loader->add_action( 'wp_ajax_delete_widget', $plugin_admin, 'delete_widget' );
		$this->loader->add_action( 'wp_ajax_delete_email', $plugin_admin, 'delete_email' );
		$this->loader->add_action( 'wp_ajax_change_email_status', $plugin_admin, 'change_email_status' );
		
		// Dashboard
		$this->loader->add_action( 'wp_dashboard_setup', $plugin_admin, 'dashboard_setup' );

		// Settings link
		$pluginBaseName = plugin_basename(__FILE__);
		$exploded = explode('/', $pluginBaseName);
		$pluginFilePath = $exploded[0]. '/constantcontact-mailmunch.php';
		$this->loader->add_filter( 'plugin_action_links_'. $pluginFilePath, $plugin_admin, 'settings_link');

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Constantcontact_Mailmunch_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_action( 'wp_head', $plugin_public, 'append_head' );

		$autoEmbed = get_option(CONSTANTCONTACT_MAILMUNCH_PREFIX. '_auto_embed');
		if (empty($autoEmbed) || $autoEmbed == 'yes') {
			$this->loader->add_filter( 'the_content', $plugin_public, 'add_post_containers' );
		}

		// Sidebar widget
		$this->loader->add_action( 'widgets_init', $plugin_public, 'sidebar_widget' );
	}
	
	/**
	 * Register custom post type for Landing Pages
	 *
	 * @since    3.0.0
	 * @access   private
	 */
	private function define_post_type() {
		// Landing Page
		$plugin_post_type = new Constantcontact_Mailmunch_Post_Type();
		$this->loader->add_action('init', $plugin_post_type, 'create_post_type');
		$this->loader->add_filter('template_include', $plugin_post_type, 'post_type_template');
		$this->loader->add_filter('get_pages', $plugin_post_type, 'add_pages_to_dropdown', 10, 2);
		$this->loader->add_filter('views_edit-'. CONSTANTCONTACT_MAILMUNCH_POST_TYPE, $plugin_post_type, 'post_type_desc');
		$this->loader->add_action('add_meta_boxes', $plugin_post_type, 'post_meta_box', 10, 2);
		$this->loader->add_action('save_post', $plugin_post_type, 'landing_page_save_meta', 10, 2 );
		
		$plugin_rewrite = new Constantcontact_Mailmunch_Rewrite();
		$this->loader->add_action('init', $plugin_rewrite, 'init');
		$this->loader->add_action('save_post', $plugin_rewrite, 'on_save_post');
		$this->loader->add_action('wp_insert_post', $plugin_rewrite, 'on_wp_insert_post');
		$this->loader->add_action('pre_get_posts', $plugin_rewrite, 'enable_front_page_landing_pages', 11);
		$this->loader->add_filter('post_type_link', $plugin_rewrite, 'on_post_type_link');
		$this->loader->add_filter('wp_unique_post_slug', $plugin_rewrite, 'on_wp_unique_post_slug', 10, 6);
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    2.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     2.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The name of the 3rd party integration
	 * e.g. Constant Contact, Constant Contact, etc.
	 *
	 * @since     2.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_integration_name() {
		return $this->integration_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     2.0.0
	 * @return    Constantcontact_Mailmunch_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     2.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
