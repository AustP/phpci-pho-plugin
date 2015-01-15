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

    if($this->log)
      $this->phpci->log($output);

    $matches = array();
    preg_match('~(\d+) spec.*?(\d+) failure~', $output, $matches);
    $specs = $matches[1];
    $failures = $matches[2];

    if ($specs == 0) {
      $this->phpci->logFailure(Lang::get('no_tests_performed'));
      return false;
    } elseif ($failures > 0) {
      if(!$this->log)
        $this->phpci->logFailure($output);
      
      return false;
    } else {
      return true;
    }
  }
}
