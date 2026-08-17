<?php
declare(strict_types=1);

/*
 * Parent Fee Payment API façade
 * Location: parent/api/fee_payment.php
 * Build: 2026-08-13-parent-fee-payment-v31
 *
 * No fee calculation is duplicated here.
 * - Fee detail/history delegates to existing api/fee-collection.php.
 * - Razorpay initiate/verify/cancel delegates to existing api/online-payment.php.
 * - This layer only enforces Parent -> linked Student ownership and Parent
 *   Sidebar Permission before those existing ERP actions are reached.
 */

ob_start();
ini_set('display_errors','0');
error_reporting(E_ALL);

require_once dirname(__DIR__,2).'/includes/bootstrap.php';
require_once dirname(__DIR__,2).'/includes/sidebar-manager.php';
require_once dirname(__DIR__,2).'/includes/permission-chain.php';

function pfpOut(
    bool $success,
    string $message='',
    array $data=[],
    int $status=200
):never{
    while(ob_get_level()>0)ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(
        [
            'success'=>$success,
            'message'=>$message,
            'data'=>$data,
        ],
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
    );
    exit;
}

function pfpInput():array{
    $json=json_decode(
        (string)file_get_contents('php://input'),
        true
    );
    return is_array($json)?$json:$_POST;
}

function pfpTable(PDO $pdo,string $table):bool{
    $q=$pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema=DATABASE()
           AND table_name=:table"
    );
    $q->execute(['table'=>$table]);
    return (int)$q->fetchColumn()>0;
}

function pfpColumn(
    PDO $pdo,
    string $table,
    string $column
):bool{
    $q=$pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema=DATABASE()
           AND table_name=:table
           AND column_name=:column"
    );
    $q->execute([
        'table'=>$table,
        'column'=>$column,
    ]);
    return (int)$q->fetchColumn()>0;
}

function pfpScope():array{
    $u=function_exists('current_user')
        ?current_user()
        :[];
    $u=is_array($u)?$u:[];

    return[
        'tenant_id'=>(int)(
            $u['tenant_id']
            ??$u['school_id']
            ??$_SESSION['tenant_id']
            ??$_SESSION['school_id']
            ??0
        ),
        'user_id'=>(int)(
            $u['id']
            ??$u['user_id']
            ??$_SESSION['user_id']
            ??0
        ),
        'role_id'=>(int)(
            $u['role_id']
            ??$_SESSION['role_id']
            ??0
        ),
        'role_key'=>pc_role_key(
            (string)(
                $u['role_key']
                ??$_SESSION['role_key']
                ??''
            )
        ),
        'email'=>(string)($u['email']??''),
        'mobile'=>(string)($u['mobile']??''),
    ];
}

function pfpStudentIds(
    PDO $pdo,
    array $scope
):array{
    $tenant=(int)$scope['tenant_id'];
    $user=(int)$scope['user_id'];
    $ids=[];

    if(pfpTable($pdo,'student_parent_logins')){
        $q=$pdo->prepare(
            "SELECT DISTINCT student_id
             FROM student_parent_logins
             WHERE tenant_id=:tenant
               AND user_id=:user
               AND status='active'"
        );
        $q->execute([
            'tenant'=>$tenant,
            'user'=>$user,
        ]);
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id){
            $id=(int)$id;
            if($id>0)$ids[$id]=true;
        }
    }

    if($ids)return array_keys($ids);

    if(
        pfpTable($pdo,'guardians')
        &&pfpTable($pdo,'student_guardians')
    ){
        if(pfpColumn($pdo,'guardians','user_id')){
            $q=$pdo->prepare(
                "SELECT DISTINCT sg.student_id
                 FROM guardians g
                 INNER JOIN student_guardians sg
                    ON sg.guardian_id=g.id
                 WHERE g.tenant_id=:tenant
                   AND g.user_id=:user"
            );
            $q->execute([
                'tenant'=>$tenant,
                'user'=>$user,
            ]);
            foreach(
                $q->fetchAll(PDO::FETCH_COLUMN)
                as $id
            ){
                $id=(int)$id;
                if($id>0)$ids[$id]=true;
            }
        }

        if(!$ids){
            $email=strtolower(
                trim((string)$scope['email'])
            );
            $mobile=preg_replace(
                '/\D+/',
                '',
                (string)$scope['mobile']
            )??'';

            $q=$pdo->prepare(
                "SELECT id,email,mobile
                 FROM guardians
                 WHERE tenant_id=:tenant
                 ORDER BY id"
            );
            $q->execute(['tenant'=>$tenant]);

            $guardianIds=[];
            foreach(
                $q->fetchAll(PDO::FETCH_ASSOC)
                as $guardian
            ){
                $gEmail=strtolower(
                    trim(
                        (string)(
                            $guardian['email']
                            ??''
                        )
                    )
                );
                $gMobile=preg_replace(
                    '/\D+/',
                    '',
                    (string)(
                        $guardian['mobile']
                        ??''
                    )
                )??'';

                if(
                    (
                        $email!==''
                        &&$gEmail!==''
                        &&$email===$gEmail
                    )
                    ||(
                        $mobile!==''
                        &&$gMobile!==''
                        &&$mobile===$gMobile
                    )
                ){
                    $guardianIds[]=(int)$guardian['id'];
                }
            }

            if($guardianIds){
                $marks=implode(
                    ',',
                    array_fill(
                        0,
                        count($guardianIds),
                        '?'
                    )
                );

                $q=$pdo->prepare(
                    "SELECT DISTINCT student_id
                     FROM student_guardians
                     WHERE guardian_id IN($marks)"
                );
                $q->execute($guardianIds);

                foreach(
                    $q->fetchAll(PDO::FETCH_COLUMN)
                    as $id
                ){
                    $id=(int)$id;
                    if($id>0)$ids[$id]=true;
                }
            }
        }
    }

    return array_keys($ids);
}

function pfpRequireStudent(
    PDO $pdo,
    array $scope,
    int $studentId
):void{
    if(
        $studentId<=0
        ||!in_array(
            $studentId,
            pfpStudentIds($pdo,$scope),
            true
        )
    ){
        pfpOut(
            false,
            'This student is not linked to your Parent account.',
            [],
            403
        );
    }
}

function pfpPaymentEnabled(
    PDO $pdo,
    array $scope
):bool{
    $items=school_sidebar_get_items(
        $pdo,
        (int)$scope['role_id'],
        (int)$scope['tenant_id']
    );

    foreach($items as $item){
        if(
            (string)($item['menu_key']??'')
            ==='parent_fee_payment'
        ){
            return true;
        }
    }

    return false;
}

function pfpGatewayMeta(
    PDO $pdo,
    array $scope
):array{
    if(empty($_SESSION['fee_csrf_token'])){
        $_SESSION['fee_csrf_token']=
            bin2hex(random_bytes(32));
    }

    $settings=[];
    if(pfpTable($pdo,'fee_settings')){
        $q=$pdo->prepare(
            "SELECT setting_key,setting_value
             FROM fee_settings
             WHERE tenant_id=:tenant
               AND setting_key IN(
                    'online_gateway',
                    'razorpay_key_id',
                    'razorpay_key_secret',
                    'currency_code'
               )"
        );
        $q->execute([
            'tenant'=>$scope['tenant_id'],
        ]);

        foreach(
            $q->fetchAll(PDO::FETCH_ASSOC)
            as $row
        ){
            $settings[
                (string)$row['setting_key']
            ]=(string)$row['setting_value'];
        }
    }

    return[
        'csrf_token'=>
            (string)$_SESSION['fee_csrf_token'],
        'gateway'=>[
            'name'=>strtolower(
                $settings['online_gateway']
                ??'razorpay'
            ),
            'configured'=>
                trim(
                    $settings['razorpay_key_id']
                    ??''
                )!==''
                &&trim(
                    $settings['razorpay_key_secret']
                    ??''
                )!=='',
            'currency'=>strtoupper(
                $settings['currency_code']
                ??'INR'
            ),
        ],
    ];
}

if(!isset($pdo)||!($pdo instanceof PDO)){
    pfpOut(
        false,
        'Database connection unavailable.',
        [],
        500
    );
}

if(session_status()!==PHP_SESSION_ACTIVE){
    session_start();
}

$scope=pfpScope();

if(
    $scope['tenant_id']<=0
    ||$scope['user_id']<=0
){
    pfpOut(
        false,
        'Parent session was not found.',
        [],
        401
    );
}

if($scope['role_key']!=='parent'){
    pfpOut(
        false,
        'Fee Payment is available only to Parent accounts.',
        [],
        403
    );
}

if(!pfpPaymentEnabled($pdo,$scope)){
    pfpOut(
        false,
        'Fee Payment is disabled for this Parent account.',
        [],
        403
    );
}

$input=pfpInput();
$action=strtolower(
    trim(
        (string)(
            $input['action']
            ??$_GET['action']
            ??'meta'
        )
    )
);

try{
    if($action==='meta'){
        pfpOut(
            true,
            'Parent Fee Payment metadata loaded.',
            pfpGatewayMeta($pdo,$scope)
        );
    }

    if(
        $action==='detail'
        ||$action==='student_history'
    ){
        $studentId=(int)(
            $_GET['student_id']
            ??$input['student_id']
            ??0
        );

        pfpRequireStudent(
            $pdo,
            $scope,
            $studentId
        );

        /*
         * Delegate fee totals, previous/current/future balances and history
         * to the existing Fee Collection API.
         */
        require dirname(__DIR__,2)
            .'/api/fee-collection.php';
        exit;
    }

    if($action==='initiate'){
        $assignmentId=(int)(
            $input['assignment_id']
            ??0
        );

        $q=$pdo->prepare(
            "SELECT student_id
             FROM student_fee_assignments
             WHERE id=:id
               AND tenant_id=:tenant
               AND assignment_status='active'
             LIMIT 1"
        );
        $q->execute([
            'id'=>$assignmentId,
            'tenant'=>$scope['tenant_id'],
        ]);
        $studentId=(int)($q->fetchColumn()?:0);

        pfpRequireStudent(
            $pdo,
            $scope,
            $studentId
        );

        require dirname(__DIR__,2)
            .'/api/online-payment.php';
        exit;
    }

    if(
        $action==='verify'
        ||$action==='cancel'
    ){
        $transactionId=(int)(
            $input['transaction_id']
            ??0
        );

        $q=$pdo->prepare(
            "SELECT student_id,created_by
             FROM online_payment_transactions
             WHERE id=:id
               AND tenant_id=:tenant
             LIMIT 1"
        );
        $q->execute([
            'id'=>$transactionId,
            'tenant'=>$scope['tenant_id'],
        ]);
        $transaction=$q->fetch(PDO::FETCH_ASSOC);

        if(!$transaction){
            throw new InvalidArgumentException(
                'Online transaction not found.'
            );
        }

        pfpRequireStudent(
            $pdo,
            $scope,
            (int)$transaction['student_id']
        );

        if(
            (int)($transaction['created_by']??0)>0
            &&(int)$transaction['created_by']
                !==(int)$scope['user_id']
        ){
            pfpOut(
                false,
                'This online payment does not belong to your Parent account.',
                [],
                403
            );
        }

        require dirname(__DIR__,2)
            .'/api/online-payment.php';
        exit;
    }

    if(
        $action==='receipt_print'
        ||$action==='receipt_pdf'
    ){
        $receiptId=(int)(
            $_GET['receipt_id']
            ??$input['receipt_id']
            ??0
        );

        $q=$pdo->prepare(
            "SELECT student_id
             FROM fee_receipts
             WHERE id=:id
               AND tenant_id=:tenant
             LIMIT 1"
        );
        $q->execute([
            'id'=>$receiptId,
            'tenant'=>$scope['tenant_id'],
        ]);
        $studentId=(int)($q->fetchColumn()?:0);

        pfpRequireStudent(
            $pdo,
            $scope,
            $studentId
        );

        $_GET['action']=$action==='receipt_pdf'
            ?'pdf_receipt'
            :'print_receipt';
        $_GET['id']=$receiptId;

        require dirname(__DIR__,2)
            .'/api/fee-collection.php';
        exit;
    }

    pfpOut(
        false,
        'Invalid Parent Fee Payment action.',
        [],
        400
    );
}catch(InvalidArgumentException $e){
    pfpOut(
        false,
        $e->getMessage(),
        [],
        422
    );
}catch(Throwable $e){
    error_log(
        'Parent Fee Payment API: '
        .$e->getMessage()
    );

    $status=(int)$e->getCode();
    if($status<400||$status>599){
        $status=500;
    }

    pfpOut(
        false,
        $status>=500
            ?'Unable to process Parent Fee Payment.'
            :$e->getMessage(),
        [],
        $status
    );
}
