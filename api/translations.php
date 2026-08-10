<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

$translationApiFinished = false;

register_shutdown_function(
    static function () use (&$translationApiFinished): void {
        if ($translationApiFinished) {
            return;
        }

        $error = error_get_last();

        if (
            is_array($error)
            && in_array(
                (int)$error['type'],
                [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR],
                true
            )
        ) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');

            echo json_encode(
                [
                    'success' => false,
                    'message' => 'Translations API fatal error: ' . $error['message'],
                    'data' => [],
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }
    }
);

try {
    require_once dirname(__DIR__) . '/includes/bootstrap.php';
} catch (Throwable $bootstrapError) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode(
        [
            'success' => false,
            'message' => 'Translations API bootstrap failed: '
                . $bootstrapError->getMessage(),
            'data' => [],
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function trOut(bool $success,string $message='',array $data=[],int $status=200):never{
    global $translationApiFinished;
    $translationApiFinished = true;
    while(ob_get_level()>0)ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['success'=>$success,'message'=>$message,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function trInput():array{
    $json=json_decode((string)file_get_contents('php://input'),true);
    return is_array($json)?$json:$_POST;
}
function trScope():array{
    $user=function_exists('current_user')?current_user():[];
    return[
        'tenant_id'=>(int)($user['tenant_id']??$user['school_id']??$_SESSION['tenant_id']??$_SESSION['school_id']??0),
        'branch_id'=>(int)($user['branch_id']??$_SESSION['branch_id']??$_SESSION['default_branch_id']??0),
        'user_id'=>(int)($user['id']??$user['user_id']??$_SESSION['user_id']??0),
    ];
}
function trCsrf(array $input):void{
    $session=(string)($_SESSION['translation_csrf_token']??'');
    $request=(string)($input['csrf_token']??'');
    if($session===''||$request===''||!hash_equals($session,$request)){
        trOut(false,'Invalid or expired CSRF token. Refresh the page.',[],419);
    }
}
function trSettings(PDO $pdo,int $tenantId):array{
    $defaults=[
        'default_language_id'=>'',
        'fallback_language_id'=>'',
        'auto_translate_provider'=>'disabled',
        'auto_translate_endpoint'=>'',
        'auto_translate_api_key'=>'',
        'missing_translation_mode'=>'default_text',
        'cache_version'=>'1',
    ];
    $q=$pdo->prepare("SELECT setting_key,setting_value FROM translation_settings WHERE tenant_id=:tenant_id");
    $q->execute(['tenant_id'=>$tenantId]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
        $defaults[$row['setting_key']]=$row['setting_value'];
    }
    return $defaults;
}
function trMeta(PDO $pdo,array $scope):array{
    $q=$pdo->prepare("SELECT id,language_name,language_code,locale_code,text_direction,is_default,status FROM translation_languages WHERE tenant_id=:tenant_id ORDER BY is_default DESC,language_name");
    $q->execute(['tenant_id'=>$scope['tenant_id']]);
    $languages=$q->fetchAll(PDO::FETCH_ASSOC);

    $q=$pdo->prepare("SELECT DISTINCT key_group FROM translation_keys WHERE tenant_id=:tenant_id ORDER BY key_group");
    $q->execute(['tenant_id'=>$scope['tenant_id']]);
    $groups=$q->fetchAll(PDO::FETCH_ASSOC);

    return['languages'=>$languages,'groups'=>$groups,'settings'=>trSettings($pdo,$scope['tenant_id'])];
}
function trLanguages(PDO $pdo,array $scope,array $filters):array{
    $where=['l.tenant_id=:tenant_id'];
    $params=['tenant_id'=>$scope['tenant_id']];
    $search=trim((string)($filters['language_search']??''));
    $status=trim((string)($filters['language_status']??''));

    if($search!==''){
        $where[]='(l.language_name LIKE :search OR l.language_code LIKE :search OR l.locale_code LIKE :search)';
        $params['search']='%'.$search.'%';
    }
    if($status!==''&&$status!=='all'){
        $where[]='l.status=:status';
        $params['status']=$status;
    }

    $sort=match((string)($filters['language_sort']??'')){
        'name_desc'=>'l.language_name DESC',
        'code_asc'=>'l.language_code ASC',
        default=>'l.is_default DESC,l.language_name ASC',
    };

    $q=$pdo->prepare("SELECT l.*,
        CASE WHEN COUNT(k.id)=0 THEN 100
             ELSE ROUND(100*SUM(CASE WHEN NULLIF(TRIM(t.translated_text),'') IS NOT NULL THEN 1 ELSE 0 END)/COUNT(k.id),1)
        END AS completion
        FROM translation_languages l
        LEFT JOIN translation_keys k ON k.tenant_id=l.tenant_id AND k.status='active'
        LEFT JOIN translations t ON t.translation_key_id=k.id AND t.language_id=l.id AND t.tenant_id=l.tenant_id
        WHERE ".implode(' AND ',$where)."
        GROUP BY l.id
        ORDER BY $sort");
    $q->execute($params);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
function trKeys(PDO $pdo,array $scope,array $filters):array{
    $where=['tenant_id=:tenant_id'];
    $params=['tenant_id'=>$scope['tenant_id']];
    $search=trim((string)($filters['key_search']??''));
    $group=trim((string)($filters['key_group']??''));
    $status=trim((string)($filters['key_status']??''));

    if($search!==''){
        $where[]='(translation_key LIKE :search OR key_group LIKE :search OR default_text LIKE :search OR description LIKE :search)';
        $params['search']='%'.$search.'%';
    }
    if($group!==''&&$group!=='all'){$where[]='key_group=:key_group';$params['key_group']=$group;}
    if($status!==''&&$status!=='all'){$where[]='status=:status';$params['status']=$status;}

    $sort=match((string)($filters['key_sort']??'')){
        'key_desc'=>'translation_key DESC',
        'group_asc'=>'key_group ASC,translation_key ASC',
        default=>'translation_key ASC',
    };

    $q=$pdo->prepare("SELECT * FROM translation_keys WHERE ".implode(' AND ',$where)." ORDER BY $sort");
    $q->execute($params);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
function trTranslations(PDO $pdo,array $scope,array $filters):array{
    $languageId=(int)($filters['translation_language_id']??0);
    if($languageId<=0){
        $q=$pdo->prepare("SELECT id FROM translation_languages WHERE tenant_id=:tenant_id AND status='active' ORDER BY is_default DESC,id LIMIT 1");
        $q->execute(['tenant_id'=>$scope['tenant_id']]);
        $languageId=(int)$q->fetchColumn();
    }

    if($languageId<=0)return[];

    $where=['k.tenant_id=:tenant_id','k.status=\'active\'','l.id=:language_id','l.tenant_id=:tenant_id2'];
    $params=['tenant_id'=>$scope['tenant_id'],'tenant_id2'=>$scope['tenant_id'],'language_id'=>$languageId];
    $search=trim((string)($filters['translation_search']??''));
    $group=trim((string)($filters['translation_group']??''));
    $state=trim((string)($filters['translation_state']??''));

    if($search!==''){
        $where[]='(k.translation_key LIKE :search OR k.default_text LIKE :search OR t.translated_text LIKE :search)';
        $params['search']='%'.$search.'%';
    }
    if($group!==''&&$group!=='all'){$where[]='k.key_group=:key_group';$params['key_group']=$group;}
    if($state==='translated')$where[]="NULLIF(TRIM(t.translated_text),'') IS NOT NULL";
    if($state==='missing')$where[]="NULLIF(TRIM(t.translated_text),'') IS NULL";

    $sort=match((string)($filters['translation_sort']??'')){
        'key_desc'=>'k.translation_key DESC',
        'updated_desc'=>'t.updated_at DESC,k.translation_key ASC',
        default=>'k.translation_key ASC',
    };

    $q=$pdo->prepare("SELECT
        k.id AS key_id,k.translation_key,k.key_group,k.default_text,
        l.id AS language_id,l.language_name,l.language_code,
        t.id AS translation_id,t.translated_text,t.updated_at
        FROM translation_keys k
        INNER JOIN translation_languages l ON l.id=:language_join_id
        LEFT JOIN translations t ON t.translation_key_id=k.id AND t.language_id=l.id AND t.tenant_id=k.tenant_id
        WHERE ".implode(' AND ',$where)."
        ORDER BY $sort");
    $params['language_join_id']=$languageId;
    $q->execute($params);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
function trMissing(PDO $pdo,array $scope,array $filters):array{
    $where=['k.tenant_id=:tenant_id','k.status=\'active\'','l.tenant_id=:tenant_id2','l.status=\'active\'',"NULLIF(TRIM(t.translated_text),'') IS NULL"];
    $params=['tenant_id'=>$scope['tenant_id'],'tenant_id2'=>$scope['tenant_id']];
    $search=trim((string)($filters['missing_search']??''));
    $languageId=trim((string)($filters['missing_language_id']??''));
    $group=trim((string)($filters['missing_group']??''));

    if($search!==''){$where[]='(k.translation_key LIKE :search OR k.default_text LIKE :search)';$params['search']='%'.$search.'%';}
    if($languageId!==''&&$languageId!=='all'){$where[]='l.id=:language_id';$params['language_id']=(int)$languageId;}
    if($group!==''&&$group!=='all'){$where[]='k.key_group=:key_group';$params['key_group']=$group;}

    $q=$pdo->prepare("SELECT
        k.id AS key_id,k.translation_key,k.key_group,k.default_text,
        l.id AS language_id,l.language_name,l.language_code,
        t.id AS translation_id,t.translated_text
        FROM translation_keys k
        CROSS JOIN translation_languages l
        LEFT JOIN translations t ON t.translation_key_id=k.id AND t.language_id=l.id AND t.tenant_id=k.tenant_id
        WHERE ".implode(' AND ',$where)."
        ORDER BY l.language_name,k.translation_key");
    $q->execute($params);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
function trStats(PDO $pdo,array $scope):array{
    $q=$pdo->prepare("SELECT COUNT(*) FROM translation_languages WHERE tenant_id=:tenant_id");
    $q->execute(['tenant_id'=>$scope['tenant_id']]);
    $languages=(int)$q->fetchColumn();

    $q=$pdo->prepare("SELECT COUNT(*) FROM translation_keys WHERE tenant_id=:tenant_id AND status='active'");
    $q->execute(['tenant_id'=>$scope['tenant_id']]);
    $keys=(int)$q->fetchColumn();

    $q=$pdo->prepare("SELECT COUNT(*)
        FROM translation_keys k
        CROSS JOIN translation_languages l
        LEFT JOIN translations t ON t.translation_key_id=k.id AND t.language_id=l.id AND t.tenant_id=k.tenant_id
        WHERE k.tenant_id=:tenant_id AND k.status='active'
          AND l.tenant_id=:tenant_id2 AND l.status='active'
          AND NULLIF(TRIM(t.translated_text),'') IS NULL");
    $q->execute(['tenant_id'=>$scope['tenant_id'],'tenant_id2'=>$scope['tenant_id']]);
    $missing=(int)$q->fetchColumn();

    $q=$pdo->prepare("SELECT COUNT(*) FROM translation_languages WHERE tenant_id=:tenant_id AND status='active'");
    $q->execute(['tenant_id'=>$scope['tenant_id']]);
    $activeLanguages=(int)$q->fetchColumn();

    $total=$keys*$activeLanguages;
    $completion=$total>0?round(100*($total-$missing)/$total,1):100.0;

    return compact('languages','keys','missing','completion');
}
function trTranslateWithProvider(string $text,string $sourceCode,string $targetCode,array $settings):string{
    $provider=(string)($settings['auto_translate_provider']??'disabled');
    $endpoint=trim((string)($settings['auto_translate_endpoint']??''));
    $apiKey=(string)($settings['auto_translate_api_key']??'');

    if($provider==='disabled'){
        throw new RuntimeException('Auto Translate is disabled in Translation Settings.');
    }
    if($endpoint===''){
        throw new RuntimeException('Auto Translate endpoint is not configured.');
    }
    if(!function_exists('curl_init')){
        throw new RuntimeException('PHP cURL extension is required for Auto Translate.');
    }

    $payload=$provider==='libretranslate'
        ? ['q'=>$text,'source'=>$sourceCode,'target'=>$targetCode,'format'=>'text','api_key'=>$apiKey]
        : ['text'=>$text,'source'=>$sourceCode,'target'=>$targetCode,'api_key'=>$apiKey];

    $curl=curl_init($endpoint);
    curl_setopt_array($curl,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],
        CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
        CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_TIMEOUT=>30,
        CURLOPT_FOLLOWLOCATION=>false,
    ]);
    $response=curl_exec($curl);
    $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
    $error=curl_error($curl);
    curl_close($curl);

    if($response===false||$error!==''){
        throw new RuntimeException('Auto Translate connection failed: '.$error);
    }
    if($status<200||$status>=300){
        throw new RuntimeException('Auto Translate provider returned HTTP '.$status.'.');
    }

    $decoded=json_decode((string)$response,true);
    $translated=trim((string)($decoded['translatedText']??$decoded['translation']??$decoded['text']??''));
    if($translated===''){
        throw new RuntimeException('Auto Translate provider returned an empty translation.');
    }
    return $translated;
}

if(!isset($pdo)||!$pdo instanceof PDO){
    trOut(false,'Database connection unavailable.',[],500);
}
if(session_status()!==PHP_SESSION_ACTIVE){
    session_start();
}
if(empty($_SESSION['translation_csrf_token'])){
    $_SESSION['translation_csrf_token']=bin2hex(random_bytes(32));
}

$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,true);
$scope=trScope();
if($scope['tenant_id']<=0){
    trOut(false,'School tenant session was not found.',[],401);
}

$input=trInput();
$action=strtolower(trim((string)($input['action']??$_GET['action']??'')));

try{
    if($action==='health'){
        $requiredTables=[
            'translation_languages',
            'translation_keys',
            'translations',
            'translation_settings',
        ];
        $missingTables=[];

        foreach($requiredTables as $table){
            $query=$pdo->prepare(
                "SELECT COUNT(*)
                 FROM information_schema.tables
                 WHERE table_schema=DATABASE()
                   AND table_name=:table_name"
            );
            $query->execute(['table_name'=>$table]);

            if((int)$query->fetchColumn()===0){
                $missingTables[]=$table;
            }
        }

        trOut(
            true,
            $missingTables
                ? 'Translations API is running, but database tables are missing.'
                : 'Translations API is running correctly.',
            [
                'database'=>$pdo->query('SELECT DATABASE()')->fetchColumn(),
                'missing_tables'=>$missingTables,
                'session'=>[
                    'tenant_id'=>$scope['tenant_id'],
                    'branch_id'=>$scope['branch_id'],
                    'user_id'=>$scope['user_id'],
                ],
                'api_file'=>__FILE__,
            ]
        );
    }

    $requiredTables=[
        'translation_languages',
        'translation_keys',
        'translations',
        'translation_settings',
    ];

    foreach($requiredTables as $table){
        $query=$pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name=:table_name"
        );
        $query->execute(['table_name'=>$table]);

        if((int)$query->fetchColumn()===0){
            trOut(
                false,
                'Translation database table is missing: '.$table
                    . '. Import database/install_translation_management.sql.',
                [],
                500
            );
        }
    }

    if($action==='list'){
        trOut(true,'Translation management loaded.',[
            'meta'=>trMeta($pdo,$scope),
            'languages'=>trLanguages($pdo,$scope,$_GET),
            'keys'=>trKeys($pdo,$scope,$_GET),
            'translations'=>trTranslations($pdo,$scope,$_GET),
            'missing'=>trMissing($pdo,$scope,$_GET),
            'stats'=>trStats($pdo,$scope),
            'csrf_token'=>$_SESSION['translation_csrf_token'],
        ]);
    }

    if($action==='save'){
        trCsrf($input);
        $type=(string)($input['type']??'');
        $id=(int)($input['id']??0);

        if($type==='language'){
            $name=trim((string)($input['language_name']??''));
            $code=strtolower(trim((string)($input['language_code']??'')));
            $locale=trim((string)($input['locale_code']??''));
            $direction=(string)($input['text_direction']??'ltr');
            $default=(int)($input['is_default']??0);
            $status=(string)($input['status']??'active');

            if($name===''||$code===''||$locale===''){
                throw new InvalidArgumentException('Language name, code and locale are required.');
            }
            if(!preg_match('/^[a-z]{2,10}$/',$code)){
                throw new InvalidArgumentException('Language code must contain 2 to 10 lowercase letters.');
            }
            if(!preg_match('/^[A-Za-z]{2,10}(?:-[A-Za-z0-9]{2,10})?$/',$locale)){
                throw new InvalidArgumentException('Invalid locale code.');
            }
            if(!in_array($direction,['ltr','rtl'],true)||!in_array($status,['active','inactive'],true)){
                throw new InvalidArgumentException('Invalid language configuration.');
            }
            if($default===1&&$status!=='active'){
                throw new InvalidArgumentException('Default language must be active.');
            }

            $q=$pdo->prepare("SELECT id FROM translation_languages WHERE tenant_id=:tenant_id AND (language_code=:code OR locale_code=:locale) AND id<>:id LIMIT 1");
            $q->execute(['tenant_id'=>$scope['tenant_id'],'code'=>$code,'locale'=>$locale,'id'=>$id]);
            if($q->fetchColumn())throw new InvalidArgumentException('Language code or locale already exists.');

            $pdo->beginTransaction();
            if($default===1){
                $q=$pdo->prepare("UPDATE translation_languages SET is_default=0 WHERE tenant_id=:tenant_id");
                $q->execute(['tenant_id'=>$scope['tenant_id']]);
            }

            if($id>0){
                $q=$pdo->prepare("UPDATE translation_languages SET language_name=:name,language_code=:code,locale_code=:locale,text_direction=:direction,is_default=:is_default,status=:status WHERE id=:id AND tenant_id=:tenant_id");
                $q->execute(['name'=>$name,'code'=>$code,'locale'=>$locale,'direction'=>$direction,'is_default'=>$default,'status'=>$status,'id'=>$id,'tenant_id'=>$scope['tenant_id']]);
                if($q->rowCount()===0){
                    $check=$pdo->prepare("SELECT id FROM translation_languages WHERE id=:id AND tenant_id=:tenant_id");
                    $check->execute(['id'=>$id,'tenant_id'=>$scope['tenant_id']]);
                    if(!$check->fetchColumn())throw new InvalidArgumentException('Language not found.');
                }
            }else{
                $q=$pdo->prepare("INSERT INTO translation_languages(tenant_id,language_name,language_code,locale_code,text_direction,is_default,status,created_by) VALUES(:tenant_id,:name,:code,:locale,:direction,:is_default,:status,:created_by)");
                $q->execute(['tenant_id'=>$scope['tenant_id'],'name'=>$name,'code'=>$code,'locale'=>$locale,'direction'=>$direction,'is_default'=>$default,'status'=>$status,'created_by'=>$scope['user_id']?:null]);
                $id=(int)$pdo->lastInsertId();
            }

            if($default===1){
                $q=$pdo->prepare("INSERT INTO translation_settings(tenant_id,setting_key,setting_value) VALUES(:tenant_id,'default_language_id',:value) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
                $q->execute(['tenant_id'=>$scope['tenant_id'],'value'=>(string)$id]);
            }
            $pdo->commit();
            trOut(true,'Language saved successfully.',['id'=>$id]);
        }

        if($type==='key'){
            $key=trim((string)($input['translation_key']??''));
            $group=trim((string)($input['key_group']??'general'));
            $defaultText=trim((string)($input['default_text']??''));
            $description=trim((string)($input['description']??''));
            $status=(string)($input['status']??'active');

            if($key===''||$group===''||$defaultText===''){
                throw new InvalidArgumentException('Translation key, group and default text are required.');
            }
            if(!preg_match('/^[a-zA-Z0-9_.-]{2,190}$/',$key)){
                throw new InvalidArgumentException('Translation key may contain letters, numbers, dot, underscore and hyphen only.');
            }
            if(!in_array($status,['active','inactive'],true)){
                throw new InvalidArgumentException('Invalid translation key status.');
            }

            $q=$pdo->prepare("SELECT id FROM translation_keys WHERE tenant_id=:tenant_id AND translation_key=:translation_key AND id<>:id LIMIT 1");
            $q->execute(['tenant_id'=>$scope['tenant_id'],'translation_key'=>$key,'id'=>$id]);
            if($q->fetchColumn())throw new InvalidArgumentException('Translation key already exists.');

            if($id>0){
                $q=$pdo->prepare("UPDATE translation_keys SET translation_key=:translation_key,key_group=:key_group,default_text=:default_text,description=:description,status=:status WHERE id=:id AND tenant_id=:tenant_id");
                $q->execute(['translation_key'=>$key,'key_group'=>$group,'default_text'=>$defaultText,'description'=>$description?:null,'status'=>$status,'id'=>$id,'tenant_id'=>$scope['tenant_id']]);
                if($q->rowCount()===0){
                    $check=$pdo->prepare("SELECT id FROM translation_keys WHERE id=:id AND tenant_id=:tenant_id");
                    $check->execute(['id'=>$id,'tenant_id'=>$scope['tenant_id']]);
                    if(!$check->fetchColumn())throw new InvalidArgumentException('Translation key not found.');
                }
            }else{
                $q=$pdo->prepare("INSERT INTO translation_keys(tenant_id,translation_key,key_group,default_text,description,status,created_by) VALUES(:tenant_id,:translation_key,:key_group,:default_text,:description,:status,:created_by)");
                $q->execute(['tenant_id'=>$scope['tenant_id'],'translation_key'=>$key,'key_group'=>$group,'default_text'=>$defaultText,'description'=>$description?:null,'status'=>$status,'created_by'=>$scope['user_id']?:null]);
                $id=(int)$pdo->lastInsertId();
            }
            trOut(true,'Translation key saved successfully.',['id'=>$id]);
        }

        if($type==='translation'){
            $keyId=(int)($input['key_id']??0);
            $languageId=(int)($input['language_id']??0);
            $translatedText=trim((string)($input['translated_text']??''));

            if($keyId<=0||$languageId<=0||$translatedText===''){
                throw new InvalidArgumentException('Language, key and translated text are required.');
            }

            $q=$pdo->prepare("SELECT COUNT(*) FROM translation_keys WHERE id=:id AND tenant_id=:tenant_id");
            $q->execute(['id'=>$keyId,'tenant_id'=>$scope['tenant_id']]);
            if((int)$q->fetchColumn()===0)throw new InvalidArgumentException('Translation key not found.');

            $q=$pdo->prepare("SELECT COUNT(*) FROM translation_languages WHERE id=:id AND tenant_id=:tenant_id AND status='active'");
            $q->execute(['id'=>$languageId,'tenant_id'=>$scope['tenant_id']]);
            if((int)$q->fetchColumn()===0)throw new InvalidArgumentException('Language not found or inactive.');

            $q=$pdo->prepare("INSERT INTO translations(tenant_id,translation_key_id,language_id,translated_text,status,updated_by) VALUES(:tenant_id,:key_id,:language_id,:translated_text,'active',:updated_by) ON DUPLICATE KEY UPDATE translated_text=VALUES(translated_text),status='active',updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP");
            $q->execute(['tenant_id'=>$scope['tenant_id'],'key_id'=>$keyId,'language_id'=>$languageId,'translated_text'=>$translatedText,'updated_by'=>$scope['user_id']?:null]);

            trOut(true,'Translation saved successfully.');
        }

        throw new InvalidArgumentException('Invalid translation record type.');
    }

    if($action==='delete'){
        trCsrf($input);
        $type=(string)($input['type']??'');
        $id=(int)($input['id']??0);
        if($id<=0)throw new InvalidArgumentException('Valid record ID is required.');

        if($type==='language'){
            $q=$pdo->prepare("SELECT is_default FROM translation_languages WHERE id=:id AND tenant_id=:tenant_id");
            $q->execute(['id'=>$id,'tenant_id'=>$scope['tenant_id']]);
            $isDefault=$q->fetchColumn();
            if($isDefault===false)throw new InvalidArgumentException('Language not found.');
            if((int)$isDefault===1)throw new InvalidArgumentException('Default language cannot be deleted.');

            $pdo->beginTransaction();
            $q=$pdo->prepare("DELETE FROM translations WHERE tenant_id=:tenant_id AND language_id=:id");
            $q->execute(['tenant_id'=>$scope['tenant_id'],'id'=>$id]);
            $q=$pdo->prepare("DELETE FROM translation_languages WHERE id=:id AND tenant_id=:tenant_id");
            $q->execute(['id'=>$id,'tenant_id'=>$scope['tenant_id']]);
            $pdo->commit();
            trOut(true,'Language deleted successfully.');
        }

        if($type==='key'){
            $pdo->beginTransaction();
            $q=$pdo->prepare("SELECT id FROM translation_keys WHERE id=:id AND tenant_id=:tenant_id FOR UPDATE");
            $q->execute(['id'=>$id,'tenant_id'=>$scope['tenant_id']]);
            if(!$q->fetchColumn())throw new InvalidArgumentException('Translation key not found.');
            $q=$pdo->prepare("DELETE FROM translations WHERE tenant_id=:tenant_id AND translation_key_id=:id");
            $q->execute(['tenant_id'=>$scope['tenant_id'],'id'=>$id]);
            $q=$pdo->prepare("DELETE FROM translation_keys WHERE id=:id AND tenant_id=:tenant_id");
            $q->execute(['id'=>$id,'tenant_id'=>$scope['tenant_id']]);
            $pdo->commit();
            trOut(true,'Translation key deleted successfully.');
        }

        throw new InvalidArgumentException('Invalid delete type.');
    }

    if($action==='save_settings'){
        trCsrf($input);
        $defaultLanguageId=(int)($input['default_language_id']??0);
        $fallbackLanguageId=(int)($input['fallback_language_id']??0);
        $provider=(string)($input['auto_translate_provider']??'disabled');
        $endpoint=trim((string)($input['auto_translate_endpoint']??''));
        $apiKey=(string)($input['auto_translate_api_key']??'');
        $missingMode=(string)($input['missing_translation_mode']??'default_text');
        $cacheVersion=trim((string)($input['cache_version']??'1'));

        if($defaultLanguageId<=0||$fallbackLanguageId<=0){
            throw new InvalidArgumentException('Default and fallback languages are required.');
        }
        if(!in_array($provider,['disabled','libretranslate','custom'],true)){
            throw new InvalidArgumentException('Invalid Auto Translate provider.');
        }
        if($provider!=='disabled'&&$endpoint===''){
            throw new InvalidArgumentException('Provider endpoint is required.');
        }
        if(!in_array($missingMode,['default_text','key','blank'],true)){
            throw new InvalidArgumentException('Invalid missing translation behaviour.');
        }

        $q=$pdo->prepare("SELECT COUNT(*) FROM translation_languages WHERE tenant_id=:tenant_id AND id IN(:default_id,:fallback_id) AND status='active'");
        $q->execute(['tenant_id'=>$scope['tenant_id'],'default_id'=>$defaultLanguageId,'fallback_id'=>$fallbackLanguageId]);
        if((int)$q->fetchColumn()<($defaultLanguageId===$fallbackLanguageId?1:2)){
            throw new InvalidArgumentException('Default or fallback language is invalid.');
        }

        $settings=[
            'default_language_id'=>(string)$defaultLanguageId,
            'fallback_language_id'=>(string)$fallbackLanguageId,
            'auto_translate_provider'=>$provider,
            'auto_translate_endpoint'=>$endpoint,
            'missing_translation_mode'=>$missingMode,
            'cache_version'=>$cacheVersion?:'1',
        ];
        if($apiKey!=='')$settings['auto_translate_api_key']=$apiKey;

        $pdo->beginTransaction();
        $upsert=$pdo->prepare("INSERT INTO translation_settings(tenant_id,setting_key,setting_value) VALUES(:tenant_id,:setting_key,:setting_value) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        foreach($settings as $key=>$value){
            $upsert->execute(['tenant_id'=>$scope['tenant_id'],'setting_key'=>$key,'setting_value'=>$value]);
        }
        $q=$pdo->prepare("UPDATE translation_languages SET is_default=CASE WHEN id=:default_id THEN 1 ELSE 0 END WHERE tenant_id=:tenant_id");
        $q->execute(['default_id'=>$defaultLanguageId,'tenant_id'=>$scope['tenant_id']]);
        $pdo->commit();

        trOut(true,'Translation settings saved successfully.');
    }

    if($action==='import'){
        trCsrf($input);
        $format=(string)($input['format']??'json');
        $languageId=(int)($input['language_id']??0);
        $content=(string)($input['content']??'');

        if($languageId<=0||trim($content)===''){
            throw new InvalidArgumentException('Language and import file are required.');
        }

        $q=$pdo->prepare("SELECT id FROM translation_languages WHERE id=:id AND tenant_id=:tenant_id");
        $q->execute(['id'=>$languageId,'tenant_id'=>$scope['tenant_id']]);
        if(!$q->fetchColumn())throw new InvalidArgumentException('Language not found.');

        $pairs=[];
        if($format==='json'){
            $decoded=json_decode($content,true,512,JSON_THROW_ON_ERROR);
            if(!is_array($decoded))throw new InvalidArgumentException('Invalid JSON translation file.');
            foreach($decoded as $key=>$value){
                if(is_scalar($value))$pairs[(string)$key]=(string)$value;
            }
        }elseif($format==='csv'){
            $stream=fopen('php://temp','r+');
            fwrite($stream,$content);
            rewind($stream);
            $header=fgetcsv($stream);
            if(!$header)throw new InvalidArgumentException('CSV header is missing.');
            $header=array_map(fn($v)=>strtolower(trim((string)$v)),$header);
            $map=array_flip($header);
            if(!isset($map['translation_key'],$map['translated_text'])){
                throw new InvalidArgumentException('CSV requires translation_key and translated_text columns.');
            }
            while(($row=fgetcsv($stream))!==false){
                $key=trim((string)($row[$map['translation_key']]??''));
                $value=trim((string)($row[$map['translated_text']]??''));
                if($key!=='')$pairs[$key]=$value;
            }
            fclose($stream);
        }else{
            throw new InvalidArgumentException('Unsupported import format.');
        }

        $pdo->beginTransaction();
        $find=$pdo->prepare("SELECT id FROM translation_keys WHERE tenant_id=:tenant_id AND translation_key=:translation_key LIMIT 1");
        $upsert=$pdo->prepare("INSERT INTO translations(tenant_id,translation_key_id,language_id,translated_text,status,updated_by) VALUES(:tenant_id,:key_id,:language_id,:translated_text,'active',:updated_by) ON DUPLICATE KEY UPDATE translated_text=VALUES(translated_text),status='active',updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP");
        $count=0;
        foreach($pairs as $key=>$value){
            $find->execute(['tenant_id'=>$scope['tenant_id'],'translation_key'=>$key]);
            $keyId=(int)$find->fetchColumn();
            if($keyId<=0)continue;
            $upsert->execute(['tenant_id'=>$scope['tenant_id'],'key_id'=>$keyId,'language_id'=>$languageId,'translated_text'=>$value,'updated_by'=>$scope['user_id']?:null]);
            $count++;
        }
        $pdo->commit();

        trOut(true,$count.' translation(s) imported successfully.');
    }

    if($action==='export'){
        $format=strtolower((string)($_GET['format']??'json'));
        $languageId=(int)($_GET['language_id']??0);
        $group=trim((string)($_GET['group']??'all'));

        $filters=['translation_language_id'=>$languageId,'translation_group'=>$group,'translation_state'=>'all','translation_search'=>'','translation_sort'=>'key_asc'];
        $rows=trTranslations($pdo,$scope,$filters);

        if($format==='json'){
            $result=[];
            foreach($rows as $row)$result[$row['translation_key']]=$row['translated_text']?:$row['default_text'];
            while(ob_get_level()>0)ob_end_clean();
            header('Content-Type:application/json; charset=utf-8');
            header('Content-Disposition:attachment; filename="translations-'.date('Ymd-His').'.json"');
            echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        while(ob_get_level()>0)ob_end_clean();

        if($format==='excel'){
            header('Content-Type:application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition:attachment; filename="translations-'.date('Ymd-His').'.xls"');
            echo "\xEF\xBB\xBF";
            echo "translation_key\tgroup\tdefault_text\ttranslated_text\tlanguage\n";
            foreach($rows as $row){
                echo str_replace(["\t","\r","\n"],' ',$row['translation_key'])."\t"
                    .str_replace(["\t","\r","\n"],' ',$row['key_group'])."\t"
                    .str_replace(["\t","\r","\n"],' ',$row['default_text'])."\t"
                    .str_replace(["\t","\r","\n"],' ',(string)$row['translated_text'])."\t"
                    .str_replace(["\t","\r","\n"],' ',$row['language_name'])."\n";
            }
            exit;
        }

        header('Content-Type:text/csv; charset=utf-8');
        header('Content-Disposition:attachment; filename="translations-'.date('Ymd-His').'.csv"');
        $file=fopen('php://output','wb');
        fputcsv($file,['translation_key','group','default_text','translated_text','language']);
        foreach($rows as $row){
            fputcsv($file,[$row['translation_key'],$row['key_group'],$row['default_text'],$row['translated_text'],$row['language_name']]);
        }
        fclose($file);
        exit;
    }

    if($action==='auto_translate'){
        trCsrf($input);
        $languageId=(string)($input['language_id']??'all');
        $settings=trSettings($pdo,$scope['tenant_id']);

        $where=['k.tenant_id=:tenant_id','k.status=\'active\'','l.tenant_id=:tenant_id2','l.status=\'active\'',"NULLIF(TRIM(t.translated_text),'') IS NULL"];
        $params=['tenant_id'=>$scope['tenant_id'],'tenant_id2'=>$scope['tenant_id']];
        if($languageId!=='all'&&(int)$languageId>0){
            $where[]='l.id=:language_id';
            $params['language_id']=(int)$languageId;
        }

        $q=$pdo->prepare("SELECT k.id AS key_id,k.default_text,l.id AS language_id,l.language_code
            FROM translation_keys k
            CROSS JOIN translation_languages l
            LEFT JOIN translations t ON t.translation_key_id=k.id AND t.language_id=l.id AND t.tenant_id=k.tenant_id
            WHERE ".implode(' AND ',$where)."
            ORDER BY l.id,k.id
            LIMIT 100");
        $q->execute($params);
        $rows=$q->fetchAll(PDO::FETCH_ASSOC);

        if(!$rows)trOut(true,'No missing translations found.');

        $sourceLanguage='en';
        $defaultId=(int)($settings['default_language_id']??0);
        if($defaultId>0){
            $q=$pdo->prepare("SELECT language_code FROM translation_languages WHERE id=:id AND tenant_id=:tenant_id");
            $q->execute(['id'=>$defaultId,'tenant_id'=>$scope['tenant_id']]);
            $sourceLanguage=(string)($q->fetchColumn()?:'en');
        }

        $upsert=$pdo->prepare("INSERT INTO translations(tenant_id,translation_key_id,language_id,translated_text,status,updated_by) VALUES(:tenant_id,:key_id,:language_id,:translated_text,'active',:updated_by) ON DUPLICATE KEY UPDATE translated_text=VALUES(translated_text),status='active',updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP");
        $count=0;
        foreach($rows as $row){
            $translated=trTranslateWithProvider((string)$row['default_text'],$sourceLanguage,(string)$row['language_code'],$settings);
            $upsert->execute(['tenant_id'=>$scope['tenant_id'],'key_id'=>$row['key_id'],'language_id'=>$row['language_id'],'translated_text'=>$translated,'updated_by'=>$scope['user_id']?:null]);
            $count++;
        }

        trOut(true,$count.' missing translation(s) generated successfully.');
    }

    trOut(false,'Invalid Translation Management action.',[],400);
}catch(JsonException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    trOut(false,'Invalid JSON file: '.$error->getMessage(),[],422);
}catch(InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    trOut(false,$error->getMessage(),[],422);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    error_log('translations.php: '.$error->getMessage());
    trOut(false,'Translation request failed: '.$error->getMessage(),[],500);
}
