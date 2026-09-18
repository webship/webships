<?php

/**
 * @file
 * Contains the install tasks hook of the Webships installer.
 *
 * Core only discovers hook_install_tasks_alter() as a procedural function.
 *
 * @see \Drupal\webships\Installer\InstallTasks
 *
 * @internal
 *   Everything in the Webships installer is internal and may be changed or
 *   removed at any time without warning.
 */

declare(strict_types=1);

use Drupal\webships\Form\SiteTemplateForm;
use Drupal\webships\Installer\InstallTasks;

/**
 * Implements hook_install_tasks_alter().
 */
function webships_install_tasks_alter(array &$tasks, array $install_state): void {
  // Webships installs in English; there is nothing to translate before a site
  // template is applied.
  unset($tasks['install_select_language'], $tasks['install_download_translation']);

  $insert_before = function (string $key, array $additions) use (&$tasks): void {
    $position = array_search($key, array_keys($tasks), TRUE);
    if ($position === FALSE) {
      return;
    }
    // This isn't very clean, but it's the only way to positionally splice
    // into an associative (and therefore by definition unordered) array.
    $tasks = array_slice($tasks, 0, $position, TRUE)
      + $additions
      + array_slice($tasks, $position, NULL, TRUE);
  };

  // User and its configuration have to be in place before the administrator
  // account is set up.
  $install_profile_task = [
    'function' => InstallTasks::class . '::installProfile',
  ] + $tasks['install_install_profile'];

  $configure_form_task = $tasks['install_configure_form'];
  unset($tasks['install_install_profile'], $tasks['install_configure_form']);

  // Before applying any recipe: install the profile itself, choose an API site
  // template, then set up the administrator account.
  $insert_before('install_profile_modules', [
    'install_install_profile' => $install_profile_task,
    'webships_choose_template' => [
      'display_name' => t('Choose site template'),
      'run' => $install_state['parameters'][SiteTemplateForm::TASK_ID] ?? INSTALL_TASK_RUN_IF_REACHED,
      'function' => InstallTasks::class . '::chooseTemplate',
    ],
    'install_configure_form' => $configure_form_task,
  ]);

  // We can't use the passed-in $install_state here because it's not passed
  // by reference.
  $GLOBALS['install_state']['parameters'] += ['langcode' => 'en'];

  // Wrap install_profile_modules(), which returns a batch job, and add the
  // operations that apply the chosen site template.
  $tasks['install_profile_modules']['function'] = InstallTasks::class . '::applyRecipes';

  // When the installation is finished, uninstall this profile.
  $tasks['install_finished']['function'] = InstallTasks::class . '::finished';
}
