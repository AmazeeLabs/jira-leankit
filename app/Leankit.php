<?php

namespace App;

use LeanKitKanban;

class Leankit extends LeanKitKanban {
  public function __construct() {
    parent::__construct(env('LEANKIT_USERNAME'), env('LEANKIT_PASSWORD'));

    $this->account = env('LEANKIT_ACCOUNT');
    $this->host = 'https://'.$this->account.'.leankit.com';
    $this->api_url = $this->host.'/Kanban/Api/';
  }

  public function getGmtBoards() {
    $boards = $this->getBoards('Boards');
    if ($boards) {
      $boards = json_decode($boards);
    }

    return ($boards and $boards->ReplyCode == 200) ? $boards->ReplyData[0] : false;
  }

  public function getGmtBoard($name = 'Amazee GMT') {
    $board = false;

    $boards = $this->getGmtBoards();
    if ($boards) {
      foreach ($boards as $b) {
        if ($b->Title == $name) {
          $board = $this->getBoard($b->Id);
        }
      }
    }
    if ($board) {
      $board = json_decode($board);
    }

    return ($board and $board->ReplyCode == 200) ? $board->ReplyData[0] : false;
  }
}