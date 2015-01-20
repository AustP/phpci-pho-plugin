<?php
/**
* PHPCI - Continuous Integration for PHP
*
* @copyright    Copyright 2014, Block 8 Limited.
* @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
* @link         https://www.phptesting.org/
*/

namespace PHPCI_Pho_Plugin;

use PHPCI\Builder;
use PHPCI\Model\Build;
use PHPCI\Helper\Lang;
use PHPCI\Plugin\Util\TapParser;

/**
* Pho plugin, runs Pho tests within a project.
* @package PHPCI\Plugin
*/
class Pho implements \PHPCI\Plugin
{
  private $executable;
  private $bootstrap;
  private $filter;
  private $namespace;
  private $directory;
  private $log;

  /**
  * Set up the plugin, configure options, etc.
  * @param Builder $phpci
  * @param Build $build
  * @param array $options
  */
  public function __construct(Builder $phpci, Build $build, array $options = array())
  {
    $this->phpci = $phpci;
    $this->build = $build;

    if (isset($options['executable'])) {
      $this->executable = $options['executable'];
    } else {
      $curdir = getcwd();
      chdir($this->phpci->buildPath);
      $this->executable = $this->phpci->findBinary('pho');
      chdir($curdir);
    }

    if (isset($options['bootstrap'])) {
      $this->bootstrap = $options['bootstrap'];
    }
    if (isset($options['filter'])) {
      $this->filter = $options['filter'];
    }
    if (isset($options['namespace'])) {
      $this->namespace = true;
    }
    if (isset($options['directory'])) {
      $this->directory = $options['directory'];
    }
    if (isset($options['log'])) {
      $this->log = true;
    }
  }

  /**
  * Run the Pho plugin.
  * @return bool
  */
  public function execute()
  {
    if(!$this->executable) {
      $this->phpci->logFailure(Lang::get('could_not_find', 'pho'));
      return false;
    }
    if(!$this->directory) {
      $this->phpci->logFailure(Lang::get('invalid_command'));
      return false;
    }

    $this->phpci->logExecOutput(false);

    $cmd = escapeshellarg($this->executable) . " -C --reporter spec";

    if ($this->bootstrap) {
      $bootstrapPath = $this->phpci->buildPath . $this->bootstrap;
      $cmd .= " -b " . escapeshellarg($bootstrapPath);
    }
    if ($this->filter) {
      $cmd .= " -f " . escapeshellarg($this->filter);
    }
    if ($this->namespace) {
      $cmd .= " -n";
    }

    $cmd .= " " . escapeshellarg($this->phpci->buildPath . $this->directory);
    $this->phpci->executeCommand($cmd);
    $output = $this->phpci->getLastOutput();

    $this->phpci->logExecOutput(true);

    if ($this->log)
      $this->phpci->log($output);

    $output = explode('Finished in ', $output);
    $specs = $output[0];
    $metadata = preg_split("~\r\n|\n|\r~", $output[1]);

    $seconds = 'Finished in ' . $metadata[0];
    $specData = $metadata[1];

    $matches = array();
    preg_match("~(\d+) spec.*?(\d+) failure~", $specData, $matches);
    $specCount = $matches[1];
    $failureCount = $matches[2];

    $specs = explode('Failures:', $specs);
    $expectations = preg_split("~\r\n|\n|\r~", $specs[0]);
    $specFailures = isset($specs[1])? 'Failures:' . $specs[1]: '';

    $data = array(
      'metadata'=>array(
        'seconds'=>$seconds,
        'specData'=>$specData
      ),
      'expectations'=>array()
    );
    $expectationMap = array();
    $definitions = array();
    $previousIndents = 0;
    $parentIndex = 0;
    $index = -1;
    foreach ($expectations as $expectation) {
      $match = array();
      preg_match("~^(\s*)~", $expectation, $match);
      $indents = substr_count($match[1], " ")/4;

      if ($indents > $previousIndents) {
        $data['expectations'][$index]['definition'] = true;
        $parentIndex = $index;
      } elseif ($indents && $indents < $previousIndents) {
        $times = $previousIndents - $indents;
        for($i=0; $i<$times; $i++){
          $parentIndex = $data['expectations'][$parentIndex]['parent'];
        }
      }

      $previousIndents = $indents;
      $index++;

      $expectationData = array(
        'definition'=>false,
        'parent'=>$indents? $parentIndex: null,
        'content'=>trim($expectation),
        'success'=>true,
        'indents'=>$indents
      );

      $fullExpectation = $expectationData['content'];
      $parent = $expectationData['parent'];
      while ($parent !== null) {
        $fullExpectation = $data['expectations'][$parent]['content'] . ' ' . $fullExpectation;
        $parent = $data['expectations'][$parent]['parent'];
      }
      $expectationMap[$fullExpectation] = $index;

      $data['expectations'][] = $expectationData;
    }

    if ($specFailures) {
      $matches = array();
      preg_match_all("~\"(.+?)\" FAILED\s+(.+)\s+(.+)~", $specFailures, $matches);
      foreach ($matches[0] as $i=>$match) {
        $expectation = $matches[1][$i];
        $file = $matches[2][$i];
        $expected = $matches[3][$i];

        $index = $expectationMap[$expectation];
        $data['expectations'][$index]['success'] = false;
        $data['expectations'][$index]['f'] = $file;
        $data['expectations'][$index]['e'] = $expected;

        $parent = $data['expectations'][$index]['parent'];
        while ($parent !== null) {
          $data['expectations'][$parent]['success'] = false;
          $parent = $data['expectations'][$parent]['parent'];
        }
      }
    }

    foreach ($data['expectations'] as $i=>$expectation) {
      if ($expectation['success']) {
        unset($data['expectations'][$i]);
        continue;
      }

      unset($data['expectations'][$i]['parent']);

      $data['expectations'][$i]['d'] = $expectation['definition'];
      unset($data['expectations'][$i]['definition']);

      $data['expectations'][$i]['c'] = $expectation['content'];
      unset($data['expectations'][$i]['content']);

      $data['expectations'][$i]['s'] = (int)$expectation['success'];
      unset($data['expectations'][$i]['success']);

      $data['expectations'][$i]['i'] = $expectation['indents'];
      unset($data['expectations'][$i]['indents']);
    }
    $data['expectations'] = array_values($data['expectations']);

    $this->build->storeMeta('pho-errors', $failureCount);
    $this->build->storeMeta('pho-data', $data);

    if ($specCount == 0) {
      $this->phpci->logFailure(Lang::get('no_tests_performed'));
      return false;
    } elseif ($failureCount > 0) {
      if (!$this->log)
        $this->phpci->logFailure($specFailures);

      return false;
    } else {
      return true;
    }
  }
}
