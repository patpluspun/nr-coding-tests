<?php
/**
 * @file
 *
 * Install file for nr_review module.
 */
use Drupal\taxonomy\Entity\Term;

/**
 * Populates Categories vocabulary with movie genres.
 */
function nr_review_deploy_10001(&$sandbox) {
  $vid = 'categories';

  // A small sample of genreas from IMDB, which OMDB uses.
  $genres = [
    'Action',
    'Adventure',
    'Animation',
    'Anime',
    'Biopic',
    'Blaxploitation',
    'Comedy',
    'Courtroom',
    'Crime',
    'Cult',
    'Dark',
    'Detective',
    'Disaster',
    'Documentary',
    'Drama',
    'Epic',
    'Experimental',
    'Family',
    'Fantasy',
    'Historical',
    'Horror',
    'Independent',
    'Martial Arts',
    'Mockumentary',
    'Monster',
    'Musical',
    'Noir',
    'Parody',
    'Period Piece',
    'Political',
    'Post-Apocalyptic',
    'Prison',
    'Psychological',
    'Romantic',
    'Satire',
    'Science Fiction',
    'Screwball',
    'Slapstick',
    'Slasher',
    'Sports',
    'Spy',
    'Suspense',
    'Superhero',
    'Supernatural',
    'Teen',
    'Thriller',
    'War',
    'Western',
  ];

  foreach ($genres as $genre) {
    $term = Term::create([
      'name' => $genre,
      'vid' => $vid,
    ]);
    $term->save();
    \Drupal::messenger()->addMessage(t('@term was saved.', [
      '@term' => $term->label(),
    ]));
  }

  \Drupal::messenger()->addMessage(t('@count terms were added to @vid vocabulary', [
    '@count' => count($genres),
    '@vid' => $vid,
  ]));
}