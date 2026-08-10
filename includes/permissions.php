<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';


function school_permission_key_for_page(string $pageKey): string
{
    $key = strtolower(trim($pageKey));

    $map = [

        // Dashboard
        'dashboard' => 'dashboard',

        // Student
        'student_management' => 'student_management',
        'students' => 'student_management',
        'admission' => 'student_management',

        // Staff
        'staff' => 'staff_management',
        'teacher_management' => 'staff_management',

        // Fee Management
        'fee_management' => 'fee_management',
        'fees' => 'fee_management',
        'fee_setup' => 'fee_management',
        'fee_structure' => 'fee_management',
        'fee_collection' => 'fee_management',
        'fee_assignment' => 'fee_management',
        'fee_receipts' => 'fee_management',
        'fee_reports' => 'fee_management',
        'due_fees' => 'fee_management',
        'discount_management' => 'fee_management',
        'scholarship_management' => 'fee_management',
        'fine_management' => 'fee_management',
        'refund_management' => 'fee_management',
        'online_payment' => 'fee_management',


        // Accounts
        'accounts' => 'accounts_management',

        'income_management' => 'income_management',
        'income' => 'income_management',
        'accounts_income' => 'income_management',

        'expense_management' => 'expense_management',
        'expenses' => 'expense_management',


        // Reports
        'reports' => 'reports_access',
        'income_reports' => 'reports_access',
        'student_reports' => 'reports_access',


        // Settings
        'settings' => 'settings_access',
        'general_settings' => 'settings_access',

    ];


    if(isset($map[$key])){
        return $map[$key];
    }


    foreach([
        'fee'=>'fee_management',
        'income'=>'income_management',
        'expense'=>'expense_management',
        'student'=>'student_management',
        'staff'=>'staff_management',
        'report'=>'reports_access',
        'setting'=>'settings_access',
    ] as $prefix=>$permission){

        if(str_starts_with($key,$prefix)){
            return $permission;
        }
    }


    return $key;
}



function has_permission(
    string $pageKey,
    string $action='view'
): bool
{

    global $pdo;

    $user=current_user();


    /*
    Super Admin bypass
    */

    $role=strtolower(trim($user['role_name'] ?? ''));


    if(
        in_array(
            $role,
            [
                'super administrator',
                'super admin',
                'administrator',
                'admin'
            ],
            true
        )
    ){
        return true;
    }



    if(APP_DEMO_MODE || !$pdo){
        return true;
    }



    $columnMap=[

        'view'=>'can_view',
        'open'=>'can_open',
        'create'=>'can_create',
        'edit'=>'can_edit',
        'delete'=>'can_delete',
        'restore'=>'can_restore',
        'approve'=>'can_approve',
        'reject'=>'can_reject',
        'print'=>'can_print',
        'export'=>'can_export',
        'import'=>'can_import',
        'assign'=>'can_assign',
        'publish'=>'can_publish',
        'lock'=>'can_lock'

    ];


    $column=$columnMap[$action] ?? null;


    if(!$column){
        return false;
    }



    $permissionKey=
        school_permission_key_for_page($pageKey);



    $sql="
        SELECT COALESCE(MAX(rp.$column),0)

        FROM role_page_permissions rp

        INNER JOIN app_pages p
        ON p.id = rp.page_id

        WHERE
            rp.role_id=:role_id
        AND
            p.page_key IN(:page_key,:permission_key)
        AND
            p.is_active=1
    ";


    $stmt=$pdo->prepare($sql);


    $stmt->execute([

        'role_id'=>$user['role_id'],

        'page_key'=>$pageKey,

        'permission_key'=>$permissionKey

    ]);



    return (int)$stmt->fetchColumn()===1;

}




function require_page_permission(string $pageKey):void
{

    require_login();


    if(!has_permission($pageKey,'view')){


        http_response_code(403);


        exit(
            'You do not have permission to access '
            .$pageKey
        );

    }

}