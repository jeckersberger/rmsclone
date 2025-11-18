<?php
require_once '../bootstrap.php';
Auth::requirePermission('PROJECTS:PROJECT_PAYMENTS:VIEW');
$inst = Auth::instanceId();
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $type = $_POST['type'];
  $pid  = (int)$_POST['project_id'];
  $key  = $_POST['template_key'];
  $opts = [
    'discount_pct' => (float)$_POST['discount_pct'],
    'duration_days' => (int)$_POST['duration_days'],
    'show_component_prices' => isset($_POST['show_component_prices'])
  ];
  $res = DocumentRenderer::renderAndStore($db, $inst, $pid, $type, $key, $opts, Auth::userId());
  echo "<p>OK. Dokumentnummer: <strong>{$res['doc_number']}</strong></p>";
}
$tpls = $db->fetchAll("SELECT * FROM document_templates WHERE instances_id=? ORDER BY type,name",[$inst]);
?>
<!doctype html><html lang="de"><meta charset="utf-8"><title>Dokument erzeugen</title>
<style>body{font:14px/1.4 system-ui,Segoe UI,Arial} label{display:block;margin:6px 0}</style>
<h1>Dokument erzeugen (Test)</h1>
<form method="post">
  <label>Projekt‑ID <input name="project_id" required></label>
  <label>Typ
    <select name="type"><option value="invoice">Rechnung</option><option value="quote">Angebot</option><option value="delivery_note">Lieferschein</option></select>
  </label>
  <label>Vorlage
    <select name="template_key">
      <?php foreach ($tpls as $t): ?>
      <option value="<?=$t['key']?>"><?=$t['type']?> – <?=$t['name']?> (<?=$t['key']?>)</option>
      <?php endforeach;?>
    </select>
  </label>
  <label>Rabatt % <input name="discount_pct" value="0"></label>
  <label>Miettage (0=automatisch) <input name="duration_days" value="0"></label>
  <label><input type="checkbox" name="show_component_prices" checked> Komponentenpreise im Set anzeigen</label>
  <button>Erzeugen</button>
</form>
