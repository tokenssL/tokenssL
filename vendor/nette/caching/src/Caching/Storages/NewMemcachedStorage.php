<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Caching\Storages;

use Nette;
use Nette\Caching\Cache;


/**
 * Memcached storage using memcached extension.
 */
class NewMemcachedStorage implements Nette\Caching\IStorage, Nette\Caching\IBulkReader
{
	use Nette\SmartObject;

	/** @internal cache structure */
	const
		META_CALLBACKS = 'callbacks',
		META_DATA = 'data',
		META_DELTA = 'delta';

	/** @var \Memcached */
	private $memcached;

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
		return extension_loaded('memcached');
	}


	public function __construct($host = 'localhost', $port = 11211, $prefix = '', IJournal $journal = null)
	{
		if (!static::isAvailable()) {
			throw new Nette\NotSupportedException("PHP extension 'memcached' is not loaded.");
		}

		$this->prefix = $prefix;
		$this->journal = $journal;
		$this->memcached = new \Memcached;
		if ($host) {
			$this->addServer($host, $port);
		}
	}


	public function addServer($host = 'localhost', $port = 11211)
	{
		if ($this->memcached->addServer($host, $port, 1) === false) {
			$error = error_get_last();
			throw new Nette\InvalidStateException("Memcached::addServer(): $error[message].");
		}
	}


	/**
	 * @return \Memcached
	 */
	public function getConnection()
	{
		return $this->memcached;
	}


	public function read($key)
	{
		$key = urlencode($this->prefix . $key);
		$meta = $this->memcached->get($key);
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
			$this->memcached->delete($key, 0);
			return null;
		}

		if (!empty($meta[self::META_DELTA])) {
			$this->memcached->replace($key, $meta, $meta[self::META_DELTA] + time());
		}

		return $meta[self::META_DATA];
	}


	public function bulkRead($keys)
	{
		$prefixedKeys = array_map(function ($key) {
			return urlencode($this->prefix . $key);
		}, $keys);
		$keys = array_combine($prefixedKeys, $keys);
		$metas = $this->memcached->getMulti($prefixedKeys);
		$result = [];
		$deleteKeys = [];
		foreach ($metas as $prefixedKey => $meta) {
			if (!empty($meta[self::META_CALLBACKS]) && !Cache::checkCallbacks($meta[self::META_CALLBACKS])) {
				$deleteKeys[] = $prefixedKey;
			} else {
				$result[$keys[$prefixedKey]] = $meta[self::META_DATA];
			}

			if (!empty($meta[self::META_DELTA])) {
				$this->memcached->replace($prefixedKey, $meta, $meta[self::META_DELTA] + time());
			}
		}
		if (!empty($deleteKeys)) {
			$this->memcached->deleteMulti($deleteKeys, 0);
		}

		return $result;
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

		$this->memcached->set($key, $meta, $expire);
	}


	public function remove($key)
	{
		$this->memcached->delete(urlencode($this->prefix . $key), 0);
	}


	public function clean($conditions)
	{
		if (!empty($conditions[Cache::ALL])) {
			$this->memcached->flush();

		} elseif ($this->journal) {
			foreach ($this->journal->clean($conditions) as $entry) {
				$this->memcached->delete($entry, 0);
			}
		}
	}
}
