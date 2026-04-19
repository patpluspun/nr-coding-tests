<?php
/**
 * @file
 *
 * UserRating block.
 */
namespace Drupal\nr_review\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[Block(
  id: 'user_rating_block',
  admin_label: new TranslatableMarkup('User Movie Rating'),
  category: new TranslatableMarkup('New Relic Review')
)]
class UserRatingBlock extends BlockBase {

  public function build() {
    $form = \Drupal::formBuilder()
      ->getForm('\Drupal\nr_review\Form\UserRatingForm');

    return $form;
  }

}