<?php

class Route
{
  static function start()
  {
    $routes = explode('/', filter_input(INPUT_SERVER, 'REQUEST_URI'));

    $last_route_index = count($routes) - 1;
    $routes[$last_route_index] = explode('?', $routes[$last_route_index])[0];

    $api_path = !empty($routes[1]) ? $routes[1] : '';
    $api_version = !empty($routes[2]) ? $routes[2] : '';

    if ($api_path === API_PATH and $api_version === API_VERSION) {

      $api_controller = !empty($routes[3]) ? $routes[3] : '';
      $api_controller_name = 'controller_' . $api_controller;

      $controller_file = strtolower($api_controller_name) . '.php';
      $controller_path = CTRL_PATH . $controller_file;

      require_once CORE_PATH . 'model.php';
      require_once CORE_PATH . 'controller.php';

      if (file_exists($controller_path)) {
        require_once MODULES_PATH . 'connection.php';
        require_once MODULES_PATH . 'session.php';
        require_once MODULES_PATH . 'dumphper.php';

        include $controller_path;

        $model_name = 'model_' . $api_controller;
        $model_file = strtolower($model_name) . '.php';
        $model_path = MODEL_PATH . $model_file;
        if (file_exists($model_path)) {
          include $model_path;
        }

        $controller = new $api_controller_name;
        $api_method = !empty($routes[4]) ? 'action_' . $routes[4] : '';
        $construct_method = 'action_construct';

        if (method_exists($controller, $api_method)) {
          $controller->$api_method();
        } else if (!empty($api_method) && method_exists($controller, $construct_method)) {
          $controller->$construct_method($api_method);
        } else {
          self::echo_method_not_found();
        }
      } else {
        self::echo_method_not_found();
      }
    } else {
      if (file_exists(REACT_FILE)) {
        echo file_get_contents(REACT_FILE);
      }
    }
  }

  static function echo_method_not_found()
  {
    $controller = new Controller();
    $controller->echoRequest('Данный метод API не существует', 405);
  }
}
