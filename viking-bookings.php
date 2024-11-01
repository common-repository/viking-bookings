<?php
/**
 * Viking Bookings
 *
 * Plugin Name: Viking Bookings
 * Plugin URI: http://wordpress.org/plugins/viking-bookings/
 * Description: Easily embed booking forms from your Viking Bookings account on your WordPress site.
 * Author: Viking Bookings
 * Version: 1.0.2
 * Author URI: https://www.vikingbookings.com/
 * License: GPLv2 or later
 * Text Domain: viking-bookings
 * Domain Path: /languages
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General Public License version 2, as published by the Free Software Foundation. You may NOT assume
 * that you can use any other version of the GPL.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

if (!class_exists('VikingWidgetPlugin')) {
  class VikingWidgetPlugin {

    private static $version = '1.0.2';
    private static $text_domain = 'viking-bookings';

    public function init() {
      add_action('wp_head', [$this, 'page_header'], 1);
      add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

      add_action('admin_menu', [$this, 'settings_page']);
      add_action('admin_init', [$this, 'settings']);
      add_action('admin_enqueue_scripts', [$this, 'enqueue_color_picker']);
      add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
      add_action('save_post', [$this, 'save_post']);

      add_filter('plugin_action_links_viking-bookings/viking-bookings.php', [$this, 'add_action_links']);
    }

    function page_header() {
      $button_url = '';
      if (is_page() || is_single()) {
        $post_id = get_queried_object_id();
        $button_url = get_post_meta($post_id, 'viking_button_url', true);
      }
      if (!$button_url) $button_url = get_option('viking_button_url');

      $arr = [];
      if (get_option('viking_source')) $arr['source'] = get_option('viking_source');
      if (get_option('viking_style_color')) $arr['style_color'] = get_option('viking_style_color');
      if ($button_url) {
        $arr['button_form_url'] = $button_url;
        $arr['button_alignment'] = get_option('viking_button_alignment');
        $arr['button_horizontal_padding'] = get_option('viking_button_horizontal_padding');
        $arr['button_vertical_padding'] = get_option('viking_button_vertical_padding');
      }
      $arr = array_filter($arr);
      if(!empty($arr)) {
        ?><script>window.vikingWidgetSettings = <?= json_encode((object)$arr) ?>;</script><?php
      }
    }

    function enqueue_scripts() {
      wp_enqueue_script('viking_bookings_widget', 'https://app.vikingbookings.com/widget/v2/widget.js', array(), self::$version, true);
    }

    function add_meta_boxes() {
      foreach (['page', 'post'] as $screen) {
        add_meta_box('vikingbookings_metabox', 'Viking Bookings', [$this, 'metabox_html'], $screen, 'side');
      }
    }

    function metabox_html($post) {
      wp_nonce_field('viking_meta_box', 'viking_nonce');
      $default_button_url = get_option('viking_button_url');
      $val = get_post_meta($post->ID, 'viking_button_url', true);
      ?>
      <p class="post-attributes-label-wrapper menu-order-label-wrapper"><label class="post-attributes-label" for="viking_button_url"><?php _e('Button widget URL', self::$text_domain); ?></label></p>
      <input type="url" name="viking_button_url" style="width:100%;margin-bottom:10px" value="<?= $val ?>" id="viking_button_url" class="regular-text code" />
      <?php if ($default_button_url) : ?>
      <p class="post-attributes-help-text"><?php printf(__('Defaults to booking form with URL %s', self::$text_domain), '<a href="'.$default_button_url.'" target="_blank">'.$default_button_url.'</a>'); ?></p>
      <?php
      endif;
    }

    function save_post($post_id) {
      $nonce_name   = isset($_POST['viking_nonce']) ? $_POST['viking_nonce'] : '';
      if (!wp_verify_nonce( $nonce_name, 'viking_meta_box')) return;
      if (!current_user_can('edit_post', $post_id) && !current_user_can('edit_page', $post_id)) return;
      if (wp_is_post_autosave($post_id)) return;
      if (wp_is_post_revision($post_id)) return;

      if (array_key_exists('viking_button_url', $_POST)) {
        update_post_meta($post_id, 'viking_button_url', sanitize_text_field($_POST['viking_button_url']));
      }
    }

    function settings_page() {
      add_options_page('Viking Bookings', 'Viking Bookings', 'manage_options', 'vikingbookings', [$this, 'edit_settings']);
    }

    function settings() {
      register_setting('vikingbookings', 'viking_source');
      register_setting('vikingbookings', 'viking_button_url');
      register_setting('vikingbookings', 'viking_button_alignment');
      register_setting('vikingbookings', 'viking_button_horizontal_padding');
      register_setting('vikingbookings', 'viking_button_vertical_padding');
      register_setting('vikingbookings', 'viking_style_color');

      add_settings_section('viking_settings_section', 'Settings Section', [$this, 'settings_title'], 'vikingbookings');
      add_settings_field('viking_button_url', __('Button URL', self::$text_domain), [$this, 'button_url'], 'vikingbookings', 'vikingbookings');
      add_settings_field('viking_style_color', __('Button color', self::$text_domain), [$this, 'style_color'], 'vikingbookings', 'vikingbookings');
      add_settings_field('viking_button_alignment', __('Button position', self::$text_domain), [$this, 'floating_button_position'], 'vikingbookings', 'vikingbookings');

      add_settings_field('viking_button_horizontal_padding', __('Button horizontal padding', self::$text_domain), [$this, 'viking_button_horizontal_padding'], 'vikingbookings', 'vikingbookings');
      add_settings_field('viking_button_vertical_padding', __('Button vertical padding', self::$text_domain), [$this, 'viking_button_vertical_padding'], 'vikingbookings', 'vikingbookings');
    }

    public function settings_title() {
      return "Viking Bookings";
    }

    public function button_url() {
      $val = get_option('viking_button_url');
      ?>
      <input type="url" name="viking_button_url" value="<?= (isset($val) ? esc_attr($val) : '') ?>" class="regular-text code" />
      <p class="description" id="home-description"><?php _e('Enter the booking form URL you want to use (by default) for your floating button on every page. Leave blank to disable the floating button by default.', self::$text_domain); ?></p>
      <?php
    }

    public function enable_floating_button() {
      $val = get_option('viking_enable_button');
      ?><label><input type="checkbox" name="viking_enable_button" value="1" <?= (!empty($val) ? 'checked' : '') ?> /> <?php _e('Enable floating button', 'vikingbookings'); ?></label><p class="description" id="home-description"><?php _e('Do you want to enable the floating button on every page? You can still embed booking forms manually if this option is disabled.', self::$text_domain); ?></p><?php
    }

    public function floating_button_position() {
      $val = get_option('viking_button_alignment');
      $positions = [
        'left' => __('Left', self::$text_domain),
        'right' => __('Right', self::$text_domain),
      ];
      $str = "<select name='viking_button_alignment'>";
      foreach ($positions as $k => $text) {
        $str .= '<option value="' . $k . '" ' . ($k == $val ? ' selected' : '') . '>' . $text . '</option>';
      }
      $str .= '</select>';
      echo $str;
    }

    public function style_color() {
      $val = get_option('viking_style_color', '#ff6b24');
      echo '<input type="text" value="' . esc_attr($val) . '" name="viking_style_color" class="viking-button-color" data-default-color="#ff6b24" />';
    }

    public function viking_button_horizontal_padding() {
      $val = get_option('viking_button_horizontal_padding', 32);
      echo '<input type="number" step="1" min="0" value="' . esc_attr($val) . '" name="viking_button_horizontal_padding" class="small-text" /> px';
    }

    public function viking_button_vertical_padding() {
      $val = get_option('viking_button_vertical_padding', 32);
      echo '<input type="number" step="1" min="0" value="' . esc_attr($val) . '" name="viking_button_vertical_padding" class="small-text" /> px';
    }

    function enqueue_color_picker($hook_suffix) {
      // first check that $hook_suffix is appropriate for your admin page
      wp_enqueue_style('wp-color-picker');
      wp_enqueue_script('viking-admin', plugins_url('viking-admin.js', __FILE__), array('wp-color-picker'), false, true);
    }

    function add_action_links($links) {
      $settings_link = '<a href="options-general.php?page=vikingbookings">'.__('Settings').'</a>';
      array_unshift($links, $settings_link);
      return $links;
    }

    function edit_settings() {
      global $wp_settings_sections, $wp_settings_fields;
      // check user capabilities
      if (!current_user_can('manage_options')) {
        return;
      }

      settings_errors('viking_messages');
      ?>
      <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
          <?= settings_fields('vikingbookings'); ?>
          <table class="form-table">
            <?= do_settings_fields('vikingbookings', 'vikingbookings'); ?>
          </table>
          <?= submit_button(); ?>
        </form>
      </div>
      <?php
    }
  }

  $plugin = new VikingWidgetPlugin();
  $plugin->init();
}
