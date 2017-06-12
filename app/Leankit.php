<?php

namespace App;

use LeanKitKanban;

/**
 * Class Leankit
 *
 * @package App
 */
class Leankit extends LeanKitKanban {

  /**
   * Leankit constructor.
   *
   * Override certain values that cannot be overriden in the base class.
   */
  public function __construct() {
    parent::__construct(env('LEANKIT_USERNAME'), env('LEANKIT_PASSWORD'));

    $this->account = env('LEANKIT_ACCOUNT');
    $this->host = 'https://'.$this->account.'.leankit.com';
    $this->api_url = $this->host.'/Kanban/Api/';
  }

  /**
   * Returns the boards attached to the account.
   *
   * @return mixed|bool
   *  List of boards belonging to the account.
   */
  public function getGmtBoards() {
    $boards = $this->getBoards('Boards');
    if ($boards) {
      $boards = json_decode($boards);
    }

    return ($boards and $boards->ReplyCode == 200) ? $boards->ReplyData[0] : false;
  }

  /**
   * Returns the board with the given name.
   *
   * @param string $name
   *  Name of the board to retrieve.
   *
   * @return mixed|bool
   *  Get board object.
   */
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

  /**
   * Helper method to extract the cards from a given response object.
   *
   * @param $cards
   *  Response object received from API.
   *
   * @return array|bool
   *  Results contained in the given object.
   */
  protected function _getCards($cards) {
    if ($cards) {
      $cards = json_decode($cards);
      $cards = ($cards && $cards->ReplyCode == 200) ? $cards->ReplyData[0] : FALSE;
    }

    return $cards ? $cards->Results : FALSE;
  }

  /**
   * Returns the cards belonging a specific board.
   *
   * @param string $board
   *  Board name or id.
   *
   * @return array|bool
   *  Cards belonging to the given board.
   */
  public function getCards($board = 'Amazee GMT') {
    if (!is_numeric($board)) {
      $b = $this->getGmtBoard($board);
      $board = ($b) ? $b->Id : false;
    }

    if ($board) {
      $cards = false;
      $cards_result = $this->searchCards($board);
      if ($cards_result) {
        // Parse data.
        $cards = $this->_getCards($cards_result);
        $cards_result = json_decode($cards_result);
        $cards_result = ($cards_result && $cards_result->ReplyCode == 200) ? $cards_result->ReplyData[0] : false;

        // If we're getting the first page of a bigger set.
        if ($cards_result && ($cards_result->TotalResults > $cards_result->MaxResults) && $cards_result->Page == 1) {
          // We need more pages as API limits to 20 items.
          for ($i = 2; $i <= ceil($cards_result->TotalResults / $cards_result->MaxResults); $i++) {
            $cards = array_merge($cards, $this->_getCards($this->searchCards($board, array('page' => $i))));
          }
        }
      }

      return $cards;
    }

    return false;
  }
}