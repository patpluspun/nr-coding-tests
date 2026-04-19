<?php

namespace Drupal\nr_review\Commands;

use Drupal\Core\Messenger\Messenger;
use Drupal\Core\State\StateInterface;
use Drupal\nr_review\Import;
use Drush\Commands\DrushCommands;

/**
 * New Relic Movie Review import commands for drush.
 */
class ImportCommands extends DrushCommands {

  /**
   * The State interface.
   *
   * @var Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The Messenger service.
   *
   * @var Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * Import service.
   *
   * @var Drupal\nr_review\Import
   */
  protected $importer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
      $container->get('messenger'),
      $container->get('nr_review.import')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(StateInterface $state, Messenger $messenger, Import $importer) {
    $this->state = $state;
    $this->messenger = $messenger;
    $this->importer = $importer;
  }

  /**
   * Imports a movie by title from OMDB.
   *
   * @param string $title
   *   The title to import.
   *
   * @command nr:import
   * @aliases nr-import
   * @usage drush nr:import "Teenage Mutant Ninja Turtles"
   */
  public function runImport(string $title = '') {
    $items = [];
    $year = '';
    // If no title is passed, check the provided list via StateAPI.
    // If it's been imported already, we just ask for a title.
    if (empty($title)) {
      if ($this->state->get('nr_review.first_import', TRUE)) {
        $confirm = $this->io()
          ->confirm('Do you want to import the sample list?');
        if (!$confirm) {
          goto input;
        }
        $items = $this->importer->getSampleList();
        $this->state->set('nr_review.first_import', FALSE);
      }
      else {
        input:
        $movie = $this->io()->ask('Enter the title of a movie to import');
        $year = $this->io()->ask('Enter the year, for remakes using the same title in case you want the original. You can leave this blank.');
        $items[$movie] = $year;
      }
    }

    if (!empty($items)) {
      foreach ($items as $movie => $year) {
        $message = dt('Importing @title from OMDB...', [
          '@title' => $movie,
        ]);
        $this->output()->writeLn($message);

        if (empty($year) || !is_numeric($year)) {
          $year = '';
        }
        if ($data = $this->importer->fetchMovie($movie, $year)) {
          $this->importer->processMovie($data);
        }
      }
    }
  }

}
