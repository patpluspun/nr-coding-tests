<?php

namespace Drupal\nr_review\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flood_control\FloodWhiteList;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * New Relic User Rating form.
 */
class UserRatingForm extends FormBase {

  /**
   * The CacheTagsInvalidator interface.
   *
   * @var Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheInvalidator;

  /**
   * The database service.
   *
   * @var Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Messenger service.
   *
   * @var Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * The current user.
   *
   * @var Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The RouteMatch interface.
   *
   * @var Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The Symfony request object.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The Flood Control service.
   *
   * @var Drupal\flood_control\FloodWhiteList
   */
  protected $flood;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache_tags.invalidator'),
      $container->get('database'),
      $container->get('messenger'),
      $container->get('current_user'),
      $container->get('current_route_match'),
      $container->get('request_stack'),
      $container->get('flood')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(CacheTagsInvalidatorInterface $cacheTagsInvalidator, Connection $database, Messenger $messenger, AccountInterface $currentUser, RouteMatchInterface $routeMatch, RequestStack $requestStack, FloodWhiteList $flood) {
    $this->cacheInvalidator = $cacheTagsInvalidator;
    $this->database = $database;
    $this->messenger = $messenger;
    $this->currentUser = $currentUser;
    $this->routeMatch = $routeMatch;
    $this->request = $requestStack->getCurrentRequest();
    $this->flood = $flood;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'nr_review_user_rating_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $user = $this->currentUser;
    $uid = $user->id();
    $nid = $this->routeMatch->getParameter('node')->id();
    $query = $this->database
      ->select('user_rating', 'ur')
      ->condition('ur.uid', $uid)
      ->condition('ur.nid', $nid)
      ->fields('ur', ['rating', 'ip']);

    $result = $query->execute()->fetchObject();

    if (empty($result)) {
      $form = $this->ratingForm($form, $form_state);
    }
    else {
      $form['user_rating'] = [
        '#type' => 'container',
      ];
      $content = $this->t('@name has voted @vote on this movie from @ip.', [
        '@name' => $user->getAccountName(),
        '@vote' => $result->rating,
        '@ip' => $result->ip,
      ]);
      $form['user_rating']['voted'] = [
        '#markup' => $content,
      ];
    }

    return $form;
  }

  /**
   * User Rating form for movies.
   */
  public function ratingForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    $form['user_rating'] = [
      '#type' => 'container',
    ];
    $form['user_rating']['rating'] = [
      '#type' => 'select',
      '#title' => $this->t('Rate this movie!'),
      '#weight' => '0',
      '#options' => [
        1 => $this->t('One star'),
        2 => $this->t('Two stars'),
        3 => $this->t('Three stars'),
        4 => $this->t('Four stars'),
        5 => $this->t('Five stars'),
      ],
    ];

    $form['user_rating']['actions'] = [
      '#type' => 'container',
      '#weight' => '2',
    ];
    $form['user_rating']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Rate It'),
      '#weight' => '2',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    $flood = $this->getFloodData();

    if (!$this->flood
      ->isAllowed($flood['event'], $flood['threshold'], $flood['window'])) {
      $form_state->setErrorByName('', $this->t('Slow your roll, please.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    if (!empty($values['user_rating']['rating'])) {
      $user = $this->currentUser();
      $node = $this->routeMatch->getParameter('node');
      $rating = $values['user_rating']['rating'];
      $ip = $this->request->getClientIp();

      $this->database->insert('user_rating')
        ->fields([
          'uid' => $user->id(),
          'nid' => $node->id(),
          'rating' => $rating,
          'ip' => $ip,
        ])->execute();

      // Log the successful user submission in flood.
      $flood = $this->getFloodData();
      $this->flood->register($flood['event'], $flood['window']);

      // Invalidate cache tags.
      $cache_tags = $node->getCacheTags();
      $cache_tags[] = 'nr.rating.average.' . $node->id();
      $this->cacheInvalidator->invalidateTags($cache_tags);
    }
  }

  /**
   * Default parameters for flood control.
   *
   * @return array
   *   Array of settings for flood control.
   */
  protected function getFloodData() {
    $form_id = $this->getFormId();
    return [
      'event' => $form_id . '.form_limit',
      'threshold' => 5,
      'window' => 3600,
    ];
  }

}
