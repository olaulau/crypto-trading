<?php
namespace COMMON__\svc;



class Account
{
	
	public function __construct (private string $asset, private float $quantity=0)
	{
		
	}
	
	
	public function get_asset () : string
	{
		return $this->asset;
	}
	
	public function get_quantity () : string
	{
		return $this->quantity;
	}
	
	
	public function add(float $amount)
	{
		$this->quantity += $amount;
	}
	
}
