<?

    require(__DIR__ . "\\pimodule.php");

    // Klassendefinition
    class SymconSzenenV2 extends PISymconModule {
 
        public $sensorOld = null;

        public $Details = true;

        // Der Konstruktor des Moduls
        // Überschreibt den Standard Kontruktor von IPS
        public function __construct($InstanceID) {
            // Diese Zeile nicht löschen
            parent::__construct($InstanceID);

            // Selbsterstellter Code
        }
 
        // Überschreibt die interne IPS_Create($id) Funktion
        public function Create() {

            parent::Create();
 
        }
 
        protected function setExcludedHide () {

            return array($this->detailsVar, $this->AutomatikVar, $this->SperreVar, $this->searchObjectByName("Szenen"), $this->searchObjectByName("Status"));
    
        }

        protected function setExcludedShow () {

            return array("instance", "script", $this->searchObjectByName("SceneData"), $this->searchObjectByName("Geräte"), $this->searchObjectByName("LastScene"));
    
        }

        protected function onDetailsChangeHide () {
            
            $sensorSet = false;
            $sensorID = $this->ReadPropertyInteger("Sensor");
            $prnt = IPS_GetParent($this->InstanceID);

            $name = IPS_GetName($this->InstanceID);

            if ($sensorID != null) {

                $sensorSet = true;

            }

            // Targets ausblenden
            $this->deleteObject($this->searchObjectByRealName($name . " Geräte", $prnt));

            if ($sensorSet) {

                $this->deleteObject($this->searchObjectByName($name . " DaySets", $prnt));

            }

        }

        protected function onDetailsChangeShow () {

            $sensorSet = false;
            $sensorID = $this->ReadPropertyInteger("Sensor");
            $prnt = IPS_GetParent($this->InstanceID);

            $name = IPS_GetName($this->InstanceID);

            if ($sensorID != null) {

                $sensorSet = true;

            }

            $this->linkFolderMobile($this->searchObjectByName("Targets"), $name . " Geräte", $prnt);

            // DaySets einblenden
            if ($sensorSet) {
            
                //$this->linkVar($this->searchObjectByName("DaySets"), "DaySets-Auswahl", $prnt, "|AFTER|" . $this->InstanceID, true);
                $this->linkFolderMobile($this->searchObjectByName("DaySets"), $name . " DaySets", $prnt);
                
            }

        }

        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() {
           
            parent::ApplyChanges();

            $daysetActivated = $this->isSensorSet();

            //$onChangeEventName, $targetId, $function, $parent = null
            
            $this->CheckScripts();

            $this->checkSceneVars();

            $this->updateSceneVarProfile();

            $this->deleteOldDaysets();

            $this->CheckVariables();

            $this->checkSceneTimerVars();

            // $this->easyCreateOnChangeFunctionEvent("onChange Optionen", $this->searchObjectByName("Einstellungen"), "onOptionsChange", $this->searchObjectByName("Events"));
            $this->easyCreateOnChangeFunctionEvent("onChange Szenen", $this->searchObjectByName("Szenen"), "onSzenenChange", $this->searchObjectByName("Events"));

            if ($daysetActivated) {

                 $this->easyCreateOnChangeFunctionEvent("onChange Sensor", $this->ReadPropertyInteger("Sensor"), "onSensorChange", $this->searchObjectByName("Events"));
                 $this->easyCreateOnChangeFunctionEvent("onChange Automatik", $this->searchObjectByName("Automatik"), "onAutomatikChange", $this->searchObjectByName("Events"));

            }

            $this->addProfile($this->searchObjectByName("Szenen"), $this->prefix . ".ScenesVarProfile." . $this->InstanceID, true);

            $this->deleteUnusedVars();

            $this->setTargetsOnChangeEvent();

            $this->deleteUnusedTargetOnChangeEvents();

        }

        public function Destroy () {

            parent::Destroy();

            //IPS_DeleteVariableProfile($this->prefix . ".Options" . $this->InstanceID);
            IPS_DeleteVariableProfile($this->prefix . ".ScenesVarProfile." . $this->InstanceID);

            if (IPS_VariableProfileExists($this->prefix . ".DaysetScenes." . $this->InstanceID)) {

                IPS_DeleteVariableProfile($this->prefix . ".DaysetScenes." . $this->InstanceID);

            }

        }


        public function CheckVariables () {

            //$optionen = $this->checkInteger("Einstellungen", false, null, 99, -1);
            $sceneVar = $this->checkInteger("Szenen", false, null, 3, 0);

            $targets = $this->checkFolder("Targets", null, 4);
            $events = $this->checkFolder("Events", null, 5);
            $sceneData = $this->checkFolder("SceneData", null, 6);


            $daysetActivated = $this->isSensorSet();
            $daysetSensor = $this->ReadPropertyInteger("Sensor");
            $timeIsActivated = $this->ReadPropertyBoolean("ModeTime");

            if ($timeIsActivated) {

                $status = $this->checkBoolean("Status", true, "", 2);
                $lastScene = $this->checkString("LastScene", false, $this->InstanceID, 5, null);

                $this->setIcon($status, "Power");

                $this->easyCreateOnChangeFunctionEvent("onChange Status", $status, "onStatusChange", $this->searchObjectByName("Events"));


                $this->hide($lastScene);
                

            } else {

                $this->deleteObject($this->searchObjectByName("Status"));

                $this->deleteObject($this->searchObjectByName("onChange Status", $this->searchObjectByName("Events")));

            }

            if ($daysetActivated) {

                $switches = $this->createSwitches(array("Automatik|false|0", "Sperre|false|1"));

                $daysets = $this->checkFolder("DaySets", null, 7);

                $this->updateSceneVarProfile();

                $profName = $this->getVariableProfileByVariable($daysetSensor);

                if ($profName != null) {

                    $assocs = $this->getProfileAssociations($profName);

                    if ($assocs != null) {

                        $counter = 1;

                        foreach ($assocs as $assoc) {

                            $newVar = $this->checkInteger($assoc['Name'], false, $this->searchObjectByName("DaySets"), $counter, -1);
                            $this->addProfile($newVar, $this->prefix . ".DaysetScenes." . $this->InstanceID, true);
                            $counter = $counter + 1;

                        }

                    }

                }
 
                $this->setIcon($switches[0], "Power");
                $this->setIcon($switches[1], "Power");

                $this->addSwitch($switches[0]);

                $this->activateVariableLogging($switches[0]);
                $this->activateVariableLogging($switches[1]);

                // if (!$this->profileHasAssociation($this->prefix . ".Options" . $this->InstanceID, "DaySets anzeigen") && !$this->profileHasAssociation($this->prefix . ".Options" . $this->InstanceID, "DaySets verstecken")) {

                //     $this->addAssociations($this->prefix . ".Options" . $this->InstanceID, array("DaySets anzeigen" => 3));
                //     $this->addProfile($this->searchObjectByName("Einstellungen"), $this->prefix . ".Options" . $this->InstanceID);

                // }

            } else {

                $prnt = IPS_GetParent($this->InstanceID);

                $this->deleteObject($this->searchObjectByName("DaySets"));

                $this->deleteObject($this->searchObjectByName("DaySets-Auswahl", $prnt));

                // $this->removeAssociation($this->prefix . ".Options" . $this->InstanceID, "DaySets anzeigen");
                // $this->removeAssociation($this->prefix . ".Options" . $this->InstanceID, "DaySets verstecken");

            }

            //$name, $setProfile = false, $position = "", $index = 0, $defaultValue = null, $istAbstand = false
            //$this->checkString("", false, $this->InstanceID, "|AFTER|" . $sceneVar, null, true);

            // $this->addProfile($optionen, $this->prefix . ".Options" . $this->InstanceID);
            $this->addProfile($sceneVar, $this->prefix . ".ScenesVarProfile." . $this->InstanceID);

            // $this->setIcon($optionen, "Gear");
            $this->setIcon($sceneVar, "Rocket");

            // $this->addSetValue($optionen);


        }
    
        public function RegisterProperties () {
    
            $this->RegisterPropertyBoolean("ModeDaySet", true);
            $this->RegisterPropertyString("Names", "");
            $this->RegisterPropertyBoolean("ModeTime", false);
            $this->RegisterPropertyBoolean("Loop", false);
            $this->RegisterPropertyInteger("Sensor", null);
    
        }
    
        public function CheckScripts () {
    
            // Hier werden alle nötigen Scripts erstellt (SetValue wird automatisch erstellt)
            $timeIsActivated = $this->ReadPropertyBoolean("ModeTime");

            if ($timeIsActivated) {

                $nextElement = $this->checkScript("nextElement", $this->prefix . "_nextElement", 1001); 
                $this->hide($nextElement);

            } else {

                $this->deleteObject($this->searchObjectByName("nextElement"));

            }
    
        }

        public function CheckProfiles () {

            //checkVariableProfile ($name, $type, $min = 0, $max = 100, $steps = 1, $associations = null) {
            // $this->checkVariableProfile($this->prefix . ".Options" . $this->InstanceID, $this->varTypeByName("int"), 0, 3, 0, array("Zeige Einstellungen" => 0, "Modul einklappen" => 1, "Start" => 2));
            //$this->checkVariableProfile($this->prefix . ".StartStop." . $this->InstanceID, 1, 0, 1, 0, array("Start" => 1));
            $this->checkVariableProfile($this->prefix . ".SceneOptions", $this->varTypeByName("int"), 0, 1, 0, array("Speichern" => 0, "Ausführen" => 1));
            $this->checkVariableProfile($this->prefix . ".SceneTimerVar", $this->varTypeByName("int"), 0, 3600, 1, null, "", " s");

        }

        #                            #
        #   Modulspez. Funktionen    #
        #                            #

        protected function deleteOldDaysets () {

            if ($this->isSensorSet()) {

                $oldSensor = $this->eventGetTriggerVariable($this->searchObjectByName("onChange Sensor", $this->searchObjectByName("Events")));
            //echo "OldSensor: " . $oldSensor;
            $sensor = $this->ReadPropertyInteger("Sensor");

            if ($sensor != null) {

                if ($oldSensor != $sensor) {

                    $this->deleteAllChildren($this->searchObjectByName("DaySets"));

                }

            }

            }

        }

        protected function setTargetsOnChangeEvent () {

            $targets = $this->searchObjectByName("Targets");

            if (IPS_HasChildren($targets)) {

                $targets = IPS_GetObject($targets);

                foreach ($targets['ChildrenIDs'] as $child) {

                    if ($this->isLink($child)) {

                        $child = IPS_GetLink($child);
                        $childTarget = $child['TargetID'];

                        if (!$this->doesExist($this->searchObjectByName("onChangeSensor " . $childTarget . " " . $this->InstanceID))) {

                            $this->easyCreateOnChangeFunctionEvent("onChangeSensor " . $childTarget . " " . $this->InstanceID, $childTarget, "<?php " . $this->prefix . "_targetSensorChange(" . $this->InstanceID . ");" . " ?>", $this->searchObjectByName("Events"), false);

                        }

                    }

                }

            }

        }

        protected function deleteUnusedTargetOnChangeEvents () {

            $events = $this->searchObjectByName("Events");
            $targets = $this->searchObjectByName("Targets");

            $targets = IPS_GetObject($targets);

            if (IPS_HasChildren($events)) {

                $children = $this->getAllObjectsContainsString("onChangeSensor", $this->searchObjectByName("Events"));

                foreach ($children as $child) {

                    $child = IPS_GetEvent($child);
                    $isUsed = false;

                    foreach ($targets['ChildrenIDs'] as $target) {

                        if ($this->isLink($target)) {

                            $target = IPS_GetLink($target);

                            if ($target['TargetID'] == $child['TriggerVariableID']) {

                                $isUsed = true;

                            }

                        }

                    }

                    if (!$isUsed) {

                        $this->deleteObject($child['EventID']);

                    }

                }

            }

        }

        public function nextElement () {

            $allScenes = $this->getAllScenesSorted();

            $lastScene = GetValue($this->searchObjectByName("LastScene"));

            if ($lastScene == null) {

                SetValue($this->searchObjectByName("LastScene"), $allScenes[1]);
                SetValue($this->searchObjectByName($allScenes[1]), 1);

                $lc = GetValue($this->searchObjectByName("LastScene"));

                IPS_SetScriptTimer($this->searchObjectByName("nextElement"), $this->getTimerLengthBySceneName($allScenes[1]));

                $this->linkVar($this->getFirstChildFrom($this->searchObjectByName("nextElement")), "Timer Status", $this->InstanceID, "|AFTER|" . $this->searchObjectByName($lc . " Timer"), true);

            } else {

                $nextElement = $this->getElementAfterInArray($lastScene, $allScenes);

                if ($nextElement != "last") {

                    SetValue($this->searchObjectByName("LastScene"), $nextElement);
                    SetValue($this->searchObjectByName($nextElement), 1);

                    $lc = GetValue($this->searchObjectByName("LastScene"));

                    IPS_SetScriptTimer($this->searchObjectByName("nextElement"), $this->getTimerLengthBySceneName($nextElement));
                    $this->setPosition($this->searchObjectByName("Timer Status"), "|AFTER|" . $this->searchObjectByName($lc . " Timer"));

                } else {

                    if ($this->ReadPropertyBoolean("Loop")) {

                        SetValue($this->searchObjectByName("LastScene"), $allScenes[1]);
                        SetValue($this->searchObjectByName($allScenes[1]), 1);

                        $fc = $this->searchObjectByName($allScenes[1] . " Timer");

                        IPS_SetScriptTimer($this->searchObjectByName("nextElement"), $this->getTimerLengthBySceneName($allScenes[1]));

                        $this->setPosition($this->searchObjectByName("Timer Status"), "|AFTER|" . $fc);

                    } else {

                        IPS_DeleteLink($this->searchObjectByName("Timer Status"));

                        SetValue($this->searchObjectByName("LastScene"), null);
                        SetValue($this->searchObjectByName($allScenes[0]), 1);

                        // if ($this->profileHasAssociation($this->prefix . ".StartStop." . $this->InstanceID, "Stop")) {

                        //     $this->changeAssociations($this->prefix . ".StartStop." . $this->InstanceID, array("Stop" => "Start"));
                        //     $this->addProfile($this->searchObjectByName("Einstellungen"), $this->prefix . ".StartStop." . $this->InstanceID);

                        // }

                        IPS_SetScriptTimer($this->searchObjectByName("nextElement"), 0);

                        SetValue($this->searchObjectByName("Status"), false);

                    }

                }

            }

        }

        public function getAllScenesSorted () {

            $scenes = $this->getAllVarsByVariableCustomProfile($this->prefix . ".SceneOptions");
            return $scenes;

        }

        protected function getTimerLengthBySceneName ($sceneName) {

            $timer = GetValue($this->searchObjectByName($sceneName . " Timer"));
            return $timer;

        }

        protected function checkSceneVars () {

            $own = IPS_GetObject($this->InstanceID);

            $scenes = $this->ReadPropertyString("Names");

            $scenes = json_decode($scenes);

            //print_r($scenes);

            $existingScenes = $this->getAllVarsByVariableCustomProfile($this->prefix . ".SceneOptions");

            $sceneNames = null;

            if (count($scenes) > 0) {

                foreach ($scenes as $scene) {

                    $doesexist = false;

                    if (count($existingScenes) > 0) {

                        foreach ($existingScenes as $escene) {

                            if ($escene == $scene->Name) {

                                $doesexist = true;

                            }

                        }

                    }

                    if (!$doesexist) {

                        $newPos = $this->getHighestPosition() + 1;
                        $newInt = $this->checkInteger($scene->Name, false, $this->InstanceID, $newPos, -1);
                        $newSceneData = $this->checkString($scene->Name . " SceneData", false, $this->searchObjectByName("SceneData"), 0, "");
                        $this->addSetValue($newInt);
                        $this->setIcon($newInt, "Rocket");
                        $this->addProfile($newInt, $this->prefix . ".SceneOptions");

                        $this->easyCreateOnChangeFunctionEvent("onChange " . $newInt, $newInt, "onSceneVarChange", $this->searchObjectByName("Events"));


                    }

                }

            }

        }

        protected function checkSceneTimerVars () {

            $modeActivated = $this->ReadPropertyBoolean("ModeTime");

            if ($modeActivated) {

                $allTimerVars = $this->getAllVarsByVariableCustomProfile($this->prefix . ".SceneTimerVar");
                $allSceneVars = $this->getAllVarsByVariableCustomProfile($this->prefix . ".SceneOptions");

                //print_r($allSceneVars);

                if ($allSceneVars == null) {
                    return;
                }

                foreach ($allSceneVars as $sceneVar) {

                    $doesExist = false;

                    $sceneVarObj = IPS_GetObject($this->searchObjectByName($sceneVar));

                    if (count($allTimerVars) > 0) {

                        foreach ($allTimerVars as $timerVar) {

                            $timerVarObj = IPS_GetObject($this->searchObjectByName($timerVar));

                            if ($timerVarObj['ObjectName'] == $sceneVarObj['ObjectName'] . " Timer") {

                                $doesExist = true;

                            }

                        }

                    }

                    if (!$doesExist) {

                        $checkTimer = $this->checkInteger($sceneVarObj['ObjectName'] . " Timer", false, "", "|AFTER|" . $this->searchObjectByname($sceneVar), 10);
                        $this->setIcon($checkTimer, "Clock");
                        $this->addProfile($checkTimer, $this->prefix . ".SceneTimerVar");
                        $this->addSetValue($checkTimer);

                    }

                }
                

            }

        }

        protected function deleteUnusedVars () {

            $existingScenes = $this->getAllVarsByVariableCustomProfile($this->prefix . ".SceneOptions");
            $existingSceneTimers = $this->getAllVarsByVariableCustomProfile($this->prefix . ".SceneTimerVar");
            $timerIsEnabled = $this->ReadPropertyBoolean("ModeTime");
            $daysetActivated = $this->isSensorSet();

            $sceneNames = $this->getAllSceneNames();

            $sensor = $this->ReadPropertyInteger("Sensor");

            // if ($sensor != $lastSensor) {

            //     $this->deleteAllChildren($this->searchObjectByName("DaySets"));

            // }

            if (!$daysetActivated) {

                $this->deleteObject($this->AutomatikVar);
                $this->deleteObject($this->SperreVar);

                $this->deleteObject($this->searchObjectByName("onChange Sensor", $this->searchObjectByName("Events")));

            } else {

                //echo "Dayset activated";
                $oldSensor = $this->eventGetTriggerVariable($this->searchObjectByName("onChange Sensor", $this->searchObjectByName("DaySets")));
                $sensor = $this->ReadPropertyInteger("Sensor");

                if ($oldSensor != $sensor) {
                    $this->deleteObject($this->searchObjectByName("onChange Sensor", $this->searchObjectByName("Events")));
                    $this->easyCreateOnChangeFunctionEvent("onChange Sensor", $this->ReadPropertyInteger("Sensor"), "onSensorChange", $this->searchObjectByName("Events"));
                }

            }


            if ($existingScenes == null) {
                return;
            }

            if ($timerIsEnabled != true) {

                if ($existingSceneTimers != null) {

                    if (count($existingSceneTimers) > 0) {

                        foreach ($existingSceneTimers as $timerVar) {

                            $timerVar = $this->searchObjectByName($timerVar);

                            $this->deleteObject($timerVar);

                        }

                    }

                }

            }

            if ($sceneNames == null || $sceneNames == "") {

                foreach ($existingScenes as $eScene) {

                    $eSceneVarId = $this->searchObjectByName($eScene);

                    // Delete Object
                    $this->deleteObject($this->searchObjectByName($eScene));

                    // Delete Timer if existing
                    if ($this->doesExist($this->searchObjectByName($eScene . " Timer"))) {

                        $this->deleteObject($this->searchObjectByName($eScene . " Timer"));

                    }

                    // Delete Event if existing
                    if ($this->doesExist($this->searchObjectByName("onChange " . $eSceneVarId, $this->searchObjectByName("Events")))) {

                        $this->deleteObject($this->searchObjectByName("onChange " . $eSceneVarId, $this->searchObjectByName("Events")));

                    }


                    // Delete SceneData if existing
                    if ($this->doesExist($this->searchObjectByName($eScene . " SceneData", $this->searchObjectByName("SceneData")))) {

                        $this->deleteObject($this->searchObjectByName($eScene . " SceneData", $this->searchObjectByName("SceneData")));

                    }

                }

            }

            $completeDelete = false;

            if ($sceneNames == null) {
                $completeDelete = true;
                $sceneNames = array();
            }

            foreach ($existingScenes as $eScene) {

                if (!in_array($eScene, $sceneNames) || $completeDelete) {

                    if ($this->doesExist($this->searchObjectByName($eScene))) {

                        $eSceneVarId = $this->searchObjectByName($eScene);

                        // Delete Object
                        $this->deleteObject($this->searchObjectByName($eScene));

                        // Delete Timer if existing
                        if ($this->doesExist($this->searchObjectByName($eScene . " Timer"))) {

                            $this->deleteObject($this->searchObjectByName($eScene . " Timer"));

                        }

                        // Delete Event if existing
                        if ($this->doesExist($this->searchObjectByName("onChange " . $eSceneVarId, $this->searchObjectByName("Events")))) {

                            $this->deleteObject($this->searchObjectByName("onChange " . $eSceneVarId, $this->searchObjectByName("Events")));

                        }

                        // Delete SceneData if existing
                        if ($this->doesExist($this->searchObjectByName($eScene . " SceneData", $this->searchObjectByName("SceneData")))) {

                            $this->deleteObject($this->searchObjectByName($eScene . " SceneData", $this->searchObjectByName("SceneData")));

                        }

                    }

                }

            }

        }

        protected function isSensorSet () {

            $sens = $this->ReadPropertyInteger("Sensor");

            if ($sens != null && $sens != 0) {

                return true;

            } else {

                return false;

            }

        }

        protected function getAllSceneNames () {

            $scenes = json_decode($this->ReadPropertyString("Names"));

            $ary = null;

            if (count($scenes) > 0) {

                foreach ($scenes as $scene) {

                    $ary[] = $scene->Name;

                }

            }

            return $ary;

        }

        protected function updateSceneVarProfile () {

            $scenes = $this->getAllVarsByVariableCustomProfile($this->prefix . ".SceneOptions");

            $assocs = null;

            $counter = 0;

            if (count($scenes) > 0) {

                foreach ($scenes as $scene) {

                    $scene = IPS_GetObject($this->searchObjectByName($scene));

                    $sceneName = $scene['ObjectName'];

                    $assocs[$sceneName] = $counter;
                    
                    $counter = $counter + 1;

                }

                if (IPS_VariableProfileExists($this->prefix . ".DaysetScenes." . $this->InstanceID)) {
                    IPS_DeleteVariableProfile($this->prefix . ".DaysetScenes." . $this->InstanceID);
                }

                $this->createDynamicProfile($this->prefix . ".ScenesVarProfile." . $this->InstanceID, $assocs);
                $this->cloneVariableProfile($this->prefix . ".ScenesVarProfile." . $this->InstanceID, $this->prefix . ".DaysetScenes." . $this->InstanceID);
                $this->addAssociations($this->prefix . ".DaysetScenes." . $this->InstanceID, array("—" => -1));
                $this->addAssociations($this->prefix . ".ScenesVarProfile." . $this->InstanceID, array("Individuell" => 999));

            } else {

                $this->createDynamicProfile($this->prefix . ".ScenesVarProfile." . $this->InstanceID, array("Individuell" => 999));

            }

            //$this->createDynamicProfile();

        }

        protected function getSceneHashList () {

            $sceneData = $this->searchObjectByName("SceneData");
            $sceneData = IPS_GetObject($sceneData);

            $ary = array();

            if (IPS_HasChildren($sceneData['ObjectID'])) {

                foreach ($sceneData['ChildrenIDs'] as $child) {

                    $childVal = GetValue($child);
                    //$childVal = md5($childVal);
                    $ary[] = md5($childVal);

                }

            }

            return $ary;

        }

        ##                 ##
        ## OnChange Events ##
        ##                 ##
        
        public function onSensorChange () {

            $senderVar = $_IPS['VARIABLE'];
            $senderVal = GetValue($senderVar);
            $automatik = GetValue($this->AutomatikVar);
            $sperre = GetValue($this->SperreVar);
            $sensor = $this->ReadPropertyInteger("Sensor");
            $sensorProfile = $this->getVariableProfileByVariable($sensor);
            $sensorVal = GetValue($sensor);

            if ($automatik && !$sperre) {

                $dsName = $this->getAssociationTextByValue($sensorProfile, $senderVal);
                //echo "dsName: " . $dsName;
                $dsObj = $this->searchObjectByName($dsName, $this->searchObjectByName("DaySets"));

                $dsVal = GetValue($dsObj);



                    if ($dsVal != -1) {

                        SetValue($this->searchObjectByName("Szenen"), $dsVal);

                    }

                

            }
            

        }

        public function onAutomatikChange () {

            $automatik = GetValue($this->AutomatikVar);

            // Wenn Automatik auf true
            if ($automatik) {



            } else {
            // Wenn Automatik auf false, Timer Löschen (Funktion prüft autom. ob Element existiert)!

                $this->deleteObject($this->getFirstChildFrom($this->searchObjectByName("nextElement")));

            }

        }

        public function onSceneVarChange () {

            $senderVar = $_IPS['VARIABLE'];
            $senderObj = IPS_GetObject($senderVar);
            $senderVal = GetValue($senderVar);
            $senderName = $senderObj['ObjectName'];
            $targets = IPS_GetObject($this->searchObjectByName("Targets"));

            if ($_IPS['OLDVALUE'] == $senderVal) {
                return;
            }

            // Wenn Speichern
            if ($senderVal == 0) {
            
                $sceneDataName = $senderName . " SceneData";
                $sceneDataVar = $this->searchObjectByName($sceneDataName, $this->searchObjectByName("SceneData"));
                $sceneDataVal = GetValue($sceneDataVar);
                
                //if ($sceneDataVal != null && $sceneDataVal != "") {

                    $states = array();

                    if (count($targets['ChildrenIDs']) > 0)  {

                        foreach ($targets['ChildrenIDs'] as $child) {

                            $child = IPS_GetObject($child);

                            if ($child['ObjectType'] == $this->objectTypeByName("Link")) {

                                $child = IPS_GetLink($child['ObjectID']);

                                $tg = $child['TargetID'];

                                $states[$tg] = GetValue($tg);

                            }

                        }

                        SetValue($sceneDataVar, json_encode($states));

                    }

                //}


            } else if ($senderVal == 1) {

                // Wenn Ausführen
                $sceneDataName = $senderName . " SceneData";
                $sceneDataVar = $this->searchObjectByName($sceneDataName, $this->searchObjectByName("SceneData"));
                $sceneDataVal = GetValue($sceneDataVar);
                
                if ($sceneDataVal != null && $sceneDataVal != "") {

                    $json = json_decode($sceneDataVal);

                    foreach ($json as $id => $val) {

                        $oldVal = GetValue($id);

                        if ($oldVal != $val) {

                            $this->setDevice($id, $val);

                        }

                    }

                } else {

                    $this->sendWebfrontNotification("Keine Szenen Daten", "Es konnten keine Szenen Daten gefunden werden!", "Bulb", 5);

                }

            }

            SetValue($senderVar, -1);

        }

        public function onSzenenChange() {

            $sender = $_IPS['VARIABLE'];
            $senderVal = GetValue($sender);

            if ($_IPS['OLDVALUE'] == $senderVal) {
                return;
            }

            if ($senderVal == 999) {
                return;
            }

            $sceneName = $this->getAssociationTextByValue($this->prefix . ".ScenesVarProfile." . $this->InstanceID, $senderVal);
            $sceneDataVal = GetValue($this->searchObjectByName($sceneName . " SceneData", $this->searchObjectByName("SceneData")));
            
            if ($sceneDataVal != null && $sceneDataVal != "") {

                $sceneData = json_decode($sceneDataVal);

                foreach ($sceneData as $devId => $devVal) {

                    $devValOld = GetValue($devId);

                    if ($devValOld != $devVal) {
                        $this->setDevice($devId, $devVal);
                    }

                }

            }

        }

        public function onStatusChange () {

            $var = $_IPS['VARIABLE'];
            $val = GetValue($var);

            // Start / Stop Zeitschaltung
            if ($val == true) {

                $this->nextElement();

                return;

            } else {

                $allScenes = $this->getAllScenesSorted();

                SetValue($allScenes[0], 1);

                $this->deleteObject($this->searchObjectByName("Timer Status"));
                $this->deleteObject($this->getFirstChildFrom($this->searchObjectByName("nextElement")));

                SetValue($this->searchObjectByName("LastScene"), "");

            }

        }

        public function targetSensorChange () {
                
                //if ($sceneDataVal != null && $sceneDataVal != "") {

                $states = array();
                $targets = IPS_GetObject($this->searchObjectByName("Targets"));
                $send = $_IPS['VARIABLE'];
                $send = GetValue($send);


                if ($_IPS['OLDVALUE'] == $send) {

                }

                if (count($targets['ChildrenIDs']) > 0)  {

                    foreach ($targets['ChildrenIDs'] as $child) {

                            $child = IPS_GetObject($child);

                            if ($child['ObjectType'] == $this->objectTypeByName("Link")) {

                                $child = IPS_GetLink($child['ObjectID']);

                                $tg = $child['TargetID'];

                                $states[$tg] = GetValue($tg);

                            }

                        }

                        if (!in_array(md5(json_encode($states)), $this->getSceneHashList())) {

                            SetValue($this->searchObjectByName("Szenen"), 999);

                        } else {

                            foreach ($this->getSceneHashList() as $kkey => $kval) {

                                if ($kval == md5(json_encode($states))) {

                                    SetValue($this->searchObjectByName("Szenen"), $kkey);

                                }

                            }

                        }
                        //echo md5(json_encode($states));

                    }

                //}

        }


        //  Öffentliche Funktionen

        public function Start () {

            $timeIsActivated = $this->ReadPropertyBoolean("ModeTime");

            $started = GetValue($this->searchObjectByName("Status"));

            if ($timeIsActivated) {

                if ($started) {

                    // $this->changeAssociations($this->prefix . ".Options" . $this->InstanceID, array("Start" => "Stop"));
                    // $this->addProfile($this->searchObjectByName("Einstellungen"), $this->prefix . ".Options" . $this->InstanceID);

                    $this->nextElement();

                    SetValue($this->searchObjectByName("Einstellungen"), -1);
                    return;

                } else {

                    echo "Ist bereits gestartet!";

                }

            }

        }

        public function Stop () {

            $timeIsActivated = $this->ReadPropertyBoolean("ModeTime");

            $started = GetValue($this->searchObjectByName("Status"));

            if ($timeIsActivated) {

                if (!$started) {

                    $this->deleteObject($this->searchObjectByName("Timer Status"));
                    $this->deleteObject($this->getFirstChildFrom($this->searchObjectByName("nextElement")));

                    SetValue($this->searchObjectByName("LastScene"), "");

                    // $this->changeAssociations($this->prefix . ".Options" . $this->InstanceID, array("Stop" => "Start"));
                    // $this->addProfile($this->searchObjectByName("Einstellungen"), $this->prefix . ".Options" . $this->InstanceID);

                } else {

                    echo "Läuft nicht!";

                }

            }

        }

    }


?>