<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Nette\Iterators;



/**
 * Applies the callback to the elements of the inner iterator.
 */
class Mapper extends \IteratorIterator
{
	/** @var callable */
	private $callback;

	#[\ReturnTypeWillChange]
	public function __construct(\Traversable $iterator, $callback)
	{
		parent::__construct($iterator);
		$this->callback = $callback;
	}

	#[\ReturnTypeWillChange]
	public function current()
	{
		return call_user_func($this->callback, parent::current(), parent::key());
	}
}
