<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Caching\Storages;

use Nette;
use Nette\Caching\Cache;


/**
 * Memcached storage.
 */
class MemcachedStorage implements Nette\Caching\IStorage
{
	use Nette\SmartObject;

	/** @internal cache structure */
	const
		META_CALLBACKS = 'callbacks',
		META_DATA = 'data',
		META_DELTA = 'delta';

	/** @var \Memcache */
	private $memcache;

	/** @var string */
	private $prefix;

	/** @var IJournal */
	private $journal;


	/**
	 * Checks if Memcached extension is available.
	 * @return bool
	 */
	public static function isAvailable()
	{
		return extension_loaded('memcache');
	}


	public function __construct($host = 'localhost', $port = 11211, $prefix = '', IJournal $journal = null)
	{
		if (!static::isAvailable()) {
			throw new Nette\NotSupportedException("PHP extension 'memcache' is not loaded.");
		}

		$this->prefix = $prefix;
		$this->journal = $journal;
		$this->memcache = new \Memcache;
		if ($host) {
			$this->addServer($host, $port);
		}
	}


	public function addServer($host = 'localhost', $port = 11211, $timeout = 1)
	{
		if ($this->memcache->addServer($host, $port, true, 1, $timeout) === false) {
			$error = error_get_last();
			throw new Nette\InvalidStateException("Memcache::addServer(): $error[message].");
		}
	}


	/**
	 * @return \Memcache
	 */
	public function getConnection()
	{
		return $this->memcache;
	}


	public function read($key)
	{
		$key = urlencode($this->prefix . $key);
		$meta = $this->memcache->get($key);
		if (!$meta) {
			return null;
		}

		// meta structure:
		// array(
		//     data => stored data
		//     delta => relative (sliding) expiration
		//     callbacks => array of callbacks (function, args)
		// )

		// verify dependencies
		if (!empty($meta[self::META_CALLBACKS]) && !Cache::checkCallbacks($meta[self::META_CALLBACKS])) {
			$this->memcache->delete($key, 0);
			return null;
		}

		if (!empty($meta[self::META_DELTA])) {
			$this->memcache->replace($key, $meta, 0, $meta[self::META_DELTA] + time());
		}

		return $meta[self::META_DATA];
	}


	public function lock($key)
	{
	}


	public function write($key, $data, $dp)
	{
		if (isset($dp[Cache::ITEMS])) {
			throw new Nette\NotSupportedException('Dependent items are not supported by MemcachedStorage.');
		}

		$key = urlencode($this->prefix . $key);
		$meta = [
			self::META_DATA => $data,
		];

		$expire = 0;
		if (isset($dp[Cache::EXPIRATION])) {
			$expire = (int) $dp[Cache::EXPIRATION];
			if (!empty($dp[Cache::SLIDING])) {
				$meta[self::META_DELTA] = $expire; // sliding time
			}
		}

		if (isset($dp[Cache::CALLBACKS])) {
			$meta[self::META_CALLBACKS] = $dp[Cache::CALLBACKS];
		}

		if (isset($dp[Cache::TAGS]) || isset($dp[Cache::PRIORITY])) {
			if (!$this->journal) {
				throw new Nette\InvalidStateException('CacheJournal has not been provided.');
			}
			$this->journal->write($key, $dp);
		}

		$this->memcache->set($key, $meta, 0, $expire);
	}


	public function remove($key)
	{
		$this->memcache->delete(urlencode($this->prefix . $key), 0);
	}


	public function clean($conditions)
	{
		if (!empty($conditions[Cache::ALL])) {
			$this->memcache->flush();

		} elseif ($this->journal) {
			foreach ($this->journal->clean($conditions) as $entry) {
				$this->memcache->delete($entry, 0);
			}
		}
	}
}
