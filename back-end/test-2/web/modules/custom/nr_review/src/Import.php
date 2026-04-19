<?php

namespace Drupal\nr_review;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * New Relic Movie Review Import service.
 *
 * Pulls movie data from OMDB for quick populating of data.
 */
class Import {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The OMDB Api Key.
   *
   * @var string
   */
  private $apiKey = '4c07c134';

  /**
   * The EntityTypeManager object.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * The StringTranslation trait.
   *
   * @var \Drupal\Core\StringTranslation\StringTranslationTrait
   */
  protected $stringTranslation;

  /**
   * The HTTP Client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  public $httpClient;

  /**
   * The FileRepository service.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected $fileRepository;

  /**
   * The FileUsage service.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * The Messenger service.
   *
   * @var Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('string_translation'),
      $container->get('http_client'),
      $container->get('file.repository'),
      $container->get('file.usage'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config, EntityTypeManagerInterface $entityTypeManager, TranslationManager $stringTranslation, ClientInterface $httpClient, FileRepositoryInterface $fileRepository, FileUsageInterface $fileUsage, Messenger $messenger) {
    $this->apiKey = $config->get('nr_review.settings')->get('apiKey');
    $this->entityManager = $entityTypeManager;
    $this->stringTranslation = $stringTranslation;
    $this->httpClient = $httpClient;
    $this->fileRepository = $fileRepository;
    $this->fileUsage = $fileUsage;
    $this->messenger = $messenger;
  }

  /**
   * Processes a json payload.
   *
   * @param string $payload
   *   The json payload.
   *
   * @return array
   *   A structured array of movie data.
   */
  public function processJson(string $payload) {
    $json = Json::decode($payload);
    if (json_last_error() === JSON_ERROR_NONE) {
      return $json;
    }
  }

  /**
   * Fetches a movie by title from OMDB.
   *
   * @param string $title
   *   The movie title.
   *
   * @param string $year
   *   Optional: The movie year.
   *
   * @return array
   *   A php array decoded from json from OMDB.
   */
  public function fetchMovie(string $title = '', string $year = '') {
    $url = 'https://www.omdbapi.com';
    $data = [];

    try {
      $query = [
        'apiKey' => $this->apiKey,
        't' => str_replace(' ', '+', trim($title)),
      ];
      // OMDB defaults to the newest title, so add a year.
      if (!empty($year) && is_numeric($year)) {
        $query['y'] = $year;
      }
      $response = $this->httpClient->get($url, [
        'query' => $query,
      ]);
      $data = $this->processJson($response->getBody()->getContents());
      if ($data['Response'] == 'False') {
        $message = $this->t('The movie @title was not found.', [
          '@title' => $title,
        ]);
        $this->messenger->addWarning($message);
        return [];
      }
    }
    catch (RequestException $e) {
      $message = $this->t('Failed to fetch @title.\r\nError: @e', [
        '@title' => $title,
        '@e' => $e->getMessage(),
      ]);
      $this->messenger->addWarning($message);
    }

    return $data;
  }

  /**
   * Processes the uploaded json data into Movie nodes.
   *
   * @param array $movie
   *   A structured array of movie data.
   */
  public function processMovie(array $movie = []) {
    if (empty($movie)) {
      $message = $this->t('No movie data was found.');
      $this->messenger->addWarning($message);
      return;
    }

    $title = $movie['Title'] . ' (' . $movie['Year'] . ')';
    // Handle the movie genre.
    $genres = $this->processGenre($movie['Genre']);
    // Handle the directors.
    $directors = $this->processTalent($movie['Director'], 'director');
    // Handle the actors.
    $actors = $this->processTalent($movie['Actors'], 'actor');
    // Handle the writers.
    $writers = $this->processTalent($movie['Writer'], 'writer');
    // Handle poster.
    $poster = $this->processPoster($title, $movie['imdbID']);

    $nodeManager = $this->entityManager->getStorage('node');
    $node = $nodeManager->create([
      'type' => 'movie',
      'title' => $title,
    ]);
    $node->set('field_imdb_id', $movie['imdbID']);
    $node->set('field_year', $movie['Year']);
    $node->set('field_categories', $genres);
    $node->set('field_director', $directors);
    $node->set('field_writer', $writers);
    $node->set('field_actors', $actors);
    $node->set('body', [
      'value' => $movie['Plot'],
      'format' => 'basic_html',
    ]);
    $node->set('field_poster', $poster);
    $node->set('field_box_office', str_replace(['$', ','], '', $movie['BoxOffice']));
    $node->set('field_imdb_rating', $movie['imdbRating']);
    $node->set('field_imdb_votes', str_replace(',', '', $movie['imdbVotes']));
    $node->set('field_rotten_tomatoes', str_replace('%', '', $movie['Ratings'][1]['Value']));
    $node->set('field_metacritic', $movie['Metascore']);
    $node->setPublished();
    $node->save();

    $message = $this->t('Movie @title has been saved.', [
      '@title' => $node->label(),
    ]);
    $this->messenger->addMessage($message);
  }

  /**
   * Finds or creates a category for imported movie genres.
   *
   * @param string $genres
   *   A comma delimited of movie genres.
   *
   * @return array
   *   An associative array suitable for saving as an entity reference.
   */
  protected function processGenre(string $genres = '') {
    $categories = $terms = [];
    $vid = 'categories';

    $categories = $this->convertString($genres);

    foreach ($categories as $category) {
      $terms[] = $this->findOrCreateEntity($category, 'taxonomy_term', $vid);
    }

    return $terms;
  }

  /**
   * Creates or finds a director, actor, or writer.
   *
   * @param string $talent
   *   A comma delimited string of talent names.
   *
   * @param string $bundle
   *   The node type, i.e. director, actor, writer.
   *
   * @return array
   *   An associative array suitable for saving as an entity reference.
   */
  protected function processTalent(string $talent = '', string $bundle = '') {
    $talents = $nodes = [];

    $talents = $this->convertString($talent);

    foreach ($talents as $talent) {
      $fields = [
        'body' => [
          'value' => 'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec qu',
          'format' => 'basic_html',
        ],
      ];
      $nodes[] = $this->findOrCreateEntity($talent, 'node', $bundle, $fields);
    }

    return $nodes;
  }

  /**
   * Fetch poster from OMDB's url resource.
   *
   * @param string $title
   *   The movie title.
   *
   * @param string $id
   *   The movie IMDB id.
   *
   * @return array
   *   An array suitable to save as an image in a Drupal field.
   */
  protected function processPoster(string $title = '', string $id = '') {
    $image = [];

    // Fetch the image.
    try {
      $url = 'https://img.omdbapi.com';
      $response = $this->httpClient->get($url, [
        'query' => [
          'apiKey' => $this->apiKey,
          'i' => $id,
        ],
      ]);
      $image_data = $response->getBody()->getContents();
      $content_type = $response->getHeaderLine('Content-Type');
      $image_ext = array_pop(explode('/', $content_type));
      if ($image_ext === 'jpeg') {
        $image_ext = 'jpg';
      }

      $image_schema = 'public://';
      $replace = [' ', '(', ')', ':', '.', '/', '\\'];
      $safe = ['-', ''];
      $image_name = strtolower(str_replace($replace, $safe, $title));
      $image_path = $image_schema . $image_name . '.' . $image_ext;
      $file = $this->fileRepository
        ->writeData($image_data, $image_path);
      $file->setPermanent();
      $file->save();

      $message = $this->t('@title poster saved to @path.', [
        '@title' => $title,
        '@path' => $image_path,
      ]);
      $this->messenger->addMessage($message);

      $image = [
        'target_id' => $file->id(),
        'alt' => $title,
        'title' => $title,
      ];
    }
    catch (RequestException $e) {
      $message = $this->t('Failed to fetch poster for @title.\r\nError: @e', [
        '@url' => $title,
        '@e' => $e->getMessage(),
      ]);
      $this->messenger->addWarning($message);
    }

    return $image;
  }

  /**
   * Converts a comma delimited string to an array of one or more items.
   *
   * @param string $string
   *   A comma delimited string.
   *
   * @return array
   *   An array of items split by comma and trimmed.
   */
  protected function convertString(string $string = '') {
    $items = [];
    if (str_contains($string, ',')) {
      // Split the string into an array.
      $items = array_map('trim', explode(',', $string));
    }
    else {
      // There is only one item. Trim whitespace and make array.
      $items[] = trim($string);
    }

    return $items;
  }

  /**
   * Uses entityManager to find existing entities by title, or creates
   * new ones if not found.
   *
   * @param string $title
   *   Title to search for, or use for new entities.
   *
   * @param string $type
   *   The type of entity.
   *
   * @param string $bundle
   *   The bundle or vid of the entity.
   *
   * @param array $fields
   *   An array keyed by field name with the field value as the value.
   *
   * @return array
   *   An array of entity keys ready to be included by reference.
   */
  protected function findOrCreateEntity(string $title, string $type = 'node', string $bundle = '', array $fields = []) {
    switch ($type) {
      case 'node':
        $label = 'title';
        $bundle_type = 'type';
        break;

      case 'taxonomy_term':
        $label = 'name';
        $bundle_type = 'vid';
        break;

      default:
        break;
    }

    $entityManager = $this->entityManager->getStorage($type);
    $entity = $entityManager->loadByProperties([
      $label => $title,
      $bundle_type => $bundle,
    ]);

    if (!empty($entity)) {
      $entity = reset($entity);
      $message = $this->t('Found @bundle @entity.', [
        '@bundle' => $bundle,
        '@entity' => $entity->label(),
      ]);
      $entities = ['target_id' => $entity->id()];
    }
    else {
      $entity = $entityManager->create([
        $bundle_type => $bundle,
        $label => $title,
      ]);
      if (!empty($fields)) {
        // OMDB doesn't have talent bio, but maybe IMDB does, and
        // this section could be used to populate it if passed in.
        // We'll pass Lorem Ipsum for body as an example though.
        foreach ($fields as $field => $value) {
          if ($entity->hasField($field)) {
            $entity->set($field, $value);
          }
        }
      }
      if ($type === 'node') {
        $entity->setPublished();
      }
      $entity->save();
      $message = $this->t('Created @bundle @entity.', [
        '@bundle' => $bundle,
        '@entity' => $entity->label(),
      ]);
      $entities = ['target_id' => $entity->id()];
    }
    $this->messenger->addStatus($message);

    return $entities;
  }

  /**
   * Returns a list of movies to fetch.
   * Reset with:
   * `ddev drush php:eval "\Drupal::state()->set('nr_review.first_import, TRUE);"
   *
   * @return
   *   An array of movie titles.
   */
  public function getSampleList() {
    return [
      'Pulp Fiction' => '1994',
      'A Few Good Men' => '1992',
      'Ace Ventura Pet Detective' => '1994',
      'Interstellar' => '2014',
      'Shaolin Soccer' => '2001',
      'Hereditary' => '2018',
      'Shrek' => '2001',
      '2001 A Space Odyssey' => '1968',
      'How To Train Your Dragon' => '2010',
      'Friday The 13th' => '1980',
    ];
  }
}