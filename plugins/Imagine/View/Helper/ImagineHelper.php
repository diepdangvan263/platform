<?php
/**
 * Copyright 2011-2012, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * Copyright 2011-2012, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

App::uses('Security', 'Utility');

/**
 * CakePHP Imagine Plugin
 *
 * @package Imagine.View.Helper
 */
class ImagineHelper extends AppHelper {
/**
 * Helpers
 *
 * @var array $helpers
 * @access public
 */
	public $helpers = array('Html');

/**
 * Finds URL for specified action and sign it.
 *
 * Returns an URL pointing to a combination of controller and action. Param
 *
 * @param  mixed  $url    Cake-relative URL, like "/products/edit/92" or "/presidents/elect/4"
 *                        or an array specifying any of the following: 'controller', 'action',
 *                        and/or 'plugin', in addition to named arguments (keyed array elements),
 *                        and standard URL arguments (indexed array elements)
 * @param array $options List of named arguments that need to sign
 * @param boolean $full If true, the full base URL will be prepended to the result
 * @return string Full translated signed URL with base path and with
 * @access public
 */
	public function imageUrl($url = array(), $options, $full = false) {
		if (is_string($url)) {
			$url = array_merge(array('plugin' => 'media', 'admin' => false, 'controller' => 'media', 'action' => 'image'), array($url));
		}
		$options = $this->pack($options);
		$options['hash'] = $this->hash($options);

		$url = array_merge((array)$url, $options + array('base' => false));
		return $this->requestAction($url, array('return' => true));
		#return $this->Html->url($url, $full);
	}

/**
 * Signs the url with a salted hash
 *
 * @param array $options
 * @return string
 * @access public
 */
	public function hash($options) {
		$mediaSalt = Configure::read('Imagine.salt');
		if (empty($mediaSalt)) {
			throw new Exception(__d('imagine', 'Please configure Imagine.salt using Configure::write(\'Imagine.salt\', \'YOUR-SALT-VALUE\')', true));
		}
		ksort($options);
		return urlencode(Security::hash(serialize($options) . $mediaSalt));
	}

/**
 * Packs the image options array into an array of named arguments that can be used in a cake url
 *
 * @param array $options
 * @return array
 * @access public
 */
	public function pack($options) {
		$result = array();
		foreach ($options as $operation => $data) {
			$tmp = array();
			foreach ($data as $key => $value) {
				if (is_string($value) || is_numeric($value) || is_bool($value)) {
					if (is_bool($value)) {
						$value = (int) $value;
					}
					$tmp[] = "$key|$value";
				}
			}
			$result[$operation] = join(';', $tmp);
		}
		return $result;
	}

}