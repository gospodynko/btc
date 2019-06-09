<?php

class Controller
{
  public $model;
  const SESSION_AUTH_KEY = 'partner_panel_token';
  const ADMIN_RIGHTS_KEY = 'admin';
  const TRADER_RIGHTS_KEY = 'trader';

  public function __construct()
  {
    $this->model = new Model();
  }

  /*
   * code = 0 - нет ошибки
   * code = 500 - ошибка сервера
   * code = 401 - отсутствие авторизации
   * code = 403 - запрет доступа
   * code = 405 - ошибка на стороне клиента
   * code = 406 - наличии авторизации при повторном логине
   * */
  public function echoRequest($data = null, $code = 0)
  {
    header('Content-Type: application/json');
    $answer['code'] = $code;
    if (isset($data)) $answer['result'] = $data;
    echo json_encode($answer, JSON_UNESCAPED_UNICODE);
  }

  protected function parsePostData($echo_if_empty = true)
  {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data) && $echo_if_empty) {
      $error = 'Ошибка передачи данных';
      self::echoRequest($error, 405);
      return null;
    }
    return $data;
  }

  protected function isSqlError($queryResult, $echoError = true)
  {
    $isSqlError = $this->model->isSqlError($queryResult);
    if ($isSqlError && $echoError) {
      self::echoRequest('Ошибка SQL: ' . $queryResult['error'], 500);
    }
    return $isSqlError;
  }

  protected function getAccountWithAdminRights($getFullInfo = false, $echo_if_have_not_rights = true)
  {
    $accounts = self::getAccountsByCookie(null, $getFullInfo, $echo_if_have_not_rights);
    if (!empty($accounts) && count($accounts) === 1 && $accounts[0]['rights'] === self::ADMIN_RIGHTS_KEY) {
      return $accounts[0];
    }
    if (!is_null($accounts) && $echo_if_have_not_rights) {
      self::echoRequest('Недостаточно прав. Пожалуйста, авторизуйтесь', 401);
    }
    return null;
  }

  protected function getAccountWithTraderRights($getFullInfo = false, $echo_if_have_not_rights = true)
  {
    $accounts = self::getAccountsByCookie(null, $getFullInfo, $echo_if_have_not_rights);
    if (!empty($accounts) && count($accounts) === 1) {
      return $accounts[0];
    }
    if (!is_null($accounts) && $echo_if_have_not_rights) {
      self::echoRequest('Недостаточно прав. Пожалуйста, авторизуйтесь', 401);
    }
    return null;
  }

  protected function getAccountsByCookie($hash = null, $getFullInfo = false, $echo_error = true)
  {
    if (empty($hash)) {
      $hash = Session::get(self::SESSION_AUTH_KEY);
    }
    if (empty($hash) && isset($_COOKIE[self::SESSION_AUTH_KEY])) {
      $hash = $_COOKIE[self::SESSION_AUTH_KEY];
    }
    if (!empty($hash)) {
      $accounts = $this->model->getAccountsByHash($hash, $getFullInfo);
      if (self::isSqlError($accounts, $echo_error)) {
        return null;
      }
      return $accounts;
    }
    return [];
  }

  protected function getAccountsById($id)
  {
    return $this->model->getAccountsById($id);
  }

  protected function changeAccountBalance($id, $changeValue, $echo_if_error = true)
  {
    $query = $this->model->changeAccountBalance($id, round($changeValue, 10));
    if (self::isSqlError($query, $echo_if_error)) {
      if ($echo_if_error) {
        return null;
      }
    }
    return $query;
  }

  protected function getPlatformExchangeRate($token, $echo_if_error = true) {
    $balanceQuery = $this->exchangePanelApiRequest($token, '/balance?format=json');
    if (isset($balanceQuery['error']) || !isset($balanceQuery['rate_rur'])) {
      if ($echo_if_error) {
        $this->echoRequest($balanceQuery['error'] || 'Ошибка при получении значения курса обмена', 500);
      }
      return null;
    }
    return $balanceQuery['rate_rur'];
  }

  protected function getBtcToRurExchangeRate($echo_if_error = true)
  {
    $forexExchange = $this->getForexExchange('usdrur');
    if (isset($forexExchange['error'])) {
      if ($echo_if_error) {
        $this->echoRequest($forexExchange['error'], 500);
      }
      return $forexExchange;
    }

    $binanceExchange = $this->getBinanceExchange('BTCUSDT');
    if (isset($binanceExchange['error'])) {
      if ($echo_if_error) {
        $this->echoRequest($binanceExchange['error'], 500);
      }
      return $binanceExchange;
    }

    return round($forexExchange * $binanceExchange, 2);
  }

  protected function exchangePanelApiRequest($token, $path = '', $postQuery = null)
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, EXCHANGE_PANEL_HOST . '/' . EXCHANGE_PANEL_API_PATH . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if (!empty(EXCHANGE_PANEL_API_TOR_PROXY)) {
      curl_setopt($ch, CURLOPT_PROXY, EXCHANGE_PANEL_API_TOR_PROXY);
      curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    if (!empty(EXCHANGE_PANEL_API_HEADER) || !empty($token)) {
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        EXCHANGE_PANEL_API_HEADER,
        'Access-Token: ' . $token
      ));
    }
    if (!is_null($postQuery)) {
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $postQuery);
    }
    $result = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if (!empty($curl_error)) {
      return array('error' => 'Ошибка выполнения API запроса');
    }

    $result = json_decode($result, true);

    if (isset($result['status']) && $result['status'] === 'error') {
      return array('error' => 'Ошибка в данных API запроса');
    }

    return $result;
  }

  protected function generateCode($length = 6)
  {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPRQSTUVWXYZ0123456789';
    $code = '';
    $clen = strlen($chars) - 1;
    while (strlen($code) < $length) {
      $code .= $chars[mt_rand(0, $clen)];
    }
    return $code;
  }

  private function getBinanceExchange($symbol)
  {
    $url = 'https://api.binance.com/api/v3/ticker/price';
    $params = '?symbol=' . $symbol;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . $params);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    $data = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if (!empty($curl_error)) {
      return array('error' => 'Ошибка выполнения API запроса');
    }

    $data = json_decode($data, true);

    /*
     * {"symbol":"BTCUSDT","price":"3860.52000000"}
     */
    if (isset($data['msg'])) {
      return array('error' => $data['msg']);
    }

    $price = $data['price'];
    if (!isset($price)) {
      return array('error' => 'API не вернуло значение курса');
    }

    return $price;
  }

  private function getForexExchange($type_param)
  {
    $url = 'https://quotes.instaforex.com/api/quotesTick';
    $get = array(
      'm' => 'json',
      'q' => $type_param
    );
    $full_url = $url . '?' . http_build_query($get);
    $ch = curl_init();
    // GET запрос указывается в строке URL
    curl_setopt($ch, CURLOPT_URL, $full_url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    $data = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if (!empty($curl_error)) {
      return array('error' => 'Ошибка выполнения API запроса');
    }
    /*
     * $data = [
     *  {
     *    "digits":4,
     *    "ask":1.1637,
     *    "bid":1.1634,
     *    "change":0.0001,
     *    "symbol":"EURUSD",
     *    "lasttime":1532041342,
     *    "change24h":-0.0004
     *  }
     * ]
     * */
    //return $data['ask'];
    $data = json_decode($data, true);
    if (isset($data['error'])) {
      return $data;
    }
    if (!isset($data[0])) {
      return array('error' => 'Информация по курсу валют не найдена');
    }
    $current_data = $data[0];
    if (!isset($current_data['ask'])) {
      return array('error' => $data);
    }
    if (!isset($current_data['bid'])) {
      return array('error' => $data);
    }
    if (!is_numeric($current_data['ask'])) {
      $error = 'API вернуло нечисловое значение';
      return array('error' => $error);
    }
    if (!is_numeric($current_data['bid'])) {
      $error = 'API вернуло нечисловое значение';
      return array('error' => $error);
    }
    return ($current_data['ask'] + $current_data['bid']) / 2;
  }
}