# MBTA Demo

A custom Drupal 8 website that will connect to the MBTA api and display route information.

## Installation:

To install, run the following commands.

`composer install --no-dev`

`ddev config`

`ddev start`

`drush si config_installer`

Add the following to your settings.local.php:

`$config['config_split.config_split.development']['status'] = FALSE;`

Once the site is operational, ensure the MBTA custom module is enabled. Then, go to the following URL:

`/mbta/routes`
