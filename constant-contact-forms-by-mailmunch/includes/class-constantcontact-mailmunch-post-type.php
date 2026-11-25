<?php
class Constantcontact_Mailmunch_Post_Type {
  /**
  * Register custom post type for Landing Pages
  *
  * @since    3.0.0
  */
  
  public function initiate_api() {
		if (empty($this->mailmunch_api)) {
			$this->mailmunch_api = new ConstantContact_Mailmunch_Api();
		}
		return $this->mailmunch_api;
	}
  
  public function create_post_type() {
    // Register Custom Post Type
    $labels = array(
        'name'               => __( 'Landing Pages by MailMunch'),
        'singular_name'      => __( 'MailMunch Page' ),
        'menu_name'          => __( 'Landing Pages' ),
        'name_admin_bar'     => __( 'Landing Page' ),
        'add_new'            => __( 'Add New Page' ),
        'add_new_item'       => __( 'Add New Page' ),
        'new_item'           => __( 'New Page' ),
        'edit_item'          => __( 'Edit Page' ),
        'view_item'          => __( 'View Page' ),
        'all_items'          => __( 'All Pages' ),
        'search_items'       => __( 'Search Pages' ),
        'parent_item_colon'  => __( 'Parent Pages:' ),
        'not_found'          => __( 'No pages found.' ),
        'not_found_in_trash' => __( 'No pages found in Trash.' )
    );

    $args = array(
        'labels'             => $labels,
        'menu_icon'          => 'dashicons-admin-page',
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array('with_front' => false, 'slug' => CONSTANTCONTACT_MAILMUNCH_POST_TYPE ),
        // MM-3766: only administrators (manage_options) may see, create or edit landing pages.
        // Ordinary post permissions let Contributors and Authors in, and the page shows raw
        // MailMunch HTML, which WordPress only trusts administrators with.
        'capability_type'    => array( 'constantcontact_mailmunch_landing_page', 'constantcontact_mailmunch_landing_pages' ),
        'map_meta_cap'       => false,
        'capabilities'       => array(
            'edit_post'              => 'manage_options',
            'read_post'              => 'read',
            'delete_post'            => 'manage_options',
            'edit_posts'             => 'manage_options',
            'edit_others_posts'      => 'manage_options',
            'edit_private_posts'     => 'manage_options',
            'edit_published_posts'   => 'manage_options',
            'publish_posts'          => 'manage_options',
            'read_private_posts'     => 'manage_options',
            'delete_posts'           => 'manage_options',
            'delete_private_posts'   => 'manage_options',
            'delete_published_posts' => 'manage_options',
            'delete_others_posts'    => 'manage_options',
            'create_posts'           => 'manage_options',
        ),
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 5,
        'supports'           => array( 'title', 'author' )
    );
    register_post_type( CONSTANTCONTACT_MAILMUNCH_POST_TYPE, $args );
  }
  
  /**
  * Template
  */
  public function post_type_template( $template ) {
    global $post;
    if (empty($post)) return $template;
    if ($post->post_type == CONSTANTCONTACT_MAILMUNCH_POST_TYPE) {
      return plugin_dir_path( dirname( __FILE__ ) ) . 'public/constantcontact-mailmunch-landing-page.php';
    }
    return $template;
  }
  
  public function post_type_desc( $views ) {
    $currentStep = 'landingpages';
    require_once(plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/constantcontact-mailmunch-tabs.php');
    return $views;
  }

  public function post_meta_box($postType, $post) {
      if ($postType == CONSTANTCONTACT_MAILMUNCH_POST_TYPE) {
          add_meta_box(
              'constantcontact_mailmunch_meta_choose_page',
              'Choose or Create New Page',
              array($this, 'get_landing_pages_dropdown'),
              CONSTANTCONTACT_MAILMUNCH_POST_TYPE,
              'normal',
              'high'
          );
      }
  }
  
  public function get_landing_pages_dropdown($post) {
    if (!current_user_can('manage_options')) { return; }
    $this->initiate_api();
    $landing_pages = $this->mailmunch_api->getLandingPages();
    if (is_wp_error($landing_pages) || !is_array($landing_pages)) {
      echo '<p>' . esc_html__('Unable to load your landing pages from MailMunch. Please try again later.') . '</p>';
      return;
    }
    $html = '<input type="hidden" name="mailmunch_landing_page_noncename" value="' . wp_create_nonce( plugin_basename(__FILE__) ) . '" />';
    $key = '_mailmunch_landing_page_id';
    $selected_landing_page_id = get_post_meta( $post->ID, $key, true );
    $selected_landing_page_id = intval($selected_landing_page_id);
    if (!$selected_landing_page_id && !empty($landing_pages)) $selected_landing_page_id = intval($landing_pages[0]->id);
    $site_path = "/sites/". intval(get_option(CONSTANTCONTACT_MAILMUNCH_PREFIX."_site_id")). "/landing_pages";
    $html .= '<p>Create or choose the landing page that you want to activate here.</p>';
    $html .= '<input id="mailmunch_landing_page_id" name="mailmunch_landing_page_id" type="hidden" value="'. esc_attr($selected_landing_page_id) .'" />';
    foreach ($landing_pages as $key => $landing_page) {
      $landing_page_id = intval($landing_page->id);
      $html .= '<div class="mailmunch-landing-page'. ($selected_landing_page_id == $landing_page_id ? ' active' : '') .'" data-landing-page-id="'. esc_attr($landing_page_id) .'">';
      $html .= '<div class="page-image">';
      $html .= '<img src="'. esc_url($landing_page->preview_url) .'" />';
      $html .= '<div class="page-hover">';
      // MM-3766: no MailMunch token in the page; the link asks for a one-use sign-in link when clicked.
      $html .= '<a href="'. esc_url(ConstantContact_Mailmunch_Api::ssoAdminUrl($site_path. "/". $landing_page_id ."/edit")) .'" target="_blank" class="mailmunch-edit-page button button-primary button-large">Edit Page</a>';
      $html .= '</div>';
      $html .= '</div>';
      $html .= '<div class="page-name">'. esc_html($landing_page->name ? $landing_page->name : "Untitled") .'</div>';
      $html .= '</div>';
    }
    $html .= '<a href="'. esc_url(ConstantContact_Mailmunch_Api::ssoAdminUrl($site_path. "/new")) .'" class="mailmunch-landing-page new-landing-page" target="_blank">';
    $html .= '<div class="page-image">';
    $html .= '<span class="dashicons dashicons-plus-alt"></span>';
    $html .= '<div class="new-page-title">Create New Page</div>';
    $html .= '</div>';
    $html .= '<div class="page-name">&nbsp;</div>';
    $html .= '</a>';
    
    echo $html;
  }
  
  public function landing_page_save_meta($post_id, $post) {
    /*
     * We need to verify this came from our screen and with proper authorization,
     * because the save_post action can be triggered at other times.
     */

    if ( ! isset( $_POST['mailmunch_landing_page_noncename'] ) ) { // Check if our nonce is set.
      return;
    }

    // verify this came from the our screen and with proper authorization,
    // because save_post can be triggered at other times
    if( !wp_verify_nonce( $_POST['mailmunch_landing_page_noncename'], plugin_basename(__FILE__) ) ) {
      return $post->ID;
    }

    // MM-3766: landing pages are for administrators only.
    if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $post->ID ) ) {
        return $post->ID;
    }

    $key = '_mailmunch_landing_page_id';
    $value = isset( $_POST['mailmunch_landing_page_id'] ) ? absint( $_POST['mailmunch_landing_page_id'] ) : 0;
    if( !$value ) { // delete if blank
      delete_post_meta( $post->ID, $key );
      return;
    }

    // MM-3766: only a landing page of the connected MailMunch site may be shown here.
    $this->initiate_api();
    $allowed_ids = $this->mailmunch_api->getLandingPageIds();
    if ( is_wp_error( $allowed_ids ) || ! in_array( $value, $allowed_ids, true ) ) {
      set_transient( CONSTANTCONTACT_MAILMUNCH_PREFIX . '_landing_page_refused_' . get_current_user_id(), is_wp_error( $allowed_ids ) ? 'unavailable' : 'not_yours', 60 );
      return $post->ID;
    }

    update_post_meta( $post->ID, $key, $value );
  }

  /**
   * MM-3766: says why a landing page choice was not saved.
   */
  public function landing_page_refused_notice() {
    $transient = CONSTANTCONTACT_MAILMUNCH_PREFIX . '_landing_page_refused_' . get_current_user_id();
    $reason = get_transient( $transient );
    if ( ! $reason ) { return; }
    delete_transient( $transient );
    $message = ( 'unavailable' === $reason )
      ? 'The landing page was not changed: MailMunch could not be reached to check it. Please try again.'
      : 'The landing page was not changed: it does not belong to the MailMunch site connected to this WordPress site.';
    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
  }
  
  public function add_pages_to_dropdown($pages, $r) {
    if (array_key_exists('name', $r) && 'page_on_front' == $r['name']) {
      $args = array('post_type' => CONSTANTCONTACT_MAILMUNCH_POST_TYPE);
      $stacks = get_posts($args);
      $pages = array_merge($pages, $stacks);
    }

    return $pages;
  }
}
