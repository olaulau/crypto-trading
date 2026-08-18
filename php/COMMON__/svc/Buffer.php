<?php
namespace COMMON__\svc;

use ErrorException;


class Buffer //TODO soon useless ?
{

	private int $max_size;
	private array $data;
	
	
	public function __construct ($max_size)
	{
		$this->max_size = $max_size;
		$this->data = [];
	}
	
	
	public function push (mixed $element) : void
	{
		if ($this->full()) {
			$this->pop();
		}
		array_push($this->data, $element);
	}
	
	
	public function pop () : mixed
	{
		return array_shift ($this->data);
	}
	
	
	public function get (int $i) : mixed
	{
		if ($i < 0 || $i > $this->size()) {
			throw new ErrorException("invalid buffer index");
		}
		return $this->data [$i];
	}
	
	
	public function size () : int
	{
		return count($this->data);
	}

	public function empty () : bool
	{
		return $this->size() === 0;
	}
	
	
	public function max_size () : int
	{
		return $this->max_size;
	}

	public function full () : bool
	{
		return $this->size() === $this->max_size();
	}
	
	
	public function get_data () : array
	{
		return $this->data;
	}
	
	
	public function clear () : void
	{
		$this->data = [];
	}
	
	
	public function first () : mixed
	{
		return $this->get(0);
	}
	
	
	public function last () : mixed
	{
		return $this->get($this->size()-1);
	}

}
