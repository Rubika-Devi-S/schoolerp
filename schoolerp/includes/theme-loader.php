<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$themeDefaults = [
    'sidebar_bg'=>'#ffffff','sidebar_text'=>'#334155','sidebar_active_bg_1'=>'#6d4df2',
    'sidebar_active_bg_2'=>'#3559dc','sidebar_active_text'=>'#ffffff','sidebar_hover_bg'=>'#eef2ff',
    'sidebar_hover_text'=>'#27305f','topbar_bg_1'=>'#653dd8','topbar_bg_2'=>'#1959c8',
    'topbar_text'=>'#ffffff','body_bg'=>'#f6f8fc','card_bg'=>'#ffffff','text_main'=>'#101b46',
    'text_muted'=>'#6c7895','border_soft'=>'#e5e9f2','brand_1'=>'#6747e8','brand_2'=>'#2f62d7',
    'success_color'=>'#21ae71','warning_color'=>'#ff9f1a','danger_color'=>'#f54267','info_color'=>'#3478f6',
    'layout_density'=>'comfortable'
];
$theme = $themeDefaults;

if (!APP_DEMO_MODE && $pdo) {
    $stmt = $pdo->prepare('SELECT setting_key, setting_value FROM website_color_settings WHERE tenant_id=:tenant_id AND is_active=1');
    $stmt->execute(['tenant_id'=>(int)($_SESSION['tenant_id'] ?? 1)]);
    foreach ($stmt->fetchAll() as $row) {
        if (array_key_exists($row['setting_key'], $theme)) $theme[$row['setting_key']] = $row['setting_value'];
    }
}
?>
<style id="databaseThemeVariables">
:root{
<?php foreach ($theme as $key=>$value): if ($key==='layout_density') continue; ?>
--<?= e(str_replace('_','-',$key)) ?>:<?= e($value) ?>;
<?php endforeach; ?>
--layout-density:<?= e($theme['layout_density']) ?>;
}
</style>
