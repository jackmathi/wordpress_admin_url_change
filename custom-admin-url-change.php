<?php
/*
Plugin Name: Custome Admin url change
Plugin URI: www.kok-india.in
Description: Plugin for to change your Admin longin url
Version: 1
Author: Mathivanan Marimuthu
Author URI: https://www.kok-india.in/
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if ( ! class_exists( 'kok_CustomWPAdminURL' ) ) {

  define( 'COOKIE_kok_WPADMIN', 'valid_login_slug' );
  define( 'kok_WPADMIN_OPTION', 'custom_wpadmin_slug' );
  define( 'kok_WPADMIN_PLUGIN_ENABLED_OPTION', 'kok_custom_wpadmin_url_plugin_enabled' );

  class kok_CustomWPAdminURL {

    function rewrite_admin_slug() {
      // be sure rules are written every time permalinks are updated
      $wpadmin_slug = get_option( kok_WPADMIN_OPTION );
      $is_active = get_option( kok_WPADMIN_PLUGIN_ENABLED_OPTION );

      if ( $is_active === false ) {
        // we're here for the first time
        update_option( kok_WPADMIN_PLUGIN_ENABLED_OPTION, 1 );
      }

      if ( $wpadmin_slug && $is_active == 1 ) {
        add_rewrite_rule( "{$wpadmin_slug}/?$", 'wp-login.php', 'top' );
      }
    }

    function set_admin_slug() {
      if ( isset( $_POST[kok_WPADMIN_OPTION] ) ) {
        $wpadmin_slug = trim( sanitize_key( $_POST[kok_WPADMIN_OPTION] ) );

        // save to db
        update_option( kok_WPADMIN_OPTION, $wpadmin_slug );

        if ( $wpadmin_slug ) {
          $this->rewrite_admin_slug();
        }
        else {
          flush_rewrite_rules();
        }
      }

      add_settings_field( kok_WPADMIN_OPTION, 'WP-Admin slug', array( $this, 'option_field' ), 'permalink', 'optional', array( 'label_for' => kok_WPADMIN_OPTION ) );
      register_setting( 'permalink', kok_WPADMIN_OPTION, 'strval' );
    }

    function option_field() {
      ?>
      <input id="<?php echo kok_WPADMIN_OPTION; ?>" name="<?php echo kok_WPADMIN_OPTION; ?>" type="text" class="regular-text code" value="<?php echo get_option( kok_WPADMIN_OPTION ); ?>">
      <p class="howto">Allowed characters are a-z, 0-9, - and _</p>
      <?php
    }

    function login() {
      $wpadmin_slug = get_option( kok_WPADMIN_OPTION );

      // are we in the right place?
      if ( in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ) ) && $wpadmin_slug ) {
        // check if our plugin have wrote necesery line to .htaccess
        // sometimes WP doesn't write correctly so we don't want to disable login in that case
        $htaccess = implode( '', file( ABSPATH . '.htaccess' ) );

        if ( $htaccess && preg_match( '/RewriteRule \^' . $wpadmin_slug . '\/\?\$/', $htaccess ) ) {
          $this->validate_login();
        }
      }
    }

    function validate_login() {
      $wpadmin_slug = get_option( kok_WPADMIN_OPTION );
      $url = $this->get_current_url();
      $query_arr = $url['query_arr'];

      if ( "/wp-login.php?loggedout=true" === $url['path'] . "?" . $url['query_string'] ) {
        wp_redirect( home_url() );
        exit();
      }
      else if ( isset( $query_arr['action'] ) && $query_arr['action'] == 'logout' ) {
        $this->clear_auth_cookie();
      }
      else if ( isset( $query_arr['action'] ) && in_array( $query_arr['action'], array( 'lostpassword', 'postpass', 'resetpass', 'rp' ) ) ) {
        // let user to this pages
      }
      else if ( trim( $url['path'], '/' ) == $wpadmin_slug ) {
        $this->set_auth_cookie();
      }
      else if ( $this->validate_auth_cookie() ) {
        // we're on default url, redirect to our
        wp_redirect( $wpadmin_slug );
      }
      else {
        wp_redirect( home_url() );
        exit();
      }
    }

    function set_auth_cookie() {
      setcookie( COOKIE_kok_WPADMIN, 1, 0, COOKIEPATH, COOKIE_DOMAIN );
    }

    function validate_auth_cookie() {
      return isset( $_COOKIE[COOKIE_kok_WPADMIN] );
    }

    function clear_auth_cookie() {
      unset( $_COOKIE[COOKIE_kok_WPADMIN] );
      setcookie( COOKIE_kok_WPADMIN, '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
    }

    function activate_plugin() {
      update_option( kok_WPADMIN_PLUGIN_ENABLED_OPTION, 1 );
      $this->set_auth_cookie();
      flush_rewrite_rules();
    }

    function deactivate_plugin() {
      update_option( kok_WPADMIN_PLUGIN_ENABLED_OPTION, 0 );
      $this->clear_auth_cookie();
      flush_rewrite_rules();
    }

    function uninstall_plugin() {
      delete_option( kok_WPADMIN_OPTION );
      delete_option( kok_WPADMIN_PLUGIN_ENABLED_OPTION );
      $this->clear_auth_cookie();
      flush_rewrite_rules();
    }

    function get_current_url() {
      // extract query string into array
      parse_str( $_SERVER['QUERY_STRING'], $query_arr );

      list( $path, $arguments ) = explode( "?", $_SERVER['REQUEST_URI'] );

      $url = array();
      $url['scheme'] = isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] != "off" ? "https" : "http";
      $url['domain'] = $_SERVER['HTTP_HOST'];
      $url['port'] = isset( $_SERVER["SERVER_PORT"] ) && $_SERVER["SERVER_PORT"] ? $_SERVER["SERVER_PORT"] : "";
      $url['query_string'] = $_SERVER['QUERY_STRING'];
      $url['query_arr'] = $query_arr;
      $url['rewrite_base'] = ( $host = explode( $url['scheme'] . "://" . $_SERVER['HTTP_HOST'], get_bloginfo( 'url' ) ) ) ? preg_replace( "/^\//", "", implode( "", $host ) ) : "";
      $url['path'] = $url['rewrite_base'] ? implode( "", explode( "/" . $url['rewrite_base'], $path ) ) : $path;
      $url['filename'] = $url['rewrite_base'] ? implode( "", explode( "/" . $url['rewrite_base'], $_SERVER["SCRIPT_NAME"] ) ) : $_SERVER["SCRIPT_NAME"];

      if ($url['path'] == $url['filename']) {
        $url['path'] = '/';
      }

      $url['filename'] = ltrim( $url['filename'], '/' );

      return $url;
    }
  }

  $kok_custom_wpadmin_url = new kok_CustomWPAdminURL();

  // add hooks
  add_filter( 'generate_rewrite_rules', array( $kok_custom_wpadmin_url, 'rewrite_admin_slug' ) );
  add_action( 'admin_init', array( $kok_custom_wpadmin_url, 'set_admin_slug' ) );
  add_action( 'login_init', array( $kok_custom_wpadmin_url, 'login' ) );
  register_activation_hook( __FILE__, array( $kok_custom_wpadmin_url, 'activate_plugin' ) );
  register_deactivation_hook( __FILE__, array( $kok_custom_wpadmin_url, 'deactivate_plugin' ) );
  register_uninstall_hook( __FILE__, array( $kok_custom_wpadmin_url, 'uninstall_plugin' ) );
}

?>