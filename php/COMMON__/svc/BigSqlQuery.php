<?php
namespace COMMON__\svc;

use Base;
use DB\SQL;


class BigSqlQuery
{
	const max = 1000;
	
	private int $offset=0;
	private Iterable $data;

	function __construct (private string $sql, private array $params)
	{
		
	}
	
	private function get_data () : void
	{
		$f3 = Base::instance ();
		$db = $f3->get ("db"); /** @var SQL $db */
		
		$sql = $this->sql . " LIMIT " . static::max . " OFFSET " . $this->offset ;
		$this->data = $db->exec ($sql, $this->params);
	}
	
	
	public function next () : ?array
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
