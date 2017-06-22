<?php

/**
 * Class LeankitTest.
 */
class LeankitTest extends TestCase
{
  /**
   * Leankit REST client instance.
   * @var \App\Leankit $leankit
   */
  private $leankit;

  /**
   * Run this method before every test.
   */
  public function setUp() {
    parent::setUp();
    $this->leankit = new \App\Leankit(env('LEANKIT_ACCOUNT'), env('LEANKIT_USERNAME'), env('LEANKIT_PASSWORD'));
  }

  /**
   * We can start testing all the new \App\Jira class methods.
   */

  public function testGetBoards() {
    // https://amazeegmt.leankit.com/Kanban/Api/Boards
    $boards = $this->leankit->getGmtBoards();

    $this->assertNotFalse($boards);
    $this->assertGreaterThanOrEqual(0, count($boards));
  }

  public function testGetGmtBoard() {
    $board = $this->leankit->getGmtBoard('Amazee GMT');

    $this->assertEquals('Amazee GMT', $board->Title);
  }

  public function testGetGmtCards() {
    $cards = $this->leankit->getCards();

    $this->assertNotFalse($cards);
    $this->assertGreaterThanOrEqual(0, count($cards));
  }

  public function testGetJiraTicket() {
    $jira_id = 'VNT-948';
    $bad_jira_id = 'VNT-948879879';

    $leankit_ticket = $this->leankit->getCardByJiraId($jira_id);
    $no_leankit_ticket = $this->leankit->getCardByJiraId($bad_jira_id);

    $this->assertEquals($leankit_ticket->ExternalCardID, $jira_id);
    $this->assertFalse($no_leankit_ticket);
  }

  public function testUpsertCard() {
    $card_1 = [
      'Issue key' => 'VNT-948',
      'Summary' => 'Create an admin view to export both sides of the flagging data - test',
    ];
    $card_2 = [
      'Issue key' => 'TEST-123',
      'Summary' => 'Test card',
      'Issue type' => 'Bug',
    ];

    $leankit_card_1 = $this->leankit->upsertCard($card_1);
    $leankit_card_2 = $this->leankit->upsertCard($card_2);

    $this->assertNotFalse($leankit_card_1);
    $this->assertEquals($leankit_card_1->ExternalCardID, 'VNT-948');
    $this->assertNotFalse($leankit_card_2);
    $this->assertEquals($leankit_card_2->ExternalCardID, 'TEST-123');

    // Remove the card we just added so that we can test again create method.
    $this->leankit->deleteCard($this->leankit->getGmtBoard()->Id, $leankit_card_2->Id);
  }
}
