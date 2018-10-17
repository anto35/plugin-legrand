<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class legrandeco extends eqLogic {

  public static function cron() {
    foreach (eqLogic::byType('legrandeco',true) as $legrandeco) {
      $legrandeco->getInformations();
      $legrandeco->getData();
    }
  }

  public function preUpdate() {
    if ($this->getConfiguration('addr') == '') {
      throw new Exception(__('L\'adresse ne peut être vide',__FILE__));
    }
  }

  public function postUpdate() {
    $this->getInformations();
    $this->getData();
  }

  public function checkCmdOk($_type, $_name, $_template) {
    $legrandecoCmd = legrandecoCmd::byEqLogicIdAndLogicalId($this->getId(),$_type . '-' . $_name);
    if (!is_object($legrandecoCmd)) {
      log::add('stock', 'debug', 'Création de la commande ' . $_name);
      $legrandecoCmd = new legrandecoCmd();
      $legrandecoCmd->setName(__($_type . ' - ' . $_name, __FILE__));
      $legrandecoCmd->setEqLogic_id($this->getId());
      $legrandecoCmd->setEqType('legrandeco');
      $legrandecoCmd->setLogicalId($_type . '-' . $_name);
      $legrandecoCmd->setType('info');
      $legrandecoCmd->setSubType('numeric');
      $legrandecoCmd->setIsVisible('1');
      $legrandecoCmd->setTemplate("mobile",'line' );
      $legrandecoCmd->setTemplate("dashboard",'line' );
      $legrandecoCmd->setDisplay('icon', $_template);
      $legrandecoCmd->setConfiguration('type', $_type);
      $legrandecoCmd->save();
      $legrandecoCmd->event(0);
    }
  }

  public function getInformations() {
    $devAddr = 'http://' . $this->getConfiguration('addr', '') . '/inst.json';
    $request_http = new com_http($devAddr);
    $devResult = $request_http->exec(30);
    log::add('legrandeco', 'debug', 'getInformations ' . $devAddr);
    if ($devResult === false) {
      log::add('legrandeco', 'info', 'problème de connexion ' . $devAddr);
    } else {
      $devResbis = utf8_encode($devResult);
      $devList = json_decode($devResbis, true);
      log::add('legrandeco', 'debug', print_r($devList, true));
      foreach($devList as $name => $value) {
        if ($name === 'heure' || $name === 'minute') {
          // pas de traitement sur l'heure
        } else {
          $this->checkCmdOk('inst', $name, '<i class="fa fa-flash"></i>');
          $this->checkAndUpdateCmd($name, $value);
        }
      }
    }
    $this->refreshWidget();
  }

  public function getData() {
    $devAddr = 'http://' . $this->getConfiguration('addr', '') . '/data.json';
    $request_http = new com_http($devAddr);
    $devResult = $request_http->exec(30);
    log::add('legrandeco', 'debug', 'getInformations ' . $devAddr);
    if ($devResult === false) {
      log::add('legrandeco', 'info', 'problème de connexion ' . $devAddr);
    } else {
      $devResbis = utf8_encode($devResult);
      $corrected = preg_replace('/\s+/', '', $devResbis);
      $corrected = preg_replace('/\:0,/', ': 0,', $corrected);
      $corrected = preg_replace('/\:[0]+/', ":", $corrected);
      $devList = json_decode($corrected, true);
      log::add('legrandeco', 'debug', print_r($devList, true));
      if (json_last_error() == JSON_ERROR_NONE) {
        foreach($devList as $name => $value) {
          if (strpos($name,'type_imp') !== false || strpos($name,'label_entree') !== false || strpos($name,'entree_imp') !== false) {
            // pas de traitement sur ces données
          } else {
            $this->checkCmdOk('teleinfo', $name, '<i class="fa fa-flash"></i>');
            $this->checkAndUpdateCmd($name, $value);
          }
        }
      }
    }
    $this->refreshWidget();
  }

  public function getConso() {
    $devAddr = 'http://' . $this->getConfiguration('addr', '') . '/LOG2.CSV';
    $devResult = fopen($devAddr, "r");
    log::add('legrandeco', 'info', 'getConso ' . $devAddr);
    /*
    jour	mois	annee	heure	minute	energie_tele_info	prix_tele_info	energie_circuit1	prix_circuit1	energie_cirucit2	prix_circuit2	energie_circuit3	prix_circuit3	energie_circuit4	prix_circuit4	energie_circuit5	prix_circuit5	volume_entree1	volume_entree2	tarif	energie_entree1	energie_entree2	prix_entree1	prix_entree2
    17	8	15	20	2	0.000	0.000	0.000	0.000	0.000	0.000	0.000	0.000	0.000	0.000	0.000	0.000	0.000	0.000	0	0.000	0.000	0.000	0.000
    17	8	15	21	2	0.000	0.000	0.000	0.000	0.000	0.000	0.000	0.000	0.000	0.000	0.000	0.000	0.000	0.000	11	0.000	0.000	0.000	0.000
    */
    if ($devResult === false) {
      log::add('legrandeco', 'info', 'problème de connexion ' . $devAddr);
    } else {
      while ( ($data = fgetcsv($devResult,1000,";") ) !== FALSE ) {
        $num = count($data);
        if ($data[0] == date('j') && $data[1] == date('n') && $data[2] == date('y') && $data[3] == date('G')) {
          $this->checkCmdOk('csv', 'energie_tele_info', '<i class="fa fa-flash"></i>');
          $this->checkAndUpdateCmd('energie_tele_info', $data[5]);

          $this->checkCmdOk('csv', 'energie_circuit1', '<i class="fa fa-flash"></i>');
          $this->checkAndUpdateCmd('energie_circuit1', $data[7]);

          $this->checkCmdOk('csv', 'energie_circuit2', '<i class="fa fa-flash"></i>');
          $this->checkAndUpdateCmd('energie_circuit2', $data[9]);

          $this->checkCmdOk('csv', 'energie_circuit3', '<i class="fa fa-flash"></i>');
          $this->checkAndUpdateCmd('energie_circuit3', $data[11]);

          $this->checkCmdOk('csv', 'energie_circuit4', '<i class="fa fa-flash"></i>');
          $this->checkAndUpdateCmd('energie_circuit4', $data[13]);

          $this->checkCmdOk('csv', 'energie_circuit5', '<i class="fa fa-flash"></i>');
          $this->checkAndUpdateCmd('energie_circuit5', $data[15]);

          $this->checkCmdOk('csv', 'volume_entree1', '<i class="fa fa-cloud"></i>');
          $this->checkAndUpdateCmd('volume_entree1', $data[17]);

          $this->checkCmdOk('csv', 'volume_entree2', '<i class="fa fa-cloud"></i>');
          $this->checkAndUpdateCmd('volume_entree2', $data[18]);

          $this->checkCmdOk('csv', 'energie_entree1', '<i class="fa fa-flash"></i>');
          $this->checkAndUpdateCmd('energie_entree1', $data[20]);

          $this->checkCmdOk('csv', 'energie_entree2', '<i class="fa fa-flash"></i>');
          $this->checkAndUpdateCmd('energie_entree2', $data[21]);
        }
      }
    }
  }

}

class legrandecoCmd extends cmd {

}

?>
