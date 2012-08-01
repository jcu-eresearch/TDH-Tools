<?php
session_start();
include_once dirname(__FILE__).'/includes.php';


?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Species Suitability</title>

    <link type="text/css" href="css/start/jquery-ui-1.8.21.custom.css" rel="stylesheet" />
    <link type="text/css" href="css/selectMenu.css" rel="stylesheet" />
    <script type="text/javascript" src="js/jquery-1.7.2.min.js"></script>
    <script type="text/javascript" src="js/jquery-ui-1.8.21.custom.min.js"></script>
    <script type="text/javascript" src="js/selectMenu.js"></script>
    <script type="text/javascript" src="js/Utilities.js"></script>

    <link href="styles.css" rel="stylesheet" type="text/css">
    <link href="HotSpots.css" rel="stylesheet" type="text/css">
    
    <script type="text/javascript" >
    <?php     
        echo htmlutil::AsJavaScriptSimpleVariable(configuration::ApplicationFolderWeb(),'ApplicationFolderWeb');
        echo htmlutil::AsJavaScriptObjectArray(SpeciesData::speciesList(),"full_name","species_id","availableSpecies");    
        echo htmlutil::AsJavaScriptSimpleVariable(configuration::IconSource(),'IconSource');
     ?>
         
    var availableTaxa = [
         { label: "Amphibia", value: "1" }
        ,{ label: "Aves",     value: "2" }
        ,{ label: "Mammalia", value: "3" }
        ,{ label: "Plantae",  value: "4" }
        ,{ label: "Reptilia", value: "5" }
        ];

    var availableFamily = [
         { label: "Amphibia", value: "1" }
        ,{ label: "Aves",     value: "2" }
        ,{ label: "Mammalia", value: "3" }
        ,{ label: "Plantae",  value: "4" }
        ,{ label: "Reptilia", value: "5" }
        ];

    var availableGenus = [
         { label: "Amphibia", value: "1" }
        ,{ label: "Aves",     value: "2" }
        ,{ label: "Mammalia", value: "3" }
        ,{ label: "Plantae",  value: "4" }
        ,{ label: "Reptilia", value: "5" }
        ];

    var availableLocation = [
         { label: "Cairns", value: "1" }
        ,{ label: "Queensland",     value: "2" }
        ,{ label: "Sydney", value: "3" }
        ,{ label: "Melbourne",  value: "4" }
        ,{ label: "Perth", value: "5" }
        ];


    </script>
    
    <script type="text/javascript" src="HotSpots.js"></script>

</head>
<body>
<?php include_once 'ToolsHeader.php';  ?>
    
<?php 
    $liFormat = '<li class="ui-widget-content ui-corner-all " ><h4>{DataName}</h4><p>{Description}</p> </li>'; 
    
?>
        

    
<div id="thecontent">

    
    <div id="tabs">
        <ul>
            <li><a href="#tabs-1">Inputs</a></li>
            <li><a href="#tabs-2">Climate Models</a></li>
            <li><a href="#tabs-3">Emission Scenarios</a></li>
            <li><a href="#tabs-4">Years</a></li>
            <li><a href="#tabs-5">Bioclimatic Layers</a></li>
            <li><a href="#tabs-6">Process</a></li>
        </ul>
        <div id="tabs-1">
            
            <h3 id="InputsSearchBar" class="ui-widget-header ui-corner-all">
                <form>
                    <div id="InputTypesSet">
                        <h4>SEARCH BY</h4>
                        <input type="radio" id="InputTypeTaxa"     name="InputTypes" /><label for="InputTypeTaxa">Taxa</label>
                        <input type="radio" id="InputTypeFamily"   name="InputTypes" /><label for="InputTypeFamily">Family</label>
                        <input type="radio" id="InputTypeGenus"    name="InputTypes" /><label for="InputTypeGenus">Genus</label>
                        <input type="radio" id="InputTypeSpecies"  name="InputTypes" /><label for="InputTypeSpecies">Species</label>
                        <input type="radio" id="InputTypeLocation" name="InputTypes" /><label for="InputTypeLocation">Location</label>
                        <input type="input" id="InputText"         name="InputText" class="ui-corner-all">
                    </div>
                </form>                
            </h3>
            
            <ul id="InputsSelection" class="selectable">

            </ul>
            
            <div id="MapContainer" class="ui-widget-content ui-corner-all" >

                <div id="MapTools" class="ui-widget-header ui-corner-all">
                    <button id="ToolClearAll"   onclick="ClearAll();"      >Clear All</button>
                    <button id="ToolFullExtent" onclick="SetFullExtent();" >Reset Map</button>
                    <input name="MapsTools" type="radio" id="ToolZoomOut"  onclick="SetZoom(this,-2.0);"                   /><label for="ToolZoomOut">Zoom Out</label>
                    <input name="MapsTools" type="radio" id="ToolCentre"   onclick="SetZoom(this,1.0)"                     /><label for="ToolCentre" >Centre</label>
                    <input name="MapsTools" type="radio" id="ToolZoomIn"   onclick="SetZoom(this,2.0)"   checked="checked" /><label for="ToolZoomIn" >Zoom In</label>
                </div>

                <iframe class="ui-widget-content ui-corner-all" ID="GUI" src="HotSpotsMap.php?w=800&h=600" width="820" height="626" frameBorder="0" border="0" style="margin: 0px; overflow:hidden; float:none; clear:both;" onload="map_gui_loaded()"></iframe>
                <FORM id="MapInteractionData" METHOD=POST ACTION="<?php echo $_SERVER['PHP_SELF']?>"><INPUT TYPE="HIDDEN" ID="ZoomFactor" NAME="ZoomFactor" VALUE="2"><INPUT TYPE="HIDDEN" ID="UserLayer"  NAME="UserLayer"  VALUE=""><INPUT TYPE="HIDDEN" ID="SpeciesID"  NAME="SpeciesID"  VALUE=""></FORM>        

            </div>
            
            
        </div>

        <div id="tabs-2">
            <div class="SelectionToolBar ui-widget-header ui-corner-all">
                <button id="SelectAllModels"  >select all</button>
                <button id="SelectNoneModels" >deselect all</button>
            </div>       
            
            <ul id="ModelsSelection" class="selectable">
            <?php 
                $liFormat = '<li id="Models_{DataName}" class="ui-widget-content ui-corner-all " ><h4>{DataName}</h4><p>{Description}</p> </li>'; 
                echo DatabaseClimate::GetModelsDescriptions()->asFormattedString($liFormat); 
            ?>
            </ul>
            
        </div>
        
        <div id="tabs-3">
            <div class="SelectionToolBar ui-widget-header ui-corner-all">
                <button id="SelectAllScenarios"  >select all</button>
                <button id="SelectNoneScenarios" >deselect all</button>
            </div>       
            
            <ul id="ScenariosSelection" class="selectable" >
            <?php 
                $liFormat = '<li id="Scenarios_{DataName}" class="ui-widget-content ui-corner-all " ><h4>{DataName}</h4><p>{Description}</p> </li>'; 
                echo DatabaseClimate::GetScenarioDescriptions()->asFormattedString($liFormat); 
            ?>
            </ul>
        </div>

        <div id="tabs-4">
            <div class="SelectionToolBar ui-widget-header ui-corner-all">
                <button id="SelectAllTimes"  >select all</button>
                <button id="SelectNoneTimes" >deselect all</button>
            </div>       
            <ul id="TimesSelection" class="selectable" >
            <?php 
                $liFormat = '<li id="Times_{DataName}" class="ui-widget-content ui-corner-all " ><h4>{DataName}</h4><p>{Description}</p> </li>'; 
                echo DatabaseClimate::GetTimesDescriptions()->asFormattedString($liFormat); 
            ?>
            </ul>    
        </div>

        <div id="tabs-5">
            <div class="SelectionToolBar ui-widget-header ui-corner-all">
                <button id="SelectAllBioclims"  >select all</button>
                <button id="SelectNoneBioclims" >deselect all</button>
            </div>       
            <ul id="BioclimsSelection" class="selectable" >
            <?php 
                $liFormat = '<li id="Bioclims_{DataName}" class="ui-widget-content ui-corner-all " ><h4>{DataName}</h4><p>{Description}</p> </li>'; 
                echo DatabaseClimate::GetBioclimDescriptions()->asFormattedString($liFormat); 
            ?>
            </ul>
        </div>

        <div id="tabs-6">
            <button id="CreateProcess">Process Climates</button>
            <div id="CreateProcessData" class="ui-corner-all">
                
            </div>       
        </div>


    </div>    


</div>

    
<?php    include_once 'ToolsFooter.php'; ?>    


    
    

</body>
</html>
