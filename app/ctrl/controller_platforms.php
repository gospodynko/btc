<?php

class controller_Platforms extends Controller
{

  function __construct()
  {
    $this->model = new model_Platforms();
  }

  function action_get()
  {
    if (empty(parent::getAccountWithAdminRights())) {
      return;
    }

    $platforms = $this->model->get_platforms();
    if (parent::isSqlError($platforms)) {
      return;
    }

    parent::echoRequest($platforms);
  }

  function action_exchange_rate()
  {
    $account = parent::getAccountWithAdminRights();
    if (empty($account)) {
      return;
    }

    if (!isset($_GET['platform_id'])) {
      parent::echoRequest('Не указан id платформы для получения обменного курса', 405);
      return;
    }

    $platformId = $_GET['platform_id'];
    $platformsList = $this->model->getPlatformById($platformId);
    if (parent::isSqlError($platformsList)) {
      return;
    }

    if (empty($platformsList)) {
      parent::echoRequest('Отсутствует платформа с указанным id', 405);
      return;
    }

    $platform = $platformsList[0];
    if (empty($platform['token'])) {
      parent::echoRequest('Отсутствует токен для указанной платформы', 500);
      return;
    }

    $platformToken = $platform['token'];
    $exchangeRate = parent::getPlatformExchangeRate($platformToken);
    if (is_null($exchangeRate)) {
      return;
    }

    parent::echoRequest($exchangeRate);
  }
}
