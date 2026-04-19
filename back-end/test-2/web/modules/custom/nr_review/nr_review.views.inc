<?php
/**
 * @file
 *
 * NR User Rating views file.
 */

/**
 * Implements hook_views_data().
 */
function nr_review_views_data() {
  $data = [];

  $data['user_rating'] = [];
  $data['user_rating']['table'] = [];

  // Define the group name for the Views UI.
  $data['user_rating']['table']['group'] = t('User Ratings');

  // Define the base table.
  $data['user_rating']['table']['base'] = [
    'field' => 'id',
    'title' => t('User Ratings'),
    'help' => t('Contains ratings provided by users for movies.'),
  ];

  $data['user_rating']['table']['join'] = [
    'node_field_data' => [
      'left_field' => 'nid',
      'field' => 'nid',
    ],
    'users_field_data' => [
      'left_field' => 'uid',
      'field' => 'uid',
    ],
  ];

  // ID Field.
  $data['user_rating']['id'] = [
    'title' => t('Rating ID'),
    'help' => t('The primary key for the rating.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'numeric'],
    'argument' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
  ];

  // UID Field & Relationship to Users.
  $data['user_rating']['uid'] = [
    'title' => t('User UID'),
    'help' => t('The user who provided the rating.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'numeric'],
    'argument' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'relationship' => [
      'base' => 'users_field_data',
      'base field' => 'uid',
      'id' => 'standard',
      'label' => t('Rating User'),
    ],
  ];

  // NID Field & Relationship to Content (Nodes).
  $data['user_rating']['nid'] = [
    'title' => t('Movie NID'),
    'help' => t('The movie node associated with the rating.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'numeric'],
    'argument' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'relationship' => [
      'base' => 'node_field_data',
      'base field' => 'nid',
      'id' => 'standard',
      'label' => t('Rated Movie'),
    ],
  ];

  // Rating Field.
  $data['user_rating']['rating'] = [
    'title' => t('Rating Score'),
    'help' => t('The numeric rating value.'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
  ];

  // IP Address Field.
  $data['user_rating']['ip'] = [
    'title' => t('IP Address'),
    'help' => t('The IP address of the user.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
  ];

  return $data;
}

/**
 * Implements hook_views_data_alter().
 */
function nr_review_views_data_alter(array &$data) {
  // Add entity reference options for Director, Actor, Writer, and Genre.
  $fields = [
    'field_director',
    'field_actors',
    'field_writer',
    'field_categories',
  ];
  foreach ($fields as $field) {
    $data['node__' . $field][$field . '_target_id']['filter']['id'] = 'entity_reference';
  }
}
