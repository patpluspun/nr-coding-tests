Instructions for setup:

OMDB Api key to fetch movies: Go to https://www.omdbapi.com/ and signup for a key, or you can use mine:
`4c07c134`
Add that to `nr_review.settings.yml`. I would've added a config form for that but was crunched for time.

Run DDEV setup as normal.
Run `ddev drush pm:install nr_review`
To populate the database, run `ddev drush nr:import`
On first run it will ask if you want to use the default list.  Hit yes.
To add more movies, re-run the command and enter the title and year of the movie you want.  Year is optional, and if excluded it will grab the newest movie with that title.

Continue testing.  Flood Control is set up and protects the forms, so after rating five movies you'll have to clear the block from the backend.  
The import is based very heavily off of my previous NR code test, while everything else was developed from scratch.

I used an LLM to help generate the hook_views_data code since it has literally been years since I've done that, but that is the only code here that was not written by me.
