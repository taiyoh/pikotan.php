<?php

if (!defined('APPLIB')) {
  define('APPLIB', realpath(dirname(__FILE__)));
}

// from http://zapanet.info/blog/item/1193
function h($str)
{
  return (is_array($str))
    ? array_map("h",$str)
    : htmlspecialchars($str,ENT_QUOTES);
}

// from http://d.hatena.ne.jp/fbis/20091112/1258002754
function is_hash(&$array)
{
  list($i, $is_hash) = array(0, false);
  foreach($array as $k => $dummy) {
    if ( $k !== $i++ ) {
      $is_hash = true;
      break;
    }
  }
  return $is_hash;
}

function url_for($path, $param = array(), $extends_get = false)
{
  if ($extends_get === true) {
    $param = array_merge($_GET, $param);
  }
  $query = http_build_query($param);
  return ($query) ? "{$path}?{$query}" : $path;
}

// <!-- パラメータ関係のsyntax sugar
function get_param($name, $default = '', $security = false)
{
  $val = (isset($_REQUEST[$name])) ? $_REQUEST[$name] : $default;
  return ($security === false) ? $val : h($val);
}

function has_param($name)
{
  return isset($_REQUEST[$name]);
}

function get_authparam($name, $default = '', $security = false)
{
  $val = (@$_SESSION[$name]) ? $_SESSION[$name] : $default;
  return ($security === false) ? $val : h($val);
}

function has_authparam($name)
{
  return isset($_SESSION[$name]);
}

function set_authparam($name, $value)
{
  $_SESSION[$name] = $value;
}

function unset_authparam($name)
{
  if (has_authparam($name)) {
    $v = $_SESSION[$name];
    unset($_SESSION[$name]);
    return $v;
  }
  return null;
}
// パラメータ関係のsyntax sugar -->

// <!-- エラー関係の処理
$pikotan_errors = array();
function has_error($name)
{
  global $pikotan_errors;
  return isset($pikotan_errors[$name]);
}

function has_errors()
{
  global $pikotan_errors;
  return !empty($pikotan_errors);
}

function get_error($name, $tmpl = false)
{
  global $pikotan_errors;
  if (isset($pikotan_errors[$name])) {
    if (!$tmpl) {
      return $pikotan_errors[$name];
    }
    else {
      return preg_replace('/__ERR__/', $pikotan_errors[$name], $tmpl);
    }
  }
  else {
    return '';
  }
}

function get_errors()
{
  global $pikotan_errors;
  return $pikotan_errors;
}

function set_error($name, $message)
{
  global $pikotan_errors;
  $pikotan_errors[$name] = $message;
  return false;
}
// エラー関係の処理 -->

// <!-- validator関係の関数
function Pikotan_validate_not_null($val = false)
{
  return $val || $val === 0;
}

function Pikotan_validate_ascii($val = '')
{
  return preg_match('/^[a-z0-9]*$/i', $val);
}

function Pikotan_validate_numeric($val = false)
{
  if (!$val && $val !== 0) return true;
  return is_numeric($val);
}

function Pikotan_validate_length($val = '', $min = 0, $max = false)
{
  $len = mb_strlen($val, 'UTF-8');
  if ($min > $len) return false;
  return ($max !== false && $max < $len) ? false : true;
}

function Pikotan_validate_regex($val = '', $r = '/^(.+?)$/')
{
  return preg_match($r, $val);
}

function Pikotan_validate_in_list($val = '', $l = array())
{
  return in_array($val, $l);
}

function validate($conditions = array())
{
  // パラメータ名 => 設定 という対応関係
  foreach ($conditions as $name => $params) {
    // constraintsが定義されていなければスルー
    if (!isset($params['constraints']) || !is_array($params['constraints'])) {
      continue;
    }
    // constraintsを回してチェック
    foreach ($params['constraints'] as $args) {
      $args = (!is_array($args)) ? array($args) : $args;
      $cond = (isset($args[0])) ? $args[0] : $args;
      $func = "Pikotan_validate_{$cond}";
      $args[0] = get_param($name);
      // constraintsに入ってる名前から登録されてる関数に引き当たれば実行。無関係な関数名は無視
      // バリデーションエラーとなった場合、エラーをセットしてこのパラメータ名での処理を止める
      if (function_exists($func) && !call_user_func_array($func, $args)) {
        $m = (isset($params['messages']) && isset($params['messages'][$cond]))
          ? $params['messages'][$cond]
          : "invalid about {$cond}";
        set_error($name, $m);
        break;
      }
    }
  }
  return !has_errors();
}
// validator関係の関数 -->

function load_lib($app)
{
  $backtrace = debug_backtrace();
  $called_dir = dirname($backtrace[0]['file']);
  if (file_exists("{$called_dir}/{$app}.php")) {
    return include_once("{$called_dir}/{$app}.php");
  }
  else if (file_exists(APPLIB."/{$app}.php")) {
    return include_once(APPLIB."/{$app}.php");
  }
  $path_list = explode(PATH_SEPARATOR, get_include_path());
  $filepath = false;
  foreach($path_list as $path) {
    if (file_exists("{$path}/{$app}.php")) {
      $filepath = "{$path}/{$app}.php";
      include_once($filepath);
      break;
    }
  }
  return $filepath !== false;
}

if (!defined('PIKOTAN_UTILITY_USE') || !PIKOTAN_UTILITY_USE) {

  // <!-- dispatcher
  $pikotan_dispatcher_table = array();
  function register_dispatch($table)
  {
    if (!is_array($table)) {
      return;
    }
    global $pikotan_dispatcher_table;
    $set_action_tmpl = array(
      'action' => '',
      'method' => null,
      'accept' => array('GET', 'POST')
    );
    foreach($table as $path => $val) {
      if (!is_array($val) || !isset($val[0])) {
        $val = array($val);
      }
      foreach($val as $i => $v) {
        if (is_string($v)) {
          $v = array_merge($set_action_tmpl, array('action' => $v));
        }
        if (!isset($v['accept'])) {
          $v['accept'] = array('GET', 'POST');
        }
        if (!isset($v['method'])) {
          $v['method'] = null;
        }
        $val[$i] = $v;
      }
      $pikotan_dispatcher_table[$path] = $val;
    }
  }

  function dispatch($path = '/')
  {
    global $pikotan_dispatcher_table;
    $set_action = false;
    $path_matched = false;
    // ドンピシャでマッチするパスはここで返す
    if (@$pikotan_dispatcher_table[$path]) {
      $set_action = $pikotan_dispatcher_table[$path];
    }
    else {
      // リストを回してみていく
      foreach ($pikotan_dispatcher_table as $key => $action) {
        $path_list = explode('/', $key);
        array_shift($path_list);
        // リストのパスを再構築して、正規表現でのチェックに使える形に
        list($new_action,$param_register) = array(array(''), array());
        foreach($path_list as $p) {
          if (strpos($p, ':') === false) {
            $new_action[] = $p;
          }
          else {
            $param_register[] = substr($p, 1);
            $new_action[] = '([^\/]+?)';
          }
        }
        if (preg_match("/^".implode('\/', $new_action)."$/", $path, $m)) {
          $set_action = $action;
          array_shift($m);
          if (count($param_register) > 0 && count($param_register) === count($m)) {
            $param_register = array_combine($param_register, $m);
            foreach(array('action', 'method') as $k) {
              if (isset($param_register[$k])) {
                $path_matched[$k] = $param_register[$k];
                unset($param_register[$k]);
              }
            }
            $_REQUEST = array_merge($_REQUEST, $param_register);
          }
          break;
        }
      }
    }
    try {
      if ($set_action === false) {
        throw new Exception('no action');
      }
      $action = false;
      // $_SERVER['REQUEST_METHOD']を見て判別
      foreach($set_action as $ac) {
        if (in_array($_SERVER['REQUEST_METHOD'], $ac['accept'])) {
          $action = $ac;
          break;
        }
      }
      if (!$action) {
        throw new Exception('no action');
      }
      if ($path_matched !== false) {
        foreach(array('action', 'method') as $t) {
          if (isset($path_matched[$t])) {
            $action[$t] = $path_matched[$t];
          }
        }
      }
      return $action;
    }
    catch(Exception $e) {
      return array(
        'action' => substr($path, 1),
        'method' => null,
        'accept' => array('GET', 'POST')
      );
    }
  }
  // dispatcher-->

  // <!-- レスポンス周り

  // ディフォルトのレスポンスオブジェクト
  $pikotan_response = array(200, array(), '');

  function set_status($code = 200)
  {
    global $pikotan_response;
    $pikotan_response[0] = (is_int($code)) ? $code : 200;
  }

  function set_header($headers = array())
  {
    global $pikotan_response;
    $pikotan_response[1] = $headers;
  }

  function add_header($name, $value)
  {
    global $pikotan_response;
    $pikotan_response[1][$name] = $value;
  }

  function set_content($content)
  {
    global $pikotan_response;
    $pikotan_response[2] = (is_string($content))
      ? $content
      : '';
  }

  function set_redirect($url, $status = 302)
  {
    //set_status(200);
    add_header('Content-Type', 'text/html');
    add_header('Location', $url);
    set_content(<<<_H
<html><head><title>refresh</title><meta http-equiv="Refresh" content="0;URL={$url}"></head><body></body></html>
_H
  );
  }
  // レスポンス周り -->

  // <!-- なんちゃってDIコンテナ
  $pikotan_models = array();
  function register_model($name, $callback, $save = false)
  {
    global $pikotan_models;
    if (function_exists($callback)) {
      $pikotan_models[$name]['callback'] = $callback;
      $pikotan_models[$name]['instance'] = ($save === false)? false : null;
    }
  }
  
  function models($name)
  {
    global $pikotan_models;
    $args = func_get_args();
    $name = array_shift($args);
    if (!isset($pikotan_models[$name])) {
      return false;
    }
    if ($pikotan_models[$name]['instance'] === false) {
      return call_user_func_array($pikotan_models[$name]['callback'], $args);
    }
    else if (is_null($pikotan_models[$name]['instance'])) {
      $pikotan_models[$name]['instance'] = call_user_func_array($pikotan_models[$name]['callback'], $args);
    }
    return $pikotan_models[$name]['instance'];
  }
  // なんちゃってDIコンテナ -->

  $pikotan_app_config = array();
  function app_config($conf = null)
  {
    global $pikotan_app_config;
    if (!$conf) {
      return $pikotan_app_config;
    }
    if (!is_array($conf)) {
      return (isset($pikotan_app_config[$conf])) ? $pikotan_app_config[$conf] : null;
    }
    else if (is_array($conf) && !empty($conf)) {
      $pikotan_app_config = array_merge($pikotan_app_config, $conf);
    }
    return $pikotan_app_config;
  }

  // <!-- ログ出力
  function logging($msg, $code = LOG_DEBUG)
  {
    if (!defined('Pikotan_Log_Level')) {
      $level_map = array(
        'production'  => LOG_ERR,
        'test'        => LOG_INFO,
        'development' => LOG_DEBUG
      );
      if (!defined('APP_ENVIRONMENT')) {
        define('APP_ENVIRONMENT', 'production');
      }
      define('Pikotan_Log_Level', $level_map[APP_ENVIRONMENT]);
    }

    if ((!defined('APP_LOGGING') || !file_exists(APP_LOGGING)) || Pikotan_Log_Level < $code) {
      return;
    }

    if (is_object($msg) || is_array($msg) || is_bool($msg)) {
      $eh = ini_get('html_errors');
      ini_set('html_errors', 0);
      ob_start();
      var_dump($msg);
      $msg = ob_get_contents();
      ob_end_clean();
      ini_set('html_errors', $eh);
    }

    $logging_code_map = array('EMERG', 'ALERT', 'CRIT', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG');

    error_log(sprintf("%s[%s]\t[%s] <--%s-->\n",APPNAME,$logging_code_map[$code],date('Y-m-d H:i:s'),$msg), 3, APP_LOGGING);
  }
  // ログ出力 -->

  // application process sequence
  function app_start()
  {
    try {
      // routing path
      $dispatched = Pikotan_app_routingPath($_SERVER['REQUEST_URI']);

      $action_file = ((defined('APP_ACTIONS'))? APP_ACTIONS : APPLIB) ."/{$dispatched['action']}.php";
      if (file_exists($action_file)) {
        include_once( $action_file );
      }

      // validate params if exists
      Pikotan_app_validate($dispatched);

      // do action
      Pikotan_app_executeProcess($dispatched);
    }
    catch(Exception $e) {
      $errors = get_errors();
      $code = $e->getCode();
      if ($code > 1000) { // エラーコードが1000以上の時は、ユーザ側でビューの定義がしてあるということ
        $code -= 1000;
      }
      else {
        add_header('Content-Type', 'text/plain; charset=utf-8');
        set_content($e->getMessage());
      }
      if (!$code) {
        $code = 200;
      }
      set_status($code);
    }

    // render view
    Pikotan_app_renderView();
  }

  function Pikotan_app_routingPath($uri)
  {
    // GET, POST以外のメソッドで来た時、$_REQUESTにパラメータを入れる
    if (!in_array($_SERVER['REQUEST_METHOD'], array('GET', 'POST'))) {
      $putdata = fopen("php://input", "r");
      $dddd = '';
      while ($data = fread($putdata, 1024))
        $dddd .= $data;

      $pppp = explode('&', $dddd);
      $_params = array();
      foreach($pppp as $ppp) {
        list($key, $val) = explode('=', $ppp);
        $_params[$key] = $val;
      }
      //logging($_params);
      $_REQUEST = array_merge($_REQUEST, $_params);
    }
    $parsed_url = parse_url($uri);
    $dispatched = dispatch($parsed_url['path']);

    // method check
    if (!$dispatched) {
      set_error('method', 'not acceptable request method for this path');
      throw new Exception('invalid request method', 200);
    }

    return $dispatched;
  }

  function Pikotan_app_validate($dispatched)
  {
    // validate params if exists
    $vfunc = (is_null($dispatched['method'])) ? 'validate' : "validate_{$dispatched['method']}";
    $validate_conditions = array();
    if (function_exists("regist_{$vfunc}")) {
      $validate_conditions = call_user_func("regist_{$vfunc}");
    }
    if (!validate($validate_conditions) || (function_exists("custom_{$vfunc}") && !call_user_func("custom_{$vfunc}"))) {
      $code = 200;
      if (function_exists("on_{$vfunc}_error")) {
        call_user_func("on_{$vfunc}_error");
        $code += 1000; //app_startのrenderErrorが走らないようにする
      }
      throw new Exception('invalid param', $code);
    }
  }

  function Pikotan_app_renderView()
  {
    global $pikotan_response;
    header("{$_SERVER['SERVER_PROTOCOL']} {$pikotan_response[0]}");
    if (isset($pikotan_response[1])) {
      foreach ($pikotan_response[1] as $key => $val) {
        header("{$key}: {$val}");
      }
    }
    echo $pikotan_response[2];
  }

  function Pikotan_app_executeProcess($dispatched)
  {
    $func = null;
    if (!$dispatched['method'] && function_exists('execute')) {
      $func = 'execute';
    }
    else {
      if (function_exists("execute_{$dispatched['method']}")) {
        $func = "execute_{$dispatched['method']}";
      }
      else if (function_exists("execute_{$dispatched['action']}")) {
        $func = "execute_{$dispatched['action']}";
      }
    }
    if (is_null($func)) {
      set_error('action', 'no executable method!');
      throw new Exception('no executable method!', 200);
    }

    try {
      call_user_func($func);
    }
    catch(Exception $e) {
      set_error('message', $e->getMessage());
      throw new Exception('error founds!', 500);
    }
  }
}
