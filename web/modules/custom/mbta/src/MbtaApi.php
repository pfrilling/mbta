<?php

namespace Drupal\mbta;

use Drupal\Core\Template\Attribute;

class MbtaApi {

  protected $stops;

  public function __construct() {
    $this->stops = $this->getStops();
  }

  private function getStops() {
    $stops = json_decode($this->call('/stops', 86400));

    $cid = 'mbta:stop:names';

    if ($cache = \Drupal::cache('mbta')->get($cid)) {
      $data = $cache->data;
    }
    else {
      $data = [];

      foreach($stops->data as $stop) {
        $data[$stop->id] = $stop->attributes->name;
      }

      \Drupal::cache('mbta')->set($cid, $data, REQUEST_TIME + (86400));
    }

    return $data;
  }

  private function getStopName($id) {

    return $this->stops[$id];
  }

  // Call the MBTA api.
  private function call($path, int $expiration = 180) {

    $uri = 'https://api-v3.mbta.com/' . $path;

    // Setup a cache ID based on the path of the API.
    $cid = 'mbta:' . $path;

    $data = NULL;

    // Check to see if this has already been cached.
    if ($cache = \Drupal::cache('mbta')->get($cid)) {
      $data = $cache->data;
    }
    else {
      // Check to see if we cached the last modified date of this requst.
      if ($date_cache = \Drupal::cache('mbta')->get($cid . ':last-modified')) {
        $last_modified = $date_cache->data;
      }
      else {
        $last_modified = date('D, j M Y H:i:s T');
      }

      try {
        $response = \Drupal::httpClient()->get($uri, ['headers' => [
          'Accept' => 'application/json',
          'If-Modified-Since' => $last_modified,
        ]]);

        // Get the last modified date.
        $last_modified = $response->getHeaders()['last-modified'][0];
        \Drupal::cache('mbta')->set($cid . ':last-modified', $last_modified);

        // Get the json.
        $data = (string) $response->getBody();

        // Set the cache and expire it in 3 minutes.
        \Drupal::cache('mbta')->set($cid, $data, REQUEST_TIME + ($expiration));

      }
      catch (RequestException $e) {
        return FALSE;
      }
    }

    return $data;
  }

  // Get the routes.
  private function getRoutes () {
    // Call the api to get new data.
    $routes = $this->call('routes');

    // Decode the cached routes.
    $routes = json_decode($routes);

    if (!empty($routes->data)) {
      $items = [];

      $render = [];

      foreach ($routes->data as $route) {
        $items[$route->attributes->fare_class][] = [
          'name' => $route->attributes->long_name,
          'link' => '/mbta' . $route->links->self,
          'link_attributes' => new Attribute([
            'style' => 'color: #' . $route->attributes->text_color . ';'
          ]),
          'attributes' => new Attribute([
            'style' => 'background-color: #' . $route->attributes->color . ';color: #' . $route->attributes->text_color . ';'
          ]),
        ];
      }

      foreach (array_keys($items) as $key) {
        $render[] = [
          '#theme' => 'mbta_routes',
          '#items' => $items[$key],
          '#heading' => $key,
        ];
      }
    }
    else {
      $render = [
        '#markup' => t('An error occurred while accessing routes.')
      ];
    }

    return $render;
  }

  /**
   * @param \Drupal\mbta\string $route_id
   *
   * @return array
   */
  private function getSchedule($route_id) {
    $schedule = json_decode($this->call('/schedules?page[limit]=50&filter[route]=' . $route_id));

    $items = [];

    if (!empty($schedule->data)) {
      foreach ($schedule->data as $key => $stop) {
        $items[$key] = [
          'name' => $this->getStopName($stop->relationships->stop->data->id),
          'arrival' => $stop->attributes->arrival_time,
          'departure' => $stop->attributes->departure_time,
        ];
      }

      $render[] = [
        '#theme' => 'mbta_schedule',
        '#items' => $items,
        '#heading' => t('Upcoming schedule for %route route', ['%route' => $schedule->data[0]->relationships->route->data->id]),
      ];
    }
    else {
      $render = [
        '#markup' => t('No upcoming schedule is currently available for this route.')
      ];
    }


    return $render;
  }

  public function getRouteTable() {
    return $this->getRoutes();
  }

  public function getRouteSchedule($route_id) {
    return $this->getSchedule($route_id);
  }
}