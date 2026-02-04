<?php
namespace COMMON__\svc;



class Account
{
	
	public function __construct (private array $assets_reference_price, private string $asset, private float $quantity=0)
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
	
	
	public function get_reference_price ()
	{
		$res = $this->get_quantity() * ($this->assets_reference_price [$this->get_asset()] ?? 0);
		return $res;
	}
	
}
