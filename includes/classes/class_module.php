<?php
/**
 *
 * Class : Module
 *
 * This is a lightweight module class and does not contain the database access stuff
 *
 * @copyright 2007 Loughborough University
 * @license http://www.gnu.org/licenses/gpl.txt
 * @version 1.0.1.0
 *
 */

require_once('class_group_handler.php');

class Module {
  // Public Vars
  public $module_code = NULL;
  public $module_title = NULL;
  public $module_id = NULL;
    public $module_lang = APP__DEFAULT_LOCALE;

  public $DAO = NULL;

  /**
  * CONSTRUCTOR for the class function
  * @param string $code
  * @param string $title
  */
  function Module($module_code = null, $module_title = null, $module_lang = APP__DEFAULT_LOCALE) {
    $this->module_code = $module_code;
    $this->module_title = $module_title;
    $this->module_id = null;
    $this->module_lang = $module_lang;
  }// /->Module()

/*
* ================================================================================
* PUBLIC
*================================================================================
*/

  /**
  * Load the object from the given data
  *
  * @param array $module_info  assoc-array of Module info
  *
  * @return boolean did the load succeed
  */
  function load_from_row($module_info) {
    if (is_array($module_info) && isset($module_info['module_id'])) {
      $this->module_id = $module_info['module_id'];
      $this->module_code = $module_info['module_code'];
      $this->module_title = $module_info['module_title'];
      $this->module_lang = $module_info['module_lang'];
    }
    return true;
  }// /->load_from_row()

  /**
   * Function to update the module details
   */
   function save_module(){

    $_fields = array ('module_code'        => $this->module_code ,
              'module_title'        => $this->module_title,
            'module_lang'   => $this->module_lang,
               );

    //save the changes to the module
    $this->DAO->do_update("UPDATE " . APP__DB_TABLE_PREFIX . "module SET {fields} WHERE module_id = {$this->module_id}; ",$_fields);

    return true;
   }

   /**
    * Function to set the database connection to be used
    * @param database connection $DB
    */
    function set_dao_object($DB){
      $this->DAO = $DB;
    }

  /**
   * Function to add new module details
   */
   function add_module(){

    $_fields = array ('module_code'         => $this->module_code ,
                      'module_title'        => $this->module_title,
                      'module_lang'         => $this->module_lang,
               );

    //save the changes to the module
    $this->DAO->do_update('INSERT INTO ' . APP__DB_TABLE_PREFIX . "module SET {fields}", $_fields);

    return $this->DAO->get_insert_id() ;
   }

  /**
   * Function to delete a module
   */
   function delete(){

     $collections = $this->DAO->fetch_col("SELECT collection_id FROM " . APP__DB_TABLE_PREFIX . "collection WHERE module_id = $this->module_id");
     $group_handler = new GroupHandler();
     for ($i=0; $i<count($collections); $i++) {
       $collection = $group_handler->get_collection($collections[$i]);
       $collection->delete();
     }
     $this->DAO->execute('DELETE FROM ' . APP__DB_TABLE_PREFIX . "user_module WHERE module_id = {$this->module_id}");
     $this->DAO->execute('DELETE FROM ' . APP__DB_TABLE_PREFIX . "module WHERE module_id = {$this->module_id}");

     $this->module_code = null;
     $this->module_title = null;
     $this->module_id = null;
     $this->module_lang = null;
   }

/*
* ================================================================================
* PRIVATE
* ================================================================================
*/

}// /class: Module

?>
