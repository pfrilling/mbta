<?php

namespace Drupal\mbta\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\mbta\MbtaApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the MBTA module.
 */
class MbtaController extends ControllerBase {

  /**
   * The MBTA route api service.
   *
   * @var \Drupal\mbta\MbtaApi
   */
  protected $routeapi;

  /**
   * MbtaController constructor.
   *
   * @param \Drupal\mbta\MbtaApi $routeapi
   *   The MbtaApi service.
   */
  public function __construct(MbtaApi $routeapi) {
    $this->routeapi = $routeapi;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mbta.api')
    );
  }

  /**
   * Get the routes from the MbtaApi service.
   *
   * @return array
   *   A render array.
   */
  public function getRoutes() {
    return $this->routeapi->getRouteTable();
  }

  /**
   * Get the routes from the MbtaApi service.
   *
   * @return array
   *   A render array.
   */
  public function getSchedule($route_id) {
    return $this->routeapi->getRouteSchedule($route_id);
  }

}
