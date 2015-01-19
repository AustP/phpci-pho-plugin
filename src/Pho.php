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

    if($this->log)
      $this->phpci->log($output);

    $output = explode('Finished in ', $output);
    $specs = $output[0];
    $metadata = preg_split("~\r\n|\n|\r~", $output[1], PREG_SPLIT_NO_EMPTY);

    $seconds = 'Finished in ' . $metadata[0];
    $specData = $metadata[1];

    $matches = array();
    preg_match("~(\d+) spec.*?(\d+) failure~", $specData, $matches);
    $specCount = $matches[1];
    $failureCount = $matches[2];

    $specs = explode('Failures:', $specs);
    $expectations = preg_split("~\r\n|\n|\r~", $specs[0], PREG_SPLIT_NO_EMPTY);
    $specFailures = $specs[1];

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
    foreach($expectations as $expectation){
      $indents = substr_count($expectation, "\t");

      if($indents > $previousIndents){
        $data['expectations'][$index]['definition'] = true;
        $parentIndex = $index;
      }

      $previousIndents = $indents;
      $index++;

      $expectationData = array(
        'definition'=>false,
        'parent'=>$indents? $parentIndex: null,
        'content'=>str_replace("\t", "&nbsp;&nbsp;", $expectation),
        'success'=>true
      );

      $fullExpectation = $expectationData['content'];
      $parent = $expectationData['parent'];
      while($parent !== null){
        $fullExpectation = trim($data[$parent]['content']) . ' ' . trim($fullExpectation);
        $parent = $data[$parent]['parent'];
      }
      $expectationMap[trim($fullExpectation)] = $index;

      $data['expectations'][] = $expectationData;
    }

    if($specFailures){
      $dir = explode(DIRECTORY_SEPARATOR, dirname(__FILE__));
      $dir = implode(DIRECTORY_SEPARATOR, array_slice($dir, 0, -4));

      $matches = array();
      preg_match_all("~\"(.+)\" FAILED(\r\n|\n|\r)" . $dir . "/PHPCI/build/\d+/(.+)~", $specFailures, $matches);
      foreach($matches as $match){
        $expectation = $match[1];
        $file = $match[2];

        $index = $expectationMap[$expectation];
        if($index > -1){
          $data['expectations'][$index]['success'] = false;
          $data['expectations'][$index]['file'] = $file;
        }
      }
    }

    $this->build->storeMeta('pho-errors', $failureCount);
    $this->build->storeMeta('pho-data', $data);

    if ($specCount == 0) {
      $this->phpci->logFailure(Lang::get('no_tests_performed'));
      return false;
    } elseif ($failureCount > 0) {
      if(!$this->log)
        $this->phpci->logFailure($output);

      return false;
    } else {
      return true;
    }
  }
}
