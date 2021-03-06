<?php

App::uses('Folder', 'Utility');
App::uses('File', 'Utility');
App::uses('Component', 'Controller');


class LessHelper extends AppHelper {

	public $helpers = array('Html');

	public function css($file, $options = array())
	{

		if (isset($options['theme']) and trim($options['theme'])) {
			$this->set_theme($options['theme']);
		}

		if (is_array($file)) {
			foreach ($file as $candidate) {
				$source = $this->lessFolder->path.DS.$candidate.'.less';
				$target = str_replace('.less', '.css', str_replace($this->lessFolder->path, $this->cssFolder->path, $source));
				$this->auto_compile_less($source, $target);
			}
		} else {
			if (isset($options['plugin']) and trim($options['plugin'])){
				$this->lessFolder= new Folder(APP.'Plugin'.DS.$options['plugin'].DS.'webroot'.DS.'less');
			}
			$source = $this->lessFolder->path.DS.$file.'.less';
			$target = str_replace('.less', '.css', str_replace($this->lessFolder->path, $this->cssFolder->path, $source));
			$this->auto_compile_less($source, $target);
		}
		echo $this->Html->css($file);
	}

	public function auto_compile_less($lessFilename, $cssFilename) {
		// Check if cache & output folders are writable and the less file exists.
		if (!is_writable(CACHE.'less')) {
			trigger_error(__d('cake_dev', '"%s" directory is NOT writable.', CACHE.'less'), E_USER_NOTICE);
			return;
		}
		if (file_exists($lessFilename) == false) {
			trigger_error(__d('cake_dev', 'File: "%s" not found.', $lessFilename), E_USER_NOTICE);
			return;
		}

		// Cache location
		$cacheFilename = CACHE.'less'.DS.str_replace('/', '_', str_replace($this->lessFolder->path, '', $lessFilename).".cache");

		// Load the cache
		if (file_exists($cacheFilename)) {
			$cache = unserialize(file_get_contents($cacheFilename));
		} else {
			$cache = $lessFilename;
		}

		$new_cache = Lessify::cexecute($cache);
		if (!is_array($cache) || $new_cache['updated'] > $cache['updated'] || file_exists($cssFilename) === false) {
			$cssFile = new File($cssFilename, true);
			if ($cssFile->write($new_cache['compiled']) === false) {
				if (!is_writable(dirname($cssFilename))) {
					trigger_error(__d('cake_dev', '"%s" directory is NOT writable.', dirname($cssFilename)), E_USER_NOTICE);
				}
				trigger_error(__d('cake_dev', 'Failed to write "%s"', $cssFilename), E_USER_NOTICE);
			}

			$cacheFile = new File($cacheFilename, true);
			$cacheFile->write(serialize($new_cache));
		}
	}

}