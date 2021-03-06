<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 resources plugin for GLPI
 Copyright (C) 2009-2016 by the resources Development Team.

 https://github.com/InfotelGLPI/resources
 -------------------------------------------------------------------------

 LICENSE
      
 This file is part of resources.

 resources is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 resources is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with resources. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */
if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginResourcesTask extends CommonDBTM {
   
   static $rightname = 'plugin_resources_task';
   
   public $itemtype = 'PluginResourcesResource';
   public $items_id = 'plugin_resources_resources_id';
   public $dohistory=true;
   
   static function getTypeName($nb = 0) {

      return _n('Task', 'Tasks', $nb);
   }
   
   static function canView() {
      return Session::haveRight(self::$rightname, READ);
   }

   static function canCreate() {
      return Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, DELETE));
   }
   
   /**
   * Clean object veryfing criteria (when a relation is deleted)
   *
   * @param $crit array of criteria (should be an index)
   */
   public function clean ($crit) {
      global $DB;
      
      foreach ($DB->request($this->getTable(), $crit) as $data) {
         $this->delete($data);
      }
   }
   
   function cleanDBonPurge() {
      
      $temp = new PluginResourcesTask_Item();
      $temp->deleteByCriteria(array('plugin_resources_tasks_id' => $this->fields['id']));
      
      $temp = new PluginResourcesTaskPlanning();
      $temp->deleteByCriteria(array('plugin_resources_tasks_id' => $this->fields['id']));
   }
   
   function prepareInputForAdd($input) {

      Toolbox::manageBeginAndEndPlanDates($input['plan']);
      
      if (isset($input['plan'])) {
         $input['_plan'] = $input['plan'];
         unset($input['plan']);
      }
      
      if (isset($input["hour"]) && isset($input["minute"])) {
         $input["actiontime"] = $input["hour"]*HOUR_TIMESTAMP+$input["minute"]*MINUTE_TIMESTAMP;
         $input["_hour"]      = $input["hour"];
         $input["_minute"]    = $input["minute"];
         unset($input["hour"]);
         unset($input["minute"]);
      }
      
      unset($input["minute"]);
      unset($input["hour"]);
      
      if (!isset($input['plugin_resources_resources_id']) 
            || $input['plugin_resources_resources_id'] <= 0) {
         return false;
      }

      return $input;
   }
   
   function post_addItem() {
      global $CFG_GLPI;
      
      if (isset($this->input["_plan"])) {
         $this->input["_plan"]['plugin_resources_tasks_id'] = $this->fields['id'];
         $pt = new PluginResourcesTaskPlanning();

         if (!$pt->add($this->input["_plan"])) {
            return false;
         }
      }
      
      $PluginResourcesResource = new PluginResourcesResource();
      if ($CFG_GLPI["use_mailing"]) {
         $options = array('tasks_id' => $this->fields["id"]);
         if ($PluginResourcesResource->getFromDB($this->fields["plugin_resources_resources_id"])) {
            NotificationEvent::raiseEvent("newtask",$PluginResourcesResource,$options);  
         }
      }
   }
   
   function prepareInputForUpdate($input) {
      
      Toolbox::manageBeginAndEndPlanDates($input['plan']);
      if (isset($input["hour"]) 
            && isset($input["minute"])) {
         $input["actiontime"] = $input["hour"]*HOUR_TIMESTAMP+$input["minute"]*MINUTE_TIMESTAMP;
         unset($input["hour"]);
         unset($input["minute"]);
      }
      
      if (isset($input["plan"])) {
         $input["_plan"] = $input["plan"];
         unset($input["plan"]);
      }
      
      $this->getFromDB($input["id"]);
      $input["_old_name"]=$this->fields["name"];
      $input["_old_plugin_resources_tasktypes_id"]=$this->fields["plugin_resources_tasktypes_id"];
      $input["_old_users_id"]=$this->fields["users_id"];
      $input["_old_groups_id"]=$this->fields["groups_id"];
      $input["_old_actiontime"]=$this->fields["actiontime"];
      $input["_old_is_finished"]=$this->fields["is_finished"];
      $input["_old_comment"]=$this->fields["comment"];

      return $input;
   }
   
   function post_updateItem($history=1) {
      global $CFG_GLPI;
      
      if (isset($this->input["_plan"])) {
         $pt = new PluginResourcesTaskPlanning();
         // Update case
         if (isset($this->input["_plan"]["id"])) {
            $this->input["_plan"]['plugin_resources_tasks_id'] = $this->input["id"];

            if (!$pt->update($this->input["_plan"])) {
               return false;
            }
            unset($this->input["_plan"]);
         // Add case
         } else {
            $this->input["_plan"]['plugin_resources_tasks_id'] = $this->input["id"];
            if (!$pt->add($this->input["_plan"])) {
               return false;
            }
            unset($this->input["_plan"]);
         }

      }
      
      if (!isset($this->input["withtemplate"]) 
            || (isset($this->input["withtemplate"]) 
                  && $this->input["withtemplate"]!=1)) {
         if ($CFG_GLPI["use_mailing"]) {
            $options = array('tasks_id' => $this->fields["id"]);
            $PluginResourcesResource = new PluginResourcesResource();
            if ($PluginResourcesResource->getFromDB($this->fields["plugin_resources_resources_id"])) {
               NotificationEvent::raiseEvent("updatetask",$PluginResourcesResource,$options);  
            }
         }
      }
   }
   
   function pre_deleteItem() {
      global $CFG_GLPI;

      if ($CFG_GLPI["use_mailing"] 
            && isset($this->input['delete'])) {
         $PluginResourcesResource = new PluginResourcesResource();
         $options = array('tasks_id' => $this->fields["id"]);
         if ($PluginResourcesResource->getFromDB($this->fields["plugin_resources_resources_id"])) {
            NotificationEvent::raiseEvent("deletetask",$PluginResourcesResource,$options);  
         }
      }
      return true;
   }
   
   function cleanDBonMarkDeleted() {
      global $DB;
      
      $query = "UPDATE `glpi_plugin_resources_checklists` 
            SET `plugin_resources_tasks_id` = 0 
            WHERE `plugin_resources_tasks_id` = '".$this->fields["id"]."' ";
      $result = $DB->query($query);

   }
   
   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if ($item->getType()=='PluginResourcesResource' 
            && $this->canView()) {
         if ($_SESSION['glpishow_count_on_tabs']) {
            return self::createTabEntry(self::getTypeName(2), self::countForItem($item));
         }
         return self::getTypeName(2);
      } else if ($item->getType()=='Central' 
                     && $this->canView()) {
         return PluginResourcesResource::getTypeName(2);
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      
      $self = new self();
      if ($item->getType()=='PluginResourcesResource') {
         if (Session::haveRight(self::$rightname, READ)) {
               self::addNewTasks($item, $withtemplate);
               self::showMinimalList(array('id' => $item->getID(),
                                            'withtemplate' => $withtemplate));
         }
      } else if ($item->getType()=='Central') {
         $self->showCentral(Session::getLoginUserID());
      }
      return true;
   }
   
   static function countForItem(CommonDBTM $item) {

      $restrict = "`plugin_resources_resources_id` = '".$item->getField('id')."'
                  AND is_finished != 1 AND is_deleted = 0";
      $nb = countElementsInTable(array('glpi_plugin_resources_tasks'), $restrict);

      return $nb ;
   }
   
   function getSearchOptions() {

      $tab = array();
    
      $tab['common']             = PluginResourcesResource::getTypeName(2)." - ".self::getTypeName(2);

      $tab[1]['table']           = $this->getTable();
      $tab[1]['field']           = 'name';
      $tab[1]['name']            = __('Name');
      $tab[1]['datatype']        = 'itemlink';
      $tab[1]['itemlink_type']   = $this->getType();

      $tab[2]['table']           = 'glpi_users';
      $tab[2]['field']           = 'name';
      $tab[2]['name']            = __('Technician');
      $tab[2]['datatype']        = 'dropdown';
      $tab[2]['massiveaction']   = false;

      $tab[3]['table']           = 'glpi_groups';
      $tab[3]['field']           = 'completename';
      $tab[3]['name']            = __('Group');
      $tab[3]['condition']       = '`is_assign`';
      $tab[3]['massiveaction']   = false;
      $tab[2]['datatype']        = 'dropdown';
      
      $tab[4]['table']           ='glpi_plugin_resources_taskplannings';
      $tab[4]['field']           = 'id';
      $tab[4]['name']            = __('Planning');
      $tab[4]['massiveaction']   = false;
      $tab[4]['datatype']        = 'number';
      
      $tab[7]['table']           = $this->getTable();
      $tab[7]['field']           = 'actiontime';
      $tab[7]['name']            = __('Effective duration', 'resources');
      $tab[7]['datatype']        = 'timestamp';
      $tab[7]['massiveaction']   = false;
      $tab[7]['nosearch']        = true;
      
      $tab[8]['table']           = $this->getTable();
      $tab[8]['field']           = 'comment';
      $tab[8]['name']            = __('Comments');
      $tab[8]['datatype']        = 'text';

      $tab[9]['table']           = $this->getTable();
      $tab[9]['field']           = 'is_finished';
      $tab[9]['name']            = __('Carried out task', 'resources');
      $tab[9]['datatype']        = 'bool';
      
      $tab[10]['table']          = 'glpi_plugin_resources_tasks_items';
      $tab[10]['field']          = 'items_id';
      $tab[10]['name']           = _n('Associated item', 'Associated items', 2);
      $tab[10]['massiveaction']  = false;
      $tab[10]['forcegroupby']   = true;
      $tab[10]['joinparams']     = array('jointype' => 'child');
      
      $tab[11]['table']          = 'glpi_plugin_resources_tasktypes';
      $tab[11]['field']          = 'name';
      $tab[11]['name']           = PluginResourcesTaskType::getTypeName(1);
      $tab[11]['datatype']       = 'dropdown';
      
      $tab[13]['table']          = 'glpi_plugin_resources_resources';
      $tab[13]['field']          = 'id';
      $tab[13]['name']           = PluginResourcesResource::getTypeName(1)." ".__('ID');
      $tab[13]['massiveaction']  = false;
      $tab[13]['datatype']       = 'number';
      
      $tab[12]['table']          = 'glpi_plugin_resources_resources';
      $tab[12]['field']          = 'name';
      $tab[12]['name']           = PluginResourcesResource::getTypeName(2);
      $tab[12]['massiveaction']  = false;
      
      $tab[30]['table']          = $this->getTable();
      $tab[30]['field']          = 'id';
      $tab[30]['name']           = __('ID');
      $tab[30]['massiveaction']  = false;
      $tab[30]['datatype']       = 'number';
      
      $tab[80]['table']          = 'glpi_entities';
      $tab[80]['field']          = 'completename';
      $tab[80]['name']           = __('Entity');
      $tab[80]['datatype']       = 'dropdown';
      
      return $tab;
   }
   
   
   function defineTabs($options=array()) {
      
      $ong = array();
      $this->addDefaultFormTab($ong);
      $this->addStandardTab('PluginResourcesTask_Item', $ong,$options);
      $this->addStandardTab('Log',$ong,$options);
      
      return $ong;
   }
   
   /**
    * Duplicate task of resources from an item template to its clone
    *
    * @since version 0.84
    *
    * @param $itemtype     itemtype of the item
    * @param $oldid        ID of the item to clone
    * @param $newid        ID of the item cloned
    * @param $newitemtype  itemtype of the new item (= $itemtype if empty) (default '')
   **/
   static function cloneItem($oldid, $newid) {

      $task_item = new PluginResourcesTask_Item();
      
      $restrict = "`plugin_resources_resources_id` = '".$oldid."'
                  AND is_deleted != 1";
      $ptasks = getAllDatasFromTable("glpi_plugin_resources_tasks",$restrict);
      if (!empty($ptasks)) {
         foreach ($ptasks as $ptask) {
            $item = new self();
            $values=$ptask;
            $taskid = $values["id"];
            unset($values["id"]);
            $values["plugin_resources_resources_id"]=$newid;
            $values["name"] = addslashes($ptask["name"]);
            $values["comment"] = addslashes($ptask["comment"]);
            
            $newtid = $item->add($values);
            
            $restrictitems = "`plugin_resources_tasks_id` = '".$taskid."'";
            $tasksitems = getAllDatasFromTable("glpi_plugin_resources_tasks_items",$restrictitems);
            if (!empty($tasksitems)) {
               foreach ($tasksitems as $tasksitem) {
                  $task_item->add(array('plugin_resources_tasks_id' => $newtid,
                     'itemtype' => $tasksitem["itemtype"],
                     'items_id' => $tasksitem["items_id"]));
               }
            }
         }
      }
   }
   
   static function addNewTasks(CommonDBTM $item, $withtemplate='') {
      global $CFG_GLPI;
      
      $rand=mt_rand();
      
      $ID = $item->getField('id');
      $entities_id = $item->getField('entities_id');
      $canedit = $item->can($ID, UPDATE);
      if (Session::haveRight(self::$rightname, READ) 
            && $canedit 
               && $withtemplate<2) {
         
         echo "<div align='center'>";
         echo "<a href='".
         $CFG_GLPI["root_doc"]."/plugins/resources/front/task.form.php?plugin_resources_resources_id=".$ID
         ."&entities_id=".$entities_id."' >".__('Add a new task')."</a></div>";
         echo "</div>";
      }
   }
   
   function showForm ($ID, $options=array()) {

      if (!$this->canView()) return false;
      
      $plugin_resources_resources_id = -1;
      if (isset($options['plugin_resources_resources_id'])) {
         $plugin_resources_resources_id = $options['plugin_resources_resources_id'];
      }
      
      $item = new PluginResourcesResource();
      if ($item->getFromDB($plugin_resources_resources_id)){
         $entities_id = $item->fields["entities_id"];
      }
      
      if ($ID > 0) {
         $this->check($ID,READ);
         $plugin_resources_resources_id=$this->fields["plugin_resources_resources_id"];
      } else {
         // Create item
         $input=array('plugin_resources_resources_id'=>$plugin_resources_resources_id,
                        'entities_id' => $entities_id);
         $this->check(-1,UPDATE,$input);
      }
      
      $this->showFormHeader($options);
      
      echo "<input type='hidden' name='plugin_resources_resources_id' value='$plugin_resources_resources_id'>";
      
      echo "<tr class='tab_bg_1'>";
      echo "<td>".PluginResourcesResource::getTypeName(2)."&nbsp;</td><td>";

      $user = PluginResourcesResource::getResourceName($plugin_resources_resources_id,2);
      $out= "<a href='".$user['link']."'>";
      $out.= $user["name"];
      if ($_SESSION["glpiis_ids_visible"]) $out.= " (".$plugin_resources_resources_id.")";
      $out.= "</a>";
      echo $out;
      echo "</td>";
      echo "<td colspan='2'>";
      echo "</td>";
      echo "</tr>";
      
      echo "<tr class='tab_bg_1'><td>".__('Name')."</td>";
      echo "<td>";
      Html::autocompletionTextField($this,"name",array('size' => "50"));
      echo "</td>";
      echo "<td>".PluginResourcesTaskType::getTypeName(1)."</td><td>";
      Dropdown::show('PluginResourcesTaskType',
                     array('value'  => $this->fields["plugin_resources_tasktypes_id"]));
      echo "</td>";
      echo "</tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Technician')."</td><td>";
      User::dropdown(array('name' => "users_id",
                           'value' => $this->fields["users_id"],
                           'right' => 'interface'));
      echo "</td>";
      echo "<td>".__('Planning')."</td>";
      echo "<td>";
      $plan = new PluginResourcesTaskPlanning();
      $plan->showFormForTask($plugin_resources_resources_id, $this);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Group')."</td><td>";
      Dropdown::show('Group',
                     array('value'  => $this->fields["groups_id"]));
      echo "</td>";
      echo "<td>".__('Carried out task', 'resources')."</td><td>";
      Dropdown::showYesNo("is_finished",$this->fields["is_finished"]);
      echo "</td>";
      
      echo "<tr class='tab_bg_1'>";
      echo "<td>".__('Effective duration', 'resources')."</td><td>";
      $toadd = array();
      for ($i=9;$i<=100;$i++) {
         $toadd[] = $i*HOUR_TIMESTAMP;
      }

      Dropdown::showTimeStamp("actiontime",array('min'             => 0,
                                                 'max'             => 8*HOUR_TIMESTAMP,
                                                 'value'           => $this->fields["actiontime"],
                                                 'addfirstminutes' => true,
                                                 'inhours'          => true,
                                                 'toadd'           => $toadd));

      echo "</td><td colspan='2'></td></tr>";
      
      echo "<tr class='tab_bg_1'>";
      echo "<td colspan='4'>".__('Comments')."</td>";
      echo "</tr>";
      echo "<tr class='tab_bg_1'><td colspan='4'>";			
      echo "<textarea cols='130' rows='4' name='comment' >".$this->fields["comment"]."</textarea>";
      echo "<input type='hidden' name='withtemplate' value=\"".$options['withtemplate']."\" >";
      echo "</td></tr>";

      $this->showFormButtons($options);
      
      return true;
   }
   
   /**
    * get the task status list
    *
    * @param $withmetaforsearch boolean
    * @return an array
    */
   static function getAllStatusArray() {

      $tab = array('1'      => __('Yes'),
                   '0'   => __('No'));

      return $tab;
   }
   
   /**
    * Get task status Name
    *
    * @param $value status ID
    */
   static function getStatus($value) {

      $tab = self::getAllStatusArray();
      return (isset($tab[$value]) ? $tab[$value] : '');
   }
   
   static function showMinimalList($options = array()) {
      $task = new PluginResourcesTask();
      
      if (!empty($options)) {
         foreach ($options as $key => $val) {
            $params[$key] = $val;
         }
      }

      // Set search params
      $params = array(
          'start'       => 0,
          'order'       => 'DESC',
          'is_deleted'  => 0
      );
      
      $toview=null;
      foreach ($task->getSearchOptions() as $key => $option) {
         if (isset($option['table'])) {
            if ($option['table'] == "glpi_plugin_resources_resources" && $option['field'] == "id") {
               $params['criteria'][] = array('field'      => $key,
                                             'searchtype' => 'contains',
                                             'value'      => $options['id']);
               $toview = $key;
            }
            if ($option['table'] == $task->getTable() && $option['field'] == "name") {
               $params['sort'] = $key;
            }
         }
      }
      
      $data = Search::prepareDatasForSearch(self::getType(), $params);
      // Force to view resource id  
      if ($toview != null && !in_array($toview, $data['toview'])) {
         array_push($data['toview'], $toview);
      }
      Search::constructSQL($data);
      Search::constructDatas($data);
      Search::displayDatas($data);
   }
   
   function showCentral($who) {
      global $DB,$CFG_GLPI;
      
      echo "<table class='tab_cadre_central'><tr><td>";
      
      if ($this->canView()) {
         $who=Session::getLoginUserID();
         
         if (Session::isMultiEntitiesMode()) {
            $colsup=1;
         } else {
            $colsup=0;
         }
         
         $ASSIGN="";
         if ($who>0) {
            $ASSIGN=" AND ((`".$this->getTable()."`.`users_id` = '$who')";
         }
         //if ($who_group>0) {
         $ASSIGN.=" OR (`".$this->getTable()."`.`groups_id` IN (SELECT `groups_id` 
                                                      FROM `glpi_groups_users` 
                                                      WHERE `users_id` = '$who') )";
         //}
         
         $query = "SELECT `".$this->getTable()."`.`id` AS plugin_resources_tasks_id, `".$this->getTable()."`.`name` AS name_task, `".$this->getTable()."`.`plugin_resources_tasktypes_id` AS plugin_resources_tasktypes_id,`".$this->getTable()."`.`is_deleted` AS is_deleted, ";
         $query.= "`".$this->getTable()."`.`users_id` AS users_id_task, `glpi_plugin_resources_resources`.`id` as id, `glpi_plugin_resources_resources`.`name` AS name, `glpi_plugin_resources_resources`.`firstname` AS firstname, `glpi_plugin_resources_resources`.`entities_id`, `glpi_plugin_resources_resources`.`users_id` as users_id ";
         $query.= " FROM `".$this->getTable()."`,`glpi_plugin_resources_resources` ";
         $query.= " WHERE `glpi_plugin_resources_resources`.`is_template` = '0' 
                  AND `glpi_plugin_resources_resources`.`is_deleted` = '0' 
                  AND `".$this->getTable()."`.`is_deleted` = '0' 
                  AND `".$this->getTable()."`.`is_finished` = '0' 
                  AND `".$this->getTable()."`.`plugin_resources_resources_id` = `glpi_plugin_resources_resources`.`id` 
                  $ASSIGN ) ";

         // Add Restrict to current entities
         $PluginResourcesResource = new PluginResourcesResource();
         $itemtable = "glpi_plugin_resources_resources";
         if ($PluginResourcesResource->isEntityAssign()) {
            $LINK= " AND " ;
            $query.=getEntitiesRestrictRequest($LINK,$itemtable);
         }
         
         $query .= " ORDER BY `glpi_plugin_resources_resources`.`name` DESC LIMIT 10;";
         
         $result = $DB->query($query);
         $number = $DB->numrows($result);
         
         if ($number > 0) {
            
            echo "<div align='center'><table class='tab_cadre' width='100%'>";
            echo "<tr><th colspan='".(7+$colsup)."'>".PluginResourcesResource::getTypeName(2).
            ": ".__('Tasks in progress', 'resources')." <a href='".$CFG_GLPI["root_doc"]."/plugins/resources/front/task.php?contains%5B0%5D=0&field%5B0%5D=9&sort=1&is_deleted=0&start=0'>".__('All')."</a></th></tr>";
            echo "<tr><th>".__('Name')."</th>";
            if (Session::isMultiEntitiesMode())
               echo "<th>".__('Entity')."</th>";
            echo "<th>".PluginResourcesTaskType::getTypeName(2)."</th>";
            echo "<th>".__('Planning')."</th>";
            echo "<th>".PluginResourcesResource::getTypeName(1)."</th>";
            echo "<th>".__('Resource manager', 'resources')."</th>";
            echo "<th>".__('User')."</th>";
            echo "</tr>";
         
            while ($data=$DB->fetch_array($result)) {
               
               echo "<tr class='tab_bg_1".($data["is_deleted"]=='1'?"_2":"")."'>";
               echo "<td class='center'><a href='".$CFG_GLPI["root_doc"]."/plugins/resources/front/task.form.php?id=".$data["plugin_resources_tasks_id"]."'>".$data["name_task"];
               if ($_SESSION["glpiis_ids_visible"]) echo " (".$data["plugin_resources_tasks_id"].")";
               echo "</a></td>";
               if (Session::isMultiEntitiesMode())
                  echo "<td class='center'>".Dropdown::getDropdownName("glpi_entities",$data['entities_id'])."</td>";		
               echo "<td class='center'>".Dropdown::getDropdownName("glpi_plugin_resources_tasktypes",$data["plugin_resources_tasktypes_id"])."</td>";
               echo "<td align='center'>";
               $restrict = " `plugin_resources_tasks_id` = '".$data['plugin_resources_tasks_id']."' ";
               $plans = getAllDatasFromTable("glpi_plugin_resources_taskplannings",$restrict);
               
               if (!empty($plans)) {
                  foreach ($plans as $plan) {
                     echo Html::convDateTime($plan["begin"]) . "&nbsp;->&nbsp;" .
                     Html::convDateTime($plan["end"]);
                  }
               } else {
                  echo __('None');
               }
               echo "</td>";
               
               echo "<td class='center'><a href='".$CFG_GLPI["root_doc"]."/plugins/resources/front/resource.form.php?id=".$data["id"]."'>".$data["name"]." ".$data["firstname"];
               if ($_SESSION["glpiis_ids_visible"]) echo " (".$data["id"].")";
               echo "</a></td>";
               
               echo "<td class='center'>".getUserName($data["users_id"])."</td>";
               
               echo "<td class='center'>".getUserName($data["users_id_task"])."</td>";
                        
               echo "</tr>";
            }
            
            echo "</table></div><br>";
            
         }
      }
      
      $PluginResourcesChecklist = new PluginResourcesChecklist();
      $PluginResourcesChecklist->showOnCentral(false);
      echo "<br>";
      $PluginResourcesChecklist->showOnCentral(true);

      echo "</td></tr></table>";
   }
   
   // Cron action
   static function cronInfo($name) {
       
      switch ($name) {
         case 'ResourcesTask':
            return array (
               'description' => __('Not finished tasks', 'resources'));   // Optional
            break;
      }
      return array();
   }

   /**
    * Cron action on tasks : ExpiredTasks
    *
    * @param $task for log, if NULL display
    *
    **/
   static function cronResourcesTask($task=NULL) {
      global $DB,$CFG_GLPI;
      
      if (!$CFG_GLPI["use_mailing"]) {
         return 0;
      }

      $message=array();
      $cron_status = 0;
      
      $resourcetask = new self();
      $query_expired = $resourcetask->queryAlert();
      
      $querys = array(Alert::END=>$query_expired);
      
      $task_infos = array();
      $task_messages = array();

      foreach ($querys as $type => $query) {
         $task_infos[$type] = array();
         foreach ($DB->request($query) as $data) {
            $entity = $data['entities_id'];
            $message = $data["name"].": ".
                        Html::convDate($data["date_end"])."<br>\n";
            $task_infos[$type][$entity][] = $data;

            if (!isset($task_messages[$type][$entity])) {
               $task_messages[$type][$entity] = __('Not finished tasks', 'resources')."<br />";
            }
            $task_messages[$type][$entity] .= $message;
         }
      }
      
      foreach ($querys as $type => $query) {
      
         foreach ($task_infos[$type] as $entity => $tasks) {
            Plugin::loadLang('resources');

            if (NotificationEvent::raiseEvent("AlertExpiredTasks",
                                              new PluginResourcesResource(),
                                              array('entities_id'=>$entity,
                                                    'tasks'=>$tasks))) {
               $message = $task_messages[$type][$entity];
               $cron_status = 1;
               if ($task) {
                  $task->log(Dropdown::getDropdownName("glpi_entities",
                                                       $entity).":  $message\n");
                  $task->addVolume(1);
               } else {
                  Session::addMessageAfterRedirect(Dropdown::getDropdownName("glpi_entities",
                                                                    $entity).":  $message");
               }

            } else {
               if ($task) {
                  $task->log(Dropdown::getDropdownName("glpi_entities",$entity).
                             ":  Send tasks alert failed\n");
               } else {
                  Session::addMessageAfterRedirect(Dropdown::getDropdownName("glpi_entities",$entity).
                                          ":  Send tasks alert failed",false,ERROR);
               }
            }
         }
      }
      
      return $cron_status;
   }
   
   function queryAlert() {

      $date=date("Y-m-d");
      $query = "SELECT `".$this->getTable()."`.*, `glpi_plugin_resources_resources`.`entities_id`,
                        `glpi_plugin_resources_taskplannings`.`end` AS date_end
            FROM `".$this->getTable()."`
            LEFT JOIN `glpi_plugin_resources_taskplannings` ON (`glpi_plugin_resources_taskplannings`.`plugin_resources_tasks_id` = `".$this->getTable()."`.`id`)
            LEFT JOIN `glpi_plugin_resources_resources` ON (`glpi_plugin_resources_resources`.`id` = `".$this->getTable()."`.`plugin_resources_resources_id`)
            WHERE `glpi_plugin_resources_taskplannings`.`end` IS NOT NULL 
            AND `glpi_plugin_resources_taskplannings`.`end` <= '".$date."' 
            AND `glpi_plugin_resources_resources`.`is_template` = '0' 
            AND `glpi_plugin_resources_resources`.`is_deleted` = '0' 
            AND `".$this->getTable()."`.`is_deleted` = '0' 
            AND `".$this->getTable()."`.`is_finished` = '0'";
      
      return $query;
   }
   
   /**
    * Get the specific massive actions
    * 
    * @since version 0.84
    * @param $checkitem link item to check right   (default NULL)
    * 
    * @return an array of massive actions
    * */
   function getSpecificMassiveActions($checkitem = NULL) {
      $isadmin = static::canUpdate();
      $actions = parent::getSpecificMassiveActions($checkitem);

      if ($isadmin) {
         $actions['PluginResourcesTask'.MassiveAction::CLASS_ACTION_SEPARATOR.'Install'] = __('Associate');
         $actions['PluginResourcesTask'.MassiveAction::CLASS_ACTION_SEPARATOR.'Desinstall'] = __('Dissociate');
         $actions['PluginResourcesTask'.MassiveAction::CLASS_ACTION_SEPARATOR.'Duplicate'] = __('Duplicate', 'resources');
         if (Session::haveRight('transfer', READ)
            && Session::isMultiEntitiesMode()) {
            $actions['PluginResourcesTask'.MassiveAction::CLASS_ACTION_SEPARATOR.'Transfert'] = __('Transfer');
         }
      }
      return $actions;
   }

   static function showMassiveActionsSubForm(MassiveAction $ma) {

      switch ($ma->getAction()) {
         case "Install" :
            Dropdown::showAllItems("item_item",0,0,-1,PluginResourcesResource::getTypes());
            break;
            
         case "Desinstall" :
            Dropdown::showAllItems("item_item",0,0,-1,PluginResourcesResource::getTypes());
            break;
            
         case "Duplicate" :
            Dropdown::show('Entity');
            break;
         
         case "Transfert" :
            Dropdown::show('Entity');
            break;
      }
      
      return parent::showMassiveActionsSubForm($ma);
   }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
    * */
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids) {

      $task_item = new PluginResourcesTask_Item();
      $input     = $ma->getInput();
      $itemtype = $ma->getItemtype(false);

      switch ($ma->getAction()) {
         case "Transfert" :
            if ($itemtype == 'PluginResourcesTask') {
               foreach ($ids as $key => $val) {
                  $item->getFromDB($key);
                  $tasktype = PluginResourcesTaskType::transfer($item->fields["plugin_resources_tasktypes_id"],
                                                               $input['entities_id']);
                  if ($tasktype > 0) {
                     $values["id"] = $key;
                     $values["plugin_resources_tasktypes_id"] = $tasktype;
                     $item->update($values);
                  }

                  unset($values);
                  $values["id"] = $key;
                  $values["entities_id"] = $input['entities_id'];

                  if ($item->update($values)) {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                  } else {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                  }
               }
            }
            break;
            
         case "Duplicate" :
            if ($itemtype == 'PluginResourcesTask') {
               foreach ($ids as $key => $val) {
                  $item->getFromDB($key);
                  unset($item->fields["id"]);
                  $item->fields["name"]    = addslashes($item->fields["name"]);
                  $item->fields["comment"] = addslashes($item->fields["comment"]);
                  if ($item->add($item->fields)) {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                  } else {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                  }
               }
            }
            break;
            
         case "Install" :
            foreach ($ids as $key => $val) {
               $values = array('plugin_resources_tasks_id' => $key,
                               'items_id'                  => $input["item_item"],
                               'itemtype'                  => $input['itemtype']);
               if ($task_item->add($values)) {
                  $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
               } else {
                  $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
               }
            }
            break;
            
         case "Desinstall" :
            foreach ($ids as $key => $val) {
               if ($task_item->deleteItemByTaskAndItem($key, $input['item_item'], $input['itemtype'])) {
                  $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
               } else {
                  $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
               }
            }
            break;
            
         default :
            return parent::doSpecificMassiveActions($input);
      }
   }
   
   static function displayTabContentForPDF(PluginPdfSimplePDF $pdf, CommonGLPI $item, $tab) {

      if ($item->getType()=='PluginResourcesResource') {
         self::pdfForResource($pdf, $item);

      } else {
         return false;
      }
      return true;
   }
   
   /**
    * Show for PDF an resources : tasks informations
    * 
    * @param $pdf object for the output
    * @param $ID of the resources
    */
   static function pdfForResource(PluginPdfSimplePDF $pdf, PluginResourcesResource $appli) {
      global $DB;
      
      $ID = $appli->fields['id'];

      if (!$appli->can($ID,READ)) {
         return false;
      }
      
      if (!Session::haveRight("plugin_resources",READ)) {
         return false;
      }

      $query = "SELECT * 
               FROM `glpi_plugin_resources_tasks` 
               WHERE `plugin_resources_resources_id` = '$ID'
               AND `is_deleted` ='0'";
      $result = $DB->query($query);
      $number = $DB->numrows($result);
      
      $i=$j=0;
      
      $pdf->setColumnsSize(100);

      if($number>0) {
         
         $pdf->displayTitle('<b>'.self::getTypeName(2).'</b>');

         $pdf->setColumnsSize(14,14,14,14,16,14,14);
         $pdf->displayTitle('<b><i>'.
            __('Name'),
            __('Type'),
            __('Comments'),
            __('Duration'),
            __('Planning'),
            __('Resource manager', 'resources'),
            __('Group').'</i></b>'
            );
      
         $i++;
         
         while ($j < $number) {
            
            $tID=$DB->result($result, $j, "id");
            $actiontime_ID=$DB->result($result, $j, "actiontime");
            
            $actiontime='';
            $units=Toolbox::getTimestampTimeUnits($actiontime_ID);

            $hour = $units['hour'];
            $minute = $units['minute'];
            if ($hour) $actiontime = $hour .__('Hour', 'Hours', 2);
            if ($minute||!$hour)
               $actiontime.= $minute .__('Minute', 'Minutes', 2);
            
            $restrict = " `plugin_resources_tasks_id` = '".$tID."' ";
            $plans = getAllDatasFromTable("glpi_plugin_resources_taskplannings",$restrict);
            
            if (!empty($plans)) {
               foreach ($plans as $plan) {
                  $planification = Html::convDateTime($plan["begin"]) . "&nbsp;->&nbsp;" .
                  Html::convDateTime($plan["end"]);
               }
            } else {
               $planification = __('None');
            }
            
            $users_id=$DB->result($result, $j, "users_id");
            
            $managers=Html::clean(getUserName($users_id));
            $name=$DB->result($result, $j, "name");
            $task_type=$DB->result($result, $j, "plugin_resources_tasktypes_id");
            $comment=$DB->result($result, $j, "comment");
            $groups_id=$DB->result($result, $j, "groups_id");
            
            $pdf->displayLine(
               Html::clean($name),
               Html::clean(Dropdown::getDropdownName("glpi_plugin_resources_tasktypes",$task_type)),
               $comment,
               $actiontime,
               Html::clean($planification),
               $managers,
               Html::clean(Dropdown::getDropdownName("glpi_groups",$groups_id))
               );
            $j++;
         }
      } else {
         $pdf->displayLine(__('No item found'));
      }	
      
      $pdf->displaySpace();
   }
}

?>