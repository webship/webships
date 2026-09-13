<?php

declare(strict_types=1);

namespace Drupal\webships\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for the Webships installation profile.
 */
class WebshipsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_form_FORM_ID_alter() for install_configure_form().
   *
   * Allows the profile to alter the site configuration form.
   */
  #[Hook('form_install_configure_form_alter')]
  public function formInstallConfigureFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['site_information']['site_name']['#default_value'] = $this->t('Webships.org App Store');
    $form['site_information']['site_mail']['#default_value'] = 'info@webship.co';
    $form['admin_account']['account']['name']['#default_value'] = 'webmaster';
    $form['admin_account']['account']['mail']['#default_value'] = 'info@webship.co';
  }

  /**
   * Implements hook_preprocess_HOOK() for install_page.
   */
  #[Hook('preprocess_install_page')]
  public function preprocessInstallPage(array &$variables): void {
    // Webships has custom styling for the install page.
    $variables['#attached']['library'][] = 'webships/install-page';
  }

}
