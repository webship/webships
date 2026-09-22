<?php

declare(strict_types=1);

namespace Drupal\webships\Form;

use Composer\InstalledVersions;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webships\RecipeHandler;
use Drupal\webships\SiteTemplate;
use GuzzleHttp\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Defines a form to choose an API site template.
 *
 * The Webships installer enables no modules of its own. The site template
 * chosen here is the only thing that decides what the site has.
 *
 * @internal
 *   Everything in the Webships installer is internal and may be changed or
 *   removed at any time without warning. External code should not interact
 *   with this class.
 */
final class SiteTemplateForm extends FormBase {

  use AutowireTrait;

  /**
   * An identifier for this task, to mark it as completed.
   */
  public const string TASK_ID = 'template';

  /**
   * The site template chosen when none is named, if it is available.
   */
  public const string DEFAULT_TEMPLATE = 'webships_starter';

  public function __construct(
    protected ClientInterface $http,
    protected RecipeHandler $recipeHandler,
    #[Autowire(service: 'cache.default')]
    protected CacheBackendInterface $cache,
    #[Autowire(param: 'site.path')]
    protected string $sitePath,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    // Keep this form ID stable: non-interactive installs name the chosen site
    // template with `installer_site_template_form.add_ons=<name>`.
    return 'installer_site_template_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?array $install_state = NULL): array {
    // @see \Drupal\webships\Installer\InstallTasks::chooseTemplate()
    $all_choices = $install_state['site_templates'] ?? $this->getChoices($install_state['recipes'] ?? []);

    // Must be called `add_ons` to agree with the form ID above. In the
    // interactive installer nothing is preselected: the user chooses.
    $form['add_ons'] = [
      '#type' => 'radios',
      '#title' => $this->t('Site template'),
      '#options' => [],
      '#required' => TRUE,
      '#required_error' => $this->t('Choose a site template.'),
    ];
    // Installing non-interactively (for example with Drush) chooses the
    // default site template, or the first one when it is not available.
    if (empty($install_state['interactive'])) {
      $form['add_ons']['#default_value'] = array_key_exists(self::DEFAULT_TEMPLATE, $all_choices)
        ? self::DEFAULT_TEMPLATE
        : array_key_first($all_choices);
    }

    foreach ($all_choices as $key => $choice) {
      assert($choice instanceof SiteTemplate);

      $form['add_ons']['#options'][$key] = $choice->name;
      $form['add_ons'][$key] = [
        '#description' => $choice->description,
        '#locator' => $choice->locator,
      ];
    }
    if (array_key_exists(self::DEFAULT_TEMPLATE, $all_choices)) {
      $form['add_ons'][self::DEFAULT_TEMPLATE]['#weight'] = -100;
    }

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
        '#button_type' => 'primary',
      ],
    ];
    $form['#title'] = $this->t('Choose a site template');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $choice = $form_state->getValue('add_ons');
    // Ask for a site template when none is chosen. The error of the required
    // radios comes first; this covers any other empty or unknown value.
    if (!is_string($choice) || $choice === '' || !isset($form['add_ons'][$choice])) {
      $form_state->setErrorByName('add_ons', $this->t('Choose a site template.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $choice = $form_state->getValue('add_ons');
    $this->recipeHandler->enqueue($form['add_ons'][$choice]['#locator']);
    // Mark the task as finished.
    $GLOBALS['install_state']['parameters'][self::TASK_ID] = INSTALL_TASK_SKIP;
  }

  /**
   * Returns every site template the installer can offer.
   *
   * @param array<string, \Drupal\webships\SiteTemplate> $local
   *   The site templates already in the code base, keyed by machine name.
   *
   * @return array<string, \Drupal\webships\SiteTemplate>
   *   The local site templates, then the curated ones. A site template already
   *   in the code base wins over the curated entry of the same name.
   */
  public function getChoices(array $local): array {
    return $local + array_map(
      fn (array $values): SiteTemplate => new SiteTemplate(
        name: $values['name'],
        path: $values['path'] ?? NULL,
        package: $values['package'] ?? '',
        description: $values['description'] ?? NULL,
        links: $values['links'] ?? [],
        creator: $values['creator'] ?? NULL,
      ),
      iterator_to_array($this->getCuratedList()),
    );
  }

  /**
   * Returns a curated list of site template information.
   *
   * @return iterable<string, array>
   *   Information about site templates, keyed by machine name.
   */
  private function getCuratedList(): iterable {
    $messenger = $this->messenger();

    // Allow the list of site templates to be defined per-site. This is helpful
    // for testing, or for hosts that want to limit the available choices. It
    // comes first, because its site templates can be local directories, which
    // do not need Composer. This is an official extension point.
    // @api
    $list = @include $this->sitePath . '/site-templates.php';
    if (is_iterable($list)) {
      return $list;
    }

    // Ensure the file system is writable. If it is not, there is no point in
    // showing site templates that would have to be downloaded.
    ['install_path' => $project_root] = InstalledVersions::getRootPackage();
    if (!is_writable($project_root)) {
      $messenger->addWarning(
        $this->t('Only showing site templates that are already downloaded, because %dir is not writable.', [
          '%dir' => realpath($project_root),
        ]),
      );
      return [];
    }

    // If the original file exists, read it directly. It is not included in
    // releases of the installer.
    // @see .gitattributes
    $file = dirname(__DIR__, 2) . '/site-templates.yml';
    if (file_exists($file)) {
      return Yaml::decode(file_get_contents($file));
    }

    // @see site-templates.yml
    $url = 'https://git.drupalcode.org/api/v4/projects/project%2Fwebships/repository/files/site-templates.yml/raw?ref=3.0.x';
    $cid = hash('xxh32', $url);

    $cached = $this->cache->get($cid);
    if ($cached) {
      return $cached->data;
    }

    $list = [];
    try {
      $list = Yaml::decode((string) $this->http->request('GET', $url)->getBody());
    }
    catch (ParseException | ClientExceptionInterface $e) {
      $messenger->addWarning($e->getMessage());
    }
    $this->cache->set($cid, $list);
    return $list;
  }

}
