<?php

set_time_limit(120);

if(!is_numeric($_SERVER['argv'][1]) || strlen($_SERVER['argv'][2]) != 2 || !is_numeric($_SERVER['argv'][3]) || !is_numeric($_SERVER['argv'][4]) || !is_numeric($_SERVER['argv'][5])  || $_SERVER['argv'][7] != 'c45a5f3b2cfa74ac94bd5bbfb2c5d6a5'){
    die();
}

require_once(__DIR__.'/../config.php');
require_once(__DIR__.'/../access_token_3_1/get_access_token.php');

/*********** Parámetros **************/
$max_nun_intentos = 6;
/************************************/

$accessToken_data = getAccessToken();
$accessToken_data_index = -1;
$nun_intentos = 0;
$accessToken = NULL;
$accountId = NULL;
$pageId = NULL;
$pageName = NULL;

function nextAccessToken(){
    global $accessToken_data, $accessToken_data_index, $accessToken, $accountId, $pageId, $pageName, $nun_intentos, $max_nun_intentos;
    $accessToken_data_index++;
    if($accessToken_data_index < count($accessToken_data)){
        $accessToken = $accessToken_data[$accessToken_data_index]['access_token'];
        $accountId = $accessToken_data[$accessToken_data_index]['accountId'];
        $pageId = $accessToken_data[$accessToken_data_index]['pageId'];
        $pageName = $accessToken_data[$accessToken_data_index]['pageName'];
    }
    else{
        $nun_intentos++;
        if($nun_intentos < $max_nun_intentos){
            /*setAccessToken();
            $accessToken_data = getAccessToken();
            $accessToken_data_index = -1;*/
            nextAccessToken();
        }
        else{
            send_email('country_interest_3_1', 'Owloo ERROR - Pais INTEREST 3.1', 'ERROR en la captura de datos.', true, 'Access Token - Pais INTEREST - ID = '.$_SERVER['argv'][1]);
        }
    }
}

function getNumAudience($code, $gender, $key_interest, $_accessToken, $_accountId, $_pageId, $_pageName){
    $numAudience = "";
    try{
        
        $datos = get_url_content('https://graph.facebook.com/act_'.$_accountId.'/reachestimate?access_token='.$_accessToken.'&accountId='.$_accountId.'&bid_for=["conversion"]&currency=USD&endpoint=/act_'.$_accountId.'/reachestimate&locale=es_LA&method=get&pretty=0&targeting_spec={"genders":['.$gender.'],"age_max":65,"age_min":13,"regions":[],"countries":[],"cities":[],"zips":[],"keywords":[],"interests":[{"id":"'.$key_interest.'","name":""}],"connections":[],"friends_of_connections":[],"relationship_statuses":[],"interested_in":[],"college_years":[],"education_statuses":[],"locales":[],"user_adclusters":[],"user_os":[],"user_device":[],"wireless_carrier":[],"work_positions":[],"education_majors":[],"education_schools":[],"work_employers":[],"page_types":null,"geo_locations":{"countries":["'.$code.'"],"cities":[],"regions":[],"zips":[]},"excluded_geo_locations":{"countries":[],"cities":[],"regions":[],"zips":[]}}');
        
        $datosarray2 = json_decode ($datos, true);
        
        //print_r($datosarray2 ); die();
        
        if(isset($datosarray2['users'])){
            $numAudience = $datosarray2['users'];
            if($numAudience != "" && is_numeric($numAudience)){
                return $numAudience;
            }
        }else {
            if(isset($datosarray2['error'])){
                if($datosarray2['error']['code'] != 190){
                    send_email('country_3_1', 'Owloo ERROR - Pais LANGUAGE 3.1', 'ERROR en la captura de datos.', true, 'Facebook error code - Pais - ID = '.$_SERVER['argv'][1].' - Code: '.$datosarray2['error']['code'].' - Mensaje: '.$datosarray2['error']['message']);
                }
            }
        }

    }
    catch(Exception $e) {
        return false;
    }
    return false;
}


/***************************************** GET TOTALES ********************************************************/

nextAccessToken();

$next_id = $_SERVER['argv'][1];
$next_code = $_SERVER['argv'][2];
$total_interest = $_SERVER['argv'][3];
$id_interest = $_SERVER['argv'][4];
$key_interest = $_SERVER['argv'][5];
$check_if_exist = $_SERVER['argv'][6];

if(!empty($next_id)){
        
    if($check_if_exist){
        $query = 'SELECT id_country FROM facebook_record_country_interest_3_1 WHERE id_country = $1 AND id_interest = $2 AND date = DATE_FORMAT(NOW(), \'%Y-%m-%d\');';
        $que = db_query($query, array($next_id, $id_interest));
        if($row = mysql_fetch_assoc($que)){
            die();
        }
    }
            
    $cont = 0;
    $ban = 0;
    while($ban == 0 && $cont < 5){
        //Audiencia total
        $check_audience = false;
        $numAudience = NULL;
        while(!$check_audience){
            $numAudience = getNumAudience($next_code, "", $key_interest, $accessToken, $accountId, $pageId, $pageName);
            if(!$numAudience){
                nextAccessToken();
            }
            else
                $check_audience = true;
        }
        //Audiencia total mujeres
        $check_audience = false;
        $numAudienceFemale = NULL;
        while(!$check_audience){
            $numAudienceFemale = getNumAudience($next_code, "2", $key_interest, $accessToken, $accountId, $pageId, $pageName);
            if(!$numAudienceFemale){
                nextAccessToken();
            }
            else
                $check_audience = true;
        }
        //Audiencia total hombres
        $check_audience = false;
        $numAudienceMale = NULL;
        while(!$check_audience){
            $numAudienceMale = getNumAudience($next_code, "1", $key_interest, $accessToken, $accountId, $pageId, $pageName);
            if(!$numAudienceMale){
                nextAccessToken();
            }
            else
                $check_audience = true;
        }
        
        if(is_numeric($numAudience) && is_numeric($numAudienceMale) && is_numeric($numAudienceFemale)){
            
            $query = 'INSERT INTO facebook_record_country_interest_3_1 VALUES($1, $2, $3, $4, $5, NOW());';
            $values = array($next_id, $id_interest, $numAudience, $numAudienceFemale, $numAudienceMale);
            $res = db_query($query, $values, 1);
            if($res){
                $ban = 1;
            }
            
        }
        else {
            send_email('country_interest_3_1', 'Owloo ERROR - Pais INTEREST - '.$next_id.' - 3.1', 'ERROR en la captura de datos.', true, 'Captura de datos - Pais INTEREST - ID = '.$_SERVER['argv'][1].' => '.$numAudience.' - '.$numAudienceFemale.' - '.$numAudienceMale);
        }
        $cont++;
    }

    /***************************************** FIN GET TOTALES ********************************************************/
    
    /********************************** Verificación de finalización *************************************************/
    
    //Cantidad de filas insertadas
    /*$query = 'SELECT COUNT(*) cantidad FROM `facebook_record_country_interest_3_1` WHERE date = DATE_FORMAT(now(), \'%Y-%m-%d\');'; 
    $que = db_query($query, array());
    
    if($fila = mysql_fetch_assoc($que)){
        if($fila['cantidad'] == ($total_interest))
            send_email('country_interest_3_1', 'Owloo EXITO - Pais INTEREST 3.1', 'EXITO en la captura de datos.');
    }*/
    
    /******************************* FIN - Verificación de finalización **********************************************/
}
die();