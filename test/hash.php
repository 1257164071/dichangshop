<?php
/**
 * Created by PhpStorm.
 * User: nightdays
 * Date: 2019/1/15
 * Time: 21:22
 */

function hash($key,$m){
    $strlen = strlen($key);
    $hashval = 0;
    for ($i=0;$i<$strlen;$i++){
        $hashval += ord($key{$i});
    }
    return $hashval%$m;
}

class HashTable{
    private $buckets;
    private $size = 10;
    public function __construct(){
        $this->buckets = new SqlFixedArray($this->size);
    }
    private function hashfunc($key){
        $strlen = strlen($key);
        $hashval = 0;
        for ($i=0;$i<$strlen;$i++){
            $hashval += ord($key{$i});
        }
        return $hashval%$this->size;
    }
    public function insert_v1($key,$val){
        $index = $this->hashfunc($key);
        $this->buckets[$index] = $val;
    }
    public function insert($key,$value){
        $index = $this->hashfunc($key);
        if(isset($this->buckets[$index])){
            $newNode = new HashNode($key,$value,$this->buckets[$index]);
        }else{
            $newNode = new HashNode($key,$value,NULL);
        }
        $this->buckets[$index] = $newNode;
    }
    public function find_v1($key){
        $index = $this->hashfunc($key);
        return $this->buckets[$index];
    }
    public function find($key){
        $index = $this->hashfunc($key);
        $current = $this->buckets[$index];
        while (isset($current)){
            if($current->key == $key){
                return $current->value;
            }
            $current = $current->nextNode;
        }
        return NULL;
    }
}

class HashNode{
    public $key;
    public $value;
    public $nextNode;
    public function __construct($key,$value,$nextNode = NULL){
        $this->key = $key;
        $this->value = $value;
        $this->nextNode = $nextNode;
    }
}

