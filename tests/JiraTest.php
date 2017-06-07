<?php

/**
 * Class JiraTest.
 */
class JiraTest extends TestCase
{
  /**
   * Jira REST client instance.
   * @var \App\Jira $jira
   */
  private $jira;

  /**
   * Run this method before every test.
   */
  public function setUp() {
    parent::setUp();
    $this->jira = new \App\Jira();
  }

  /**
   * No need to test this as it's covered in test folder in library
   * but just to get started.
   */
  public function testGetIssue() {
    $issue = $this->jira->getIssue('VNT-948');

    $this->assertNotFalse($issue);
    $this->assertArrayHasKey('key', $issue->getResult());
    $this->assertEquals($issue->getResult()['key'], 'VNT-948');
  }

  /**
   * No need to test this as it's covered in test folder in library
   * but just to get started.
   */
  public function testGetGmtQueue() {
    $gmtQ = $this->jira->getGmtIssues();

    $this->assertNotFalse($gmtQ);
    $this->assertGreaterThanOrEqual(0, $gmtQ->getIssuesCount());
  }

  /**
   * We can start testing all the new \App\Jira class methods.
   */

  public function testLastUpdated() {
    $updated = $this->jira->getLastUpdated('VNT-948');

    $this->assertNotFalse($updated);
    $this->assertNotFalse(strtotime($updated));
  }
}
