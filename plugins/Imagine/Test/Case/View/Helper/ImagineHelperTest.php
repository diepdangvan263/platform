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

App::uses('ImagineHelper', 'Imagine.View/Helper');
App::uses('View', 'View');

/**
 * ImagineHelperTest class
 *
 * @package       Imagine.Test.Case.View.Helper
 */
class ImagineHelperTest extends CakeTestCase {

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		Configure::write('Imagine.salt', 'this-is-a-nice-salt');
		$controller = null;
		$View = new View($controller);
		$this->Imagine = new ImagineHelper($View);
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		unset($this->Imagine);
	}

/**
 * testUrl method
 *
 * @return void
 */
	public function testUrl() {
		$result = $this->Imagine->url(
			array(
				'controller' => 'images',
				'action' => 'display',
				1),
			array(
				'thumbnail' => array(
					'width' => 200,
					'height' => 150)));
		$expected = '/images/display/1/thumbnail:width|200;height|150/hash:69aa9f46cdc5a200dc7539fc10eec00f2ba89023';
		$this->assertEqual($result, $expected);
	}

/**
 * testHash method
 *
 * @return void
 */
	public function testHash() {
		$options = $this->Imagine->pack(array(
			'thumbnail' => array(
				'width' => 200,
				'height' => 150)));
		$result = $this->Imagine->hash($options);
		$this->assertEqual($result, '69aa9f46cdc5a200dc7539fc10eec00f2ba89023');
	}

/**
 * testHash method
 *
 * @expectedException Exception
 * @return void
 */
	public function testMissingSaltForHash() {
		Configure::write('Imagine.salt', null);
		$result = $this->Imagine->hash('foo');
	}
/**
 * testUrl method
 *
 * @return void
 */
	public function testPack() {
		$result = $this->Imagine->pack(array(
			'thumbnail' => array(
				'width' => 200,
				'height' => 150)));

		$this->assertEqual($result, array('thumbnail' => 'width|200;height|150'));
	}

}
