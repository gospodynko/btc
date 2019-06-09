<?php

class model_Platforms extends Model
{
  public function get_platforms()
  {
    return Conn::queryData("
      SELECT id, name FROM platforms
     ");
  }

  public function getPlatformById($platformId)
  {
    return Conn::queryData("
      SELECT * FROM platforms
      WHERE id = :id
    ", array('id' => $platformId));
  }
}