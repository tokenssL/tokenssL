<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Caching;


/**
 * Cache storage.
 */
interface IStorage
{

	/**
	 * Read from cache.
	 * @param  string  $key
	 * @return mixed
	 */
	public function read($key);

	/**
	 * Prevents item reading and writing. Lock is released by write() or remove().
	 * @param  string  $key
	 * @return void
	 */
	public function lock($key);

	/**
	 * Writes item into the cache.
	 * @param  string  $key
	 * @param  mixed  $data
	 * @return void
	 */
	public function write($key, $data, $dependencies);

	/**
	 * Removes item from the cache.
	 * @param  string  $key
	 * @return void
	 */
	public function remove($key);

	/**
	 * Removes items from the cache by conditions.
	 * @return void
	 */
	public function clean($conditions);
}
