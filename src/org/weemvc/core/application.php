<?php
require_once './org/weemvc/core/controller.php';
require_once './org/weemvc/pager.php';

/**
 * Inspired by https://github.com/panique/php-mvc/
 */
class Application{
  // those variables can be reset in the sub class
  // http://stackoverflow.com/questions/1685922/php5-const-vs-static
  public static $ROUTER_PATH = './application/router/';
  public static $COMMAND_PATH = './application/command/';
  public static $DAO_PATH = './application/model/dao/';
  public static $PLUGIN_PATH = './application/model/plugin/';
  public static $ROUTER_CONTROLLER = 'controller';
  public static $ROUTER_ACTION = 'action';

  private static $_instance = null;
  /** @var null The controller */
  private $_controller = null;
  //
  private $_router = null;
  private $_controllerKey = null;
  private $_actionKey = null;

  /**
   * Singleton class
   * Private constructor
   */
  private function __construct(){
    // NOTICE: do NOT initialize anything here, because 'self::$_instance' haven't setup
    // yet, everything in here could be running multiple time.
  }

  public static function getInstance(){
    if(!isset(self::$_instance)){
        self::$_instance = new Application();
        // initialize everything, and make sure it only run once
        self::$_instance->initialize();
    }
    return self::$_instance;
  }

  public function getController(){
    return $this->_controller;
  }

  private function initialize(){
    $this->_controller = new Controller();
    // create array with URL parts in url
    $this->splitUrl();
    // Analyze the URL elements and calls the according controller/method or the fallback
    $this->startRouting();
  }

  private function defaultRouter(){
    // invalid URL, so simply show default/index
    // to put it here in order to save time
    require_once './org/weemvc/core/router.php';
    $this->_router = new Router();
    $this->_router->index($_GET, $_POST);
  }

  private function startRouting(){
    $lowcase = strtolower($this->_controllerKey);
    $filePath = self::$ROUTER_PATH . "{$lowcase}.php";
    // check for controller: does such a controller exist ?
    if (file_exists($filePath)){
      // if so, then load this file and create this controller
      // example: if controller would be "car", then this line would translate into: $this->car = new car();
      require_once $filePath;
      if(class_exists($this->_controllerKey)){
        try{
            $this->_router = new $this->_controllerKey();
        }catch(Exception $e) {
          Pager::output(1000, null, "new controller exception: {$e->getMessage()}", $this);
          exit;
        }
        // check for method: does such a method exist in the controller
        if(method_exists($this->_router, $this->_actionKey)){
          // call the method and pass the arguments to it
          // will translate to something like $this->home->method($param_1, $param_2);
          // http://stackoverflow.com/questions/4160901/how-to-check-if-a-function-is-public-or-protected-in-php
          $reflection = new ReflectionMethod($this->_router, $this->_actionKey);
          if ($reflection->isPublic()) {
            $this->_router->{$this->_actionKey}($_GET, $_POST);
          }else{
            $this->_router->index($_GET, $_POST);
          }
        }else{
          // default/fallback: call the index() method of a selected controller
          $this->_router->index($_GET, $_POST);
        }
      }else{
        $this->defaultRouter();
      }
    }else{
      $this->defaultRouter();
    }
  }

  /**
   * Get and split the URL
   */
  private function splitUrl(){
    if (isset($_GET[self::$ROUTER_CONTROLLER])) {
      // thread_test --> ThreadTest
      $this->_controllerKey = $this->getHumpName($_GET[self::$ROUTER_CONTROLLER], true);
    }
    if(isset($_GET[self::$ROUTER_ACTION])){
      // get_user_info --> getUserInfo
      $this->_actionKey = $this->getHumpName($_GET[self::$ROUTER_ACTION]);
    }
    // echo '$this->_controllerKey' . $this->_controllerKey;
    // echo '$this->_actionKey' . $this->_actionKey;
  }

  // $first = true : get_user_info ==> GetUserInfo
  // $first = false: get_user_info ==> getUserInfo
  private function getHumpName($name, $first = false){
    $path = filter_var($name, FILTER_SANITIZE_URL);
    // from get_user_info to getUserInfo
    $path = strtolower($path);
    $list = explode('_', $path);
    $path = '';
    for ($i = 0; $i < count($list); $i++) {
      // if the $first needs to be UpperCase
      if($i === 0 && !$first){
        $path .= $list[$i];
      }else{
        $path .= ucfirst($list[$i]);
      }
    }
    return $path;
  }

}