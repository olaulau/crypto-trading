<?php
namespace COMMON__\svc;

use COMMON__\mdl\Mdl;


class BigQuery
{
	const max = 1000;
	
	private Mdl $wrapper;
	private int $offset=0;
	private Iterable $data;

	function __construct (private string $entity, private array $filter, private array $options)
	{
		$this->wrapper = new $this->entity;
	}
	
	private function get_data () : void
	{
		$options = $this->options;
		$options ["offset"] = $this->offset;
		$options ["limit"] = static::max;
		$this->data = $this->wrapper->find($this->filter, $options);
	}
	
	
	public function next () : ?Mdl
	{
		$i = $this->offset % static::max;
		if ($i === 0) {
			$this->get_data();
		}
		
		if ($i+1 > count($this->data)) {
			return null;
		}
		
		$res = $this->data [$i];
		$this->offset ++;
		return $res;
	}

}
