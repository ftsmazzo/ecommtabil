<?php

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use MatthiasMullie\Minify;
use MatthiasMullie\Minify\CSS;
use MatthiasMullie\Minify\JS;

$sourcePath = dirname(__DIR__, 2) . "/public/assets";

/* JS Login */
// $loginJS = new JS();
// $loginJS
//     ->add($sourcePath . "/dist/bootstrap/js/bootstrap.bundle.min.js")
//     ->add($sourcePath . "/dist/jquery/jquery.4.0.0.min.js")
//     ->add($sourcePath . "/dist/validate/jquery.validate.1.19.5.js")
//     ->add($sourcePath . "/dist/validate/localization/messages_pt_BR.js")
//     ->add($sourcePath . "/app/scripts/login.js")
//     ->minify($sourcePath . "/app/js/login.min.js");

/**
 * CSS ÚTIL
 * plugins comuns
 */
// $utilCSS = new CSS();
// $utilCSS
//     ->add($sourcePath . "/dist/animate/animate.min.css") /** Animate */
//     ->add($sourcePath . "/dist/jquery-confirm/css/jquery-confirm.css") /** jQuery Confirm */
//     ->add($sourcePath . "/dist/toastr/toastr.min.css") /** Toastr */
//     ->add($sourcePath . "/dist/notyf/notyf.min.css") /** Notyf Toastr */
//     ->add($sourcePath . "/dist/datatables/css/dataTables.bootstrap5.css") /** DataTables Bootstrap */
//     ->add($sourcePath . "/dist/tabulator/css/tabulator.min.css") /** Tabulator */
//     ->add($sourcePath . "/dist/tabulator/css/tabulator_bootstrap5.min.css") /** Tabulator Bootstrap */
//     ->minify($sourcePath . "/app/css/util.min.css");

/**
 * JS co todos os scripts uteis
 */
// $utilJS = new JS();
// $utilJS
//     ->add($sourcePath . "/dist/bootstrap/js/bootstrap.bundle.min.js")
//     ->add($sourcePath . "/dist/jquery/jquery.3.7.1.min.js")
//     ->add($sourcePath . "/dist/datatables/js/jquery.dataTables.min.js")
//     ->add($sourcePath . "/dist/datatables/js/dataTables.bootstrap5.js")
//     ->add($sourcePath . "/dist/validate/jquery.validate.1.19.5.js")
//     ->add($sourcePath . "/dist/validate/additional-methods.js")
//     ->add($sourcePath . "/dist/validate/localization/messages_pt_BR.js")
//     ->add($sourcePath . "/dist/webpush/webpush-register.js")
//     ->add($sourcePath . "/dist/toastr/toastr.min.js")
//     ->add($sourcePath . "/dist/notyf/notyf.min.js")
//     ->add($sourcePath . "/dist/tabulator/js/tabulator.min.js")
//     ->add($sourcePath . "/dist/simplebar/simplebar.min.js")
//     ->add($sourcePath . "/dist/jquery-confirm/js/jquery-confirm.js")
//     ->add($sourcePath . "/dist/mask/jquery.mask.js")
//     ->minify($sourcePath . "/app/js/util.min.js");

/**
 * APP
 * bootstrap + tema
 */
$appCSS = new CSS();
$appCSS
    ->add($sourcePath . "/dist/bootstrap/css/bootstrap.css") /* Bootstrap */
    ->add($sourcePath . "/app/css/volt.css") /* Volt Theme */
    ->add($sourcePath . "/app/css/style.min.css") /* Custom Style */
    ->add($sourcePath . "/app/css/web-awesome.css") /* Custom Web Awesome */
    ->minify($sourcePath . "/app/css/app.min.css");

$overridesCSS = new CSS();
$overridesCSS
    ->add($sourcePath . "/app/scss/_overrides.scss") /* Global overrides */
    ->minify($sourcePath . "/app/css/overrides.min.css");

/**
 * Js do Sistema
 */
$appJS = new JS();
$appJS
    ->add($sourcePath . "/app/scripts/app.js") // Helpers
    ->add($sourcePath . "/app/scripts/helpers.js") // Helpers
    ->add($sourcePath . "/app/scripts/util.js") // Scripts Uteis para todos
    ->add($sourcePath . "/app/scripts/forms.js") // Helpers de formulario
    ->minify($sourcePath . "/app/js/app.min.js");

/**
 * JS DO CENÁRIO ADMIN
 */
$adminJS = new JS();
$adminJS
    ->add($sourcePath . "/admin/scripts/tables.js")
    ->minify($sourcePath . "/admin/js/admin.min.js");
