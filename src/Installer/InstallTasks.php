<?php

declare(strict_types=1);

namespace Drupal\webships\Installer;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\webships\ComposerExecutor;
use Drupal\webships\Form\SiteTemplateForm;
use Drupal\webships\RecipeHandler;
use Drupal\webships\SiteTemplate;

/**
 * Install task and batch callbacks for the Webships installer.
 *
 * @see webships_install_tasks_alter()
 *
 * @internal
 *   Everything in the Webships installer is internal and may be changed or
 *   removed at any time without warning. External code should not interact
 *   with this class.
 */
final class InstallTasks {

  /**
   * Installs the install profile.
   *
   * This is only overridden to ensure that User is installed first, since we
   * need it and its configuration to set up the administrator account.
   *
   * @param array $install_state
   *   The current install state.
   */
  public static function installProfile(array &$install_state): void {
    \Drupal::service(ModuleInstallerInterface::class)->install(['user']);
    install_install_profile($install_state);
  }

  /**
   * Selects a site template, or presents a form to choose one.
   *
   * @param array $install_state
   *   The current install state.
   *
   * @return array|null
   *   The form to choose a site template, if any.
   */
  public static function chooseTemplate(array &$install_state): ?array {
    $recipes = \Drupal::service(RecipeHandler::class)
      // Always apply the administrator role recipe.
      ->enqueue('core/recipes/administrator_role')
      ->scan('Site');

    // Every site template on offer: the ones in the code base, then the
    // curated ones. Put them into $install_state because we have no other way
    // to pass them to the form.
    $install_state['site_templates'] = \Drupal::classResolver(SiteTemplateForm::class)
      ->getChoices(array_map(
        SiteTemplate::createFromRecipe(...),
        iterator_to_array($recipes),
      ));

    $was_interactive = $install_state['interactive'];
    // If there's only one site template on offer, local or curated, submit the
    // form programmatically. Counting only the local ones would skip the
    // question, and pick the default curated one, whenever exactly one site
    // template is in the code base.
    if (count($install_state['site_templates']) === 1) {
      $install_state['interactive'] = FALSE;
    }
    $return = install_get_form(SiteTemplateForm::class, $install_state);
    $install_state['interactive'] = $was_interactive;
    unset($install_state['site_templates']);

    return $return;
  }

  /**
   * Uninstalls the profile.
   *
   * @param array $install_state
   *   The current install state.
   */
  public static function finished(array &$install_state): void {
    \Drupal::service(ModuleInstallerInterface::class)->uninstall(['webships']);
    install_finished($install_state);

    // Clear all previous status messages to avoid clutter, including the
    // "Congratulations, you installed Drupal!" message set by
    // `install_finished()`.
    $messenger = \Drupal::messenger();
    $messenger->deleteByType($messenger::TYPE_STATUS);
  }

  /**
   * Install task to apply all queued recipes.
   *
   * Recipes that are not yet in the code base will be required using Composer,
   * then applied in a subsequent batch job.
   *
   * @param array $install_state
   *   The current install state.
   *
   * @return array
   *   A batch job to execute.
   */
  public static function applyRecipes(array &$install_state): array {
    $list = \Drupal::service(RecipeHandler::class)->list();
    // If we've already applied all the queued recipes, there's nothing to do.
    if (empty($list)) {
      return [];
    }
    // Let `install_profile_modules()` generate the initial batch job, to which
    // we will add operations.
    $batch = [
      'title' => t('Setting up your site'),
    ] + install_profile_modules($install_state);

    $operations = [];
    foreach ($list as $locator) {
      // If the locator is a directory, the recipe is already present in the
      // code base and we just need to apply it as per usual.
      if (is_dir($locator)) {
        $recipe = Recipe::createFromDirectory($locator);
        $operations = array_merge($operations, RecipeRunner::toBatchOperations($recipe));
        $operations[] = [[self::class, 'markRecipeApplied'], [$locator]];
      }
      // Otherwise, prepend an operation to require the recipe via Composer,
      // then generate an additional batch job to apply it. We prepend it so
      // that every dependency is physically present before anything is
      // applied or installed.
      else {
        array_unshift($batch['operations'], [[self::class, 'requireRecipe'], [$locator]]);
        $batch['init_message'] = t('Installing %name. This may take a few minutes.', [
          '%name' => $locator,
        ]);
      }
    }

    // Only do each recipe's batch operations once.
    foreach ($operations as $operation) {
      if (!in_array($operation, $batch['operations'], TRUE)) {
        $batch['operations'][] = $operation;
      }
    }
    return $batch;
  }

  /**
   * Uses Composer to install a recipe, then queues a batch job to apply it.
   *
   * This is a batch operation, so the batch API must be able to call it.
   *
   * @param string $package_name
   *   The name of the package to install.
   * @param array $context
   *   The current batch context.
   */
  public static function requireRecipe(string $package_name, array &$context): void {
    // Allow the recipe to scaffold files into the project; for example, a site
    // template may wish to provide a default AGENTS.md file at the project
    // root.
    ComposerExecutor::execute(
      'config',
      'extra.drupal-scaffold.allowed-packages',
      '--merge',
      '--json',
      json_encode([$package_name], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    );
    ComposerExecutor::execute(
      'require',
      $package_name,
      '--minimal-changes',
      '--update-with-all-dependencies',
    );

    // Since the list of available extensions has changed, we need to reset all
    // extension discovery caches. Reflection is the only real way to do this.
    (new \ReflectionProperty(ExtensionDiscovery::class, 'files'))
      ->setValue(NULL, []);
    \Drupal::service(ModuleExtensionList::class)->reset();
    \Drupal::service(ThemeExtensionList::class)->reset();

    // We have the recipe, so generate a batch job to apply it.
    $batch = new BatchBuilder();
    $directory = \Drupal::service(RecipeHandler::class)->getPath($package_name);
    $recipe = Recipe::createFromDirectory($directory);
    foreach (RecipeRunner::toBatchOperations($recipe) as [$callable, $arguments]) {
      $batch->addOperation($callable, $arguments);
    }
    $batch->addOperation([self::class, 'markRecipeApplied'], [$package_name]);
    batch_set($batch->toArray());

    $context['message'] = t('Installed @name', ['@name' => $recipe->name]);
  }

  /**
   * Marks a particular recipe as having been applied.
   *
   * Tracking the recipes that have been applied lets the installer recover and
   * pick up where it left off, without applying a recipe twice. Once the
   * install is done, the list of recipes is deleted.
   *
   * This is a batch operation, so the batch API must be able to call it.
   *
   * @param string $locator
   *   The path, or package name, of a recipe.
   */
  public static function markRecipeApplied(string $locator): void {
    \Drupal::service(RecipeHandler::class)->markAsApplied($locator);
  }

}
