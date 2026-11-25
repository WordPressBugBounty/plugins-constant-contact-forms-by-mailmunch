<?php
$landing_page_id = get_post_meta( get_the_ID(), '_mailmunch_landing_page_id', true );
// MM-3766: only a page of the connected MailMunch site is shown (an Author could set any id before this fix).
if ( ! ConstantContact_Mailmunch_Api::landingPageAllowed( $landing_page_id ) ) {
  status_header( 404 );
  nocache_headers();
  header( 'X-MailMunch-Landing-Page: not-this-site' );
  include( get_query_template( '404' ) );
  die();
}
$landing_page_id = absint( $landing_page_id );
$response = wp_remote_get(CONSTANTCONTACT_MAILMUNCH_PAGE_SERVICE_URL. '/'. $landing_page_id, array(
  'headers' => array(
    'X-MailMunch-JSON' => 1
  )
));
if ( is_array( $response ) && ! is_wp_error( $response ) ) {
  $landingPageJson = json_decode($response['body']);
  $pageHead = $landingPageJson->head;
  $pageBody = $landingPageJson->body;
  $pageTitle = $landingPageJson->title;
  $pageScreenshotUrl = $landingPageJson->screenshotUrl;
} else {
  status_header( 404 );
  nocache_headers();
  include( get_query_template( '404' ) );
  die();
}

function parse_shortcodes($html) {
  $pattern = get_shortcode_regex();
  if (preg_match_all( '/'. $pattern .'/s', $html, $matches )
      && array_key_exists( 2, $matches ))
  {
    $shortcodes = array_unique($matches[0]);
    foreach ($shortcodes as $shortcode) {
      $parsedShortcodeHtml = do_shortcode($shortcode);
      if (!empty($parsedShortcodeHtml)) {
        $jsonEncodedShortcodeHtml = wp_json_encode($parsedShortcodeHtml);
        $html = str_replace($shortcode, substr($jsonEncodedShortcodeHtml, 1, -1), $html);
      }
    }
  }
  return $html;
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
  <meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" />
  <meta name=viewport content="width=device-width, initial-scale=1">
  <meta property="og:type" content="website" />
  <meta property="og:image" content="<?php echo $pageScreenshotUrl ?>" />
  <meta property="og:image:width" content="600" />
  <meta property="og:image:height" content="600" />
  <link rel="pingback" href="<?php bloginfo('pingback_url'); ?>" />
  <title><?php echo wp_get_document_title(); ?></title>
  <?php wp_head(); ?>
  
  <style type="text/css" media="screen">
    html { margin-top: 0px !important; }
    * html body { margin-top: 0px !important; }
  </style>
  
  <?php echo $pageHead; ?>
</head>
<body>
  <?php echo parse_shortcodes($pageBody); ?>
  
  <?php wp_footer(); ?>
</body>
</html>
