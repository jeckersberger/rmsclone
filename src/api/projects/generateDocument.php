<?php
require_once '../../bootstrap.php';
Auth::requirePermission('PROJECTS:PROJECT_PAYMENTS:VIEW');
$type        = Req::postEnum('type',["invoice","quote","delivery_note"]);
$projectId   = Req::postInt('project_id');
$templateKey = Req::postString('template_key'); // z.B. 'invoice_de_kur'
$options     = [
  'discount_pct'   => Req::postFloat('discount_pct', 0),
  'duration_days'  => Req::postInt('duration_days', 0), // 0 = auto
  'show_component_prices' => Req::postBool('show_component_prices', true),
  'set_group_prefix' => Req::postString('set_group_prefix','Set:')
];
$res = DocumentRenderer::renderAndStore($db, Auth::instanceId(), $projectId, $type, $templateKey, $options, Auth::userId());
Json::ok($res);
