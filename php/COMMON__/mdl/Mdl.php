<?php
namespace COMMON__\mdl;

use Base;
use DB\Cortex;
use DB\CortexCollection;
use DB\SQL;
use ErrorException;
use Exception;


abstract class Mdl extends Cortex
{
	public const table = null; // each subclass must fill-in this var
	
	const BOOLEAN_ENUM =
	[
		0 =>	"non",
		1 =>	"oui",
	];
	
	const OPERAND_ENUM =
	[
		"<" =>	"<",
		"=" =>	"=",
		">" =>	">",
	];
	
	
	// subclasses have to implement this method to use getAsList generic method
	public function __toString ()
	{
		throw new ErrorException("__toString() method not implemented for class " . get_class($this));
	}
	
	
	// this method can lead to fatal error when the data is not foud, combining F3 with cortex ORM
	public function findone ($filter = null, ?array $options = null, $ttl = 0)
	{
		throw new ErrorException("findone() method should not be used (class : " . get_called_class() . ")");
	}
	
	
	function __construct ()
	{
		$f3 = Base::instance();
		$db = $f3->get("db"); /** @var SQL $db */
		parent::__construct($db, static::table);
	}
	
	
	public function find($filter = NULL, ?array $options = NULL, $ttl = 0) : CortexCollection
	{
		$res = parent::find($filter, $options, $ttl);
		if(empty($res)) {
			return new CortexCollection();
		}
		else {
			return $res;
		}
	}
	
	
	
	public static function findBy($key, $value)
	{
		$entity = new static();
		
		$f3 = Base::instance();
		$db = $f3->get("db"); /** @var SQL $db */
		
		// check $key is valid to avoid SQL injection
		$table = $entity->getTable();
		$schema = $db->schema($table);
		$fields = array_keys($schema);
		if(array_search($key, $fields) === false) {
			throw new ErrorException("$key is not a $table field");
		}
		
		$data = $entity->find(["$key = ?", $value]);
		return $data;
	}
	
	public static function findOneBy($key, $value) : ?static
	{
		$data = self::findBy($key, $value);
		if(empty($data)) {
			throw new ErrorException("$key = $value not found");
		}
		if(count($data) > 1) {
			throw new ErrorException("multiple $key = $value found");
		}
		return $data[0] ?? null;
	}
	
	
	public static function getAll ($order_field=null) : CortexCollection
	{
		$entity = new static(); /** @var Cortex $entity */
		$order_field = $order_field ?? "name";
		// if the entity has a property "name", order results with it
		if(in_array($order_field, $entity->fields())) {
			$res = $entity->find([], ["order" => "$order_field ASC"]);
		}
		else {
			$res = $entity->find();
		}
		
		if(empty($res)) {
			return new CortexCollection();
		}
		else {
			return $res;
		}
	}

	public static function getAllFast ($order_field=null) : array
	{
		$f3 = Base::instance();
		$db = $f3->get("db"); /** @var SQL $db */

		$sql = "
			SELECT *
			FROM " . static::table . "
			ORDER BY {$order_field} ASC
		";
		$params = [];

		$data = $db->exec($sql, $params);
		return $data;
	}
	
	/**
	 *	key -> object
	 */
	public static function objectsIndexed (CortexCollection $objects, $key="id")
	{
		$values = [];
		foreach ($objects as $row) {
			$values [$row->$key] = $row;
		}
		
		return $values;
	}
	
	
	/**
	 * id -> name
	 */
	public static function objectsAsList (CortexCollection $objects) : array
	{
		$res = [];
		foreach ($objects as $row) {
			$res [$row->id] = $row->__toString();
		}
		return $res;
	}
	
	
	public static function getAsList () : array
	{
		$all = static::getAll();
		return static::objectsAsList ($all);
	}
	
	
	/**
	 * [
	 * 	{
	 * 		"value" : id,
	 * 		"label" : name,
	 *	},
	 * ]
	 */
	public static function objectsAsAjaxList (CortexCollection $objects) : array
	{
		$res = [];
		foreach ($objects as $row) {
			$res [] = [
				"value" => $row->id,
				"label" => $row->__toString(),
			];
		}
		return $res;
	}
	
	
	public static function getAsAjaxList () : array
	{
		$all = static::getAll();
		$res = self::objectsAsAjaxList($all);
		return $res;
	}
	
	
	public function isErasable ()
	{
		$f3 = Base::instance();
		$db = $f3->get("db"); /** @var SQL $db */
		
		$db->begin();
		try {
			$this->erase();
		}
		catch (Exception $ex) {
			return false;
		}
		
		$db->rollback();
		return true;
	}
	
	
	public function tryErase ()
	{
		$f3 = Base::instance();
		$db = $f3->get("db"); /** @var SQL $db */
		
		$db->begin();
		try {
			$this->erase();
		}
		catch (Exception $e) {
			$db->rollback();
			$class = get_class($e);
			$code = $e->getCode();
			if($class === "PDOException" && $code === "23000") {
				$error_message = "La donnée ne peut être supprimée, probablement car elle est utilisée ailleurs.";
			}
			else {
				$error_message = $class . " : " . $code . " : " . $e->getMessage();
			}
			throw new ErrorException($error_message);
		}
		
		$db->commit();
	}
	
	
	
    /**
     * __get : retourne la valeur typée
     */
    public function &__get($key)
    {
        $value = parent::__get($key);
        if (!isset($this->fieldConf[$key])) return $value;

        return $this->castTheField($value, $this->fieldConf[$key]['type']);
    }

    /**
     * __set : accepte des objets / types natifs et convertit pour DB
     */
    public function __set($key, $value)
    {
        if (!isset($this->fieldConf[$key])) {
            parent::__set($key, $value);
            return;
        }

        $type = $this->fieldConf[$key]['type'] ?? '';
        switch (strtoupper($type)) {
            case 'DATETIME':
            case 'TIMESTAMP':
            case 'DATE':
                if ($value instanceof \DateTimeInterface) {
                    $value = $value->format('Y-m-d H:i:s');
                }
                break;

            case 'INT':
            case 'INTEGER':
            case 'BIGINT':
                if ($value !== null) $value = (int)$value;
                break;

            case 'FLOAT':
            case 'DOUBLE':
            case 'DECIMAL':
                if ($value !== null) $value = (float)$value;
                break;

            case 'BOOL':
            case 'BOOLEAN':
                $value = (bool)$value;
                break;

            case 'JSON':
            case 'JSONB':
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                break;
        }

        parent::__set($key, $value);
    }

    /**
     * cast : retourne toutes les valeurs typées
     */
    public function cast($obj = null, $rel_depths = 1)
    {
        $data = parent::cast($obj, $rel_depths);

        foreach ($this->fieldConf as $field => $conf) {
            if (!isset($data[$field])) continue;
            $data[$field] = static::castTheField($data[$field], $conf['type']);
        }

        return $data;
    }

    /**
     * Conversion d'une valeur selon son type DB
     */
    protected static function castTheField ($value, $type)
    {
        if ($value === null) return null;

        switch (strtoupper($type)) {
            case 'DATETIME':
            case 'TIMESTAMP':
            case 'DATE':
                return new \DateTime($value);

            case 'INT':
            case 'INTEGER':
            case 'BIGINT':
                return (int)$value;

            case 'FLOAT':
            case 'DOUBLE':
            case 'DECIMAL':
                return (float)$value;

            case 'BOOL':
            case 'BOOLEAN':
                return (bool)$value;

            case 'JSON':
            case 'JSONB':
                $decoded = json_decode($value, true);
                return $decoded === null ? [] : $decoded;

            case 'VARCHAR':
            case 'TEXT':
            case 'ENUM':
            default:
                return $value;
        }
    }

	
	public static function drop_table () : void
	{
		$f3 = Base::instance();
		$db = $f3->get("db"); /** @var SQL $db */
		$db->exec("DROP TABLE IF EXISTS " . static::table);
	}
	
	public static function delete_table () : void
	{
		$f3 = Base::instance();
		$db = $f3->get("db"); /** @var SQL $db */
		$db->exec("DELETE FROM " . static::table);
	}
	
}
