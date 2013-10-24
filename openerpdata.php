<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
include_once('openerp.class.php');

class OpenERPdata {
    
    private $conn = null;
    
    public function __construct() {
        $this->connect("admin", "admin", "AuraDB6", "http://localhost:8069/xmlrpc/");
    }
    
    public function connect($username, $password, $database, $url) {
        $rpc = new OpenERP();
        $rpc->login($username, $password, $database, $url);
        $this->conn = $rpc;
    }
    
    public function queryProduct($ean13) {
        if (trim($ean13)!="") {
            $found=false;
            $products = $this->conn->searchFilter("product.product", "default_code", "=", trim($ean13), "string");
            if (count($products)>0) {
                $found=true;
            } else {
                $products = $this->conn->searchFilter("product.product", "ean13", "=", trim($ean13), "string");
                if (count($products)>0) {
                    $found=true;
                }
            } 
            
            if ($found) {
                $product_id = array($products[0]);
                $res = $this->conn->read($product_id, array("name","id","ean13","code","default_code"), "product.product");
                return $res;
            } else {
                return null;
            }
            
        } else {
            return null;
        }
    }
    
    public function queryProductPrice ($pricelist,$product,$qty) {
        
        //default values
        $partner = 0;
        
        $vals = array(
            'id'=>array(array(
                new xmlrpcval($pricelist,"string")
                ),"array"),
            'prod_id'=>array($product,"int"),
            'qty'=>array($qty,"int"),
            'partner'=>array($partner,"int"),
            'context'=>array(array(
                'date'=>new xmlrpcval(date("Y-m-d"),"string")
                ),"struct")
            );

        $res = $this->conn->call_function("product.pricelist", "price_get", $vals);
        $price = $res->structmem($pricelist)->scalarval();
        return $price;
    }
   
}

?>
