<?php
/**
 *
 * RUn as HPC server job to pregenerate species climate predictions as a mass batch job
 *
 * 
 * 
 */
class DatabaseSetup {


    private $cmdline = null;

    /**
     * Run one or more stages of import
     * 0 = test
     *
     *
     * @param int $stage
     */
    public static function Execute($stage = "names" )
    {

        $mcg = new SpeciesClimateGenerate();

        $mcg->cmdline = $stage;

        if (is_null($stage)) $stage = "names";

        if ($stage == "names")
        {

            $mcg->header("Stage Names ");

            $method_names = get_class_methods('SpeciesClimateGenerate');

            foreach ($method_names  as $method_name)
            {

                if (util::contains($method_name, "Stage"))
                {
                    echo $mcg->$method_name(true)."\n";
                }

            }

            return;
        }




        if (util::contains($stage, ","))
        {
            foreach (explode(",",$stage)as $stage_num) {
                $method = "Stage{$stage_num}";
                $stage_result = $mcg->$method();

                if (!$stage_result) exit(1);

            }
        }
        else
        {

            $method = "Stage{$stage}";
            $mcg->$method();

        }


    }


    public function Stage001($name_only = false)
    {

        $name = 'create command action table ';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);


        // might not be a good idea to drop table - really need  to copy as we will have update
        // the models_id in other tables from old to new


$table_sql = <<<SQL
DROP TABLE IF EXISTS command_action;
CREATE TABLE command_action
(
    id SERIAL NOT NULL PRIMARY KEY,
    objectID VARCHAR(50) NOT NULL,  -- objectID
    data text,                      -- php serialised object
    execution_flag varchar(50),     -- execution state
    status varchar(200),            -- current status
    queueid varchar(50),            -- to identify where this job cam from, allows multiple environments to use same queue
    update_datetime TIMESTAMP NULL  -- the last time data was updated
);
GRANT ALL PRIVILEGES ON command_action TO ap02;
GRANT USAGE, SELECT ON SEQUENCE command_action_id_seq TO ap02;
SQL;

        echo "$table_sql\n";

        $table_result = DBO::CreateAndGrant($table_sql);
        if ($table_result instanceof ErrorMessage) 
        {
            echo $table_result."\n";
            exit(1);
        }
        
        return true;

    }

    public function Stage002($name_only = false)
    {

        $name = 'create  error log table ';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);


$table_sql = <<<SQL
DROP TABLE IF EXISTS error_log;
CREATE TABLE error_log
(
     id SERIAL NOT NULL PRIMARY KEY
    ,error_date_time   VARCHAR(100)
    ,source_code_from  VARCHAR(100)
    ,error_message     VARCHAR(5000)
);
GRANT ALL PRIVILEGES ON error_log TO ap02;
GRANT USAGE, SELECT ON SEQUENCE error_log_id_seq TO ap02;
SQL;


        $table_result = DBO::CreateAndGrant($table_sql);
        if ($table_result instanceof ErrorMessage) 
        {
            echo $table_result."\n";
            exit(1);
        }

        return true;

    }




    public function Stage01($name_only = false)
    {

        $name = 'Populate Maxent Field Names';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);

        //echo "Count = ".count($names)."\n";
        //echo "Count Unique= ".count(array_unique($names))."\n";


        $sql = "DROP TABLE IF EXISTS maxent_fields;
                CREATE TABLE maxent_fields
                (
                    id SERIAL NOT NULL PRIMARY KEY
                    ,name              varchar(256)   -- eg. maxentResults.csv
                    ,update_datetime   timestamp without time zone
                );
                GRANT ALL PRIVILEGES ON maxent_fields TO ap02;
                GRANT USAGE, SELECT ON SEQUENCE maxent_fields_id_seq TO ap02;
               ";


        //echo "$sql\n";

        $table_result = DBO::CreateAndGrant($sql);

        if (is_null($table_result)) throw new Exception("FAILED to create table maxent_fields - null result from query ");

        if (!DBO::HasTable('maxent_fields')) throw new Exception("FAILED to create table maxent_fields - Can't find table with describe ");


        $inserted_count = 0;
        $M = matrix::Load('/scratch/jc166922/tdh2/source/species/1/output/maxentResults.csv');

        $names = matrix::ColumnNames($M);

        foreach ($names as $name)
        {
            $row_sql = "insert into maxent_fields (name) values ('{$name}')";

            //echo "$row_sql  ";

            $insert_result = DBO::Insert($row_sql);

            if (is_null($insert_result)) throw new Exception("FAILED insert  Maxent field name {$name}");

            if (!is_numeric($insert_result))  throw new Exception("FAILED insert Maxent field name {$name}  Insert Result [{$insert_result}] is not a number");

            $inserted_count++;

            //echo " UR = $update_result\n";

        }

        //echo "inserted_count = $inserted_count\n";

        if ($inserted_count != count($names))
        {
            throw new Exception("### ERROR:: Failed to insert all Names  inserted_count [{$inserted_count}] != CountNames [".count($names)."] \n");

            return false;
        }

        return true;

    }





    public   function Stage02($name_only = false)
    {

        $name = 'Model Descriptions';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);


        // might not be a good idea to drop table - really need  to copy as we will have update
        // the models_id in other tables from old to new

$table_sql = <<<SQL
DROP TABLE IF EXISTS models;
CREATE TABLE models
(
    id SERIAL NOT NULL PRIMARY KEY
    ,dataname          varchar(60)
    ,description       varchar(256)
    ,moreinfo          varchar(900)
    ,uri               varchar(500)
    ,metadata_ref      varchar(500)
    ,update_datetime   timestamp without time zone
);
GRANT ALL PRIVILEGES ON models TO ap02;
GRANT USAGE, SELECT ON SEQUENCE models_id_seq TO ap02;
SQL;



        $table_result = DBO::CreateAndGrant($table_sql);

        if (is_null($table_result)) throw new Exception("FAILED to create table models - null result from query ");
        if (!DBO::HasTable('models')) throw new Exception("FAILED to create table models - Can't find table with describe ");


        $descs = Descriptions::fromFile(configuration::ResourcesFolder()."descriptions/gcm.csv");
        $descs instanceof Descriptions;

        $format = "insert into models (dataname,description,moreinfo,uri) values ({DataName},{Description},{MoreInformation},{URI});";

        $inserted_count = 0;
        foreach ($descs->Descriptions() as $desc) {

            $desc instanceof Description;
            $values_sql = $desc->asFormattedString($format,true);

            //echo "\n values_sql = $values_sql  ";

            $values_result = DBO::Insert($values_sql);

            if (is_null($values_result)) throw new Exception("\nFAILED insert Model Descriptions using sql = {$values_sql}\nresult = $values_result\n\n");

            if (!is_numeric($values_result))  throw new Exception("\nFAILED insert Model Descriptions using sql = {$values_sql} [{$values_result}] is not a number\n");


            //echo "  UR = {$values_result}";

            $inserted_count++;

        }

        if ($inserted_count != $descs->count())
        {
            throw new Exception("\n### ERROR:: Failed to insert Model Description properly {$inserted_count} != {$descs->count()}\n");
            return false;
        }


        return true;

    }


    public function Stage03($name_only = false)
    {

        $name = 'Scenario Descriptions';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);

        // might not be a good idea to drop table - really need  to copy as we will have update
        // the models_id in other tables from old to new



$table_sql = <<<SQL
DROP TABLE IF EXISTS scenarios;
CREATE TABLE scenarios
(
    id SERIAL NOT NULL PRIMARY KEY
    ,dataname          varchar(60)
    ,description       varchar(256)
    ,moreinfo          varchar(900)
    ,uri               varchar(500)
    ,metadata_ref      varchar(500)
    ,update_datetime   timestamp without time zone
);
GRANT ALL PRIVILEGES ON scenarios TO ap02;
GRANT USAGE, SELECT ON SEQUENCE scenarios_id_seq TO ap02;
SQL;


        $table_result = DBO::CreateAndGrant($table_sql);

        if (is_null($table_result)) throw new Exception("FAILED to create table scenarios - null result from query ");
        if (!DBO::HasTable('scenarios')) throw new Exception("FAILED to create table scenarios - Can't find table with describe ");


        $descs = Descriptions::fromFile(configuration::ResourcesFolder()."descriptions/scenario.csv");
        $descs instanceof Descriptions;

        $format = "insert into scenarios (dataname,description,moreinfo,uri) values ({DataName},{Description},{MoreInformation},{URI});";

        $inserted_count = 0;
        foreach ($descs->Descriptions() as $desc) {

            $desc instanceof Description;
            $values_sql = $desc->asFormattedString($format,true);

            //echo "\n\n$values_sql\n\n";

            $values_result = DBO::Insert($values_sql);

            if (is_null($values_result)) throw new Exception("\nFAILED insert Scenario Descriptions using sql = {$values_sql}\n result = $values_result\n\n");

            if (!is_numeric($values_result))  throw new Exception("\nFAILED insert Scenario Descriptions using sql = {$values_sql} [{$values_result}] is not a number\n");

            //echo "  UR = {$values_result}";

            $inserted_count++;

        }

        if ($inserted_count != $descs->count())
        {
            throw new Exception("\n### ERROR:: Failed to insert scenarios Description properly {$inserted_count} != {$descs->count()}\n");
            return FALSE;
        }

        return true;

    }



    public   function Stage04($name_only = false)
    {

        $name = 'Times Descriptions';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);

        // might not be a good idea to drop table - really need  to copy as we will have update
        // the models_id in other tables from old to new



$table_sql = <<<SQL
DROP TABLE IF EXISTS times;
CREATE TABLE times
(
    id SERIAL NOT NULL PRIMARY KEY
    ,dataname          varchar(60)
    ,description       varchar(256)
    ,moreinfo          varchar(900)
    ,uri               varchar(500)
    ,metadata_ref      varchar(500)
    ,update_datetime   timestamp without time zone
);
GRANT ALL PRIVILEGES ON times TO ap02;
GRANT USAGE, SELECT ON SEQUENCE times_id_seq TO ap02;
SQL;


        $table_result = DBO::CreateAndGrant($table_sql);

        if (is_null($table_result)) throw new Exception("FAILED to create table times - null result from query ");
        if (!DBO::HasTable('times')) throw new Exception("FAILED to create table times - Can't find table with describe ");


        //echo "table_result = $table_result\n";

        $descs = Descriptions::fromFile(configuration::ResourcesFolder()."descriptions/year.txt");
        $descs instanceof Descriptions;

        $format = "insert into times (dataname,description,moreinfo,uri) values ({DataName},{Description},{MoreInformation},{URI});";

        $inserted_count = 0;
        foreach ($descs->Descriptions() as $desc) {

            $desc instanceof Description;
            $values_sql = $desc->asFormattedString($format,true);

            //echo "\n$values_sql  ";

            $values_result = DBO::Insert($values_sql);

            if (is_null($values_result)) throw new Exception("\nFAILED insert Times Descriptions using sql = {$values_sql}\nresult = $values_result\n\n");

            if (!is_numeric($values_result))  throw new Exception("\nFAILED insert Times Descriptions using sql = {$values_sql} [{$values_result}] is not a number\n");

            //echo "  UR = {$values_result}";

            $inserted_count++;

        }

        if ($inserted_count != $descs->count())
        {
            throw new Exception("\n### ERROR:: Failed to insert Times Description properly {$inserted_count} != {$descs->count()}\n");
            return false;
        }

        return true;

    }


    public   function Stage05($name_only = false)
    {

        $name = 'maxent_values';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);

$table_sql = <<<SQL
DROP TABLE IF EXISTS maxent_values;
CREATE TABLE maxent_values
(
    id SERIAL NOT NULL PRIMARY KEY
    ,species_id  integer
    ,maxent_fields_id      integer       -- many to one with maxent_fields.id
    ,num                   float         -- a numeric value
    ,update_datetime   timestamp without time zone
);
GRANT ALL PRIVILEGES ON maxent_values TO ap02;
GRANT USAGE, SELECT ON SEQUENCE maxent_values_id_seq TO ap02;
SQL;

        $table_result = DBO::CreateAndGrant($table_sql);

        if (is_null($table_result)) throw new Exception("FAILED to create table maxent_values - null result from query ");
        if (!DBO::HasTable('maxent_values')) throw new Exception("FAILED to create table maxent_values - Can't find table with describe ");

        return true;

    }


    public   function Stage06($name_only = false)
    {

        $name = 'modelled_climates';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);


$table_sql = <<<SQL

DROP TABLE IF EXISTS modelled_species_files;
CREATE TABLE modelled_species_files
(
    id SERIAL NOT NULL PRIMARY KEY
    ,species_id        integer
    ,scientific_name   varchar(256)   -- Will be Unique
    ,common_name       varchar(256)
    ,filetype          varchar(90)
    ,file_unique_id    varchar(60)
    ,update_datetime   timestamp without time zone
);
GRANT ALL PRIVILEGES ON modelled_species_files TO ap02;
GRANT USAGE, SELECT ON SEQUENCE modelled_species_files_id_seq TO ap02;


DROP TABLE IF EXISTS modelled_climates;
CREATE TABLE modelled_climates
(
    id SERIAL NOT NULL PRIMARY KEY
    ,species_id        integer        -- may become out of sync with species (use scientific_name to resync)
    ,scientific_name   varchar(256)   -- Will be Unique
    ,common_name       varchar(256)
    ,models_id         integer
    ,scenarios_id      integer
    ,times_id          integer
    ,filetype          varchar(90)
    ,file_unique_id   varchar(60)
    ,update_datetime   timestamp without time zone
);
GRANT ALL PRIVILEGES ON modelled_climates TO ap02;
GRANT USAGE, SELECT ON SEQUENCE modelled_climates_id_seq TO ap02;

SQL;

        $table_result = DBO::CreateAndGrant($table_sql);

        if (is_null($table_result)) throw new Exception("FAILED to create table modelled_species_files & modelled_climates - null result from query ");

        if (!DBO::HasTable('modelled_species_files')) throw new Exception("FAILED to create table modelled_species_files - Can't find table with describe ");
        if (!DBO::HasTable('modelled_climates')) throw new Exception("FAILED to create table modelled_climates - Can't find table with describe ");

        return true;

    }

    
        public   function Stage061($name_only = false)
    {

        $name = 'modelled_climates_genus';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);


$table_sql = <<<SQL

DROP TABLE IF EXISTS modelled_genus_files;
CREATE TABLE modelled_genus_files
(
    id SERIAL NOT NULL PRIMARY KEY
    ,genus_id          integer
    ,genus_name        varchar(256)   -- Will be Unique
    ,filetype          varchar(90)
    ,file_unique_id    varchar(60)
    ,update_datetime   timestamp without time zone
);
GRANT ALL PRIVILEGES ON modelled_genus_files TO ap02;
GRANT USAGE, SELECT ON SEQUENCE modelled_genus_files_id_seq TO ap02;


DROP TABLE IF EXISTS modelled_genus_climates;
CREATE TABLE modelled_genus_climates
(
    id SERIAL NOT NULL PRIMARY KEY
    ,genus_id          integer        -- may become out of sync with species (use scientific_name to resync)
    ,genus_name        varchar(256)   -- Will be Unique
    ,models_id         integer
    ,scenarios_id      integer
    ,times_id          integer
    ,filetype          varchar(90)
    ,file_unique_id   varchar(60)
    ,update_datetime   timestamp without time zone
);
GRANT ALL PRIVILEGES ON modelled_genus_climates TO ap02;
GRANT USAGE, SELECT ON SEQUENCE modelled_genus_climates_id_seq TO ap02;

SQL;

        $table_result = DBO::CreateAndGrant($table_sql);

        if (is_null($table_result)) throw new Exception("FAILED to create table modelled_genus_files & modelled_genus_climates - null result from query ");

        if (!DBO::HasTable('modelled_genus_files')) throw new Exception("FAILED to create table modelled_genus_files - Can't find table with describe ");
        if (!DBO::HasTable('modelled_genus_climates')) throw new Exception("FAILED to create table modelled_genus_climates - Can't find table with describe ");

        return true;

    }

        public   function Stage0611($name_only = false)
    {

        $name = 'Genus';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);


$table_sql = <<<SQL

DROP TABLE IF EXISTS genus;
CREATE TABLE genus
(
    id SERIAL NOT NULL PRIMARY KEY
    ,name              varchar(100)
    ,guid              varchar(100)
    ,update_datetime   timestamp without time zone
);
GRANT ALL PRIVILEGES ON genus TO ap02;
GRANT USAGE, SELECT ON SEQUENCE genus_id_seq TO ap02;

SQL;

        $table_result = DBO::CreateAndGrant($table_sql);

        if (is_null($table_result)) throw new Exception("FAILED to create table genus");

        if (!DBO::HasTable('genus')) throw new Exception("FAILED to create table genus-  Can't find table with describe ");
        
        echo "PRE GENUS INSERT ".DBO::Count("genus")."\n";
        
        // populate genus from 
        DBO::Insert("insert into genus (name,guid) select genus,genus_guid from species_taxa_tree group by genus,genus_guid order by genus;");

        echo "POST GENUS INSERT ".DBO::Count("genus")."\n";
        
        return true;
        
    }

        public   function Stage062($name_only = false)
    {

        $name = 'modelled_climates_family';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);


$table_sql = <<<SQL

DROP TABLE IF EXISTS modelled_family_files;
CREATE TABLE modelled_family_files
(
    id SERIAL NOT NULL PRIMARY KEY
    ,family_id         integer
    ,family_name       varchar(256)   -- Will be Unique
    ,filetype          varchar(90)
    ,file_unique_id    varchar(60)
    ,update_datetime   timestamp without time zone
);
GRANT ALL PRIVILEGES ON modelled_family_files TO ap02;
GRANT USAGE, SELECT ON SEQUENCE modelled_family_files_id_seq TO ap02;


DROP TABLE IF EXISTS modelled_family_climates;
CREATE TABLE modelled_family_climates
(
    id SERIAL NOT NULL PRIMARY KEY
    ,family_id          integer        -- may become out of sync with species (use scientific_name to resync)
    ,family_name       varchar(256)   -- Will be Unique
    ,models_id         integer
    ,scenarios_id      integer
    ,times_id          integer
    ,filetype          varchar(90)
    ,file_unique_id   varchar(60)
    ,update_datetime   timestamp without time zone
);
GRANT ALL PRIVILEGES ON modelled_family_climates TO ap02;
GRANT USAGE, SELECT ON SEQUENCE modelled_family_climates_id_seq TO ap02;

SQL;

        $table_result = DBO::CreateAndGrant($table_sql);

        if (is_null($table_result)) throw new Exception("FAILED to create table modelled_family_files & modelled_family_climates - null result from query ");

        if (!DBO::HasTable('modelled_family_files')) throw new Exception("FAILED to create table modelled_family_files - Can't find table with describe ");
        if (!DBO::HasTable('modelled_family_climates')) throw new Exception("FAILED to create table modelled_family_climates - Can't find table with describe ");

        return true;

    }

    
        public   function Stage0621($name_only = false)
    {

        $name = 'Family';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);


$table_sql = <<<SQL

DROP TABLE IF EXISTS family;
CREATE TABLE family
(
    id SERIAL NOT NULL PRIMARY KEY
    ,name              varchar(100)
    ,guid              varchar(100)
    ,update_datetime   timestamp without time zone
);
GRANT ALL PRIVILEGES ON family TO ap02;
GRANT USAGE, SELECT ON SEQUENCE family_id_seq TO ap02;

SQL;

        $table_result = DBO::CreateAndGrant($table_sql);

        if (is_null($table_result)) throw new Exception("FAILED to create table family");

        if (!DBO::HasTable('family')) throw new Exception("FAILED to create table family-  Can't find table with describe ");
        
        echo "PRE family INSERT ".DBO::Count("family")."\n";
        
        // populate family from 
        DBO::Insert("insert into family (name,guid) select family,family_guid from species_taxa_tree group by family,family_guid order by family;");

        echo "POST family INSERT ".DBO::Count("family")."\n";
        
        return true;
        
    }
    
    
    
    
    
        public   function Stage063($name_only = false)
    {

        $name = 'modelled_climates_clazz';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);


$table_sql = <<<SQL

DROP TABLE IF EXISTS modelled_clazz_files;
CREATE TABLE modelled_clazz_files
(
    id SERIAL NOT NULL PRIMARY KEY
    ,clazz_id          integer
    ,clazz_name        varchar(256)   -- Will be Unique
    ,filetype          varchar(90)
    ,file_unique_id    varchar(60)
    ,update_datetime   timestamp without time zone
);
GRANT ALL PRIVILEGES ON modelled_clazz_files TO ap02;
GRANT USAGE, SELECT ON SEQUENCE modelled_clazz_files_id_seq TO ap02;


DROP TABLE IF EXISTS modelled_clazz_climates;
CREATE TABLE modelled_clazz_climates
(
    id SERIAL NOT NULL PRIMARY KEY
    ,clazz_id          integer        -- may become out of sync with species (use scientific_name to resync)
    ,clazz_name        varchar(256)   -- Will be Unique
    ,models_id         integer
    ,scenarios_id      integer
    ,times_id          integer
    ,filetype          varchar(90)
    ,file_unique_id   varchar(60)
    ,update_datetime   timestamp without time zone
);
GRANT ALL PRIVILEGES ON modelled_clazz_climates TO ap02;
GRANT USAGE, SELECT ON SEQUENCE modelled_clazz_climates_id_seq TO ap02;

SQL;

        $table_result = DBO::CreateAndGrant($table_sql);

        if (is_null($table_result)) throw new Exception("FAILED to create table modelled_clazz_files & modelled_clazz_climates - null result from query ");

        if (!DBO::HasTable('modelled_clazz_files')) throw new Exception("FAILED to create table modelled_clazz_files - Can't find table with describe ");
        if (!DBO::HasTable('modelled_clazz_climates')) throw new Exception("FAILED to create table modelled_clazz_climates - Can't find table with describe ");

        return true;

    }
    
        public   function Stage0631($name_only = false)
    {

        $name = 'Clazz';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);


$table_sql = <<<SQL

DROP TABLE IF EXISTS clazz;
CREATE TABLE clazz
(
    id SERIAL NOT NULL PRIMARY KEY
    ,name              varchar(100)
    ,guid              varchar(100)
    ,update_datetime   timestamp without time zone
);
GRANT ALL PRIVILEGES ON clazz TO ap02;
GRANT USAGE, SELECT ON SEQUENCE clazz_id_seq TO ap02;

SQL;

        $table_result = DBO::CreateAndGrant($table_sql);

        if (is_null($table_result)) throw new Exception("FAILED to create table clazz");

        if (!DBO::HasTable('clazz')) throw new Exception("FAILED to create table clazz-  Can't find table with describe ");
        
        echo "PRE Clazz INSERT ".DBO::Count("clazz")."\n";
        
        // populate clazz from 
        DBO::Insert("insert into clazz (name,guid) select clazz,clazz_guid from species_taxa_tree group by clazz,clazz_guid order by clazz;");

        echo "POST Clazz INSERT ".DBO::Count("clazz")."\n";
        
        return true;
        
    }
    
    
    
    
    

    public   function Stage07($name_only = false)
    {

        $name = 'files_data';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);


$table_sql = <<<SQL
DROP TABLE IF EXISTS files;
CREATE TABLE files
(
    id SERIAL NOT NULL PRIMARY KEY
    ,file_unique_id   varchar(60)
    ,mimetype         varchar(50)
    ,filetype         varchar(90)  -- e.g. ASC_GRID, QuickLook, HTML, CSV
    ,description      varchar(500)
    ,totalparts       float
    ,total_filesize   float
    ,compressed       varchar(20)    
    ,update_datetime  timestamp without time zone
);

GRANT ALL PRIVILEGES ON files TO ap02;
GRANT USAGE, SELECT ON SEQUENCE files_id_seq TO ap02;


DROP TABLE IF EXISTS files_data;
CREATE TABLE files_data
(
    id SERIAL NOT NULL PRIMARY KEY
    ,file_unique_id   varchar(60)
    ,partnum          float
    ,totalparts       float
    ,data             text
    ,update_datetime  timestamp without time zone
);

GRANT ALL PRIVILEGES ON files_data TO ap02;
GRANT USAGE, SELECT ON SEQUENCE files_data_id_seq TO ap02;
SQL;

        //echo "Create Table for files_data \n";

        $table_result = DBO::CreateAndGrant($table_sql);

        if (is_null($table_result)) throw new Exception("FAILED to create table files &  files_data - null result from query ");

        if (!DBO::HasTable('files')) throw new Exception("FAILED to create table files - Can't find table with describe ");
        if (!DBO::HasTable('files_data')) throw new Exception("FAILED to create table files_data - Can't find table with describe ");

        return true;

    }


    public function Stage08($name_only = false)
    {

        $name = 'Bioclim Descriptions';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);

        // might not be a good idea to drop table - really need  to copy as we will have update
        // the models_id in other tables from old to new



$table_sql = <<<SQL
DROP TABLE IF EXISTS bioclim;
CREATE TABLE bioclim
(
    id SERIAL NOT NULL PRIMARY KEY
    ,dataname          varchar(60)
    ,description       varchar(256)
    ,moreinfo          varchar(900)
    ,uri               varchar(500)
    ,metadata_ref      varchar(500)
    ,update_datetime   timestamp without time zone
);
GRANT ALL PRIVILEGES ON bioclim TO ap02;
GRANT USAGE, SELECT ON SEQUENCE bioclim_id_seq TO ap02;
SQL;


        $table_result = DBO::CreateAndGrant($table_sql);

        if (is_null($table_result)) throw new Exception("FAILED to create table bioclim - null result from query ");
        if (!DBO::HasTable('bioclim')) throw new Exception("FAILED to create table bioclim - Can't find table with describe ");


        $descs = Descriptions::fromFile(configuration::ResourcesFolder()."descriptions/bioclim.csv");
        $descs instanceof Descriptions;

        $format = "insert into bioclim (dataname,description,moreinfo,uri) values ({DataName},{Description},{MoreInformation},{URI});";

        $inserted_count = 0;
        foreach ($descs->Descriptions() as $desc) {

            $desc instanceof Description;
            $values_sql = $desc->asFormattedString($format,true);

            //echo "\n\n$values_sql\n\n";

            $values_result = DBO::Insert($values_sql);

            if (is_null($values_result)) throw new Exception("\nFAILED insert bioclim Descriptions using sql = {$values_sql}\n result = $values_result\n\n");

            if (!is_numeric($values_result))  throw new Exception("\nFAILED insert bioclim Descriptions using sql = {$values_sql} [{$values_result}] is not a number\n");

            //echo "  UR = {$values_result}";

            $inserted_count++;

        }

        if ($inserted_count != $descs->count())
        {
            throw new Exception("\n### ERROR:: Failed to insert bioclim Description properly {$inserted_count} != {$descs->count()}\n");
            return FALSE;
        }

        return true;

    }


    public function Stage09($name_only = false)
    {

        $name = 'Species Taxa Tree';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);

        // might not be a good idea to drop table - really need  to copy as we will have update
        // the models_id in other tables from old to new


$table_sql = <<<SQL
DROP TABLE IF EXISTS species_taxa_tree;
CREATE TABLE species_taxa_tree
(
    id SERIAL NOT NULL PRIMARY KEY
    ,species_id           integer
    ,parent_guid      varchar(100)
    ,guid             varchar(100)
    ,kingdom          varchar(100)
    ,kingdom_guid     varchar(100)
    ,phylum           varchar(100)
    ,phylum_guid      varchar(100)
    ,clazz            varchar(100)
    ,clazz_guid       varchar(100)
    ,orderz           varchar(100)
    ,orderz_guid      varchar(100)
    ,family           varchar(100)
    ,family_guid      varchar(100)
    ,genus            varchar(100)
    ,genus_guid       varchar(100)
    ,species          varchar(100)
    ,species_guid     varchar(100)
    ,update_datetime   timestamp without time zone
);
GRANT ALL PRIVILEGES ON species_taxa_tree TO ap02;
GRANT USAGE, SELECT ON SEQUENCE species_taxa_tree_id_seq TO ap02;
SQL;

        $table_result = DBO::CreateAndGrant($table_sql);

        if (is_null($table_result)) throw new Exception("FAILED to create table species_taxa_tree - null result from query ");
        if (!DBO::HasTable('species_taxa_tree')) throw new Exception("FAILED to create table species_taxa_tree - Can't find table with describe ");



        return true;

    }

    
    

    public function Stage091($name_only = false)
    {

        $name = 'Species Taxa Tree populate';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);

        // might not be a good idea to drop table - really need  to copy as we will have update
        // the models_id in other tables from old to new
        // get Species ID

        ///   - T remove all data before  new upload DBO::DeleteAll('species_taxa_tree');

        
        $speciesList = SpeciesData::SpeciesWithOccuranceData();
        
        $count = 0;
        foreach ($speciesList as $key => $row)
        {

            if ($count < 9999999)
            {
                
                $speciesID = $row['species_id'];
                $speciesName = $row['scientific_name'];
                
                $url =  'http://bie.ala.org.au/ws/search.json?q=' . urlencode($speciesName);

                // check to see if we already have this one
                
                $taxa_data_count = DBO::Count('species_taxa_tree', "species_id = {$speciesID}");
                
                if ($taxa_data_count > 0 )
                {
                    echo "ALA data for [{$count}] {$speciesID} {$speciesName} ALREADY DOWNLOAED taxa_data_count = {$taxa_data_count}\n";
                    continue;
                }
                
                
                try {

                    $data = json_decode(file_get_contents($url));

                    $guid = $data->searchResults->results[0]->guid;
                    
                    
                    echo "Get ALA data for [{$count}] {$speciesID} {$speciesName}\n";
                    $result0 = get_object_vars($data->searchResults->results[0]);

                    $parent_guid  = $result0['parentGuid'];

                    $species_data_url = "http://bie.ala.org.au/ws/species/{$guid}.json";

                    $species_data = json_decode(file_get_contents($species_data_url));

                    $f = $species_data->classification;

                    $d = array();

                    $d['species_id'] = $speciesID;
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

                    $insert_result = DBO::InsertArray('species_taxa_tree',$d,true);
                    
                    echo "Insert for [{$count}] {$speciesID} {$speciesName} insert_result = {$insert_result}\n";


                } catch (Exception $exc) {
                    echo "Can't get data for [{$count}] {$speciesID} {$speciesName}\n";
                }



            }

            $count++;
        }

        //$q = "select kingdom,phylum,clazz,orderz,family,genus,species from species_taxa_tree";
        //$result = DBO::Query($q);
        
        //matrix::display($result, " ", null, 20);
        

        return true;

    }


    public function Stage092($name_only = false)
    {

        $name = 'Species Taxa Tree populate for Common Name Only  with Occurances';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);

        // might not be a good idea to drop table - really need  to copy as we will have update
        // the models_id in other tables from old to new
        // get Species ID

        ///   - T remove all data before  new upload DBO::DeleteAll('species_taxa_tree');

        
        $speciesList = SpeciesData::TaxaWithOccurancesFiltered(1, 'common_name', 'is', 'null');
        if ($speciesList instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $speciesList);

        
        $count = 0;
        foreach ($speciesList as $key => $row)
        {

            if ($count < 999999)
            {
                
                
                try {
                    
                    echo "ALA common name for {$row['species_taxa_id']}  {$row['species']}  ... ";
                    
                    $species_data_url = "http://bie.ala.org.au/ws/species/{$row['species_guid']}.json";
                    
                    $data = json_decode(file_get_contents($species_data_url));
                    
                    $commonNames = $data->commonNames;

                    $names = array();
                    foreach ($commonNames as $index => $commonNameRow) 
                        $names[$commonNameRow->nameString] = $commonNameRow->nameString;
                    
                    $nameStr = util::dbq(implode(" / ",$names));
                    
                    $sql = "update species_taxa_tree set common_name = {$nameStr} where id = {$row['species_taxa_id']}";
                    
                    $result = DBO::Update($sql);
                    if ($result instanceof ErrorMessage) 
                    {
                        echo "\n";
                        ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
                        exit(1);
                    }
                    else
                    {
                        echo $result."\n";
                    }


                } catch (Exception $exc) {
                    echo "Can't get data for {$row['species']} using URL {$species_data_url}\n";
                }

            }

            $count++;
        }

        //$q = "select kingdom,phylum,clazz,orderz,family,genus,species from species_taxa_tree";
        //$result = DBO::Query($q);
        
        //matrix::display($result, " ", null, 20);
        

        return true;

    }

    
    public function Stage093($name_only = false)
    {

        $name = 'Species Taxa Tree populate for Common Name Only  for all ';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);

        // might not be a good idea to drop table - really need  to copy as we will have update
        // the models_id in other tables from old to new
        // get Species ID

        ///   - T remove all data before  new upload DBO::DeleteAll('species_taxa_tree');

        
        $speciesList = SpeciesData::TaxaFiltered('common_name', 'is', 'null');
        if ($speciesList instanceof ErrorMessage) return ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $speciesList);

        
        $count = 0;
        foreach ($speciesList as $key => $row)
        {

            if ($count < 999999)
            {
                
                
                try {
                    
                    echo "ALA common name for {$row['id']}  {$row['species']}  ... ";
                    
                    $species_data_url = "http://bie.ala.org.au/ws/species/{$row['species_guid']}.json";
                    
                    $data = json_decode(file_get_contents($species_data_url));
                    
                    $commonNames = $data->commonNames;

                    $names = array();
                    foreach ($commonNames as $index => $commonNameRow) 
                        $names[$commonNameRow->nameString] = $commonNameRow->nameString;
                    
                    $nameStr = util::dbq(implode(" / ",$names));
                    
                    $sql = "update species_taxa_tree set common_name = {$nameStr} where id = {$row['id']}";
                    
                    $result = DBO::Update($sql);
                    if ($result instanceof ErrorMessage) 
                    {
                        echo "\n";
                        ErrorMessage::Stacked(__METHOD__, __LINE__, "", true, $result);
                        exit(1);
                    }
                    else
                    {
                        echo $result."\n";
                    }


                } catch (Exception $exc) {
                    echo "Can't get data for {$row['species']} using URL {$species_data_url}\n";
                }

            }

            $count++;
        }

        //$q = "select kingdom,phylum,clazz,orderz,family,genus,species from species_taxa_tree";
        //$result = DBO::Query($q);
        
        //matrix::display($result, " ", null, 20);
        

        return true;

    }
    
    
    public function Stage094($name_only = false)
    {

        $name = 'Species Taxa Tree IDs';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);

        // might not be a good idea to drop table - really need  to copy as we will have update
        // the models_id in other tables from old to new


$table_sql = <<<SQL
DROP TABLE IF EXISTS taxa_lookup;
CREATE TABLE taxa_lookup
(
    id SERIAL NOT NULL PRIMARY KEY
    ,taxa_tree_id     integer
    ,species_id       integer
    ,clazz_id         integer
    ,family_id        integer
    ,genus_id         integer
    ,update_datetime   timestamp without time zone
);
GRANT ALL PRIVILEGES ON taxa_lookup TO ap02;
GRANT USAGE, SELECT ON SEQUENCE taxa_lookup_id_seq TO ap02;
SQL;

        $table_result = DBO::CreateAndGrant($table_sql);

        if (is_null($table_result)) throw new Exception("FAILED to create table taxa_lookup - null result from query ");
        if (!DBO::HasTable('taxa_lookup')) throw new Exception("FAILED to create table taxa_lookup - Can't find table with describe ");
        
        $species_taxa_tree = DBO::Query("select * from species_taxa_tree",'species_id');
        
        foreach ($species_taxa_tree as $species_id => $row) 
        {
            if (trim($row['clazz']) == "") continue;
            
            //echo "Before CLAZZ {$row['clazz']} FAMILY {$row['family']}  GENUS {$row['genus']}  SPECIES {$species_id} \n";
            
             $clazz_id = SpeciesData::ClazzID($row['clazz']);
            $family_id = SpeciesData::FamilyID($row['family']);
             $genus_id = SpeciesData::GenusID($row['genus']);
            
             $taxa_tree_id = $row['id'];
             
            echo "CLAZZ: {$row['clazz']} FAMILY:: {$row['family']}  GENUS {$row['genus']}  SPECIES:: {$species_id} \n";

            $insert = DBO::Insert("insert into taxa_lookup 
                                   (taxa_tree_id, species_id, clazz_id, family_id, genus_id) VALUES 
                                   (E'{$taxa_tree_id}', E'{$species_id}', E'{$clazz_id}', E'{$family_id}', E'{$genus_id}' )");

            
                                   
            
            echo "insert = {$insert}\n";
            
                                   
            
        }
        
        

        return true;

    }
    
    
    

    private  function header($str = "")
    {
        //echo "\n".str_repeat("=", 70);
        echo "\n=== {$str}\n";
        //echo "\n".str_repeat("=", 70)."\n";

    }


    public   function Stage1($name_only = false)
    {

        $name = 'Bulid tables for climate model data stages(01,02,03,04,05,06,07) ';

        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);

        $this->Execute('001,002,01,02,03,04,05,06,07');


    }


    public   function Stage2($name_only = false)
    {

        $name = 'Test insert of Maxent Data';
        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $species_id = 2;

        $insert_result = DatabaseMaxent::InsertMaxentResultsCSV($species_id);

        matrix::display(DatabaseMaxent::GetMaxentResultsCSV($species_id), $delim = " ",null,20);

        echo "insert_result = $insert_result\n";


    }


    public function Stage3($name_only = false)
    {

        $name = 'Test insert of Data from Filesystem to database';
        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $species_id = 3081;

        DatabaseMaxent::RemoveAllMaxentResults($species_id,true);
        DatabaseMaxent::InsertMainMaxentResults($species_id);
    }

    public function Stage4($name_only = false)
    {

        $name = 'Test insert of Data - running models first';
        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        //$species_id = 3081;

        $scenarios = DatabaseClimate::GetScenarios();

        $models    = DatabaseClimate::GetModels();

        $times     = DatabaseClimate::GetTimes();
//
//        echo "scenarios = ".implode(", ", $scenarios)."\n";
//        echo "models    = ".implode(", ", $models)."\n";
//        echo "times     = ".implode(", ", $times)."\n";



        $species  = "1103 1849 1350 1308 1999 427 194 482 1420 2073 2232 492 1822 1764";
        $scenario = implode(" ", $scenarios);
        $model    = implode(" ", $models);
        $time     = implode(" ", $times);



        $scenario = util::first_element($scenarios);
        $model    = util::first_element($models);
        //$time     = util::first_element($times);

        echo "test scenarios = ".$scenario."\n";
        echo "test models    = ".$model."\n";
        echo "test times     = ".$time."\n";




        $M = new SpeciesMaxent();

        $src = array();
        $src['species']  = $species;
        $src['scenario'] = $scenario;
        $src['model']    = $model;
        $src['time']     = $time;

        $M->initialise($src);


        if ($M->ExecutionFlag() == CommandAction::$EXECUTION_FLAG_COMPLETE)
        {
            echo "====================================\n";
            echo "MOdel output is is completed D\n";
            echo "====================================\n";

            print_r($M->Result());

        }
        else
        {

            echo "====================================\n";
            echo "RUNNING MOdel On GRID\n";
            echo "====================================\n";

            $M->Execute();

        }


        $loaded = DatabaseMaxent::ModelledSpeciesFiles($species_id);

        matrix::display($loaded, " ", null, 15);


        echo "====================================\n";
        echo "Finished\n";
        echo "====================================\n";


    }


    public function Stage5($name_only = false)
    {

        $name = 'running models for All Models for ten Species';
        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        //$species_id = 3081;

        $scenarios = DatabaseClimate::GetScenarios();

        $models    = DatabaseClimate::GetModels();

        $times     = DatabaseClimate::GetTimes();
//
//        echo "scenarios = ".implode(", ", $scenarios)."\n";
//        echo "models    = ".implode(", ", $models)."\n";
//        echo "times     = ".implode(", ", $times)."\n";




        $species = implode(" ",array_keys( DBO::Unique("species_occurence", "species_id", "count > 5000", true)));
        $scenario = implode(" ", $scenarios);
        $model    = implode(" ", $models);
        $time     = implode(" ", $times);


        echo "load species   = ".$species."\n";
        echo "load scenarios = ".$scenario."\n";
        echo "load models    = ".$model."\n";
        echo "load times     = ".$time."\n";

        $M = new SpeciesMaxent();

        $src = array();
        $src['species']  = $species;
        $src['scenario'] = $scenario;
        $src['model']    = $model;
        $src['time']     = $time;

        $M->initialise($src);

        echo "====================================\n";
        echo "RUNNING MOdel On GRID\n";
        echo "====================================\n";

        $M->Execute();


    }



    public function Stage99($name_only = false)
    {

        $name = 'Pregenerate all species / models / scenarios / times using - Maxent';
        if ($name_only) return __METHOD__."(".__LINE__.")"."::".$name;

        $this->header($name);



    }

    private  function preGenerateSpecies($species_id,$scenarios,$models,$times)
    {


        // check to see that this combination exists ?


        // look at file system to see if file exists.
        // if we have the files then



        // if file exsist then just update database if required


        // import into database ?
        // maxent data




    }





}

?>
