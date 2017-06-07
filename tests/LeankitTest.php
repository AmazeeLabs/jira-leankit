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
    $this->leankit = new \App\Leankit();
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
}
