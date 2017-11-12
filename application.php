#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

$dotenv = new \Dotenv\Dotenv(__DIR__);
$dotenv->load();

use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new \App\Commands\RunCommand());

$application->run();