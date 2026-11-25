<?php
  class ConstantContact_Mailmunch_Api {
    protected $base_url = CONSTANTCONTACT_MAILMUNCH_URL;
    protected $headers = array('Accept' => 'application/json');
    protected $requestType = 'get';
    protected $mailmunch_prefix;
    protected $referral = 'constantcontact-wordpress-plugin';

    function __construct() {
      $this->mailmunch_prefix = CONSTANTCONTACT_MAILMUNCH_PREFIX.'_';
      $this->ensureUser();
      $this->findOrCreateSite();
    }

    function ensureUser() {
      $userToken = $this->getUserToken();
      if (empty($userToken)) {
        $userToken = $this->generateUserToken();
        if( is_wp_error( $userToken ) ) {
          return new WP_Error( 'broke', "Unable to connect to MailMunch. Please try again later." );
        }
        
        $this->setUserToken($userToken);
      }
    }

    function getSetting($settingName) {
      return get_option($this->getPrefix(). $settingName);
    }

    function setSetting($settingName, $value=null) {
      return update_option($this->getPrefix(). $settingName, $value);
    }

    function getUserToken() {
      return get_option($this->getPrefix(). 'user_token');
    }

    function setUserToken($userToken) {
      self::forgetLandingPageIds();
      return update_option($this->getPrefix(). 'user_token', $userToken );
    }

    function widgets($widgetTypeName, $siteId=null) {
      if (empty($siteId)) { $siteId = $this->getSiteId(); }
      $this->requestType = 'get';
      if (!empty($widgetTypeName)) {
        return $this->ping('/sites/'.$siteId.'/widgets?widget_type_name='.$widgetTypeName);
      } else {
        return $this->ping('/sites/'.$siteId.'/widgets');
      }
    }

    function getPrefix() {
      return $this->mailmunch_prefix;
    }

    function getSites() {
      $this->requestType = 'get';
      $request = $this->ping('/sites');

      if( is_wp_error( $request ) ) {
        return new WP_Error( 'broke', "Unable to get sites. Please try again later." );
      }

      $sites = $request['body'];
      $result = json_decode($sites);
      return $result;
    }

    function getSite($siteId=null) {
      if (empty($siteId)) $siteId = $this->getSiteId();
      if (empty($siteId)) return false;

      $this->requestType = 'get';
      $request = $this->ping('/sites/'. $siteId);

      if( is_wp_error( $request ) ) {
        return false;
      }

      $site = $request['body'];
      $result = json_decode($site);
      return $result;
    }

    function getLists($siteId=null) {
      if (empty($siteId)) { $siteId = $this->getSiteId(); }
      $this->requestType = 'get';
      $request = $this->ping('/sites/'. $siteId. '/lists');
      if( is_wp_error( $request ) ) {
        return new WP_Error( 'broke', "Unable to get lists. Please try again later." );
      }

      return json_decode($request['body']);
    }
    
    function getLandingPages($siteId=null) {
      if (empty($siteId)) { $siteId = $this->getSiteId(); }
      $this->requestType = 'get';
      $request = $this->ping('/sites/'. $siteId. '/landing_pages');
      if( is_wp_error( $request ) ) {
        return new WP_Error( 'broke', "Unable to get landing pages. Please try again later." );
      }

      return json_decode($request['body']);
    }

    function findOrCreateSite() {
      $site = $this->getSite();
      if (empty($site)) {
        $siteName = get_bloginfo();
        $decodedSiteName = html_entity_decode($siteName, ENT_QUOTES);
        $decodedSiteName = stripslashes($decodedSiteName);
        $site = $this->createSite($decodedSiteName, home_url());
        if (!empty($site)) $this->setSiteId($site->id);
      }
      return $site;
    }

    function createIntegration($accessToken, $externalListId) {
      $siteId = $this->getSiteId();
      if (!empty($siteId)) {
        $this->requestType = 'post';
        $response = $this->ping('/wordpress/integrations/constantcontact', array(
          'site_id' => $siteId,
          'access_token' => $accessToken,
          'active_list_id' => $externalListId
        ));
        $integration = json_decode($response['body']);
        return $integration;
      }
      return false;
    }

    function createSite($siteName, $domain) {
      if (empty($siteName)) $siteName = 'WordPress';
      $this->requestType = 'post';
      $request = $this->ping('/sites', array(
        'site' => array(
          'name' => $siteName,
          'domain' => $domain,
          'wordpress' => true
          )
      ));
      if( is_wp_error( $request ) ) {
        return new WP_Error( 'broke', "Unable to create site. Please try again later." );
      }

      $site = json_decode($request['body']);
      return $site;
    }

    function updateSite($sitename, $domain) {
      $this->requestType = 'post';
      return $this->ping('/wordpress/update_site', array(
        'id' => $this->getSiteId(),
        'site' => array(
          'name' => $sitename,
          'domain' => $domain
          )
      ));
    }

    function isLegacyUser() {
      $email = get_option($this->getPrefix(). "user_email");
      $password = get_option($this->getPrefix(). "user_password");
      
      if (!empty($email) && !empty($password)) {
        return true;
      }

      return false;
    }

    function setSkipOnBoarding() {
      update_option($this->getPrefix(). 'skip_onboarding', true);
    }

    function skipOnBoarding() {
      $skipOnBoarding = get_option($this->getPrefix(). 'skip_onboarding');
      return $skipOnBoarding == true;
    }

    function migrateUser() {
      $currentHeaders = $this->headers;
      $email = get_option($this->getPrefix(). "user_email");
      $password = get_option($this->getPrefix(). "user_password");

      if (empty($email) || empty($password)) {
        return false;
      }

      $this->headers = array_merge($this->headers, array(
        'Authorization' => 'Basic ' . base64_encode( $email . ':' . $password )
        )
      );

      $this->requestType = 'post';
      $request = $this->ping('/wordpress/migrate_user.json', array(), false);
      $this->headers = $currentHeaders;

      if( is_wp_error( $request ) ) {
        return false;
      }

      $request = json_decode($request['body']);
      if ($request->success == true && !empty($request->token)) {
        $this->setUserToken($request->token);

        // Migrate Site ID
        $old_data = $this->deep_unserialize(get_option($this->getPrefix(). "data"));
        if (isset($old_data["site_id"])) {
          $this->setSiteId($old_data["site_id"]);
          delete_option($this->getPrefix(). 'data');
        }

        // Delete options for old site
        delete_option($this->getPrefix(). 'user_email');
        delete_option($this->getPrefix(). 'user_password');
        delete_option($this->getPrefix(). 'guest_user');
        delete_option($this->getPrefix(). 'wordpress_instance_id');

        $this->setSkipOnBoarding();

        return true;
      }

      return false;
    }

    function deep_unserialize($value, $retries=0) {
      $retries++;
      if ($retries > 3) return $value;

      if (is_string($value)) {
        $value = unserialize($value);
        if (is_string($value)) $value = $this->deep_unserialize($value, $retries);
      }
      return $value;
    }

    function generateUserToken() {
      if ($this->isLegacyUser()) {
        if ($this->migrateUser()) {
          return $this->getUserToken();
        }
      }

      $guestUser = $this->createGuestUser();
      if( is_wp_error( $guestUser ) ) {
        return new WP_Error( 'broke', "Unable to create user. Please try again later." );
      }
      return json_decode($guestUser['body'])->user_token;
    }

    function createGuestUser() {
      $this->requestType = 'post';
      return $this->ping('/users', array(
        'user' => array(
          'email' => 'guest_' . uniqid() . '@mailmunch.com',
          'password' => uniqid(),
          'guest_user' => true,
          'referral' => $this->referral,
          )
      ));
    }

    function getWidgetsHtml($siteId=null) {
      if (empty($siteId)) { $siteId = $this->getSiteId(); }

      $this->requestType = 'get';
      $request = $this->ping('/sites/'.$siteId.'/widgets/wordpress?plugin=constantcontact');
      if( is_wp_error( $request ) ) {
        return $request->get_error_message();
      }

      return self::withoutToken($request['body']);
    }
    
    function getAutorespondersHtml($siteId=null) {
      if (empty($siteId)) { $siteId = $this->getSiteId(); }

      $this->requestType = 'get';
      $request = $this->ping('/sites/'.$siteId.'/autoresponders/wordpress?plugin=mailchimp');
      if( is_wp_error( $request ) ) {
        return $request->get_error_message();
      }

      return self::withoutToken($request['body']);
    }

    /**
     * MM-3766: a wp-admin link that opens MailMunch at $nextPath for the admin clicking it.
     * It carries no MailMunch token, only a WordPress nonce; the admin-post handler asks
     * MailMunch for a one-use, 60-second sign-in link when it is clicked.
     */
    static function ssoAdminUrl($nextPath) {
      $url = add_query_arg(array(
        'action' => CONSTANTCONTACT_MAILMUNCH_PREFIX. '_sso',
        'next_url' => rawurlencode($nextPath),
      ), admin_url('admin-post.php'));
      return wp_nonce_url($url, CONSTANTCONTACT_MAILMUNCH_PREFIX. '_sso');
    }

    /**
     * MM-3766: the dashboard HTML from MailMunch has forms that post the user token to /sso.
     * Point them at the nonce-protected admin-post handler instead, and never put the token in.
     */
    static function withoutToken($html) {
      $html = preg_replace('#action="(?:https?:)?//[^"/]+/sso"#', 'action="'. esc_url(admin_url('admin-post.php')). '"', $html);
      $fields = '<input type="hidden" name="action" value="'. esc_attr(CONSTANTCONTACT_MAILMUNCH_PREFIX. '_sso'). '" />'
        . '<input type="hidden" name="_wpnonce" value="'. esc_attr(wp_create_nonce(CONSTANTCONTACT_MAILMUNCH_PREFIX. '_sso')). '" />';
      $html = preg_replace('#<input type="hidden" name="token" value="\{\{TOKEN\}\}"\s*/?>#', $fields, $html);
      // Whatever is left of the placeholder is blanked, so the token can never reach the page.
      $html = str_replace('{{TOKEN}}', '', $html);
      // Report links carry the GraphQL login token (utk); MailMunch adds a fresh one on redeem.
      $html = preg_replace('#(&amp;|&)utk=[^"&]*#', '', $html);
      $html = preg_replace('#\?utk=[^"&]*(&amp;|&)?#', '?', $html);
      return $html;
    }

    /**
     * MM-3766: asks MailMunch (server to server, with the plugin token) for a one-use sign-in link.
     * Returns the URL, or WP_Error. Only URLs on the MailMunch host this plugin talks to are accepted.
     */
    function getSsoLink($nextPath, $source) {
      $this->requestType = 'post';
      $request = $this->ping('/wordpress/sso_link', array('next_url' => $nextPath, 'source' => $source));
      if (is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
        return new WP_Error('mailmunch_sso', 'Unable to open MailMunch. Please try again.');
      }

      $body = json_decode(wp_remote_retrieve_body($request));
      if (empty($body) || empty($body->success) || empty($body->url) || !is_string($body->url)) {
        return new WP_Error('mailmunch_sso', 'Unable to open MailMunch. Please try again.');
      }

      $parsed = wp_parse_url($body->url);
      $allowedHosts = array_map('strtolower', array_filter(array(
        wp_parse_url(CONSTANTCONTACT_MAILMUNCH_URL, PHP_URL_HOST),
        wp_parse_url(CONSTANTCONTACT_MAILMUNCH_URL_SECURED, PHP_URL_HOST),
      )));
      $securedScheme = wp_parse_url(CONSTANTCONTACT_MAILMUNCH_URL_SECURED, PHP_URL_SCHEME);
      $allowedSchemes = ($securedScheme === 'https') ? array('https') : array('https', 'http');
      if (empty($parsed['host']) || empty($parsed['scheme'])
          || !in_array(strtolower($parsed['host']), $allowedHosts, true)
          || !in_array(strtolower($parsed['scheme']), $allowedSchemes, true)
          || (isset($parsed['path']) && $parsed['path'] !== '/wordpress/sso_redeem')) {
        return new WP_Error('mailmunch_sso', 'Unable to open MailMunch. Please try again.');
      }

      return $body->url;
    }

    /**
     * MM-3766: may this landing page id be shown on this WordPress site? Only the connected MailMunch site's
     * own pages. Pages saved before the fix may point at another account's page, so this runs at page view.
     * The site's list is cached for an hour; a miss refreshes it at most every 5 minutes (so a page made a
     * moment ago works). If MailMunch can't be asked, the page is shown as before rather than taken down.
     * Uses no Api instance: the constructor would create a site or user on a visitor's request.
     */
    static function landingPageAllowed($landingPageId) {
      $landingPageId = absint($landingPageId);
      if (!$landingPageId) { return false; }
      $siteId = absint(get_option(CONSTANTCONTACT_MAILMUNCH_PREFIX. '_site_id'));
      $userToken = get_option(CONSTANTCONTACT_MAILMUNCH_PREFIX. '_user_token');
      if (empty($siteId) || empty($userToken)) { return true; }

      // The cached list belongs to one MailMunch site; after reconnecting to another site it no longer counts.
      $cacheKey = CONSTANTCONTACT_MAILMUNCH_PREFIX. '_landing_page_ids';
      $cached = get_transient($cacheKey);
      $ids = (is_array($cached) && isset($cached['site_id'], $cached['ids']) && (int) $cached['site_id'] === $siteId) ? $cached['ids'] : null;
      if (is_array($ids) && in_array($landingPageId, $ids, true)) { return true; }
      if (is_array($ids) && (int) get_transient($cacheKey. '_checked') === $siteId) { return false; }
      // MailMunch didn't answer a moment ago: don't make every visitor wait for it again.
      if ((int) get_transient($cacheKey. '_unavailable') === $siteId) { return true; }

      // Token in a header, not the address (MM-3733 stops accepting ?token= on this route later).
      $url = CONSTANTCONTACT_MAILMUNCH_URL. '/sites/'. $siteId. '/landing_pages?version='. rawurlencode(CONSTANTCONTACT_MAILMUNCH_VERSION);
      $response = wp_remote_get($url, array('headers' => array('Accept' => 'application/json', 'Authorization' => 'Bearer '. $userToken), 'timeout' => 5));
      $pages = (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) ? null : json_decode(wp_remote_retrieve_body($response));
      if (!is_array($pages)) {
        set_transient($cacheKey. '_unavailable', $siteId, MINUTE_IN_SECONDS);
        return true;
      }
      $ids = array();
      foreach ($pages as $page) { if (isset($page->id)) { $ids[] = (int) $page->id; } }
      self::rememberLandingPageIds($ids);
      return in_array($landingPageId, $ids, true);
    }

    static function rememberLandingPageIds($ids) {
      $siteId = absint(get_option(CONSTANTCONTACT_MAILMUNCH_PREFIX. '_site_id'));
      set_transient(CONSTANTCONTACT_MAILMUNCH_PREFIX. '_landing_page_ids', array('site_id' => $siteId, 'ids' => array_values(array_map('intval', $ids))), HOUR_IN_SECONDS);
      set_transient(CONSTANTCONTACT_MAILMUNCH_PREFIX. '_landing_page_ids_checked', $siteId, 5 * MINUTE_IN_SECONDS);
      delete_transient(CONSTANTCONTACT_MAILMUNCH_PREFIX. '_landing_page_ids_unavailable');
    }

    static function forgetLandingPageIds() {
      foreach (array('', '_checked', '_unavailable') as $suffix) { delete_transient(CONSTANTCONTACT_MAILMUNCH_PREFIX. '_landing_page_ids'. $suffix); }
    }

    /**
     * MM-3766: ids of the landing pages that belong to the connected MailMunch site,
     * or WP_Error when MailMunch can't be asked.
     */
    function getLandingPageIds($siteId=null) {
      $pages = $this->getLandingPages($siteId);
      if (is_wp_error($pages) || !is_array($pages)) {
        return new WP_Error('mailmunch_landing_pages', 'Unable to check landing pages. Please try again later.');
      }
      $ids = array();
      foreach ($pages as $page) {
        if (isset($page->id)) { $ids[] = (int) $page->id; }
      }
      self::rememberLandingPageIds($ids);
      return $ids;
    }

    function getSiteId() {
      return get_option($this->getPrefix(). 'site_id');
    }

    function setSiteId($siteId) {
      self::forgetLandingPageIds();
      return update_option($this->getPrefix(). 'site_id', $siteId);
    }

    function getListId() {
      return get_option($this->getPrefix(). 'list_id');
    }

    function setListId($listId) {
      return update_option($this->getPrefix(). 'list_id', $listId);
    }

    function deleteWidget($widgetId) {
      $this->requestType = 'post';
      $request = $this->ping('/sites/'.$this->getSiteId().'/widgets/'.$widgetId.'/delete');
      if ( is_wp_error( $request ) ) {
        return array('success' => false);
      }
      return array('success' => true);
    }
    
    function changeEmailStatus($emailId, $emailStatus) {
      $this->requestType = 'put';
      $request = $this->ping('/sites/'.$this->getSiteId().'/emails/'.$emailId, array(
        'email' => array(
          'status' => $emailStatus  
        )
      ));
      if ( is_wp_error( $request ) ) {
        return array('success' => false);
      }
      return array('success' => true);
    }
    
    function deleteEmail($emailId) {
      $this->requestType = 'delete';
      $request = $this->ping('/sites/'.$this->getSiteId().'/emails/'.$emailId);
      if ( is_wp_error( $request ) ) {
        return array('success' => false);
      }
      return array('success' => true);
    }

    function signInUser($email, $password) {
      $this->requestType = 'post';
      $request = $this->ping('/wordpress/sign_in.json', array(
          'user' => array(
            'email' => $email,
            'password' => $password
          ),
          'site_id' => $this->getSiteId()
        )
      );

      if( is_wp_error( $request ) ) {
        return false;
      }

      $newUser = json_decode($request['body']);
      if (intval($newUser->site_id) && $newUser->site_id != $this->getSiteId()) {
        $this->setSiteId($newUser->site_id);
      }
      return $newUser;
    }

    function signUpUser($email, $password, $siteName, $siteDomain) {
      $this->requestType = 'post';
      $request = $this->ping('/wordpress/sign_up.json', array(
          'user' => array(
            'email' => $email,
            'password' => $password,
            'guest_user' => false
          ),
          'site' => array(
            'id' => $this->getSiteId(),
            'name' => $siteName,
            'domain' => $siteDomain
          )
        )
      );

      if( is_wp_error( $request ) ) {
        return false;
      }

      $newUser = json_decode($request['body']);
      if (intval($newUser->site_id) && $newUser->site_id != $this->getSiteId()) {
        $this->setSiteId($newUser->site_id);
      }
      return $newUser;
    }

    function signOutUser() {
      delete_option($this->getPrefix(). 'user_token');
      self::forgetLandingPageIds();
      delete_option($this->getPrefix(). 'site_id');
      delete_option($this->getPrefix(). 'constantcontact_access_token');
      delete_option($this->getPrefix(). 'constantcontact_list_id');
    }
    
    function ping($path, $options=array(), $useTokenAuth=true) {
      $type = $this->requestType;
      $url = $this->base_url. $path;

      $parsedUrl = wp_parse_url($url);
      $parseUrlQuery = isset($parsedUrl['query']) ? $parsedUrl['query'] : null;
      if (!empty($parseUrlQuery)) {
        $url .= '&version='. CONSTANTCONTACT_MAILMUNCH_VERSION;
      }
      else {
        $url .= '?version='. CONSTANTCONTACT_MAILMUNCH_VERSION;
      }

      if ($useTokenAuth) { $url .= '&token='. $this->getUserToken(); }

      $args = array(
        'headers' => $this->headers,
        'timeout' => 120,
      );

      if ($type == 'post') {
        $args = array_merge($args, array('method' => 'POST', 'body' => $options));
        $request = wp_remote_post($url, $args);
      }
      else if ($type == 'put') {
        $args = array_merge($args, array('method' => 'PUT', 'body' => $options));
        $request = wp_remote_request($url, $args);
      }
      else if ($type == 'delete') {
        $args = array_merge($args, array('method' => 'DELETE', 'body' => $options));
        $request = wp_remote_request($url, $args);
      }
      else {
        $request = wp_remote_get($url, $args);
      }

      if ( !is_wp_error( $request ) && ( $request['response']['code'] == 500 || $request['response']['code'] == 503 ) ) {
        return new WP_Error( 'broke', "Internal Server Error" );
      }

      if ($useTokenAuth) {
        if (!is_wp_error( $request ) && isset($request['response']['code']) && $request['response']['code'] == 401) {
          $this->signOutUser();
          return new WP_Error( 'broke', 'Unauthorized. Please try again.');
        }
      }

      return $request;
    }
  }
?>
