<?php

namespace App;

use chobie\Jira\Api;
use chobie\Jira\Api\Authentication\Basic;
use chobie\Jira\Issues\Walker;

/**
 * Class Jira
 *
 * @package App
 */
class Jira extends Api {
  /**
   * Jira constructor.
   */
  public function __construct() {
    return parent::__construct(
      env('JIRA_HOST'),
      new Basic(env('JIRA_USERNAME'), env('JIRA_PASSWORD'))
    );
  }

  /**
   * List of GMT relevant issues.
   * @return \chobie\Jira\Api\Result|false
   */
  public function getGmtIssues() {
    $jql = 'labels = GMT AND project != ALGMT AND project != AGOH AND (resolution is EMPTY OR resolution = EMPTY OR resolutiondate >= -5d) ORDER BY Rank ASC';
    return $this->search($jql);
  }

  /**
   * Gets when an issue was last updated.
   *
   * @param $issue_key
   * @return string|false Time issue was last updated.
   */
  public function getLastUpdated($issue_key) {
    $issue = $this->getIssue($issue_key);
    if ($issue) {
      return $issue->getResult()['fields']['updated'];
    }

    return false;
  }
}