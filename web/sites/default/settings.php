<?php

/**
 * Load services definition file.
 */
$settings['container_yamls'][] = __DIR__ . '/services.yml';

/**
 * Include the Pantheon-specific settings file.
 *
 * n.b. The settings.pantheon.php file makes some changes
 *      that affect all environments that this site
 *      exists in.  Always include this file, even in
 *      a local development environment, to ensure that
 *      the site settings remain consistent.
 */
include __DIR__ . "/settings.pantheon.php";

/**
 * Skipping permissions hardening will make scaffolding
 * work better, but will also raise a warning when you
 * install Drupal.
 *
 * https://www.drupal.org/project/drupal/issues/3091285
 */
// $settings['skip_permissions_hardening'] = TRUE;

/**
 * Include the custom pantheon settings file if it exists.
 */
$custom_pantheon_settings = __DIR__ . "/settings.pantheon-custom.php";
if (file_exists($custom_pantheon_settings)) {
  include $custom_pantheon_settings;
}

/**
 * Override Pantheon's default configuration sync directory.
 */
$settings['config_sync_directory'] = 'sites/default/config';

/**
 * Override Pantheon's default configuration sync directory.
 */
$settings['config_sync_directory'] = 'sites/default/config';

/**
 * Include settings.local.php if it exists.
 */
$local_settings = __DIR__ . "/settings.local.php";
if (file_exists($local_settings)) {
  include $local_settings;
}
