<?php

class sql
{
  private $db_host;
  private $db_user;
  private $db_pass;
  private $db_name;
  public $mysql;
  public $cache = true;

  public function __construct($db_host, $db_user, $db_pass, $db_name)
  {
    $this->db_host = $db_host;
    $this->db_user = $db_user;
    $this->db_pass = $db_pass;
    $this->db_name = $db_name;
  }

  public function connect()
  {
    mlog("Master Connecting to: " . $this->db_host . " - " . $this->db_name . " - " . $this->db_user . " - " . $this->db_pass);
    $this->mysql = new mysqli($this->db_host, $this->db_user, $this->db_pass, $this->db_name);
    $this->mysql->set_charset('utf8');
  }

  public function insert($query)
  {
    mlog($query);
    # Inserts should never be cached
    $this->connect();

    try {
      # Execute the query
      if ($this->mysql->query($query) === false) {
        # Log the error and return false if the query fails
        mlog("MySQL Error: " . $this->mysql->error);
        return false;
      }

      # Check if an insert occurred and return the insert ID, else return true
      if ($this->mysql->insert_id) {
        return $this->mysql->insert_id;
      }

      return true;  # Return true if no insert ID but query was successful
    } catch (Exception $e) {
      # Log the exception message and return false
      mlog("Exception: " . $e->getMessage());
      return false;
    }
  }


  public function query($query, $cache = true, $ttl = 3600)
  {
    if ($this->cache == false) {
      $cache = false;
      $isCache = "false";
    } else {
      $isCache = "true";
    }

    $return = array();
    mlog("Query:" . $query . " - Cache: " . $isCache . " - TTL: " . $ttl . " - Database: " . $this->db_name);
    $this->connect();
    try {
      $result = "";
      if ($cache == true) {
        $cachedData = Registry::get('system')->cache_get($query);
        if ($cachedData) {
          $return = unserialize($cachedData);
        } else {
          mlog($query);
          $result = ($this->mysql->query($query));
          $return = array();
          if ($this->mysql->insert_id) {
          } else {
            while ($row = $result->fetch_assoc()) {
              $return[] = $row;
            }
            if ($cache == true) {
              Registry::get('system')->cache($query, serialize($return), $ttl);
            }
          }
        }
        mlog(print_r($result, true));
      } else {
        $result = ($this->mysql->query($query));
        if ($this->mysql->insert_id) {
        } else {
          $return = array();
          while ($row = $result->fetch_assoc()) {
            $return[] = $row;
          }
          if ($cache == true) {
            Registry::get('system')->cache($query, serialize($return));
          }
        }
      }

      return $return;
    } catch (Exception $e) {
      mlog($e->getMessage());
      return false;
    }
  }

  public function insert_id()
  {
    return $this->mysql->insert_id;
  }

  public function safe($str)
  {
    $this->connect();
    return $this->mysql->real_escape_string($str);
  }
}
