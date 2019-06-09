<?php

class controller_Auth extends Controller
{

  function __construct()
  {
    $this->model = new model_Auth();
  }

  function action_login()
  {
    $data = parent::parsePostData();
    if (empty($data)) {
      return;
    }
    $login = $data['login'];
    $pass = $data['password'];
    $accountsByCookie = parent::getAccountsByCookie();
    if (is_null($accountsByCookie)) {
      return;
    }
    if (!empty($accountsByCookie) && count($accountsByCookie) === 1) {
      $accountInfo = $accountsByCookie[0];
      $currentLogin = $accountInfo['login'];
      if ($login === $currentLogin) {
        parent::echoRequest('Вы уже авторизованы', 406);
        return;
      }
    }
    $accounts = $this->model->getAccounts($login, $pass);
    if (parent::isSqlError($accounts)) {
      return;
    }
    if (empty($accounts) || count($accounts) === 0) {
      $error = 'Неверные авторизационные данные';
      parent::echoRequest($error, 403);
      return;
    }
    $account = $accounts[0];
    $hash = md5(parent::generateCode());
    $query = $this->model->setHash($account['id'], $hash);
    if (parent::isSqlError($query)) {
      return;
    }
    Session::set(parent::SESSION_AUTH_KEY, $hash);
    $accountsByCookie = parent::getAccountsByCookie($hash);
    if (is_null($accountsByCookie)) {
      return;
    }
    if (empty($accountsByCookie) || count($accountsByCookie) !== 1) {
      parent::echoRequest('Не удалось авторизоваться', 500);
      return;
    }
    $accountInfo = $accountsByCookie[0];
    parent::echoRequest($accountInfo);
  }

  function action_logout()
  {
    Session::delete(parent::SESSION_AUTH_KEY);
    $accounts= parent::getAccountsByCookie();
    if (empty($accounts)) {
      parent::echoRequest('Успешный выход из аккаунта');
    } else {
      parent::echoRequest('Не удалось выйти из аккаунта', 500);
    }
  }

  function action_info()
  {
    $account = parent::getAccountWithTraderRights();
    if (empty($account)) {
      return;
    }

    $fullAccountInfo = parent::getAccountWithTraderRights(true);
    if (empty($fullAccountInfo)) {
      return;
    }
    if (isset($fullAccountInfo['exchange_rate_markup']) && !empty($fullAccountInfo['exchange_rate_markup'])) {
      $exchangeRateMarkup = $fullAccountInfo['exchange_rate_markup'];
      $sourceExchangeRate = parent::getBtcToRurExchangeRate();
      if (isset($sourceExchangeRate['error'])) {
        return;
      }
      $account['exchange_rate'] = round($sourceExchangeRate * (1 + $exchangeRateMarkup), 2);
    }
    parent::echoRequest($account);
  }
}
