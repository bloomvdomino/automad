<?php 
/*
 *	                  ....
 *	                .:   '':.
 *	                ::::     ':..
 *	                ::.         ''..
 *	     .:'.. ..':.:::'    . :.   '':.
 *	    :.   ''     ''     '. ::::.. ..:
 *	    ::::.        ..':.. .''':::::  .
 *	    :::::::..    '..::::  :. ::::  :
 *	    ::'':::::::.    ':::.'':.::::  :
 *	    :..   ''::::::....':     ''::  :
 *	    :::::.    ':::::   :     .. '' .
 *	 .''::::::::... ':::.''   ..''  :.''''.
 *	 :..:::'':::::  :::::...:''        :..:
 *	 ::::::. '::::  ::::::::  ..::        .
 *	 ::::::::.::::  ::::::::  :'':.::   .''
 *	 ::: '::::::::.' '':::::  :.' '':  :
 *	 :::   :::::::::..' ::::  ::...'   .
 *	 :::  .::::::::::   ::::  ::::  .:'
 *	  '::'  '':::::::   ::::  : ::  :
 *	            '::::   ::::  :''  .:
 *	             ::::   ::::    ..''
 *	             :::: ..:::: .:''
 *	               ''''  '''''
 *	
 *
 *	AUTOMAD
 *
 *	Copyright (c) 2019-2020 by Marc Anton Dahmen
 *	http://marcdahmen.de
 *
 *	Licensed under the MIT license.
 *	http://automad.org/license
 */


namespace Automad\System;
use Automad\Core as Core;
use Automad\GUI as GUI;


defined('AUTOMAD') or die('Direct access not permitted!');


/**
 *	The Composer class is a wrapper for setting up Composer and executing commands. 
 *
 *	@author Marc Anton Dahmen
 *	@copyright Copyright (c) 2019-2020 by Marc Anton Dahmen - <http://marcdahmen.de>
 *	@license MIT license - http://automad.org/license
 */

class Composer {


	/**	
	 *	A chached file including the temporary Composer install directory.
	 */

	private $installDirCacheFile = AM_BASE_DIR . AM_DIR_CACHE . '/' . AM_FILE_PREFIX_CACHE . '_composer_dir';


	/**	
	 * 	Composer extraction directory within temporary directory.
	 */

	private $extractionDir = '/src';


	/**	
	 * 	Composer autoloader within the temporary extraction directory.
	 */

	private $autoloader = '/vendor/autoload.php';


	/**	
	 *	The Composer memory limit error message text.
	 */

	private $memoryErrorMessage = false;


	/**
	 * 	The download URL for the composer.phar file.
	 */

	private $pharUrl = AM_COMPOSER_PHAR_URL;


	/**
	 *	A variable to reserve memory for running a shutdown function 
	 *	when Composer reaches the allowd memory limit.
	 */

	private $reservedShutdownMemory = null;


	/**
	 *	The constructor runs the setup.
	 */

	public function __construct() {

		$this->setUp();

	}


	/**
	 *	Download the composer.phar file to a given directory.
	 *	
	 *	@param string $dir
	 *	@return string The full path to the downloaded composer.phar file
	 */

	private function downloadPhar($dir) {

		$phar = false;

		if (is_writable($dir)) {

			$phar = $dir . '/composer_' . time() . '.phar';

			set_time_limit(0);
			
			$fp = fopen($phar, 'w+');
			
			$options = array(
				CURLOPT_TIMEOUT => 120,
				CURLOPT_FILE => $fp,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_FRESH_CONNECT => 1,
				CURLOPT_URL => $this->pharUrl
			);
			
			$curl = curl_init();
			curl_setopt_array($curl, $options);
			curl_exec($curl); 
			
			if (curl_getinfo($curl, CURLINFO_HTTP_CODE) != 200 || curl_errno($curl)) {
				$phar = false;
			}
			
			curl_close($curl);
			fclose($fp);
			
			Core\Debug::log($phar, 'Downloaded Composer PHAR');

		}
		
		return $phar;

	}


	/**
	 * 	Read the Composer install directory from cache to reuse the installation
	 *	in case Composer was already used before. In case Composer hasn't been used before,
	 *	a new path will be generated and save to the cache.
	 *
	 * 	@return string The path to the installation directory
	 */

	private function getInstallDir() {

		// Get Composer install directory from cache or create new path.
		if (is_readable($this->installDirCacheFile)) {
		
			$installDir = file_get_contents($this->installDirCacheFile);
			Core\Debug::log($installDir, 'Getting Composer installation directory from cache');
			
			// To verify that the directory actually contains Composer, simply test for existance of the autoloader.
			if (!is_readable($installDir . $this->extractionDir . $this->autoloader)) {
				Core\Debug::log($installDir . $this->extractionDir . $this->autoloader, 'Autoloader not found');
				return $this->newInstallDir();
			}
		
			return $installDir;
		
		} 

		return $this->newInstallDir();

	}


	/**	
	 * 	Generate a fresh installation directory for Composer.
	 * 
	 * 	@return string The path to the directory
	 */

	private function newInstallDir() {

		$tmp = Core\FileSystem::getTmpDir();
		$installDir = $tmp . '/composer_' . time();
		Core\Debug::log($installDir, 'Generating new Composer installation path');
		Core\FileSystem::write($this->installDirCacheFile, $installDir);
		Core\FileSystem::makeDir($installDir);
		
		return $installDir;

	}


	/**	
	 * 	Run a given Composer command.
	 * 	
	 * 	@param string $command
	 */

	public function run($command) {

		$this->shutdownOnError();
		
		chdir(AM_BASE_DIR);

		set_time_limit(-1);
		ini_set('memory_limit', -1);
				
		$input = new \Symfony\Component\Console\Input\StringInput($command);
		$application = new \Composer\Console\Application();
		
		$application->setAutoExit(false);
		$application->setCatchExceptions(false);
		
		Core\Debug::log($command, 'Command');

		try {
			$application->run($input);
		} catch (\Exception $e) {
			return $e->getMessage();
		}

		Core\Debug::log(round(memory_get_peak_usage() / 1024 / 1024) . ' mb', 'Memory used');

	}


	/**	
	 * 	Set up Composer by downloading and extracting the composer.phar to a temporary directory 
	 * 	outside the document root, defining some environment variables, registering a shutdown
	 * 	function and including the autoloader.
	 */

	private function setUp() {

		$installDir = $this->getInstallDir();

		$srcDir = $installDir . $this->extractionDir;

		if (!file_exists($srcDir)) {

			$file = $this->downloadPhar($installDir);
			
			if (!is_readable($file)) {
				Core\Debug::log($file, 'Download of composer.phar failed');
				return false;
			}

			$phar = new \Phar($file);
			$phar->extractTo($srcDir);

		}

		$autoloader = $installDir . $this->extractionDir . $this->autoloader;

		if (!is_readable($autoloader)) {
			Core\Debug::log($autoloader, 'Composer autoloader not found');
			return false;
		}		

		putenv('COMPOSER_HOME=' . $installDir . '/home');

		Core\Debug::log($autoloader, 'Require Composer autoloader');
		require_once($autoloader);

	}


	/**	
	 *	A shutdown function to handle memory limit erros.
	 */

	private function shutdownOnError() {

		ini_set('display_errors', false);
		error_reporting(-1);

		// This memory is cleared on error (case of allowed memory exhausted)
		// to use that memory to run the shutdown function.
    	$this->reservedShutdownMemory = str_repeat('*', 1024 * 1024);
		$this->memoryErrorMessage = GUI\Text::get('error_composer_memory');
				
		register_shutdown_function(function(){

			// Reuse the reserved memory.
			$this->reservedShutdownMemory = null;
			$error = error_get_last();

			if (is_array($error) && !empty($error['type']) && $error['type'] === 1) {
				exit('{"error": "' . $this->memoryErrorMessage . '", "trigger": "composerDone"}');
			}

		});

	}
	

}