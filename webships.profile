<?php

/**
 * @file
 * Site configuration for Webships.org site installation.
 *
 * Hook implementations live in \Drupal\webships\Hook\WebshipsHooks. Core only
 * discovers hook_install_tasks_alter() as a procedural function.
 */

/**
 * Implements hook_install_tasks_alter().
 */
function webships_install_tasks_alter(&$tasks, $install_state) {
  unset($tasks['install_select_language']);
  unset($tasks['install_download_translation']);
}
