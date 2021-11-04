<?php

/**
 * @file
 * Site configuration for Webships site installation.
 */

/**
 * Implements hook_install_tasks_alter().
 */
function webships_install_tasks_alter(&$tasks, $install_state) {
  unset($tasks['install_select_language']);
  unset($tasks['install_download_translation']);
}

/**
 * Implements hook_preprocess_install_page().
 */
function webships_preprocess_install_page(&$variables) {
  // Webships has custom styling for the install page.
  $variables['#attached']['library'][] = 'webships/install-page';
}

/**
 * Implements hook_toolbar_alter().
 */
function webships_toolbar_alter(&$items) {
  $items['admin_toolbar_tools']['#attached']['library'][] = 'webships/toolbar-style';
}
