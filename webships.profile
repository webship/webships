<?php

/**
 * @file
 * Site configuration for Webships.org site installation.
 */

use Drupal\Core\Form\FormStateInterface;

 /**
 * Implements hook_form_FORM_ID_alter() for install_configure_form().
 *
 * Allows the profile to alter the site configuration form.
 */
function webships_form_install_configure_form_alter(&$form, FormStateInterface $form_state) {
  $form['site_information']['site_name']['#default_value'] = t('Webships.org App Store');
  $form['site_information']['site_mail']['#default_value'] = 'info@webship.co';
  $form['admin_account']['account']['name']['#default_value'] = 'webmaster';
  $form['admin_account']['account']['mail']['#default_value'] = 'info@webship.co';
}

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
