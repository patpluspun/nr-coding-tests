<?php
/**
 * @file
 *
 * AverageRating block.
 */
namespace Drupal\nr_review\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[Block(
  id: 'average_rating_block',
  admin_label: new TranslatableMarkup('Average Movie Rating'),
  category: new TranslatableMarkup('New Relic Review')
)]
class AverageRatingBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The FormBuilder interface.
   *
   * @var Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The database service.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The RouteMatch interface.
   *
   * @var Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $formBuilder, Connection $database, RouteMatchInterface $routeMatch) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $formBuilder;
    $this->database = $database;
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
      $container->get('database'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = $this->routeMatch->getParameter('node');
    $nid = $node->id();
    $cache_tags = $node->getCacheTags();

    $stats = $this->getMovieStats($nid);

    $markup = $this->t('Rating Average: @average out of @count votes.',[
      '@average' => $stats['average'],
      '@count' => $stats['total'],
    ]);
    $content['average_rating'] = [
      '#title' => $this->t('@title Average Rating', [
        '@title' => $node->label(),
      ]),
      '#type' => 'markup',
      '#markup' => $markup,
      '#cache' => [
        'tags' => $cache_tags,
      ],
    ];
    $content['user_rating'] = $this->formBuilder
      ->getForm('\Drupal\nr_review\Form\UserRatingForm');

    return $content;
  }

  /**
   * Gets movie average votes and total votes.
   *
   * @param string $nid
   *   Node id.
   *
   * @return array
   *   Associative array of average votes and total votes.
   */
  protected function getMovieStats(string $nid = '') {
    $stats = [
      'average' => 0,
      'total' => 0,
    ];
    $query = $this->database
      ->query('SELECT rating FROM {user_rating} WHERE nid = :nid', [
        ':nid' => $nid,
      ]);
    $result = $query->fetchAll();

    $sum = 0;
    foreach ($result as $record) {
      $sum = $sum + $record->rating;
    }
    $total = count($result);
    $stats['average'] = $sum === 0 ? 0 : round($sum / $total, 2);
    $stats['total'] = $total;

    return $stats;
  }

}