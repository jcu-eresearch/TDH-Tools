<?php

/**
 *
 * Connect , disconnect and data flow to and from Species database
 *  
 * 
 * 
 */
class SpeciesData extends Object {

    
    public static $OCCURANCE_MINIMUM_LINES = 12;
    
    
    /**
     *
     * For any species make sure we have occurance data
     * 
     * @param type $pattern
     * @return type 
     */
    public static function speciesList($pattern = "")  //
    {
        $pattern = util::CleanString($pattern);
        $pattern = "%{$pattern}%";

        
        $sql = "select 
                    species_id
                   ,scientific_name
                   ,common_name 
                   ,(common_name || ' (' || scientific_name  || ')' ) as full_name
                from 
                    modelled_climates 
                where 
                    ( scientific_name like '{$pattern}' or common_name like '{$pattern}' ) 
                group by 
                    species_id
                   ,scientific_name
                   ,common_name
                order by 
                    scientific_name
                   ,common_name
                 ;
                ";
        
        $result = DBO::Query($sql,'species_id');
        
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        
        return $result;
    }

    public static function speciesListScientificName($pattern = "")  //
    {

        $pattern = util::CleanString($pattern);
        
        $sql = "select 
                    so.species_id
                   ,st.species
                   ,st.common_name
                from species_occur so, 
                     species_taxa_tree st 
                where so.species_id = st.species_id 
                  and so.count > 1 
                  and so.species_id = {$species_id}
                  and species LIKE '%{$pattern}%'
                order by st.species;
                limit 1";
        

        $result = DBO::Query($sql,'species_id');
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        
        return $result;
    }
    
    
    /**
     *
     * @param type $speciesID
     * @return null|string  
     */
    public static function SpeciesQuickInformation($species_id) 
    {
        $sdf = configuration::SourceDataFolder();                
        $result = exec("cat {$sdf}species_to_id.txt | grep ',{$species_id}' | head -n1 | cut -d',' -f1");
        return $result;
    }
    
    public static function SpeciesCommonNames($species_id,$index = null) 
    {
        
        $sdf = configuration::SourceDataFolder();
        
        $commonNames = array();
        
        $cmd = "cat {$sdf}species_to_id.txt | grep ',{$species_id}'";
        exec($cmd,$commonNames);
        $commonNames = util::leftStrArray(array_util::Replace($commonNames, ",{$species_id}", ""), " (", false);
        
        
        if (count($commonNames) == 0) 
        {
            if (is_null($index)) 
                return array();   // return array of common names (delim on slash /) - no index requested
            else
                return "";
        }
        
        if (count($commonNames) == 1) 
        {
            if (is_null($index)) 
                return $commonNames;   // return array of common names (delim on slash /) - no index requested
            else
                return $commonNames[0];            
        }
        
        if (is_null($index)) 
            return $commonNames;   // return array of common names (delim on slash /) - no index requested

        
        // return the indexed common name they want
        $indexed_result = array_util::Value($result, $index);
        
        if (is_null($indexed_result)) return $commonNames[0]; // return first common name if this index does not exist
        
        
        return  $indexed_result;
    }

    
    public static function SpeciesCommonNameSimple($species_id)
    {
        return self::SpeciesCommonNames($species_id,0);
    }
    
    
    
    public static function SpeciesName($species_id) 
    {
        
        $sdf = configuration::SourceDataFolder();
        
        $cmd = "cat {$sdf}species_to_id.txt | grep ',{$species_id}' | head -n1 | cut -d'(' -f2 | cut -d')' -f1";
        $result = exec($cmd);
        
        return $result;

    }
    
    
    /**
     * Get file id from datbaase for this combination
     * 
     * 
     * @param type $species
     * @param type $scenario
     * @param type $model
     * @param type $time
     * @return string|null  file_id for that file  pr Em,pty string says that now file for these parameters
     */
    public static function GetModelledData($speciesID,$scenario, $model, $time,$filetype = null, $desc = null)
    {
        
        $filetypeAnd = (is_null($filetype)) ? "" : "and mc.filetype   = ".util::dbq($filetype,true);
        $descAnd     = (is_null($desc))     ? "" : "and f.description = ".util::dbq($desc,true);
        
        
        $sql = "select mc.id as id
                      ,mc.species_id
                      ,mc.scientific_name
                      ,mc.common_name
                      ,mc.models_id
                      , m.dataname as model_name
                      ,mc.scenarios_id
                      , s.dataname as scenario_name
                      ,mc.times_id
                      , t.dataname as time_name
                      ,mc.filetype
                      , f.description
                      ,mc.file_unique_id as file_id
                from   modelled_climates mc
                      ,models m
                      ,scenarios s
                      ,times t
                      ,files f
                where mc.species_id = {$speciesID}
                  and mc.models_id      = m.id
                  and mc.scenarios_id   = s.id
                  and mc.times_id       = t.id
                  and mc.file_unique_id = f.file_unique_id
                  and m.dataname = ".util::dbq($model,true)."
                  and s.dataname = ".util::dbq($scenario,true)."
                  and t.dataname = ".util::dbq($time,true)." {$filetypeAnd} {$descAnd}
                  limit 1
                ;";
        
        
        $result = DBO::QueryFirstValue($sql,'file_id');
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);        
        
        return $result;
        
    }

    
    
    
    /**
     *
     * get FileId for all datra files for this species 90
     * 
     * @param type $speciesID
     * @param type $filetype Limit to this filetype only
     * @return type 
     */
    public static function GetAllModelledData($speciesID,$filetype = "QUICK_LOOK")
    {
        $key = 'combination';
        
        if (is_null($speciesID)) return null;
        
        $speciesID = trim($speciesID);
        if ($speciesID == "") return null;
        
        
        $filetypeAnd = (is_null($filetype)) ? "" : "and mc.filetype   = ".util::dbq($filetype,true);
        
        
        $sql = "select mc.id as id
                      ,mc.species_id
                      ,mc.scientific_name
                      ,mc.common_name
                      ,mc.models_id
                      , m.dataname as model_name
                      ,mc.scenarios_id
                      , s.dataname as scenario_name
                      ,mc.times_id
                      , t.dataname as time_name
                      ,mc.filetype
                      , f.description
                      ,mc.file_unique_id as file_unique_id
                      ,(s.dataname || '_' || m.dataname || '_' || t.dataname) as combination
                      ,(mc.common_name  || ' (' || mc.scientific_name || ')' ) as full_name
                from   modelled_climates mc
                      ,models m
                      ,scenarios s
                      ,times t
                      ,files f
                where mc.species_id = {$speciesID}
                  and mc.models_id      = m.id
                  and mc.scenarios_id   = s.id
                  and mc.times_id       = t.id
                  and mc.file_unique_id = f.file_unique_id
                  {$filetypeAnd}
                  {$extraWhere}
                  order by 
                      m.dataname
                     ,s.dataname
                     ,t.dataname
                ;";
        
        
        $result = DBO::Query($sql,$key);
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        
        return $result;
        
    }
    
    /**
     * For a model (ALL - Medians)
     * - a matrix of file_unique_di
     * 
     *               time 1  time 2  time 3 ... 
     *   Scenario 1    .      .        .
     *   Scenario 2    .      .        .
     *   Scenario 3    .      .        .
     * 
     * @param type $speciesID
     * @param type $filetype
     * @param type $model 
     */
    public static function GetModelledDataForModel($speciesID,$filetype = null,$model = "ALL")
    {
        if (is_null($speciesID))
            return new ErrorMessage(__METHOD__, __LINE__, "speciesID passed as null");
        
        $speciesID = trim($speciesID);
        
        if ($speciesID == "")
            return new ErrorMessage(__METHOD__, __LINE__, "speciesID passed as EMPTY");

        
        $sql = "select mc.id as id
                      ,mc.species_id
                      ,mc.scientific_name
                      ,mc.common_name
                      ,mc.models_id
                      , m.dataname as model_name
                      ,mc.scenarios_id
                      , s.dataname as scenario_name
                      ,mc.times_id
                      , t.dataname as time_name
                      ,mc.filetype
                      , f.description
                      ,mc.file_unique_id as file_unique_id
                      ,(s.dataname || '_' || m.dataname || '_' || t.dataname) as combination
                      ,(mc.common_name  || ' (' || mc.scientific_name || ')' ) as full_name
                from   modelled_climates mc
                      ,models m
                      ,scenarios s
                      ,times t
                      ,files f
                where mc.species_id = {$speciesID}
                  and mc.models_id      = m.id
                  and mc.scenarios_id   = s.id
                  and mc.times_id       = t.id
                  and mc.file_unique_id = f.file_unique_id
                  and mc.filetype = ".util::dbq($filetype,true)."
                  and m.dataname  = ".util::dbq($model,true)."
                  order by 
                      s.dataname
                     ,t.dataname
                ;";
        
        
        $result = DBO::Query($sql,'file_unique_id');
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);


//        // initialise 
//        $scenarios = array();
//        foreach (matrix::ColumnUnique($result, 'scenario_name') as $scenario => $scenario_file_count) 
//            $scenarios[$scenario] = array();
       
        $scenarios = array();
        foreach ($result as $file_unique_id => $row) 
        {
            
            $scenarios[$row['scenario_name']][$row['time_name']] = $file_unique_id;            
        }
        
        // $scenarios; is now a jagged array of 
        //  time
        //        scenario  - file_unique_id
        //        scenario  - file_unique_id
        //        scenario  - file_unique_id
        //  time 
        //        scenario  - file_unique_id
        //        scenario  - file_unique_id
        //        scenario  - file_unique_id
        
        
        
        return $scenarios;
        
    }
    
    public static function GetModelledDataForModelStandardised($speciesID,$filetype = null,$model = "ALL")
    {
        
        if (is_null($speciesID))
            return new ErrorMessage(__METHOD__, __LINE__, "speciesID passed as null");
        
        $speciesID = trim($speciesID);
        
        if ($speciesID == "")
            return new ErrorMessage(__METHOD__, __LINE__, "speciesID passed as EMPTY");
        
        
        $data = self::GetModelledDataForModel($speciesID,$filetype,$model);
        if ($data instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $data);
        
        $result = array();
        foreach (DatabaseClimate::GetScenarios() as $scenario) 
        {
            $result[$scenario] = array();
            
            foreach (DatabaseClimate::GetTimes() as $time) 
            {
                $result[$scenario][$time]  = null;
                
                
                
                if (array_key_exists($scenario, $data))
                    if (array_key_exists($time, $data[$scenario]))
                        $result[$scenario][$time] = $data[$scenario][$time];
                
            }
                
        }
        
        return $result;
        
    }
    
    /**
     * get current suoitabaility for this pecies   - TODO:: this is Dodgy we need to get using Scenario Name = CURRENT
     * 
     * @param type $speciesID
     * @return \ErrorMessage 
     */
    public static function SpeciesCurrentQuickLook($speciesID) 
    {
        $q = "select file_unique_id from modelled_climates where species_id = {$speciesID} and models_id = 20 and times_id = 10 order by species_id,filetype,scenarios_id,times_id limit 1;";
        
        $result = DBO::QueryFirst($q,'file_unique_id');
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "Failed to get SpeciesCurrentQuickLook  speciesID = $speciesID  \n", true, $result);

        $file_unique_id = array_util::Value($result,'file_unique_id');
        
        return $file_unique_id;
    }
    
    
    /**
     * 
     * 
     * @param type $speciesID  - Scientific Name here as then across systems and database rebuilds it wont change
     * @return array  [index] => (longitude,latitude)
     */
    public static function SpeciesOccurance($speciesID) 
    {
        $q = "SELECT longitude, latitude FROM species_occurrences where species_id = {$speciesID}";
        
        $result = DBO::Query($q);
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "Failed to get Species Occurence data from data base using \n q = $q \n speciesID = $speciesID  \n", true, $result);        
        
        
        return $result;
    }


    
    public function SpeciesOccuranceToFile($speciesID, $occurFilename) 
    {
        
        $result = self::SpeciesOccurance($speciesID);
        if ($result instanceof ErrorMessage) 
            return ErrorMessage::Stacked(__METHOD__, __LINE__, "Failed to get Species Occurence Data speciesID = $speciesID  \n", true, $result);
        
        
        
        // this will create occurance file where the "name" of the species will be the Species ID from database
        // this seems to have to match the Lambdas filename
        
        $file = '"SPPCODE","LATDEC","LONGDEC"'."\n";  // headers specific to Maxent JAR
        foreach ($result as $row) 
            $file .= $speciesID.",".$row['latitude'].",".$row['longitude']."\n";
        
        file_put_contents($occurFilename, $file);

        
        if (!file_exists($occurFilename)) 
            return new ErrorMessage(__METHOD__, __LINE__,"Failed to wite Species Occurence Data File speciesID = $speciesID  occurFilename = $occurFilename \n");
        
        
        // check the validity of the file - a "good" number of lines
        $lineCount = file::lineCount($occurFilename);
        

        ErrorMessage::Marker("occurFilename = $occurFilename  lineCount = $lineCount\n");
        
        
        if ($lineCount < self::$OCCURANCE_MINIMUM_LINES) 
            return false;
        
        
        unset($result);
        unset($file);
        
        return true;
        
    }
    
    public static function Kingdom() 
    {
        $result = matrix::Column(DBO::Unique('species_taxa_tree', 'kingdom'),'kingdom');
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        return $result;
    }

    public static function Phylum() 
    {
        $result = matrix::Column(DBO::Unique('species_taxa_tree', 'phylum'),'phylum');
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        return $result;
    }
    
    public static function Clazz() 
    {
        $result = matrix::Column(DBO::Unique('species_taxa_tree', 'clazz'),'clazz');
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);        
        return $result;
    }
    

    public static function Orderz() 
    {
        $result = matrix::Column(DBO::Unique('species_taxa_tree', 'orderz'),'orderz');
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        return $result;
    }
    
    public static function Family() 
    {
        $result = matrix::Column(DBO::Unique('species_taxa_tree', 'family'),'family');
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        return $result;
    }
    

    public static function Genus() 
    {
        $result = matrix::Column(DBO::Unique('species_taxa_tree', 'genus'),'genus');
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        return $result;
    }

    public static function Species() 
    {
        $result = matrix::Column(DBO::Unique('species_taxa_tree', 'species'),'species');
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        return $result;
    }

    
    public static function SpeciesForKingdom($kingdom) 
    {
        $result  = DBO::Unique('species_taxa_tree', 'species', "kingdom = E'{$kingdom}' ");
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        return $result;
        
    }

    public static function SpeciesForClazz($clazz) 
    {
        $result  = DBO::Unique('species_taxa_tree', 'species', "clazz = E'{$clazz}' ");
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);        
        return $result;
    }
    
    public static function SpeciesForFamily($family) 
    {
        $result  = DBO::Unique('species_taxa_tree', 'species', "family = E'{$family}' ");
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        return $result;
        
    }
    
    public static function SpeciesForGenus($genus) 
    {
        $result  = DBO::Unique('species_taxa_tree', 'species', "genus = E'{$genus}' ");
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        return $result;
        
    }

    public static function SpeciesIDsForClazz($clazz) 
    {
        $result  = DBO::Unique('species_taxa_tree', 'species_id', "clazz = E'{$clazz}' ");
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        return $result;
        
    }

    public static function SpeciesIDsForFamily($family) 
    {
        $result  = DBO::Unique('species_taxa_tree', 'species_id', "family = E'{$family}' ");
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        return $result;
        
    }
    
    
    public static function SpeciesIDsForGenus($genus) 
    {
        
        $result  = DBO::Unique('species_taxa_tree', 'species_id', "genus = E'{$genus}' ");
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        return $result;
        
    }
    
    
    
    public static function GenusForKingdom($kingdom) 
    {
        $result  = DBO::Unique('species_taxa_tree', 'genus', "kingdom = E'{$kingdom}'");
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        return $result;
        
    }

    public static function GenusForFamily($family) 
    {
        $result  = DBO::Unique('species_taxa_tree', 'genus', "family = E'{$family}'");
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        return $result;
        
    }
    
    
    public static function FamilyForKingdom($kingdom) 
    {
        $result  = DBO::Unique('species_taxa_tree', 'family', "kingdom = E'{$kingdom}'");
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        return $result;
        
    }
    
    
    public static function SpeciesWithOccuranceData($count = 0) 
    {
        $sql = "select 
                    so.species_id
                   ,so.count as species_count
                   ,st.species
                   ,st.common_name
                   ,(st.common_name || ' (' || st.species  || ')' ) as full_name
                from species_occur so, 
                     species_taxa_tree st 
                where so.species_id = st.species_id 
                  and so.count > {$count}
                order by st.genus,st.species
                ";

        $result  = DBO::Query($sql,'species_id');
        
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        
        return $result;
        
    }

    public static function id4ScientificName($scientificName,$use_like = false) 
    {
        
        if ($use_like)
        {
            $sql = "select * from species_taxa_tree where species like E'{$scientificName}';";
        }
        else
        {
            $sql = "select * from species_taxa_tree where species = E'{$scientificName}';";    
        }
        
        $result = DBO::Query($sql,'species_id');
        
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        
        return $result;
    }
    
    public static function TaxaForClazz($clazz) 
    {
        $result =  DBO::Query("select * from species_taxa_tree where clazz = E'{$clazz}'", 'species_id');
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        return $result;
    }
    
    public static function TaxaForClazzWithOccurances($clazz,$count = 10) 
    {
        
        $sql = "select 
                     o.species_id
                    ,o.count as species_count
                    ,st.id as species_taxa_id
                    ,st.kingdom
                    ,st.phylum
                    ,st.clazz
                    ,st.orderz
                    ,st.family
                    ,st.genus
                    ,st.species
                    ,st.common_name
                from 
                     species_occur o
                    ,species_taxa_tree st 
                where o.species_id = st.species_id
                  and o.count >= {$count}
                  and st.clazz = E'{$clazz}' 
                ";
        
        $result = DBO::Query($sql, 'species_id');
                  
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);                  
        
        return $result;
    }

    public static function TaxaForFamilyWithOccurances($family,$count =10) 
    {
        
        $sql = "select 
                     o.species_id
                    ,o.count as species_count
                    ,st.id as species_taxa_id
                    ,st.kingdom
                    ,st.phylum
                    ,st.clazz
                    ,st.orderz
                    ,st.family
                    ,st.genus
                    ,st.species
                    ,st.common_name
                from 
                     species_occur o
                    ,species_taxa_tree st 
                where o.species_id = st.species_id
                  and o.count >= {$count}
                  and st.family = E'{$family}' 
                ";
        
        $result = DBO::Query($sql, 'species_id');
                  
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);                  
        
        return $result;
    }
    
    
    
    public static function TaxaForGenusWithOccurances($genus,$count =10) 
    {
        
        $sql = "select 
                     o.species_id
                    ,o.count as species_count
                    ,st.id as species_taxa_id
                    ,st.kingdom
                    ,st.phylum
                    ,st.clazz
                    ,st.orderz
                    ,st.family
                    ,st.genus
                    ,st.species
                    ,st.common_name
                from 
                     species_occur o
                    ,species_taxa_tree st 
                where o.species_id = st.species_id
                  and o.count >= {$count}
                  and st.genus = E'{$genus}' 
                ";
        
        $result = DBO::Query($sql, 'species_id');
                  
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);                  
        
        return $result;
    }
    
    
    public static function TaxaWithOccurances($count = 10) 
    {
        
        $sql = "select 
                     o.species_id
                    ,o.count as species_count
                    ,st.id as species_taxa_id
                    ,st.kingdom
                    ,st.kingdom_guid
                    ,st.phylum
                    ,st.phylum_guid
                    ,st.clazz
                    ,st.clazz_guid
                    ,st.orderz
                    ,st.orderz_guid
                    ,st.family
                    ,st.family_guid
                    ,st.genus
                    ,st.genus_guid
                    ,st.species
                    ,st.species_guid
                from 
                     species_occur o
                    ,species_taxa_tree st 
                where o.species_id = st.species_id
                  and o.species_id = sp.id
                  and o.count >= {$count}
                  and st.clazz = E'{$clazz}' 
                ";
        
        $result = DBO::Query($sql, 'species_id');
                  
        
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);                  
        
        return $result;
    }
    
    public static function TaxaWithOccurancesFiltered($count = 10,$fieldname = null,$fieldOperator = null,$fieldValue = null) 
    {
        
        $extraWhere = "";
        if (!is_null($fieldname) && !is_null($fieldOperator) && !is_null($fieldValue))
        {
            $fieldname = "st.{$fieldname}";
            
            if (!is_numeric($fieldValue)) $fieldValue = util::dbq($fieldValue,true);
            $extraWhere = "and {$fieldname} {$fieldOperator} {$fieldValue}";
        }
        
        
        $sql = "select 
                     o.species_id
                    ,o.count as species_count
                    ,st.id as species_taxa_id
                    ,st.kingdom
                    ,st.kingdom_guid
                    ,st.phylum
                    ,st.phylum_guid
                    ,st.clazz
                    ,st.clazz_guid
                    ,st.orderz
                    ,st.orderz_guid
                    ,st.family
                    ,st.family_guid
                    ,st.genus
                    ,st.genus_guid
                    ,st.species
                    ,st.species_guid
                from 
                     species_occur o
                    ,species_taxa_tree st 
                where o.species_id = st.species_id
                  and o.count >= {$count}
                  {$extraWhere}
                ";
                  
        $result = DBO::Query($sql, 'species_id');
                  
        
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);                  
        
        return $result;
    }
    

    public static function TaxaFiltered($fieldname = null,$fieldOperator = null,$fieldValue = null) 
    {
        
        $extraWhere = "";
        if (!is_null($fieldname) && !is_null($fieldOperator) && !is_null($fieldValue))
        {
            if (!is_numeric($fieldValue)) $fieldValue = util::dbq($fieldValue);
            $extraWhere = "where {$fieldname} {$fieldOperator} {$fieldValue}";
        }
        
        
        $sql = "select * from  species_taxa_tree {$extraWhere}";
                  
        $result = DBO::Query($sql, 'species_id');
        
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);                  
        
        return $result;
    }
    
    
    public static function TaxaWithOccurancesFilteredFor($clazz = "%",$family = "%", $genus = "%",$minimum_occurance = 10) 
    {
        if (is_null($clazz))  $clazz  = "%";
        if (is_null($family)) $family = "%";
        if (is_null($genus))  $genus  = "%";

        if (is_null($minimum_occurance))  $minimum_occurance  = 10;
        if (!is_numeric($minimum_occurance)) $minimum_occurance  = 10;
        
        $clazz  = trim($clazz);
        $family = trim($family);
        $genus  = trim($genus);
        
        if ($clazz  == "") $clazz = "%";
        if ($family == "") $family = "%";
        if ($genus  == "") $genus = "%";
        
        
        $sql = "select  
                     st.clazz
                    ,st.family
                    ,st.genus
                    ,st.species
                    , o.species_id
                    , o.count as species_count
                from 
                     species_occur o
                    ,species_taxa_tree st 
                where o.species_id = st.species_id
                  and o.count >= {$minimum_occurance}
                  and st.clazz like '{$clazz}'
                  and st.family like '{$family}'
                  and st.genus like '{$genus}'
                order by
                     st.clazz
                    ,st.family
                    ,st.genus
                    ,st.species
                ";
                  
        $result = DBO::Query($sql, 'species_id');
                  
        
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);                  
        
        return $result;
    }

    
    
    public static function ModelledTaxaForClazz($clazz = null) 
    {
        
        if (is_null($clazz))  return new ErrorMessage(__METHOD__, __LINE__, "clazz passed as null"); 
        $clazz = trim($clazz);
        if ($clazz == "")  return new ErrorMessage(__METHOD__, __LINE__, "clazz passed as empty"); 
        
        
        $species_list = self::SpeciesIDsForClazz($clazz);
        if ($species_list instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $species_list); 
        
        
        $result = array();
        foreach ($species_list as $species_id) 
        {
            $result[$species_id] = self::GetAllModelledData($species_id);
        }
        
        
        return $result;
    }
    
    public static function ModelledTaxaForFamily($family = null) 
    {
        
        if (is_null($family))  return new ErrorMessage(__METHOD__, __LINE__, "family passed as null"); 
        $family = trim($family);
        if ($family == "")  return new ErrorMessage(__METHOD__, __LINE__, "family passed as empty"); 
        
        
        $species_list = self::SpeciesIDsForFamily($family);
        if ($species_list instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $species_list); 
        
        
        $result = array();
        foreach ($species_list as $species_id) 
        {
            $result[$species_id] = self::GetAllModelledData($species_id);
        }
        
        
        return $result;
    }
    
    public static function ModelledTaxaForGenus($genus = null) 
    {
        
        if (is_null($genus))  return new ErrorMessage(__METHOD__, __LINE__, "genus passed as null"); 
        $genus = trim($genus);
        if ($genus == "")  return new ErrorMessage(__METHOD__, __LINE__, "genus passed as empty"); 
        
        
        $species_list = self::SpeciesIDsForGenus($genus);
        if ($species_list instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $species_list); 
        
        
        $result = array();
        foreach ($species_list as $species_id_row) 
        {
            $species_id = util::first_element($species_id_row);
            
            $data = self::GetAllModelledData($species_id);
            
            if ($data instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $data); 

            if (is_null($data)) continue;
            
            $result[$species_id] = $data;
        }
        
        return $result;
    }
    

    /**
     * Get List of species id's where we have modelled data for that genus
     *
     * 
     * @param type $genus
     * @return \ErrorMessage 
     */
    public static function SpeciesModelledForGenus($genus) 
    {
        
        if (is_null($genus))  return new ErrorMessage(__METHOD__, __LINE__, "genus passed as null"); 
        $genus = trim($genus);
        if ($genus == "")  return new ErrorMessage(__METHOD__, __LINE__, "genus passed as empty"); 
        
        
        $sql = " select 
                     mc.species_id
                    ,mc.scientific_name as species
                    ,mc.common_name
                    ,g.name as genus
                 from modelled_climates mc  
                 left join taxa_lookup tl on (mc.species_id = tl.species_id ) 
                 left join genus g on (tl.genus_id = g.id)  
                 where g.name = E'{$genus}'
                 group by 
                     mc.species_id
                    ,mc.scientific_name
                    ,mc.common_name
                    ,g.name  
                 order by 
                     g.name
                    ,mc.scientific_name;        
               ";
        
        
        $result = DBO::Query($sql, 'species_id');
        if ($result instanceof ErrorMessage) 
            return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        
        
        return $result;
    }
    
    
    public static function ModelledForAllGenus($minimum_modelled_count = 1) 
    {
        
        $sql = " select 
                     mc.species_id
                    ,mc.scientific_name as species
                    ,mc.common_name
                    ,g.name as genus
                    ,count(*) as modelled_count
                 from modelled_climates mc  
                 left join taxa_lookup tl on (mc.species_id = tl.species_id ) 
                 left join genus g on (tl.genus_id = g.id)  
                 group by 
                     mc.species_id
                    ,mc.scientific_name
                    ,mc.common_name
                    ,g.name  
                 having count(*) >= {$minimum_modelled_count}
                 order by 
                     g.name
                    ,mc.scientific_name;        
               ";
        
        $result = DBO::Query($sql, 'species_id');
        if ($result instanceof ErrorMessage) 
            return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
        
        
        return $result;
        
        
    }
    

    
    
    
    public static function GenerateMedianFor($species_id, $user_scenario = "%",$user_model = "%",$user_time = "%",$overwrite_output = false,$output_filename = null)  
    {
        

        $folder = self::species_data_folder($species_id);        
        
        if (is_null($user_model)) $user_model = '%';
        
        
        if (is_null($output_filename)) 
        {
            $scenario_bit = $user_scenario;
            $model_bit    = $user_model;
            $time_bit     = $user_time;
            
            if (util::contains($scenario_bit, "%")) $scenario_bit = ($scenario_bit == "%") ? "ALL" : "MULTIPLE";

            if (util::contains($model_bit, "%")) $model_bit = ($model_bit == "%") ? "ALL" : "MULTIPLE";
            
            if (util::contains($time_bit, "%")) $time_bit = ($time_bit == "%") ? "ALL" : "MULTIPLE";
            
            $output_filename =  "{$folder}{$scenario_bit}_{$model_bit}_{$time_bit}.asc";
            
        }
        
        if ($overwrite_output) file::Delete($output_filename);

        
        if (file_exists($output_filename)) return $output_filename;
        
        
        $scenarios = DatabaseClimate::GetScenariosNamed($user_scenario);
        $models    = DatabaseClimate::GetModelsNamed($user_model);
        $times     = DatabaseClimate::GetTimesNamed($user_time);
        

        unset($models['CURRENT']);
        unset($models['ALL']);
        
        unset($scenarios['CURRENT']);
        unset($scenarios['ALL']);
        
        
        // make this combnation
        
        $files = array();
        foreach ($scenarios as $scenario) 
            foreach ($models as $model) 
                foreach ($times as $time) 
                    $files["{$scenario}_{$model}_{$time}"] = "{$folder}{$scenario}_{$model}_{$time}.asc";

                    
        ErrorMessage::Marker("Looking to Create Median from \n".print_r($files,true));
                    
        $canCreateMedian = true;
                    
        // check files exist
        foreach ($files as $filename) 
        {
            
            if (!file_exists($filename)) 
            {
                $canCreateMedian = false;
                ErrorMessage::Marker("Looking to Create Median from can't find file {$filename}");
            }
        }
            
                
            
        if (!$canCreateMedian) return null; // not error just can't do it now

        
        $median_result = spatial_util::median($files,$output_filename);
        
        if ($median_result instanceof ErrorMessage) 
            return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $median_result);

        
        if (!file_exists($output_filename))
            return new ErrorMessage(__METHOD__, __LINE__, "After Median Output file does not exist [{$output_filename}]");
        
            
        return $output_filename;
        
        
    }
    
    
    
    public static function hasCompleteDataset($species_id,$combinations = null)
    {
        
        // get list of Models, Scenarios , Times 
        
        // check that all ASC files exist for this species.

        if (is_null($combinations))
            $combinations  = DatabaseClimate::CombinationsSingleLevel();        
        
        $folder = self::species_data_folder($species_id);
        
        foreach ($combinations as $combination => $value) 
        {
            $filename = "{$folder}{$combination}.asc";
            if (!file_exists($filename)) return false;
        }
        
        return true;
        
    }

    public static function species_data_folder($species_id)
    {
        return SpeciesFiles::species_data_folder($species_id);
    }

    
    
    public static function ScenarioTimeMedian($species_id,$scenario,$time,$overwrite_output = false,$output_filename = null)
    {
        
        $result = self::GenerateMedianFor(
                     $species_id
                    ,$scenario
                    ,null
                    ,$time
                    ,$overwrite_output
                    ,$output_filename
                    ) ;
                
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);                          
        
        return $result;
        
        
    }
    

    
    public static function ScenarioTimeMediansForSpecies($species_id,$scenario = null,$time = null,$LoadASCII = true,$LoadQuickLooks = true)    {
        
        if (is_null($LoadASCII)) $LoadASCII = true;
        if (is_null($LoadQuickLooks)) $LoadQuickLooks = true;

        
        if (is_null($scenario))
        {
            $scenarios = DatabaseClimate::GetScenarios();        
            if($scenarios instanceof ErrorMessage) return ErrorMessage::Stacked (__METHOD__, __LINE__, "", true, $scenarios);            
        }
        else
        {
            $scenarios = array();
            $scenarios[] = $scenario;
        }
        
        if (is_null($time))
        {
            $times     = DatabaseClimate::GetTimes();
            if($times instanceof ErrorMessage) return ErrorMessage::Stacked (__METHOD__, __LINE__, "", true, $times);            
        }
        else
        {
            $times = array();
            $times[] = $time;
        }

        
        // loop thru Scenario and Time
        foreach ($scenarios as $scenario) 
            foreach ($times as $time) 
            {
                ErrorMessage::Marker("Create Median for $scenario / $time  ");
                
                
                $result = self::ScenarioTimeMedian($species_id,$scenario,$time,false);
                
                if ($result instanceof ErrorMessage) 
                    return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);                          
                
                
                if (!is_null($result)) 
                {
                    ErrorMessage::Marker("Created ".print_r($result,true)." \n");
                    
                    self::InsertMedianData($species_id,$scenario,$time,$LoadASCII,$LoadQuickLooks);
                    
                }
                else
                    ErrorMessage::Marker("Data Files incomplete \n");
                
            }
            
                
            
    }    
    
    
    public static function InsertMedianData($species_id,$scenario,$time,$LoadASCII = true,$LoadQuickLooks = true)
    {

        if (is_null($LoadASCII)) $LoadASCII = true;
        if (is_null($LoadQuickLooks)) $LoadQuickLooks = true;

        
        $pathname =  SpeciesData::species_data_folder($species_id)
                    ."{$scenario}_ALL_{$time}.asc"
                    ;
        
        
        ErrorMessage::Marker("Insert Median data for species {$species_id} into DB from [{$pathname}]\n");
        
        if ($LoadASCII)
        {
            $file_id = DatabaseMaxent::InsertSingleMaxentProjectedFile(
                        $species_id
                        ,$pathname
                        ,'ASCII_GRID'
                        ,'Climate Model Median for species suitability:'.basename($pathname)
                        );

            if ($file_id instanceof ErrorMessage) 
            {
                ErrorMessage::Stacked (__FILE__,__LINE__,"Trying to insert ASCII file [{$pathname}]  species_id = $species_id ", true,$file_id);
                continue;
            }
            ErrorMessage::Marker("Inserted ASCII_GRID file_unique_id = [{$file_id}]\n");
            
        }
        
        
        
        ErrorMessage::Marker("Create Quick Look for Median data for species {$species_id} \n");

        $qlfn = SpeciesMaxentQuickLook::CreateImage($species_id,$pathname);
        if ($qlfn instanceof ErrorMessage) 
        {
            ErrorMessage::Stacked (__FILE__,__LINE__,"Failed to Create Quick Look from ASCII Grid File {$pathname}  \nspecies_id = $species_id\n", true,$qlfn);
            continue;
        }
        
        
        if ($LoadQuickLooks)
        {
            ErrorMessage::Marker("Insert Median QuickLook data for species {$species_id} into DB\n");
            $file_id = DatabaseMaxent::InsertSingleMaxentProjectedFile(
                        $species_id
                        ,$qlfn
                        ,'QUICK_LOOK'
                        ,'Climate Model Median for species suitability:'.basename($qlfn)
                        );

            ErrorMessage::Marker("Inserted quick look file_unique_id = [{$file_id}]\n");


            if($file_id instanceof ErrorMessage)
            {
                ErrorMessage::Stacked (__FILE__,__LINE__,"Failed to insert Quick Look uinto DB  \nspecies_id = $species_id\n", true,$qlfn);
                continue;
            }
            
        }

        
        
    }
    
    
    
    public static function LoadData($species_id,$file_pattern = "*")    
    {
        
        $result =  DatabaseMaxent::InsertAllMaxentResults($species_id,$file_pattern);
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);                                  
        
        return $result;
        
    }
    

    /**
     * Find ACSII grids for this species and create quicklooks
     * 
     * @param type $species_id
     * @param type $pattern     - Limit the ASCII GRID files to this pattern
     */
    public static function CreateQuickLook($species_id,$pattern)
    {
        if (is_null($pattern)) $pattern = '*';
        
        $folder = self::species_data_folder($species_id);
        
        $files = file::LS($folder."*{$pattern}*.asc", null , true);
        

        $count = 0;
        foreach ($files as $filename => $pathname) 
        {
            ErrorMessage::Marker("Create Quick Look for [$species_id] .. [$filename] \n");
            
            $qlfn = SpeciesMaxentQuickLook::CreateImage($species_id, $pathname); // build quick look from asc 
            
            ErrorMessage::Marker("Quicklook created for [$species_id] .. [".basename($qlfn)."]\n");
            
            $count++;
            
        }
        
    }
    
    
    public static function RemoveDataforSpecies($species_id)    
    {
        
        $result =  DatabaseMaxent::RemoveAllResultsforSpecies($species_id);
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);                                  
        
        return $result;
        
    }
    

    public static function ListDataforSpecies($species_id)    
    {
        
        $result = array();
        
        ErrorMessage::Marker("Getting TaxaFiltered");
        $result['TaxaFiltered'] = self::TaxaFiltered('species_id', '=', $species_id);
        
        ErrorMessage::Marker("Getting Get All Modelled Data");
        $result['GetAllModelledData'] = self::GetAllModelledData($species_id);
        
        ErrorMessage::Marker("Getting Modelled Species Files");
        $result['ModelledSpeciesFiles'] = DBO::Query("select * from modelled_species_files where species_id = {$species_id}", 'file_unique_id');
        
        ErrorMessage::Marker("Getting Modelled Species Files File Unique ID info");
        if (is_array($result['ModelledSpeciesFiles']))
        {
            foreach ($result['ModelledSpeciesFiles']  as $row) 
            {
                $result['ModelledSpeciesFiles'][$row['file_unique_id']] = DatabaseFile::FileInfo($row['file_unique_id']);
            }
        }
        
        ErrorMessage::Marker("Getting GetMaxentResultsCSV count");
        $result['GetMaxentResultsCSV'] = "Maxent Result CSV row count = ".count(DatabaseMaxent::GetMaxentResultsCSV($species_id));
        
        
        return $result;
        
    }
    
    
    
    public static function CurrentInfo($species_id) 
    {
        
        $result = array();
        
        $result['species_id'] = $species_id;
        
        ErrorMessage::Marker("Getting species_taxa_tree count");
        $count_taxa = DBO::Count('species_taxa_tree', "species_id = {$species_id}");
        $result['species_taxa_tree count'] = $count_taxa;
        if ($count_taxa instanceof ErrorMessage)  return $result;

        
        ErrorMessage::Marker("Getting species_taxa_tree data");
        $taxa_tree_data = DBO::Query("select * from species_taxa_tree where species_id = {$species_id}");
        $result['taxa tree data'] = $taxa_tree_data;
        if ($taxa_tree_data instanceof ErrorMessage)  return $result;
        
        
        ErrorMessage::Marker("Getting SpeciesQuickInformation");
        $info = SpeciesData::SpeciesQuickInformation($species_id);
        $result['SpeciesQuickInformation'] = $info;
        if ($count_taxa instanceof ErrorMessage)  return $result;
        

        ErrorMessage::Marker("Getting species_data_folder");
        $dir = SpeciesData::species_data_folder($species_id);
        if (!is_dir($dir))
        {
            $result['species_data_folder'] = "DOES NOT EXIST:: {$dir}";
            return $result;
        }
        
        $result['species_data_folder'] = $dir;

        
        ErrorMessage::Marker("Getting List of ASCII Files");
        $files = file::LS(SpeciesData::species_data_folder($species_id)."*", null, true);
        $files = file::arrayFilter($files, "asc");
        $files = file::arrayFilterOut($files, "aux");
        $files = file::arrayFilterOut($files, "xml");
        $result['ASCII file list'] = $files; 
        $result['ASCII file count'] = count($files); 


        ErrorMessage::Marker("Getting List of PNG Files");
        $files = file::LS(SpeciesData::species_data_folder($species_id)."*", null, true);
        $files = file::arrayFilter($files, "png");
        $files = file::arrayFilterOut($files, "aux");
        $files = file::arrayFilterOut($files, "xml");
        $result['PNG file list'] = $files; 
        $result['PNG file count'] = count($files); 
        

        ErrorMessage::Marker("Getting Occurance Count");
        $count_occurance = DBO::Count('species_occurrences', "species_id = {$species_id}");
        $result['occurance count'] = $count_occurance; 
        if ($count_occurance instanceof ErrorMessage)  return $result;
        
        
        ErrorMessage::Marker("Getting Other Data");
        $result['data'] = self::ListDataforSpecies($species_id);
        
        return $result;
        
    }
    
    
    public static function CurrentInfo2File($species_id,$filename = null) 
    {
        if (is_null($filename)) $filename = "info_{$species_id}.txt";
        file::Delete($filename);

        $result = SpeciesData::CurrentInfo($species_id);
        if ($result instanceof ErrorMessage) return $result;
        
        
        $fw = file_put_contents($filename, print_r($result,true));
        if (!$fw) return ErrorMessage::Marker("Failed to write to [{$filename}]\n");
        
        if (!file_exists($filename))  return ErrorMessage::Marker("Failed to create [{$filename}]\n");
        
        return $filename;
        
    }
    
    
    /**
     * List key = Common Name or Species Name and value = Species id
     * @param type $pattern 
     */
    public static function ComputedNameList($pattern = "",$indented_common_name = true) 
    {
        
        $pattern = util::CleanString($pattern);
        // $sql = "select species_id,(common_name || ' (' || scientific_name || ')' ) as full_name from species_occur where (common_name LIKE '%{$pattern}%' or scientific_name LIKE '%{$pattern}%' ) ";


        $pattern = "%{$pattern}%";        

        $sql = "select 
                   mc.species_id
                  ,mc.scientific_name 
                  ,st.common_name
                from 
                   modelled_climates mc 
                   left join species_taxa_tree st on (st.species_id = mc.species_id)
                order by scientific_name"
                ;
                
        $result = DBO::Query($sql, 'species_id');
        if ($result instanceof ErrorMessage) 
            return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);                  
        
        
        $result_list = array();
        foreach ($result as $species_id => $row) 
        {
            
            $result_list[$row['scientific_name']] = $species_id;  // scientfific name
            
            foreach (explode("/",$row['common_name']) as $common_name) 
            {
                $common_name = trim($common_name);
                if ($common_name == "") continue;
                
                
                if ($indented_common_name)
                    $result_list["..".$common_name] = $species_id;  // scientfific name
                else
                    $result_list[$common_name] = $species_id;  // scientfific name
            }
            
            
        }
        
        
        return $result_list;
        
        
        
    }
    
    
    
    

    public static function ClazzID($find)
    {
        return self::get_id_for('clazz',$find);
    }
    
    public static function FamilyID($find)
    {
        return self::get_id_for('family',$find);
    }
    
    public static function GenusID($find)
    {
        return self::get_id_for('genus',$find);
    }

    
    private static function get_id_for($table,$find,$columnName = 'name', $col = 'id')
    {
        
        if (is_null($table))  return new ErrorMessage(__METHOD__, __LINE__, "table passed as null"); 
        $table = trim($table);
        if ($table == "")  return new ErrorMessage(__METHOD__, __LINE__, "table passed as empty"); 
        
        if (is_null($find))  return new ErrorMessage(__METHOD__, __LINE__, "find passed as null"); 
        if ($find == "")  return new ErrorMessage(__METHOD__, __LINE__, "find passed as empty"); 
        
        $result =  DBO::QueryFirst("select $col from {$table} where {$columnName} = E'{$find}' limit 1",$col);
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);                                  
        
        $id = array_util::Value($result, $col);
        
        return $id;        
    }
    
    
    
    public static function CalculateAsciigridStatictis($species_id)
    {
        $folder = self::species_data_folder($species_id);

        $files  = file::folder_asc($folder, configuration::osPathDelimiter(), true);

        $files = file::arrayFilterOut($files, "aux");
        $files = file::arrayFilterOut($files, "xml");

        // .aux.xml

        foreach ($files  as $basename => $filename) 
        {
            ErrorMessage::Marker("Statsistics for {$species_id} {$basename}");

            $aux = $filename.".aux.xml";
            if (file_exists($aux)) continue;

            $stats = spatial_util::RasterStatisticsPrecision($filename);
            ErrorMessage::Marker("{$species_id} {$basename} {$stats[spatial_util::$STAT_MINIMUM]}  {$stats[spatial_util::$STAT_MEAN]}  {$stats[spatial_util::$STAT_MAXIMUM]}");

        }

    }

    
   public static function NextSpeciesID()
   {
        $result =  DBO::QueryFirst("select (max(species_id) + 10) as next_species_id from species_taxa_tree;",'next_species_id');
        if ($result instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);                                  

        return $result['next_species_id'];
        
   }
    
    
    /**
     * @param type $species_name - potental species name  (scientific name)
     *
     * @parm $return_json_as  null = json data not returned  / value = this name will be added as a key to returned array and will contain original JSON data
     * 
     * @return Array 
     * 
            $d['species_id'] = self::NextSpeciesID();
            $d['parent_guid'] = $parent_guid;
            $d['guid'] = $f->guid;
            $d['kingdom'] = $f->kingdom;
            $d['kingdom_guid'] = $f->kingdomGuid;
            $d['phylum'] = $f->phylum;
            $d['phylum_guid'] = $f->phylumGuid;
            $d['clazz'] = $f->clazz;
            $d['clazz_guid'] = $f->clazzGuid;
            $d['orderz'] = $f->order;
            $d['orderz_guid'] = $f->orderGuid;
            $d['family'] = $f->family;
            $d['family_guid'] = $f->familyGuid;
            $d['genus'] = $f->genus;
            $d['genus_guid'] = $f->genusGuid;
            $d['species'] = $f->species;
            $d['species_guid'] = $f->speciesGuid;
* 
     */
    public static function ALASpeciesTaxa($species_name,$return_json_as = null)
    {

        $url =  'http://bie.ala.org.au/ws/search.json?q='.urlencode($species_name);

        try {

            $data = json_decode(file_get_contents($url));

            $guid = $data->searchResults->results[0]->guid;

            $result0 = get_object_vars($data->searchResults->results[0]);
            
            if (!array_key_exists('parentGuid', $result0)) return null;

            $species_data_url = "http://bie.ala.org.au/ws/species/{$guid}.json";

            $species_data_json_search_results = file_get_contents($species_data_url);
            
            $species_data = json_decode($species_data_json_search_results);
            
            $f = $species_data->classification;
            
            $d = array();
            $d['parent_guid'] = $result0['parentGuid'];;
            $d['guid'] = $f->guid;
            $d['kingdom'] = $f->kingdom;
            $d['kingdom_guid'] = $f->kingdomGuid;
            $d['phylum'] = $f->phylum;
            $d['phylum_guid'] = $f->phylumGuid;
            $d['clazz'] = $f->clazz;
            $d['clazz_guid'] = $f->clazzGuid;
            $d['orderz'] = $f->order;
            $d['orderz_guid'] = $f->orderGuid;
            $d['family'] = $f->family;
            $d['family_guid'] = $f->familyGuid;
            $d['genus'] = $f->genus;
            $d['genus_guid'] = $f->genusGuid;
            $d['species'] = $f->species;
            $d['species_guid'] = $f->speciesGuid;
            
            
            $species_data_url = "http://bie.ala.org.au/ws/species/{$d['species_guid']}.json";

            $d['url'] = $species_data_url;
            
            $species_json = file_get_contents($species_data_url);

            if (!is_null($return_json_as)) $d[$return_json_as] = $species_json;
            
            $data = json_decode($species_json);
            
            $commonNames = $data->commonNames;

            $names = array();
            foreach ($commonNames as $index => $commonNameRow) 
            {
                $single_common_name = trim($commonNameRow->nameString);
                $names[$single_common_name] = $single_common_name;
            }
            
            $d['common_names'] = $names;
            
            return $d;

        } catch (Exception $exc) {
            ErrorMessage::Marker("Can't get data for {$species_name} " .$exc->getMessage());
        }
        
        return null;
        
    }
    
    
}

?> 