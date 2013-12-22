<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class MY_Model extends Core_Model {

    private $field;

    public function __call($methodName, $args) 
    {
        if (preg_match('~^(set|get|findOneBy|findBy|orderBy)([A-Z])(.*)$~', $methodName, $matches)) {
		
            $word = preg_split('#([A-Z][^A-Z]*)#', strtolower($matches[2]) . $matches[3], null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            $property = strtolower(implode('_', $word));
            if (!property_exists($this, $property)) {
                throw new Exception('Property ' . $property . ' not exists');
            }
            switch($matches[1]) {
                case 'set':
                    $this->_checkArguments($args, 1, 1);
                    return $this->set($property, $args[0]);
                case 'get':
                    $this->_checkArguments($args, 0, 0);
                    return $this->get($property);
                case 'findOneBy':
                    $this->_checkArguments($args, 1, 1);
                    return $this->findOneBy($property, $args[0]);
                case 'findBy':
                    $this->_checkArguments($args, 1, 1);
                    return $this->findBy($property, $args[0]);
                case 'orderBy':
                    $this->_checkArguments($args, 0, 1);
                    return $this->orderBy($property, isset($args[0]) ? $args[0]: 'asc');
                case 'default':
                    throw new Exception('Method bulunamadı.');
            }
        }
    }

    public function get($property) 
    {
        return $this->$property;
    }

    public function set($property, $value) 
    {
	$this->$property = $value;
        return $this;
    }
	
    private function _checkArguments(array $args, $min, $max) 
    {
        $argc = count($args);
        if ($argc < $min || $argc > $max) {
            throw new MemberAccessException('Parametre hatası.');
        }
    }

    private function _getObjectVars() 
    {
        foreach($this->_fields() as $field){
            $fields[$field] = $this->$field;
        }
        return $fields ;
    }
	
    private function _getTableName()
    {
        return strtolower(str_replace('_Model','',get_class($this)));
    }

    private function _getTableFields()
    {
        return $this->db->list_fields($this->_getTableName());
    }
	
    private function _getObjectFields()
    {
        foreach(get_object_vars($this) as $field => $value){
            $fields[] = $field;
        }
        return $fields ;
    }
	
    private function _fields() 
    {
        return array_intersect($this->_getTableFields(), $this->_getObjectFields());
    }
	
    private function _fetch()
    {
        $this->db->flush_cache();
        if (!empty($this->my_order_by)) {
            $this->db->order_by($this->my_order_by, $this->my_order_type);
        }
        if (!empty($this->my_limit)) {
            $this->db->limit($this->my_limit);
        }

        if(!empty($this->my_where)) {
            if(is_array($this->my_where)) {
                foreach($this->my_where as $item) {
                    if ($item['type'] == 'and') {
                        $this->db->where($item['field'], $item['value']);
                    } else if ($item['type'] == 'or') {
                        $this->db->or_where($item['field'], $item['value']);
                    }
                }
            }
        }
			
        $result = $this->db->get($this->_getTableName());
        if($result->num_rows()) {
            foreach($result->result() as $item) {
                foreach($this->_fields() as $field ) {
                    $this->$field = $item->$field;
                }
                if(method_exists($this,'relation')) {
                    foreach($this->relation() as $objectName => $object) {
                        $this->load->model($objectName, 'model');
                        switch($object['type']) {
                            case 'oneToMany':
                                $type = '_findBy';
                                break;
                            case 'manyToOne':
                                $type = '_findOneBy';
                                break;
                        }
                        $this->$object['this_field'] = $this->model->$type($object['related_object_field'], $item->$object['this_object_field']);
                    }
                }
                $data[] = clone $this;
            }
            $collection = new ArrayCollection($data);
            return $collection;
        } else {
                return false;
        }
    }
	
    private function _fetchOneTime()
    {
        $result = $this->db->get($this->_getTableName());
        if($result->num_rows()) {
            foreach($result->result() as $item) {
                foreach($this->_fields() as $field ) {
                    $this->$field = $item->$field;
                }
                $data[] = clone $this;
            }
            $collection = new ArrayCollection($data);
            return $collection;
        } else {
            return false;
        }
    }
	
    public function orderBy($property, $type = 'asc')
    {
        $this->my_order_by 		= $property;
        $this->my_order_type 	= $type;
        return $this;
    }
	
    public function limit($count)
    {
        $this->my_limit = $count;
        return $this;
    }
	
    function exchangeArray($data) {
        foreach($data as $field => $value) {
            if(property_exists ($this, $field)) {
                $this->$field = (!empty($value) ? $value: null);
            }
        }
        return $this;
    }

    public function save() 
    {
        if(isset($this->id)) {
            $this->db->flush_cache();
            $this->db->where('id', $this->id);
            $this->db->update($this->_getTableName(), $this->_getObjectVars());
        } else {
            $this->db->flush_cache();
            $this->db->insert($this->_getTableName(), $this->_getObjectVars());
            $this->id = $this->db->insert_id();
        }
    }

    public function findOneBy($property, $value) 
    {

        $this->db->flush_cache();
        $this->db->where($property, $value);
        $item = $this->_fetch();
        if ($item) {
            return $item->get(0);
        }
        return false;

    }

    public function _findOneBy($property, $value) 
    {
        $this->db->flush_cache();
        $this->db->where($property, $value);
        $item = $this->_fetchOneTime();
        if ($item) {
            return $item->get(0);
        }
        return false;

    }

    public function findBy($property, $value) 
    {
        $this->db->flush_cache();
        $this->db->where($property, $value);
        return $this->_fetch();

    }

    private function _findBy($property, $value) 
    {
        $this->db->flush_cache();
        $this->db->where($property, $value);
        return $this->_fetchOneTime();

    }

    public function fetchAll() 
    {
        $this->db->flush_cache();
        return $this->_fetch();
    }

    public function fetch() 
    {
        $this->db->flush_cache();
        return $this->_fetch();
    }


    public function remove() 
    {
        $this->db->flush_cache();
        foreach(get_object_vars($this) as $field => $value) {
                $this->db->where($field, $value); 
        }
        $this->db->delete($this->_getTableName());

    }

    public function flush() 
    {
        foreach(get_object_vars($this) as $field) {
            $this->$field = null;
        }
    }

    function where($field, $value) 
    {
        array_push($this->my_where, array('field' => $field, 'value' => $value, 'type' => 'and'));
        return $this;
    }

    function orWhere($field, $value) 
    {
        $this->my_where[] = array('field' => $field, 'value' => $value, 'type' => 'or');
        return $this;
    }

}

class Core_Model extends CI_Model {
	protected $my_order_by;
	protected $my_order_type;
	protected $my_limit;
	protected $my_where = array();
}

class ArrayCollection implements Iterator{
	
	private $var = array();
	private $position;
    public function __construct($array)
    {
        if (is_array($array)) {
            $this->var = $array;
            $this->position = 0; 
        }
    }

    function rewind() 
	{
        $this->position = 0;
    }

    function current() 
	{
        return $this->var[$this->position];
    }

    function key() 
	{
        return $this->position;
    }

    function next() 
	{
        ++$this->position;
    }

    function valid() 
	{
        return isset($this->var[$this->position]);
    }
	
    function get($key) 
    {
        if(!array_key_exists($key, $this->var)) {
            throw new Exception('Tanımsız.');
        }
        return $this->var[$key];
    }

    function count() 
    {
        return count($this->var);	
    }

    function isEmpty()
    {
        return count($this->var) ? TRUE:FALSE;
    }
}