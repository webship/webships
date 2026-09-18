<?php

declare(strict_types=1);

namespace Drupal\webships;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Link;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Defines a value object with information about an API site template.
 *
 * @internal
 *   Everything in the Webships installer is internal and may be changed or
 *   removed at any time without warning. External code should not interact
 *   with this class.
 */
final readonly class SiteTemplate {

  /**
   * The path of the recipe in the file system, or its package name.
   */
  public string $locator;

  /**
   * Informational links about the site template (documentation, and so on).
   *
   * All must point to external URLs.
   *
   * @var list<\Drupal\Core\Link>
   */
  public array $links;

  public function __construct(
    public string $name,
    ?string $path = NULL,
    string $package = '',
    public ?string $description = NULL,
    array $links = [],
    public ?string $creator = NULL,
  ) {
    if ($path) {
      assert(is_dir($path));
      $this->locator = $path;
    }
    else {
      assert($package !== '');
      $this->locator = $package;
    }

    $to_link = function (array|string $link): Link {
      $link = Link::fromTextAndUrl(
        $link['text'] ?? new TranslatableMarkup('More info'),
        Url::fromUri($link['url'] ?? $link),
      );
      assert($link->getUrl()->isExternal());
      return $link;
    };
    $this->links = array_map($to_link, $links);
  }

  /**
   * Constructs an instance of this class from a recipe.
   *
   * Site templates declare their Webships installer metadata, such as the
   * creator and the links, under `extra.webships_installer` in recipe.yml.
   *
   * @param \Drupal\Core\Recipe\Recipe $recipe
   *   The recipe.
   */
  public static function createFromRecipe(Recipe $recipe): self {
    $extra = $recipe->getExtra('webships_installer');

    $links = array_filter(
      $extra['links'] ?? [],
      fn (array|string $link): bool => UrlHelper::isExternal(
        is_array($link) ? ($link['url'] ?? '') : $link,
      ),
    );

    return new self(
      $recipe->name,
      $recipe->path,
      '',
      $recipe->description,
      array_values($links),
      $extra['creator'] ?? NULL,
    );
  }

}
