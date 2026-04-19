<?php
/**
 * @file
 *
 * PopularMovie block.
 */
namespace Drupal\nr_review\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
Use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Block(
  id: 'popular_movie_block',
  admin_label: new TranslatableMarkup('Popular Movies'),
  category: new TranslatableMarkup('New Relic Review')
)]
class PopularMovieBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The database service.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The EntityTypeManager interface.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database, EntityTypeManagerInterface $entityManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    $this->entityManager = $entityManager->getStorage('node');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('entity_type.manager')
    );
  }

  public function build() {
    $content = [];

    $popular = $this->mostPopular(5) ?? [$this->t('No votes yet.')];
    $content['popular'] = [
      '#title' => $this->t('Most Popular Movies'),
      '#theme' => 'item_list',
      '#items' => $popular,
      '#list_type' => 'ol',
    ];

    $rated = $this->highestRated(5) ?? [$this->t('No ratings yet.')];
    $content['highest_rated'] = [
      '#title' => $this->t('Highest Rated Movies'),
      '#theme' => 'item_list',
      '#items' => $rated,
      '#list_type' => 'ol',
    ];

    return $content;
  }

  /**
   * Queries user_rating table to find the most voted for movies.
   *
   * @param int $count
   *   Optional param to change the number of items returned.
   *
   * @return array
   *   Array of translated text ready for an item list.
   */
  protected function mostPopular(int $count = 5) {
    $data = [];

    $query = $this->database->select('user_rating', 'ur');
    $query->join('node_field_data', 'n', 'ur.nid = n.nid');
    $query->fields('n', ['nid', 'title']);
    $query->addExpression('COUNT(ur.nid)', 'nid_count');
    $query->groupBy('n.nid');
    $query->orderBy('nid_count', 'DESC');
    $query->range(0, $count);

    $results = $query->execute();

    if (!empty($results)) {
      foreach ($results as $record) {
        $data[] = $this->t('@title [@count votes]', [
          '@title' => $record->title,
          '@count' => $record->nid_count,
        ]);
      }
    }

    return $data;
  }

  /**
   * Queries user_rating table to find the highest rated movies.
   *
   * @param int $count
   *   Optional param to change the number of items returned.
   *
   * @return array
   *   Array of translated text ready for an item list.
   */
  protected function highestRated(int $count = 5) {
    $data = [];

    $query = $this->database->select('user_rating', 'ur');
    $query->join('node_field_data', 'n', 'ur.nid = n.nid');
    $query->fields('n', ['nid', 'title']);
    $query->addExpression('SUM(ur.rating)', 'rating_sum');
    $query->groupBy('n.nid');
    $query->orderBy('rating_sum', 'DESC');
    $query->range(0, $count);

    $results = $query->execute();

    if (!empty($results)) {
      foreach ($results as $record) {
        $data[] = $this->t('@title [@rating stars]', [
          '@title' => $record->title,
          '@rating' => $record->rating_sum,
        ]);
      }
    }

    return $data;
  }

}