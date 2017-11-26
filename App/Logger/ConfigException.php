<?php
namespace App\Logger;

class ConfigException extends \Exception {
	protected $message = 'Invalid config';
	protected $code = 400;
}