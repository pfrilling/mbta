<?php

namespace Drupal\mbta;

use Drupal\Core\Template\Attribute;

class MbtaApi {

  // @todo: Call the MBTA api.
  private function call($path) {
    $uri = 'https://api-v3.mbta.com/' . $path;

    // Setup a cache ID based on the path of the API.
    $cid = 'mbta:' . $path;

    $data = NULL;

    // Check to see if this has already been cached.
    // @todo: Need to check the last modified time of the API request
    if ($cache = \Drupal::cache('mbta')->get($cid)) {
      $data = $cache->data;
    }
    else {
      try {
        $response = \Drupal::httpClient()->get($uri, array('headers' => array('Accept' => 'application/json')));
        $data = (string) $response->getBody();

        // @todo: Need to set a valid expiration time.
        \Drupal::cache('mbta')->set($cid, $data);

      }
      catch (RequestException $e) {
        return FALSE;
      }
    }

    return $data;



//    $routes = [
//      [
//        'name' => 'Red line',
//        'link' => '/mbta/route/1',
//        'attributes' => new Attribute([
//          'style' => 'background-color: #ff0000;color:#ffffff;'
//        ]),
//      ],
//      [
//        'name' => 'Green line',
//        'link' => '/mbta/route/2',
//        'attributes' => new Attribute([
//          'style' => 'background-color: #00ff00;color:#ffffff;'
//        ]),
//      ],
//      [
//        'name' => 'Blue line',
//        'link' => '/mbta/route/3',
//        'attributes' => new Attribute([
//          'style' => 'background-color: #0000ff;color:#ffffff;'
//        ]),
//      ],
//    ];
//    shuffle($routes);
    return $routes;
  }

  // @todo: Get the routes.
  private function getRoutes () {
    // Call the api to get new data.
    $routes = $this->call('routes');

    // Decode the cached routes.
    $routes = json_decode($routes);
//dpm($routes);
    $items = [];

    $render = [];

    foreach ($routes->data as $route) {
      $items[$route->attributes->fare_class][] = [
        'name' => $route->attributes->long_name,
        'link' => 'mbta' . $route->links->self,
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


    return $render;
  }

  public function getRouteTable() {
    return $this->getRoutes();
  }

  // @todo: Get the schedule for a route.
}