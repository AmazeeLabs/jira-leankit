<?php

// TODO: abstract the GMT word/board into just a board && maybe play with more defaults.

namespace App;

use LeanKitKanban;

/**
 * Class Leankit
 *
 * @package App
 */
class Leankit extends LeanKitKanban {

    /**
     * @var string protocol of endpoint.
     */
    const LEANKIT_PROTOCOL = 'https://';

    /**
     * @var string domain endpoint.
     */
    const LEANKIT_DOMAIN = '.leankit.com';

    /**
     * @var string API endpoint.
     */
    const LEANKIT_API_ENDPOINT = '/Kanban/Api/';

    /**
     * @var array $map mapping for adding/updating issues from external sources.
     */
    protected $map;

    /**
     * TODO: this is too specific
     * @var array $user_map map of users from jira to leankit
     */
    protected $user_map;

    /**
     * @var mixed $board most recently used.
     */
    protected $board;


    /**
     * Leankit constructor.
     *
     * Override certain values that cannot be overriden in the base class.
     *
     * @param string $leankitAccount
     * @param string $leankitUsername
     * @param string $leankitPassword
     * @param array $userMap
     */
    public function __construct($leankitAccount, $leankitUsername, $leankitPassword, $userMap = NULL) {
        parent::__construct($leankitUsername, $leankitPassword);

        $this->account = $leankitAccount;
        $this->host = self::LEANKIT_PROTOCOL . $this->account . self::LEANKIT_DOMAIN;
        $this->api_url = $this->host . self::LEANKIT_API_ENDPOINT;

        $this->map = $this->getMap();

        // TODO: too specific...
        if (!is_null($userMap)) {
            $this->setUserMap($userMap);
        }
    }

    /**
     * Sets the mapping to follow when updating/creating issues from external
     * sources.
     *
     * @param array $map Fields mapping.
     */
    public function setMap(array $map) {
        $this->map = $map;
    }

    /**
     * Returns the mapping to follow when updating/creating issues from
     * external sources.
     *
     * @return array
     */
    public function getMap() {
        if (!$this->map) {
            $this->map = [
                'Issue key' => 'ExternalCardID',
                'Summary' => 'Title',
                'Description' => 'Description',
                'Issue type' => 'Type',
                'Assignee' => 'AssignedUserIds', //ie: [111,1112,2211]
                'Due Date' => 'DueDate', //ie: 01/01/2020
            ];
        }

        return $this->map;
    }

    /**
     * Returns the current user's map
     * @return array
     */
    public function getUserMap() {
        return $this->user_map;
    }

    /**
     * Sets a jira to leankit user map
     * @param array $map map of users
     */
    public function setUserMap($map) {
        $this->user_map = $map;
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

        return ($boards && $boards->ReplyCode == 200) ? $boards->ReplyData[0] : FALSE;
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
        if ($this->board && $this->board->Title == $name) {
            // Look no further.
            return $this->board;
        }

        $board = FALSE;

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

        $this->board = ($board && $board->ReplyCode == 200) ? $board->ReplyData[0] : FALSE;
        return $this->board;
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
    protected function _decodeCards($cards) {
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
            $board = ($b) ? $b->Id : FALSE;
        }

        if ($board) {
            $cards = FALSE;
            $cards_result = $this->searchCards($board);
            if ($cards_result) {
                // Parse data.
                $cards = $this->_decodeCards($cards_result);
                $cards_result = json_decode($cards_result);
                $cards_result = ($cards_result && $cards_result->ReplyCode == 200) ? $cards_result->ReplyData[0] : FALSE;

                // If we're getting the first page of a bigger set.
                if ($cards_result && ($cards_result->TotalResults > $cards_result->MaxResults) && $cards_result->Page == 1) {
                    // We need more pages as API limits to 20 items.
                    for ($i = 2; $i <= ceil($cards_result->TotalResults / $cards_result->MaxResults); $i++) {
                        $cards = array_merge($cards,
                            $this->_decodeCards($this->searchCards($board, ['page' => $i])));
                    }
                }
            }

            return $cards;
        }

        return FALSE;
    }

    /**
     * Gets a card by its Jira ID
     *
     * @param $jira_id
     *
     * @param string $board
     *  Board name or id.
     *
     * @return bool|mixed
     */
    public function getCardByJiraId($jira_id, $board = 'Amazee GMT') {
        $board = $this->getGmtBoard($board);
        $card = $this->getCardByExternalId($board->Id, $jira_id);

        if ($card) {
            $card = json_decode($card);
            $card = ($card && $card->ReplyCode == 200) ? (object) $card->ReplyData[0][0] : FALSE;
        }

        return $card;
    }

    /**
     * Create or update the card on leankit from the given information.
     *
     * @param array $card data of the card
     *
     * @param string $board
     *  Board name or id.
     *
     * @return bool|mixed Card result
     */
    public function upsertCard(array $card, $board = 'Amazee GMT') {
        // Map the data.
        $card_data = $this->_mapCard($card);
        $card_data = $this->_sanitizeData($card_data, $board);

        // See if we have an ID (mandatory).
        if (empty($card_data) || empty($card_data['ExternalCardID'])) {
            return FALSE;
        }

        // See if card already in the board.
        $jira_id = $card_data['ExternalCardID'];
        $leankit_card = $this->getCardByJiraId($jira_id, $board);
        $gmt_board = $this->getGmtBoard($board);

        // Map type.
        $card_data['Type'] = !empty($card_data['Type']) ? $card_data['Type'] : 'Task';
        $card_data['TypeId'] = $this->getCardTypeId($card_data['Type']);

        if ($leankit_card) {
            // Update.
            $card_data['CardId'] = $leankit_card->Id;

            // Leankit needs user emails here instead of IDs!!!!
            if (!empty($card_data['AssignedUserIds'])) {
                $card_data['AssignedUsers'] = implode(',', $this->_convertAssignedUserIdsToEmails($board, $card_data['AssignedUserIds']));
                unset($card_data['AssignedUserIds']);
            }

            $response = $this->updateCardSimple($card_data, $gmt_board->Id);
            if ($response) {
                $response = json_decode($response);
                $response = ($response && $response->ReplyCode == 202) ? $this->getCardByJiraId($jira_id) : FALSE;
            }
        }
        else {
            // Create.
            $response = $this->addCard($card_data, $gmt_board->Id, $this->getDefaultBoardLane());
            if ($response) {
                $response = json_decode($response);
                $response = ($response && $response->ReplyCode == 201) ? $this->getCardByJiraId($jira_id) : FALSE;
            }
        }

        return $response;
    }

    /**
     * Maps a given array against the defined leankit map.
     *
     * @param array $card data to map
     *
     * @return array mapped data
     */
    protected function _mapCard(array $card) {
        $mapped_card = [];
        $map = $this->getMap();

        foreach ($map as $key => $leankit_key) {
            if (isset($card[$key])) {
                $mapped_card[$leankit_key] = $card[$key];
            }
        }

        return $mapped_card;
    }

    protected function _convertDueDate($date) {
        // Jira gives this format: "20/Jan/17 12:00 AM" (NOT compatible with strtotime)
        // Leankit needs d/m/Y
        // $date = date('d/m/Y', strtotime($date));
        $date = \DateTime::createFromFormat('j/M/y g:i A', $date);
        return $date ? $date->format('d/m/Y') : NULL;
    }

    protected function _convertAssignedUserIds($users, $board) {
        // Jira gives the jira username, which doesn't match with any field in leankit
        $users = explode(',', $users);
        if (!empty($users)) {
            $tmp = [];

            $user_map = $this->getUserMap();
            if ($user_map) {
                // We have a custom mapping - not ideal.
                foreach ($users as $user) {
                    if (!empty($user_map[$user])) {
                        $tmp[] = $user_map[$user]['uid'];
                    }
                }
            }
            else {
                // This will try to match by Name - weak.
                $board_users = $this->getBoardUsers($board);
                foreach ($users as $user) {
                    if (($index = array_search($user, $board_users)) !== FALSE) {
                        $tmp[] = $index;
                    }
                }
            }

            // Copy tmp to users
            $users = $tmp;
        }

        // None passed initially or none valid.
        if (empty($users)) {
            $users = NULL;
        }

        return $users;
    }

    /**
     * Sanitize data.
     *
     * @param array $card_data original data.
     *
     * @param string $board name of the board.
     *
     * @return mixed
     */
    protected function _sanitizeData(array $card_data, $board) {
        // Format date if present.
        if (!empty($card_data['DueDate'])) {
            $card_data['DueDate'] = $this->_convertDueDate($card_data['DueDate']);
            if (is_null($card_data['DueDate'])) {
                unset($card_data['DueDate']);
            }
        }

        // Find userId if present.
        if (!empty($card_data['AssignedUserIds'])) {
            $card_data['AssignedUserIds'] = $this->_convertAssignedUserIds($card_data['AssignedUserIds'], $board);
            if (is_null($card_data['AssignedUserIds'])) {
                unset($card_data['AssignedUserIds']);
            }
        }

        return $card_data;
    }

    /**
     * Get a list of card types in the board.
     *
     * @param string $board
     *  Board name or id.
     *
     * @return bool|array types of cards in GMT board
     */
    public function getBoardCardTypes($board = 'Amazee GMT') {
        $board = $this->getGmtBoard($board);
        return ($board) ? $board->CardTypes : FALSE;
    }

    /**
     * Returns the ID of a given card type name.
     *
     * @param $name
     * @param string $board
     *
     * @return bool|string
     */
    public function getCardTypeId($name, $board = 'Amazee GMT') {
        $card_types = $this->getBoardCardTypes($board);
        if ($card_types) {
            foreach ($card_types as $ct) {
                if ($ct->Name == $name) {
                    return $ct->Id;
                }
            }
        }

        return FALSE;
    }

    /**
     * Get a list of lanes in the board.
     *
     * @param string $board
     *  Board name or id.
     *
     * @return bool|array list of lanes in GMT board
     */
    public function getBoardLanes($board = 'Amazee GMT') {
        $board = $this->getGmtBoard($board);
        return ($board) ? $board->Lanes : FALSE;
    }

    /**
     * Gets a list of users for that board.
     *
     * @param string $board name of the board.
     * @param bool $as_array return as array
     *
     * @return mixed|array|bool
     */
    public function getBoardUsers($board = 'Amazee GMT', $as_array = TRUE) {
        $board = $this->getGmtBoard($board);
        if ($board && $as_array) {
            $users_array = [];
            foreach ($board->BoardUsers as $boardUser) {
                $users_array[$boardUser->Id] = $boardUser->FullName;
            }

            return $users_array;
        }

        return ($board) ? $board->BoardUsers : FALSE;
    }

    /**
     * Gets the default board lane.
     *
     * @param string $board
     *  Board name or id.
     *
     * @return bool|string id of the lane.
     */
    public function getDefaultBoardLane($board = 'Amazee GMT') {
        $board = $this->getGmtBoard($board);
        return ($board) ? $board->DefaultDropLaneId : FALSE;
    }

    /**
     * Transform userIds into emails
     * @param string $board board to get users from
     * @param array $user_ids ids of the users
     * @return array list of emails
     */
    protected function _convertAssignedUserIdsToEmails($board, $user_ids) {
        $users = $this->getBoardUsers($board, FALSE);

        $emails = [];
        if ($users) {
            foreach ($users as $user) {
                if (in_array($user->Id, $user_ids)) {
                    $emails[] = $user->EmailAddress;
                }
            }
        }

        return $emails;
    }
}
