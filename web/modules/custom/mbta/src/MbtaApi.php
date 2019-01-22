<?php

namespace Drupal\mbta;

use Drupal\Core\Template\Attribute;
use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A service to interface with the MBTA api.
 */
class MbtaApi {

  /**
   * @var array
   */
  protected $stops;

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * MbtaApi constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   */
  public function __construct(CacheBackendInterface $cache) {
    $this->cache = $cache;
    $this->stops = $this->getStops();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.mbta')
    );
  }

  /**
   * Call the API and load all available stops.
   *
   * @return array
   *   An array of stop names keyed by the stop id.
   */
  private function getStops() {
    $stops = json_decode($this->call('/stops', 86400));

    $cid = 'mbta:stop:names';

    if ($cache = $this->cache->get($cid)) {
      $data = $cache->data;
    }
    else {
      $data = [];

      foreach ($stops->data as $stop) {
        $data[$stop->id] = $stop->attributes->name;
      }

      $this->cache->set($cid, $data, REQUEST_TIME + (86400));
    }

    return $data;
  }

  /**
   * Get the stop name based on the ID provided.
   *
   * @param string|int $id
   *   The stop id from the MBTA api.
   *
   * @return string
   *   The stop name from the MBTA api.
   */
  private function getStopName($id) {

    return $this->stops[$id];
  }

  /**
   * Call the MBTA api and store the results.
   *
   * @param string $path
   *   The path to retrieve from the MBTA api.
   * @param int $expiration
   *   Seconds to cache the API data.
   *
   * @return object
   *   The object returned from the api.
   */
  private function call($path, int $expiration = 180) {

    $uri = 'https://api-v3.mbta.com/' . $path;

    // Setup a cache ID based on the path of the API.
    $cid = 'mbta:' . $path;

    $data = NULL;

    // Check to see if this has already been cached.
    if ($cache = $this->cache->get($cid)) {
      $data = $cache->data;
    }
    else {
      // Check to see if we cached the last modified date of this requst.
      if ($date_cache = $this->cache->get($cid . ':last-modified')) {
        $last_modified = $date_cache->data;
      }
      else {
        $last_modified = date('D, j M Y H:i:s T');
      }

      try {
        $response = \Drupal::httpClient()->get($uri, [
          'headers' => [
            'Accept' => 'application/json',
            'If-Modified-Since' => $last_modified,
          ],
        ]);

        // Get the last modified date.
        $last_modified = $response->getHeaders()['last-modified'][0];
        $this->cache->set($cid . ':last-modified', $last_modified);

        // Get the json.
        $data = (string) $response->getBody();

        // Set the cache and expire it in 3 minutes.
        $this->cache->set($cid, $data, \Drupal::time()->getRequestTime() + ($expiration));

      }
      catch (RequestException $e) {
        \Drupal::logger('mbta')->error('The following error occurred while access the MBTA api: @error.',
          [
            '@error' => $e->getMessage(),
          ]
        );
        \Drupal::messenger()->addError('An error occurred while accessing the MBTA.');
      }
    }

    return $data;
  }

  /**
   * Get all available routes from the API.
   *
   * @return array
   *   A render array with results.
   */
  private function getRoutes() {
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
            'style' => 'color: #' . $route->attributes->text_color . ';',
          ]),
          'attributes' => new Attribute([
            'style' => 'background-color: #' . $route->attributes->color . ';color: #' . $route->attributes->text_color . ';',
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
        '#markup' => t('An error occurred while accessing routes.'),
      ];
    }

    return $render;
  }

  /**
   * Get the schedules from the API for the provided route id.
   *
   * @param mixed $route_id
   *   The route ID from the MBTA api.
   *
   * @return array
   *   A render array with api results.
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
        '#markup' => t('No upcoming schedule is currently available for this route.'),
      ];
    }

    return $render;
  }

  /**
   * Return the routes for display.
   */
  public function getRouteTable() {
    return $this->getRoutes();
  }

  /**
   * Return the route's schedule for display.
   */
  public function getRouteSchedule($route_id) {
    return $this->getSchedule($route_id);
  }

}
