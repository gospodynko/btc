<?php

class Session
{
  public static function init()
  {
    if (session_id() == '') {
      session_start();
    }
  }

  public static function set($key, $value)
  {
    Session::init();
    $_SESSION[$key] = $value;
  }

  public static function delete($key)
  {
    Session::init();
    unset($_SESSION[$key]);
  }

  public static function get($key)
  {
    Session::init();
    if (isset($_SESSION[$key])) {
      return $_SESSION[$key];
    }
  }

  public static function add($key, $value)
  {
    Session::init();
    $_SESSION[$key][] = $value;
  }

  public static function destroy()
  {
    session_destroy();
  }
}
