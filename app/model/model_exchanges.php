<?php

class model_Exchanges extends Model
{

  public function addToExchangesHistory($platform_exchange_id, $type, $account_id, $info = null)
  {
    return Conn::query("
      INSERT INTO exchanges_history
      (platform_exchange_id, account_id, type, info)
      VALUES (:platform_exchange_id, :account_id, :type, :info)
    ", array(
        'platform_exchange_id' => $platform_exchange_id, 'type' => $type, 'account_id' => $account_id, 'info' => $info)
    );
  }

  function saveExchange($updating_exchange, $is_new = false)
  {
    $sqlFields = array(
      'platform_exchange_id' => $updating_exchange['id'],
      'rur' => $updating_exchange['rur'],
      'btc' => $updating_exchange['btc'],
      'btc_platform' => $updating_exchange['btc_platform'],
      'exchange_rate' => $updating_exchange['exchange_rate'],
      'source_exchange_rate' => $updating_exchange['source_exchange_rate'],
      'date' => isset($updating_exchange['date']) ? $updating_exchange['date'] : null,
      'client_number' => isset($updating_exchange['client_number']) ? $updating_exchange['client_number'] : null,
      'number' => $updating_exchange['number'],
      'status' => $updating_exchange['status'],
      'account_id' => $updating_exchange['account_id'],
    );
    if ($is_new) {
      return Conn::query("
      INSERT INTO exchanges (
      platform_exchange_id, rur, btc, btc_platform, exchange_rate, source_exchange_rate, `date`, client_number, `number`,
      status, account_id
      ) VALUES (
      :platform_exchange_id, :rur, :btc, :btc_platform, :exchange_rate, :source_exchange_rate, :date, :client_number,
      :number, :status, :account_id
      )", $sqlFields);
    }
    return Conn::query("
      UPDATE exchanges SET rur = :rur, btc = :btc, btc_platform = :btc_platform, exchange_rate = :exchange_rate,
      source_exchange_rate = :source_exchange_rate, date = :date, client_number = :client_number, `number` = :number,
      status = :status, account_id = :account_id
      WHERE platform_exchange_id = :platform_exchange_id
    ", $sqlFields);
  }

  function get_saved($conditions = [], $dates_filter = null)
  {
    $conditionQuery = '';
    $i = 0;
    foreach ($conditions as $key => $value) {
      $valueQuery = '';
      if (!is_array($value)) {
        $value = [$value];
      }
      $j = 0;
      $count = count($value);
      foreach ($value as $item) {
        $valueQuery .= ($j === 0 ? "" : " OR ") . "`" . $key . "`='" . $item . "'";
        $j++;
      }
      if ($count > 1) {
        $valueQuery = "(" . $valueQuery . ")";
      }
      $conditionQuery .= ($i === 0 ? " WHERE " : " AND ") . $valueQuery;
      $i++;
    }
    if (!is_null($dates_filter)) {
      $datefrom = !empty($dates_filter['datefrom']) ? $dates_filter['datefrom'] : ' 00:00:00';
      $dateto = !empty($dates_filter['dateto']) ? $dates_filter['dateto'] . ' 23:59:59' : '';
      $andOrWhere = strlen($conditionQuery) > 0 ? ' AND' : ' WHERE';
      if (!empty($datefrom) && !empty($dateto)) {
        $conditionQuery .= " $andOrWhere date between date('$datefrom') and date('$dateto')";
      } elseif (!empty($datefrom) && empty($dateto)) {
        $conditionQuery .= " $andOrWhere `date` > '$datefrom'";
      } elseif (empty($datefrom) && !empty($dateto)) {
        $conditionQuery .=  " $andOrWhere `date` < '$dateto'";
      }
    }

    return Conn::queryData("
      SELECT platform_exchange_id as id, rur, btc, btc_platform, exchange_rate, source_exchange_rate, `date`, client_number, `number`, status, account_id, false as is_new
      FROM exchanges
      " . $conditionQuery
    );
  }

  function get_history($account_id)
  {
    return Conn::queryData("
      SELECT id, platform_exchange_id, type, date, info
      FROM exchanges_history
      WHERE account_id = :account_id
    ", array('account_id' => $account_id));
  }

  function annul_exchanges($ids, $account_id, $status)
  {
    $idsQuery = join(',', $ids);
    return Conn::query("
      UPDATE exchanges
      SET status = :status
      WHERE account_id = :account_id AND platform_exchange_id IN (" . $idsQuery . ")
    ", array('account_id' => $account_id, 'status' => $status));
  }
}